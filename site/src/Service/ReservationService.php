<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * Service métier des réservations aux campagnes hors saison (Formation / Loisir), stockées dans
 * #__gda_reservation (enveloppe) et #__gda_reservation_places (places).
 *
 * À ne pas confondre avec SouscriptionService, qui reste dédié aux souscriptions de la SAISON
 * (#__gda_souscriptions, workflow CACI / cotisation / licence du secrétariat).
 *
 * Architecture (depuis la fusion des natures Formation/Loisir) : toute campagne hors Saison
 * demande systématiquement un rôle par place, et la capacité est toujours suivie PAR RÔLE
 * (#__gda_campagne_roles) — chaque rôle a sa propre file d'attente, indépendante des autres
 * rôles de la même campagne. Une réservation Loisir peut désormais mélanger plusieurs rôles en
 * une seule fois (ex: 2 Plongeur + 1 Non-Plongeur), et être confirmée sur l'un pendant qu'elle
 * est en attente sur l'autre : le statut ne peut donc plus être porté par la réservation entière.
 *
 * #__gda_reservation_places est en conséquence l'unité ATOMIQUE de capacité/statut/rang : une
 * ligne = une place = un rôle = un statut ('confirmee'|'attente'|'annulee'), toujours qté 1.
 * #__gda_reservation redevient une simple enveloppe (qui, quand, commentaire, commande HelloAsso,
 * annulée ou non — colonne booléenne `annulee`, pas de statut détaillé).
 *
 * Règles portées ici :
 *  - une réservation (enveloppe) par adhérent et par campagne (contrainte UNIQUE en base) ;
 *  - reserver() applique une stratégie "table rase par rôle" : $demandes décrit l'état CIBLE de
 *    la réservation, un rôle absent (ou à quantité 0) revient à 0 place ;
 *  - pour un rôle donné, les places sont accordées dans la limite de sa capacité
 *    (#__gda_campagne_roles), le surplus part en liste d'attente ;
 *  - en cas de retrait de places sur un rôle, ce sont les places les plus récemment ajoutées qui
 *    partent en premier (préserve le rang des adhérents en attente depuis le plus longtemps) ;
 *  - une annulation complète (annuler()) passe l'enveloppe et toutes ses places actives à
 *    'annulee', puis promeut immédiatement la file d'attente de chaque rôle libéré.
 */
final class ReservationService
{
    public const STATUT_CONFIRMEE = 'confirmee';
    public const STATUT_ATTENTE   = 'attente';
    public const STATUT_ANNULEE   = 'annulee';

    private DatabaseInterface $db;

    /**
     * @param DatabaseInterface $db Connexion base de données (celle du composant).
     */
    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Réservation d'un adhérent pour une campagne, avec ses places, ou null s'il n'a jamais
     * réservé. Une réservation annulée est retournée telle quelle : c'est à l'appelant de décider
     * si elle compte comme "déjà réservé" (l'adhérent peut re-réserver, elle est alors réactivée).
     *
     * @param  int $idCampagne Campagne concernée.
     * @param  int $idProfil   Adhérent concerné.
     * @return object|null L'enveloppe (avec ->annulee bool et ->places, voir getPlaces()), ou
     *                      null si l'adhérent n'a jamais réservé.
     */
    public function getReservation(int $idCampagne, int $idProfil): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__gda_reservation'))
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $reservation = $this->db->loadObject();

        if (!$reservation) {
            return null;
        }

        $reservation->annulee = (bool) $reservation->annulee;
        $reservation->places  = $this->getPlaces((int) $reservation->id_reservation);

        return $reservation;
    }

    /**
     * Enregistre l'id de commande HelloAsso d'une réservation (#__gda_reservation.id_order).
     * Même motif que SouscriptionService::updateIdOrder(), pour la Saison.
     *
     * @param  int    $idCampagne Campagne concernée.
     * @param  int    $idProfil   Adhérent concerné.
     * @param  string $idOrder    Id de commande HelloAsso à enregistrer.
     * @return void
     * @throws \RuntimeException Si la requête échoue.
     */
    public function updateIdOrder(int $idCampagne, int $idProfil, string $idOrder): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_reservation'))
            ->set($this->db->quoteName('id_order') . ' = :id_order')
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->bind(':id_order', $idOrder)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Erreur mise à jour id_order : ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Résout un id_order manquant en interrogeant HelloAsso par username, et persiste le
     * résultat dans #__gda_reservation si une commande est trouvée. Recherche toujours en
     * direct (sans le cache de 30 minutes de HelloAssoService::getFormsOrders()) : contrairement
     * au statut d'adhésion (SouscriptionService::resolveIdOrder()), l'adhérent consulte ce badge
     * juste après avoir payé et ne doit pas attendre le TTL du cache pour voir son paiement
     * détecté.
     *
     * Ne fait rien (retourne l'id_order tel quel) si l'id_order est déjà connu, si formType/
     * formSlug/username sont absents, ou si aucune commande n'est trouvée. Ne lève jamais
     * d'exception : un échec HelloAsso (indisponible, mal configuré) ne doit pas casser
     * l'affichage du dashboard.
     *
     * @param  int    $idCampagne Campagne concernée.
     * @param  int    $idProfil   Adhérent concerné.
     * @param  string $idOrder    Id de commande déjà connu (chaîne vide si non résolu).
     * @param  string $formType   Type du formulaire HelloAsso de la campagne (event_helloasso).
     * @param  string $formSlug   Slug du formulaire HelloAsso de la campagne (event_helloasso).
     * @param  string $username   Username Joomla de l'adhérent, recherché dans les customFields HelloAsso.
     * @return string L'id_order résolu (trouvé ou déjà connu), ou une chaîne vide si toujours introuvable.
     */
    public function resolveIdOrder(int $idCampagne, int $idProfil, string $idOrder, string $formType, string $formSlug, string $username): string
    {
        if ($idOrder !== '') {
            return $idOrder;
        }

        if ($formType === '' || $formSlug === '' || $username === '') {
            return $idOrder;
        }

        try {
            $foundOrder = (new HelloAssoService())->findOrderByUsername($formType, $formSlug, $username, true);
        } catch (\Throwable $e) {
            GdaLogger::warning(sprintf(
                'ReservationService::resolveIdOrder() - Echec recherche HelloAsso pour username "%s" (campagne %d) : %s',
                $username,
                $idCampagne,
                $e->getMessage()
            ));

            return $idOrder;
        }

        if ($foundOrder === null) {
            return $idOrder;
        }

        try {
            $this->updateIdOrder($idCampagne, $idProfil, $foundOrder);
        } catch (\Throwable $e) {
            // L'id_order retrouvé reste utilisable pour cet affichage même si la persistance
            // échoue ; elle sera retentée au prochain appel.
            GdaLogger::warning(sprintf(
                'ReservationService::resolveIdOrder() - Echec enregistrement id_order "%s" (profil %d, campagne %d) : %s',
                $foundOrder,
                $idProfil,
                $idCampagne,
                $e->getMessage()
            ));
        }

        return $foundOrder;
    }

    /**
     * Places (hors annulées) d'une réservation, avec leur rang de file d'attente le cas échéant.
     *
     * @param  int $idReservation Réservation concernée.
     * @return object[] Chaque élément expose id_place, id_campagne, role, statut, date_rang, tri,
     *                   et rang (int|null, peuplé uniquement si statut = STATUT_ATTENTE).
     */
    private function getPlaces(int $idReservation): array
    {
        $statutAnnulee = self::STATUT_ANNULEE;
        $statutAttente = self::STATUT_ATTENTE;

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id_place', 'id_campagne', 'role', 'statut', 'date_rang', 'tri']))
            ->from($this->db->quoteName('#__gda_reservation_places'))
            ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
            ->where($this->db->quoteName('statut') . ' != :statut_annulee')
            ->order($this->db->quoteName('role') . ' ASC, ' . $this->db->quoteName('tri') . ' ASC')
            ->bind(':id_reservation', $idReservation, ParameterType::INTEGER)
            ->bind(':statut_annulee', $statutAnnulee);

        $this->db->setQuery($query);
        $places = $this->db->loadObjectList() ?: [];

        foreach ($places as $place) {
            $place->rang = $place->statut === $statutAttente
                ? $this->getRangAttente((int) $place->id_campagne, $place->role, $place->date_rang)
                : null;
        }

        return $places;
    }

    /**
     * Nombre de places CONFIRMÉES pour un rôle d'une campagne. Chaque ligne de
     * #__gda_reservation_places valant toujours 1 place, un simple COUNT suffit (plus de SUM
     * depuis que la quantité par réservation n'existe plus au niveau enveloppe).
     *
     * @param  int    $idCampagne Campagne concernée.
     * @param  string $role       Rôle concerné, parmi #__gda_role_de_campagne pour la nature de la campagne.
     * @return int Nombre de places confirmées pour ce rôle.
     */
    public function getPlacesOccupeesParRole(int $idCampagne, string $role): int
    {
        $statutConfirmee = self::STATUT_CONFIRMEE;

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__gda_reservation_places'))
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('role') . ' = :role')
            ->where($this->db->quoteName('statut') . ' = :statut_confirmee')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':role', $role)
            ->bind(':statut_confirmee', $statutConfirmee);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * Places encore disponibles pour un rôle d'une campagne. Retourne null si le rôle n'a pas de
     * limite (capacité configurée à 0), pour distinguer "illimité" de "complet".
     *
     * @param  int    $idCampagne   Campagne concernée.
     * @param  string $role         Rôle concerné.
     * @param  int    $capaciteRole Capacité configurée pour ce rôle (#__gda_campagne_roles.nbr_place, 0 = illimité).
     * @return int|null Places restantes pour ce rôle (0 si complet), ou null si illimité.
     */
    public function getPlacesDisponiblesParRole(int $idCampagne, string $role, int $capaciteRole): ?int
    {
        if ($capaciteRole <= 0) {
            return null;
        }

        return max(0, $capaciteRole - $this->getPlacesOccupeesParRole($idCampagne, $role));
    }

    /**
     * Nombre TOTAL de places confirmées d'une campagne, tous rôles confondus (pour un affichage
     * agrégé, ex: "X places occupées" dans l'onglet Gestion ou le dashboard adhérent). Pour un
     * usage sur une liste de campagnes, préférer getSelectPlacesOccupeesTotal() (sans N+1).
     *
     * @param  int $idCampagne Campagne concernée.
     * @return int Nombre de places confirmées, tous rôles confondus.
     */
    public function getPlacesOccupeesTotal(int $idCampagne): int
    {
        $statutConfirmee = self::STATUT_CONFIRMEE;

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__gda_reservation_places'))
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('statut') . ' = :statut_confirmee')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':statut_confirmee', $statutConfirmee);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * Places encore disponibles sur une campagne, tous rôles confondus. Retourne null si la
     * capacité totale est à 0 (aucun rôle configuré), pour distinguer "illimité" de "complet".
     * Utilisé pour l'alerte générale du popup de réservation ; la disponibilité déterminante pour
     * chaque rôle reste getPlacesDisponiblesParRole().
     *
     * @param  int $idCampagne    Campagne concernée.
     * @param  int $capaciteTotale Capacité totale (somme des rôles, voir getCapaciteTotale()).
     * @return int|null Places restantes (0 si complet), ou null si illimité.
     */
    public function getPlacesDisponiblesTotal(int $idCampagne, int $capaciteTotale): ?int
    {
        if ($capaciteTotale <= 0) {
            return null;
        }

        return max(0, $capaciteTotale - $this->getPlacesOccupeesTotal($idCampagne));
    }

    /**
     * Fragment SQL du nombre total de places confirmées d'une campagne (tous rôles), pour une
     * liste de campagnes (évite le N+1 qu'entraînerait un appel à getPlacesOccupeesTotal() par
     * ligne). À utiliser avec une jointure sur #__gda_campagnes, ex :
     *   ->select(ReservationService::getSelectPlacesOccupeesTotal($db, 'c') . ' AS places_occupees')
     *
     * Remplace l'ancien calcul (SUM sur #__gda_reservation.nbr_places_confirmees, colonne
     * supprimée avec le passage au modèle "place = unité atomique").
     *
     * @param  DatabaseInterface $db    Connexion base de données de l'appelant.
     * @param  string            $alias Alias donné à #__gda_campagnes dans la requête appelante.
     * @return string Fragment SQL (expression scalaire, sans alias).
     */
    public static function getSelectPlacesOccupeesTotal(DatabaseInterface $db, string $alias): string
    {
        return '(SELECT COUNT(*) FROM ' . $db->quoteName('#__gda_reservation_places')
            . ' WHERE ' . $db->quoteName('id_campagne') . ' = ' . $db->quoteName($alias . '.id_campagne')
            . ' AND ' . $db->quoteName('statut') . ' = ' . $db->quote(self::STATUT_CONFIRMEE) . ')';
    }

    /**
     * Capacité totale d'une campagne : somme des capacités par rôle (#__gda_campagne_roles).
     * Une seule source de vérité (la table campagne_roles) plutôt qu'une valeur dupliquée dans
     * gda_campagnes.nbr_place.
     *
     * À utiliser pour un affichage ponctuel (ex: popup de réservation, une seule campagne). Pour
     * une liste de campagnes, préférer getSelectCapaciteTotale() (fragment SQL, sans N+1).
     *
     * @param  object $campagne Campagne (doit exposer id_campagne).
     * @return int Capacité totale (0 = aucun rôle configuré, traité comme illimité).
     */
    public function getCapaciteTotale(object $campagne): int
    {
        $idCampagne = (int) $campagne->id_campagne;

        $query = $this->db->getQuery(true)
            ->select('COALESCE(SUM(' . $this->db->quoteName('nbr_place') . '), 0)')
            ->from($this->db->quoteName('#__gda_campagne_roles'))
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * Fragment SQL de la capacité totale d'une campagne (somme des rôles), pour une liste de
     * campagnes (évite le N+1 qu'entraînerait un appel à getCapaciteTotale() par ligne). À
     * utiliser avec une jointure sur #__gda_campagnes, ex :
     *   ->select(ReservationService::getSelectCapaciteTotale($db, 'c') . ' AS capacite_totale')
     *
     * Sous-requête corrélée plutôt qu'un JOIN + GROUP BY supplémentaire : les appelants
     * (CampagnesModel::getCampagnes(), AccueilModel::getCampagnesReservables()) agrègent déjà
     * d'autres colonnes (places_occupees) sur d'autres jointures ; un second niveau de jointure y
     * introduirait un produit cartésien.
     *
     * @param  DatabaseInterface $db    Connexion base de données de l'appelant.
     * @param  string            $alias Alias donné à #__gda_campagnes dans la requête appelante.
     * @return string Fragment SQL (expression scalaire, sans alias).
     */
    public static function getSelectCapaciteTotale(DatabaseInterface $db, string $alias): string
    {
        return '(SELECT COALESCE(SUM(' . $db->quoteName('nbr_place') . '), 0)'
            . ' FROM ' . $db->quoteName('#__gda_campagne_roles')
            . ' WHERE ' . $db->quoteName('id_campagne') . ' = ' . $db->quoteName($alias . '.id_campagne') . ')';
    }

    /**
     * Capacité par rôle (#__gda_campagne_roles) d'une campagne, ordonnée selon le gabarit de
     * rôles par défaut de sa nature (#__gda_role_de_campagne, ex: Formation -> Pratiquant puis
     * Encadrant) plutôt que l'ordre SQL naturel (clé primaire (id_campagne, role), donc
     * alphabétique - "Encadrant" avant "Pratiquant" - sans rapport avec l'ordre attendu à
     * l'écran). Un rôle absent du gabarit (renommé ou ajouté librement par le Bureau) est
     * conservé après ceux du gabarit, dans son ordre d'arrivée.
     *
     * Pour une LISTE de campagnes, préférer CampagnesModel::getRolesCapacite() (batché, sans
     * N+1) ; cette méthode reste adaptée à un affichage ponctuel (une seule campagne, ex:
     * dashboard adhérent).
     *
     * @param  int $idCampagne Campagne concernée.
     * @return array<string, int> role => nbr_place, ordonné. Tableau vide si aucun rôle configuré.
     * @throws \RuntimeException Si la requête échoue.
     */
    public function getCapacitesParRole(int $idCampagne): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['cr.role', 'cr.nbr_place', 'c.id_type']))
            ->from($this->db->quoteName('#__gda_campagne_roles', 'cr'))
            ->join('inner', $this->db->quoteName('#__gda_campagnes', 'c') . ' ON ' . $this->db->quoteName('c.id_campagne') . ' = ' . $this->db->quoteName('cr.id_campagne'))
            ->where($this->db->quoteName('cr.id_campagne') . ' = :id_campagne')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        if (empty($rows)) {
            return [];
        }

        $roles = [];
        foreach ($rows as $row) {
            $roles[(string) $row->role] = (int) $row->nbr_place;
        }

        return self::ordonnerParGabarit($roles, $this->getGabaritRoles((int) $rows[0]->id_type));
    }

    /**
     * Gabarit de rôles par défaut d'une nature de campagne (#__gda_role_de_campagne), dans
     * l'ordre d'affichage attendu.
     *
     * @param  int $idType Nature de campagne concernée.
     * @return string[] Rôles par défaut, dans l'ordre. Tableau vide si aucun gabarit défini.
     * @throws \RuntimeException Si la requête échoue.
     */
    private function getGabaritRoles(int $idType): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('roles'))
            ->from($this->db->quoteName('#__gda_role_de_campagne'))
            ->where($this->db->quoteName('id_type') . ' = :id_type')
            ->bind(':id_type', $idType, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $roles = $this->db->loadResult();

        return $roles ? explode(';', $roles) : [];
    }

    /**
     * Réordonne un tableau associatif role => valeur selon un gabarit de rôles (ordre
     * d'affichage attendu) : un rôle présent dans le gabarit est placé à sa position, un rôle
     * absent (renommé ou ajouté librement par le Bureau) est conservé après ceux du gabarit,
     * dans son ordre d'arrivée. Fonction pure, réutilisée par CampagnesModel::getRolesCapacite()
     * pour éviter de dupliquer ce tri à chaque nouveau point de lecture de la capacité par rôle.
     *
     * @param  array<string, mixed> $rolesValeur role => valeur, dans un ordre quelconque.
     * @param  string[]             $gabarit     Rôles par défaut, dans l'ordre d'affichage attendu.
     * @return array<string, mixed> Même contenu que $rolesValeur, réordonné.
     */
    public static function ordonnerParGabarit(array $rolesValeur, array $gabarit): array
    {
        uksort($rolesValeur, static function (string $roleA, string $roleB) use ($gabarit): int {
            $posA = array_search($roleA, $gabarit, true);
            $posB = array_search($roleB, $gabarit, true);

            return ($posA === false ? PHP_INT_MAX : $posA) <=> ($posB === false ? PHP_INT_MAX : $posB);
        });

        return $rolesValeur;
    }

    /**
     * Calcule le rang de file d'attente (1 = premier) de chaque place en statut 'attente' au sein
     * d'un même jeu de places déjà chargées, groupé par rôle et ordonné chronologiquement
     * (date_rang). Fonction pure, sans accès base : point d'extension unique pour tout écran
     * affichant plusieurs places d'une même campagne (rapport de campagne, suivi des
     * inscriptions), pour éviter de dupliquer ce calcul à chaque nouvel écran.
     *
     * @param  object[] $places Chaque élément doit exposer id_place, role, statut, date_rang.
     * @return array<int, int> id_place => rang (1-based), uniquement pour les places en attente.
     */
    public static function calculerRangsAttente(array $places): array
    {
        $parRole = [];

        foreach ($places as $place) {
            if ($place->statut === self::STATUT_ATTENTE) {
                $parRole[$place->role][] = $place;
            }
        }

        $rangs = [];

        foreach ($parRole as $placesDuRole) {
            usort($placesDuRole, static fn($a, $b) => strcmp((string) $a->date_rang, (string) $b->date_rang));

            $rang = 0;
            foreach ($placesDuRole as $place) {
                $rangs[(int) $place->id_place] = ++$rang;
            }
        }

        return $rangs;
    }

    /**
     * Rang dans la file d'attente d'un rôle (1 = premier) à une date de rang donnée. Requête
     * ciblée (COUNT), à préférer à calculerRangsAttente() ici : l'appelant n'a besoin que du rang
     * d'une place précise, pas de la liste complète des places de la campagne.
     *
     * @param  int    $idCampagne Campagne concernée.
     * @param  string $role       Rôle concerné.
     * @param  string $dateRang   Horodatage de la place (rang à calculer pour cette date).
     * @return int Rang dans la file d'attente de ce rôle (1-based).
     */
    public function getRangAttente(int $idCampagne, string $role, string $dateRang): int
    {
        $statutAttente = self::STATUT_ATTENTE;

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__gda_reservation_places'))
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('role') . ' = :role')
            ->where($this->db->quoteName('statut') . ' = :statut_attente')
            ->where($this->db->quoteName('date_rang') . ' <= :date_rang')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':role', $role)
            ->bind(':statut_attente', $statutAttente)
            ->bind(':date_rang', $dateRang);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * Crée ou met à jour la réservation d'un adhérent : une enveloppe (#__gda_reservation) et une
     * ou plusieurs places (#__gda_reservation_places), une ligne par place. $demandes décrit
     * l'état CIBLE de la réservation (stratégie "table rase" par rôle) : un rôle absent de
     * $demandes (ou à quantité 0) revient à 0 place. Une réservation annulée puis reprise n'a
     * plus aucune place active (ses anciennes places sont restées 'annulee') : tout rôle redemandé
     * est alors traité comme un ajout pur, reparti en fin de file — cohérent avec le principe
     * "annuler puis re-réserver ne double personne".
     *
     * @param  int                                                $idCampagne       Campagne concernée.
     * @param  int                                                $idProfil         Adhérent concerné.
     * @param  array<int, array{role: string, quantite: int}>     $demandes         État cible par rôle.
     * @param  array<string, int>                                 $capacitesParRole role => capacité configurée (0 = illimité).
     * @param  string|null                                        $commentaire      Commentaire libre de l'adhérent.
     * @param  string|null                                        $idOrder          Commande HelloAsso, si connue.
     * @return object La réservation rechargée (voir getReservation()).
     * @throws \InvalidArgumentException Si id_campagne ou id_profil est absent/invalide.
     * @throws \RuntimeException Si l'écriture en base échoue.
     */
    public function reserver(int $idCampagne, int $idProfil, array $demandes, array $capacitesParRole, ?string $commentaire = null, ?string $idOrder = null): object
    {
        if (!$idCampagne || !$idProfil) {
            throw new \InvalidArgumentException('id_campagne et id_profil sont requis pour réserver');
        }

        $demandesParRole = [];

        foreach ($demandes as $demande) {
            $role     = trim((string) ($demande['role'] ?? ''));
            $quantite = max(0, (int) ($demande['quantite'] ?? 0));

            if ($role === '') {
                continue;
            }

            $demandesParRole[$role] = ($demandesParRole[$role] ?? 0) + $quantite;
        }

        $maintenant    = ToolsHelper::now();
        $statutAnnulee = self::STATUT_ANNULEE;

        $this->db->transactionStart();

        try {
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__gda_reservation'))
                ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
                ->where($this->db->quoteName('id_profil') . ' = :id_profil')
                ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
                ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

            $this->db->setQuery($query);
            $enveloppe = $this->db->loadObject();

            if ($enveloppe) {
                $idReservation = (int) $enveloppe->id_reservation;

                $update = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__gda_reservation'))
                    ->set($this->db->quoteName('annulee') . ' = 0')
                    ->set($this->db->quoteName('commentaire') . ' = :commentaire')
                    ->set($this->db->quoteName('id_order') . ' = :id_order')
                    ->set($this->db->quoteName('last_update') . ' = :last_update')
                    ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
                    ->bind(':commentaire', $commentaire)
                    ->bind(':id_order', $idOrder)
                    ->bind(':last_update', $maintenant)
                    ->bind(':id_reservation', $idReservation, ParameterType::INTEGER);

                $this->db->setQuery($update);
                $this->db->execute();
            } else {
                $insert = $this->db->getQuery(true)
                    ->insert($this->db->quoteName('#__gda_reservation'))
                    ->columns($this->db->quoteName([
                        'id_campagne', 'id_profil', 'date_reservation', 'commentaire', 'id_order', 'last_update',
                    ]))
                    ->values(':id_campagne, :id_profil, :date_reservation, :commentaire, :id_order, :last_update')
                    ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
                    ->bind(':id_profil', $idProfil, ParameterType::INTEGER)
                    ->bind(':date_reservation', $maintenant)
                    ->bind(':commentaire', $commentaire)
                    ->bind(':id_order', $idOrder)
                    ->bind(':last_update', $maintenant);

                $this->db->setQuery($insert);
                $this->db->execute();

                $idReservation = (int) $this->db->insertid();
            }

            // Places actives existantes, comptées par rôle.
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('role'))
                ->select('COUNT(*) AS nb')
                ->from($this->db->quoteName('#__gda_reservation_places'))
                ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
                ->where($this->db->quoteName('statut') . ' != :statut_annulee')
                ->group($this->db->quoteName('role'))
                ->bind(':id_reservation', $idReservation, ParameterType::INTEGER)
                ->bind(':statut_annulee', $statutAnnulee);

            $this->db->setQuery($query);
            $existantesParRole = [];
            foreach ($this->db->loadObjectList() ?: [] as $row) {
                $existantesParRole[$row->role] = (int) $row->nb;
            }

            $query = $this->db->getQuery(true)
                ->select('COALESCE(MAX(' . $this->db->quoteName('tri') . '), -1)')
                ->from($this->db->quoteName('#__gda_reservation_places'))
                ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
                ->bind(':id_reservation', $idReservation, ParameterType::INTEGER);

            $this->db->setQuery($query);
            $triCounter = (int) $this->db->loadResult();

            $roles = array_unique(array_merge(array_keys($demandesParRole), array_keys($existantesParRole)));

            foreach ($roles as $role) {
                $delta = ($demandesParRole[$role] ?? 0) - ($existantesParRole[$role] ?? 0);

                if ($delta > 0) {
                    $this->ajouterPlaces($idCampagne, $idReservation, $role, $delta, (int) ($capacitesParRole[$role] ?? 0), $maintenant, $triCounter);
                } elseif ($delta < 0) {
                    $this->retirerPlaces($idCampagne, $role, $idReservation, -$delta);
                }
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            throw new \RuntimeException($e->getMessage(), 500, $e);
        }

        return $this->getReservation($idCampagne, $idProfil);
    }

    /**
     * Annule la réservation d'un adhérent : enveloppe passée à annulee = 1 (jamais de DELETE, pour
     * conserver l'historique et le rang initial), en cascade sur ses places actives (statut =
     * 'annulee'). Les places libérées sont aussitôt proposées au(x) premier(s) de la file
     * d'attente de leur rôle (voir promouvoirFileAttente()) : sans cela, la place resterait
     * affichée "disponible" alors que des adhérents attendent déjà.
     *
     * @param  int $idCampagne Campagne concernée.
     * @param  int $idProfil   Adhérent concerné.
     * @throws \RuntimeException Si l'écriture en base échoue (annulation et promotion annulées ensemble).
     */
    public function annuler(int $idCampagne, int $idProfil): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id_reservation'))
            ->from($this->db->quoteName('#__gda_reservation'))
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $idReservation = (int) $this->db->loadResult();

        if (!$idReservation) {
            return;
        }

        $maintenant      = ToolsHelper::now();
        $statutAnnulee   = self::STATUT_ANNULEE;
        $statutConfirmee = self::STATUT_CONFIRMEE;

        $this->db->transactionStart();

        try {
            // Places confirmées à libérer, comptées par rôle (pour promouvoir la bonne file).
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('role'))
                ->select('COUNT(*) AS nb')
                ->from($this->db->quoteName('#__gda_reservation_places'))
                ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
                ->where($this->db->quoteName('statut') . ' = :statut_confirmee')
                ->group($this->db->quoteName('role'))
                ->bind(':id_reservation', $idReservation, ParameterType::INTEGER)
                ->bind(':statut_confirmee', $statutConfirmee);

            $this->db->setQuery($query);
            $placesLibereesParRole = [];
            foreach ($this->db->loadObjectList() ?: [] as $row) {
                $placesLibereesParRole[$row->role] = (int) $row->nb;
            }

            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__gda_reservation'))
                ->set($this->db->quoteName('annulee') . ' = 1')
                ->set($this->db->quoteName('last_update') . ' = :last_update')
                ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
                ->bind(':last_update', $maintenant)
                ->bind(':id_reservation', $idReservation, ParameterType::INTEGER);

            $this->db->setQuery($update);
            $this->db->execute();

            $updatePlaces = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__gda_reservation_places'))
                ->set($this->db->quoteName('statut') . ' = :statut_annulee')
                ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
                ->where($this->db->quoteName('statut') . ' != :statut_annulee2')
                ->bind(':statut_annulee', $statutAnnulee)
                ->bind(':id_reservation', $idReservation, ParameterType::INTEGER)
                ->bind(':statut_annulee2', $statutAnnulee);

            $this->db->setQuery($updatePlaces);
            $this->db->execute();

            foreach ($placesLibereesParRole as $role => $nb) {
                $this->promouvoirFileAttente($idCampagne, $role, $nb);
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            throw new \RuntimeException($e->getMessage(), 500, $e);
        }
    }

    /**
     * Insère $quantite nouvelles places pour un rôle d'une réservation, dans la limite de la
     * capacité restante de ce rôle (le surplus part en attente). Chaque place vaut toujours 1 :
     * plus de calcul de remplissage partiel au niveau réservation, juste un partage confirmée/
     * attente sur les lignes nouvellement insérées.
     *
     * @param  int    $idCampagne     Campagne concernée.
     * @param  int    $idReservation  Réservation concernée.
     * @param  string $role           Rôle concerné.
     * @param  int    $quantite       Nombre de places à ajouter.
     * @param  int    $capaciteRole   Capacité configurée pour ce rôle (0 = illimité).
     * @param  string $maintenant     Horodatage à utiliser pour ces nouvelles places (date_rang).
     * @param  int    &$triCounter    Compteur d'ordre d'affichage, incrémenté à chaque ligne insérée.
     */
    private function ajouterPlaces(int $idCampagne, int $idReservation, string $role, int $quantite, int $capaciteRole, string $maintenant, int &$triCounter): void
    {
        if ($capaciteRole > 0) {
            $restantes  = max(0, $capaciteRole - $this->getPlacesOccupeesParRole($idCampagne, $role));
            $confirmees = min($quantite, $restantes);
        } else {
            $confirmees = $quantite;
        }

        for ($i = 0; $i < $quantite; $i++) {
            $statut = $i < $confirmees ? self::STATUT_CONFIRMEE : self::STATUT_ATTENTE;
            $triCounter++;

            $insert = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__gda_reservation_places'))
                ->columns($this->db->quoteName(['id_reservation', 'id_campagne', 'role', 'statut', 'date_rang', 'tri']))
                ->values(':id_reservation, :id_campagne, :role, :statut, :date_rang, :tri')
                ->bind(':id_reservation', $idReservation, ParameterType::INTEGER)
                ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
                ->bind(':role', $role)
                ->bind(':statut', $statut)
                ->bind(':date_rang', $maintenant)
                ->bind(':tri', $triCounter, ParameterType::INTEGER);

            $this->db->setQuery($insert);
            $this->db->execute();
        }
    }

    /**
     * Supprime $quantite places d'un rôle d'une réservation : les plus RÉCEMMENT ajoutées d'abord
     * (préserve le rang des adhérents en attente depuis le plus longtemps, sur ce même rôle et
     * les autres réservations). Si une place supprimée était confirmée, la place qu'elle libère
     * est aussitôt proposée au premier de la file d'attente de ce rôle.
     *
     * @param  int    $idCampagne    Campagne concernée.
     * @param  string $role          Rôle concerné.
     * @param  int    $idReservation Réservation concernée.
     * @param  int    $quantite      Nombre de places à retirer.
     */
    private function retirerPlaces(int $idCampagne, string $role, int $idReservation, int $quantite): void
    {
        $statutAnnulee   = self::STATUT_ANNULEE;
        $statutConfirmee = self::STATUT_CONFIRMEE;

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id_place', 'statut']))
            ->from($this->db->quoteName('#__gda_reservation_places'))
            ->where($this->db->quoteName('id_reservation') . ' = :id_reservation')
            ->where($this->db->quoteName('role') . ' = :role')
            ->where($this->db->quoteName('statut') . ' != :statut_annulee')
            ->order($this->db->quoteName('date_rang') . ' DESC, ' . $this->db->quoteName('id_place') . ' DESC')
            ->setLimit($quantite)
            ->bind(':id_reservation', $idReservation, ParameterType::INTEGER)
            ->bind(':role', $role)
            ->bind(':statut_annulee', $statutAnnulee);

        $this->db->setQuery($query);
        $aRetirer = $this->db->loadObjectList() ?: [];

        if (!$aRetirer) {
            return;
        }

        $idsPlaces            = array_map(static fn($p) => (int) $p->id_place, $aRetirer);
        $nbConfirmeesLiberees = count(array_filter($aRetirer, static fn($p) => $p->statut === $statutConfirmee));

        $delete = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__gda_reservation_places'))
            ->whereIn($this->db->quoteName('id_place'), $idsPlaces);

        $this->db->setQuery($delete);
        $this->db->execute();

        if ($nbConfirmeesLiberees > 0) {
            $this->promouvoirFileAttente($idCampagne, $role, $nbConfirmeesLiberees);
        }
    }

    /**
     * Fait avancer la file d'attente d'un rôle après libération de places : promeut en FIFO
     * (date_rang le plus ancien d'abord) les $placesALiberer places 'attente' les plus anciennes
     * de ce rôle. Chaque place valant toujours 1, il n'y a plus de calcul de remplissage partiel :
     * un simple top-N puis un UPDATE en masse.
     *
     * @param  int    $idCampagne     Campagne concernée.
     * @param  string $role           Rôle concerné.
     * @param  int    $placesALiberer Nombre de places redevenues disponibles pour ce rôle.
     */
    private function promouvoirFileAttente(int $idCampagne, string $role, int $placesALiberer): void
    {
        if ($placesALiberer <= 0) {
            return;
        }

        $statutAttente = self::STATUT_ATTENTE;

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id_place'))
            ->from($this->db->quoteName('#__gda_reservation_places'))
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('role') . ' = :role')
            ->where($this->db->quoteName('statut') . ' = :statut_attente')
            ->order($this->db->quoteName('date_rang') . ' ASC, ' . $this->db->quoteName('id_place') . ' ASC')
            ->setLimit($placesALiberer)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':role', $role)
            ->bind(':statut_attente', $statutAttente);

        $this->db->setQuery($query);
        $idsAPromouvoir = array_map('intval', $this->db->loadColumn() ?: []);

        if (!$idsAPromouvoir) {
            return;
        }

        $statutConfirmee = self::STATUT_CONFIRMEE;

        $update = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_reservation_places'))
            ->set($this->db->quoteName('statut') . ' = :statut_confirmee')
            ->whereIn($this->db->quoteName('id_place'), $idsAPromouvoir)
            ->bind(':statut_confirmee', $statutConfirmee);

        $this->db->setQuery($update);
        $this->db->execute();
    }
}
