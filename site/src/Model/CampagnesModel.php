<?php

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\ParameterType;

use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Service\BrevetService;
use NCB\Component\Gda\Site\Service\NotificationMailService;
use NCB\Component\Gda\Site\Service\ReservationService;
use NCB\Component\Gda\Site\Service\SouscriptionService;


class CampagnesModel extends ListModel
{
    /** Sous-types autorisés pour une campagne Formation (valeurs stockées ; libellés COM_GDA_CAMPAGNE_SOUS_TYPE_*). */
    public const SOUS_TYPES_FORMATION = ['fosse_apnee', 'fosse_technique_20', 'fosse_technique_12', 'rifax'];


    protected $_item = null;

    private ?BrevetService $brevetService = null;

    private ?ReservationService $reservationService = null;

    function getCampagne($id_campagne)
    {
        // $id = (int) $pk ?: (int) $this->getState('campagne.id');
        if (!$id_campagne) {
            // Creation d'un nouvelle campagne
            $item = null;
            // throw new \Exception('campagne n\'existe pas  id', 404);
        } else {
            // get la campagne $id_campagne
             $db = $this->getDatabase();
            $select = $db->createQuery();

            $select->select('c.*');
            $select->select('tc.*');
            $select->select(ReservationService::getSelectPlacesOccupeesTotal($db, 'c') . ' AS places_occupees');
            $select->select(ReservationService::getSelectCapaciteTotale($db, 'c') . ' AS capacite_totale');
            $select->from($db->quoteName('#__gda_campagnes', 'c'));
            $select->join('left', $db->quoteName('#__gda_type_de_campagne', 'tc'), $db->quoteName('c.id_type') . ' = ' . $db->quoteName('tc.id_type'));

            $select->where($db->quoteName('c.id_campagne') . '= :value_id_campagne');

            $select->bind(':value_id_campagne', $id_campagne);

            $db->setQuery($select);
            try {
                    $item = $db->loadObjectList();
                } catch (\RuntimeException $e) {
                    throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
                    // $query->__toString()
                    // Factory::getApplication()->enqueueMessage("Erreur de chargement des campagne, Contacter votre administrateur", 'error');
                }
            if (count($item) !== 1) {
             throw new \Exception("Anomalie : 0 ou plusieurs campagnes portent l'ID ".$id_campagne, 500);
            }

            // Capacité par rôle, pour préremplir le formulaire d'édition.
            $item[0]->role_places = $this->getRolesCapacite([(int) $id_campagne])[(int) $id_campagne] ?? [];
        }
        return $item[0];
    }
    /**
     *  Liste tous les items de campagnes, hors campagnes de type Saison (gérées exclusivement
     *  par la vue Saisons).
     */
    function getCampagnes()
    {
        $db = $this->getDatabase();
        $id_type_saison = ConfHelper::getValue('IdTypeSaison');

        $select = $db->createQuery();

        $select->select('c.*');
        $select->select('tc.*');
        $select->select(ReservationService::getSelectPlacesOccupeesTotal($db, 'c') . ' AS places_occupees');
        $select->select(ReservationService::getSelectCapaciteTotale($db, 'c') . ' AS capacite_totale');
        $select->from($db->quoteName('#__gda_campagnes', 'c'));
        $select->join('left', $db->quoteName('#__gda_type_de_campagne', 'tc'), $db->quoteName('c.id_type') . ' = ' . $db->quoteName('tc.id_type'));

        $select->where($db->quoteName('c.effacer') . '= 0');
        $select->where($db->quoteName('c.id_type') . ' != :id_type_saison');
        $select->bind(':id_type_saison', $id_type_saison);

        $db->setQuery($select);
            try {
                $this->_items = $db->loadObjectList();
            } catch (\RuntimeException $e) {
                throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
                // $select->__toString()
            }

        // Capacité et occupation par rôle, pour préremplir la modal d'édition et détailler la
        // colonne "Places" de chaque ligne : deux requêtes groupées plutôt qu'un appel par
        // campagne (pas de N+1).
        if (!empty($this->_items)) {
            $idsCampagne = array_map(static fn($item) => (int) $item->id_campagne, $this->_items);
            $rolesParCampagne = $this->getRolesCapacite($idsCampagne);
            $rolesOccupeesParCampagne = $this->getRolesOccupees($idsCampagne);

            foreach ($this->_items as $item) {
                $item->role_places = $rolesParCampagne[(int) $item->id_campagne] ?? [];
                $item->role_occupees = $rolesOccupeesParCampagne[(int) $item->id_campagne] ?? [];
            }
        }

        return $this->_items;
    }

    /**
     * Liste des natures de campagne disponibles (hors Saison, gérée exclusivement par la vue
     * Saisons), pour peupler le filtre de nature et les descriptions d'aide de l'onglet Gestion.
     * Indépendante des campagnes existantes : une nature reste proposée même sans campagne créée.
     *
     * @return object[]
     */
    function getTypes(): array
    {
        $db = $this->getDatabase();
        $id_type_saison = ConfHelper::getValue('IdTypeSaison');

        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__gda_type_de_campagne'))
            ->where($db->quoteName('id_type') . ' != :id_type_saison')
            ->order($db->quoteName('type_name') . ' ASC')
            ->bind(':id_type_saison', $id_type_saison);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Rôles par défaut par nature de campagne (ex: Formation -> Pratiquant/Encadrant), utilisés
     * pour préremplir les lignes rôle+capacité à la création d'une nouvelle campagne. Simple
     * gabarit de départ : les rôles réels d'une campagne (#__gda_campagne_roles) sont ensuite
     * librement ajoutés/renommés/supprimés par le Bureau. Non configurable pour l'instant
     * (#__gda_role_de_campagne n'a pas encore d'écran d'administration).
     *
     * @return array<int, string[]> id_type => liste des rôles par défaut
     */
    function getRolesDeCampagne(): array
    {
        $db = $this->getDatabase();

        $query = $db->createQuery()
            ->select($db->quoteName(['id_type', 'roles']))
            ->from($db->quoteName('#__gda_role_de_campagne'));

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $roles = [];
        foreach ($rows as $row) {
            $roles[(int) $row->id_type] = explode(';', $row->roles);
        }

        return $roles;
    }

    /**
     * Type de formulaire HelloAsso attendu pour chaque nature de campagne, utilisé pour restreindre
     * la liste déroulante "Event HelloAsso" du formulaire Campagnes (models/fields/eventshelloasso.php,
     * filtrage côté client dans campagne.js selon la nature sélectionnée). Formation et Loisir
     * pointent vers un événement HelloAsso ("Event") ; Boutique vers une boutique ("Shop") — sans
     * rôles ni réservation, voir la vue Accueil et l'onglet Suivi des inscriptions qui l'excluent
     * déjà. La Saison a son propre champ, filtré statiquement sur "Membership"
     * (models/forms/saison_courante.xml), indépendant de cette méthode.
     *
     * Règle métier fixe, non administrable (même motif que getRolesDeCampagne()) : à faire évoluer
     * ici si HelloAsso expose un jour un autre type de formulaire pertinent pour une nature future.
     *
     * @return array<string, string> type_name => formType HelloAsso attendu
     */
    function getHelloAssoFormTypeParNature(): array
    {
        return [
            'Formation' => 'Event',
            'Loisir'    => 'Event',
            'Boutique'  => 'Shop',
        ];
    }

    /**
     * Capacité par rôle (#__gda_campagne_roles) pour une ou plusieurs campagnes, à la fois pour
     * préremplir le formulaire d'édition (une campagne) et pour enrichir une liste sans N+1
     * (plusieurs campagnes en une seule requête, groupées ensuite en PHP).
     *
     * L'ordre des rôles retourné suit le gabarit par défaut de la nature de chaque campagne
     * (#__gda_role_de_campagne, ex: Formation -> Pratiquant puis Encadrant) plutôt que l'ordre
     * SQL naturel (clé primaire (id_campagne, role), donc alphabétique - "Encadrant" avant
     * "Pratiquant" - sans rapport avec l'ordre attendu à l'écran). Un rôle absent du gabarit
     * (renommé ou ajouté librement par le Bureau) est conservé après ceux du gabarit.
     *
     * @param  int[] $idsCampagne Identifiants des campagnes concernées.
     * @return array<int, array<string, int>> id_campagne => [role => nbr_place], ordonné.
     */
    function getRolesCapacite(array $idsCampagne): array
    {
        if (empty($idsCampagne)) {
            return [];
        }

        $db = $this->getDatabase();

        $query = $db->createQuery()
            ->select($db->quoteName(['cr.id_campagne', 'cr.role', 'cr.nbr_place', 'c.id_type']))
            ->from($db->quoteName('#__gda_campagne_roles', 'cr'))
            ->join('inner', $db->quoteName('#__gda_campagnes', 'c') . ' ON ' . $db->quoteName('c.id_campagne') . ' = ' . $db->quoteName('cr.id_campagne'))
            ->whereIn($db->quoteName('cr.id_campagne'), $idsCampagne);

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $capacites = [];
        $idsType   = [];
        foreach ($rows as $row) {
            $capacites[(int) $row->id_campagne][(string) $row->role] = (int) $row->nbr_place;
            $idsType[(int) $row->id_campagne] = (int) $row->id_type;
        }

        $gabarits = $this->getRolesDeCampagne();

        foreach ($capacites as $idCampagne => $roles) {
            $capacites[$idCampagne] = ReservationService::ordonnerParGabarit($roles, $gabarits[$idsType[$idCampagne]] ?? []);
        }

        return $capacites;
    }

    /**
     * Places occupées par rôle, réparties par statut (confirmée / en cours de validation), pour
     * une ou plusieurs campagnes, à mettre en regard de getRolesCapacite() pour détailler la
     * colonne "Places" de la liste (ex: "Encadrant : ✔ 1, ⏳ 2 / 5") sans N+1 (une seule requête
     * groupée pour toute la liste). STATUT_REFUSEE et STATUT_ANNULEE ne comptent pas comme
     * occupation d'une place.
     *
     * @param  int[] $idsCampagne Identifiants des campagnes concernées.
     * @return array<int, array<string, array{confirmee: int, attente: int}>> id_campagne => [role => [...]].
     */
    function getRolesOccupees(array $idsCampagne): array
    {
        if (empty($idsCampagne)) {
            return [];
        }

        $db = $this->getDatabase();
        $statutConfirmee = ReservationService::STATUT_CONFIRMEE;
        $statutAttente   = ReservationService::STATUT_ATTENTE;

        $query = $db->createQuery()
            ->select($db->quoteName(['id_campagne', 'role', 'statut']))
            ->select('COUNT(*) AS ' . $db->quoteName('occupees'))
            ->from($db->quoteName('#__gda_reservation_places'))
            ->whereIn($db->quoteName('statut'), [$statutConfirmee, $statutAttente], ParameterType::STRING)
            ->whereIn($db->quoteName('id_campagne'), $idsCampagne)
            ->group($db->quoteName(['id_campagne', 'role', 'statut']));

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $occupees = [];
        foreach ($rows as $row) {
            $idCampagne = (int) $row->id_campagne;
            $role       = (string) $row->role;

            if (!isset($occupees[$idCampagne][$role])) {
                $occupees[$idCampagne][$role] = ['confirmee' => 0, 'attente' => 0];
            }

            if ($row->statut === $statutConfirmee) {
                $occupees[$idCampagne][$role]['confirmee'] = (int) $row->occupees;
            } else {
                $occupees[$idCampagne][$role]['attente'] = (int) $row->occupees;
            }
        }

        return $occupees;
    }

    /**
     * Remplace la répartition des places par rôle d'une campagne (stratégie "table rase", même
     * motif que ReservationService::reserver() côté places : le nombre de rôles peut varier d'une
     * campagne à l'autre et être librement renommé, un diff ligne à ligne n'apporterait rien).
     *
     * Tableau INDEXÉ de paires (et non associatif par nom de rôle) : les noms de rôle sont du
     * texte libre, renommable en direct dans le même enregistrement — une clé associative ne peut
     * pas représenter proprement "cette ligne s'appelait X, s'appelle maintenant Y". Une capacité
     * à 0 reste une ligne valide et persistée (ex: "15 Pratiquant, 0 Encadrant") ; seules les
     * lignes sans nom de rôle sont ignorées. Les rôles en double (même nom soumis deux fois) sont
     * fusionnés par somme des capacités, pour éviter un conflit sur la clé primaire (id_campagne, role).
     *
     * @param  int                                    $idCampagne Campagne concernée.
     * @param  array<int, array{role: mixed, nbr_place: mixed}> $rolePlaces Paires role/capacité.
     */
    private function saveRolePlaces(int $idCampagne, array $rolePlaces): void
    {
        $db = $this->getDatabase();

        $delete = $db->createQuery()
            ->delete($db->quoteName('#__gda_campagne_roles'))
            ->where($db->quoteName('id_campagne') . ' = :id_campagne')
            ->bind(':id_campagne', $idCampagne, \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($delete);
        $db->execute();

        $capacitesParRole = [];

        foreach ($rolePlaces as $ligne) {
            $role     = trim((string) ($ligne['role'] ?? ''));
            $nbrPlace = max(0, (int) ($ligne['nbr_place'] ?? 0));

            if ($role === '') {
                continue;
            }

            $capacitesParRole[$role] = ($capacitesParRole[$role] ?? 0) + $nbrPlace;
        }

        foreach ($capacitesParRole as $role => $nbrPlace) {
            $insert = $db->createQuery()
                ->insert($db->quoteName('#__gda_campagne_roles'))
                ->columns($db->quoteName(['id_campagne', 'role', 'nbr_place']))
                ->values(':id_campagne, :role, :nbr_place')
                ->bind(':id_campagne', $idCampagne, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':role', $role)
                ->bind(':nbr_place', $nbrPlace, \Joomla\Database\ParameterType::INTEGER);

            $db->setQuery($insert);
            $db->execute();
        }
    }

    /**
     * Récapitulatif des réservations de toutes les campagnes Formation : une ligne par adhérent ayant
     * réservé au moins une fois, une colonne par campagne, et à leur croisement le dernier statut
     * connu (place la plus récente de l'adhérent sur cette campagne, désistement compris).
     *
     * @return array{campagnes: object[], adherents: object[]} `campagnes` : id_campagne, titre,
     *         date_evenement, active, sous_type ; `adherents` : id_profil, civilite, nom, prenom, photo et
     *         `statuts` (id_campagne => statut), `places` (id_campagne => [[rôle, statut], ...] chronologique), triés par nom puis prénom.
     * @throws \Exception Si une requête échoue.
     */
    public function getRecapitulatifFormations(): array
    {
        $db = $this->getDatabase();
        $idTypeFormation = (int) ConfHelper::getValue('IdTypeFormation');

        $query = $db->createQuery()
            ->select($db->quoteName(['id_campagne', 'titre', 'date_evenement', 'active', 'sous_type']))
            ->from($db->quoteName('#__gda_campagnes'))
            ->where($db->quoteName('id_type') . ' = :id_type')
            ->where($db->quoteName('effacer') . ' = 0')
            ->order($db->quoteName('date_debut') . ' ASC, ' . $db->quoteName('id_campagne') . ' ASC')
            ->bind(':id_type', $idTypeFormation, ParameterType::INTEGER);

        try {
            $campagnes = $db->setQuery($query)->loadObjectList() ?: [];

            if (empty($campagnes)) {
                return ['campagnes' => [], 'adherents' => []];
            }

            $idsCampagne = array_map(static fn ($campagne) => (int) $campagne->id_campagne, $campagnes);

            $query = $db->createQuery()
                ->select($db->quoteName(['rp.id_campagne', 'rp.id_place', 'rp.role', 'rp.statut', 'rp.date_rang', 'p.id_profil', 'p.civilite', 'p.nom', 'p.prenom', 'p.photo']))
                ->from($db->quoteName('#__gda_reservation_places', 'rp'))
                ->innerJoin($db->quoteName('#__gda_reservation', 'r') . ' ON ' . $db->quoteName('r.id_reservation') . ' = ' . $db->quoteName('rp.id_reservation'))
                ->innerJoin($db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('r.id_profil'))
                ->whereIn($db->quoteName('rp.id_campagne'), $idsCampagne, ParameterType::INTEGER)
                ->order($db->quoteName('rp.date_rang') . ' ASC, ' . $db->quoteName('rp.id_place') . ' ASC');

            $places = $db->setQuery($query)->loadObjectList() ?: [];
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        // Tri chronologique : la dernière place rencontrée pour un couple adhérent/campagne l'emporte.
        $adherents = [];

        foreach ($places as $place) {
            $idProfil = (int) $place->id_profil;

            if (!isset($adherents[$idProfil])) {
                $adherent = new \stdClass();
                $adherent->id_profil = $idProfil;
                $adherent->civilite = (string) ($place->civilite ?? '');
                $adherent->nom = (string) ($place->nom ?? '');
                $adherent->prenom = (string) ($place->prenom ?? '');
                $adherent->photo = $place->photo;
                $adherent->statuts = [];
                $adherent->places = [];
                $adherents[$idProfil] = $adherent;
            }

            $adherents[$idProfil]->statuts[(int) $place->id_campagne] = (string) $place->statut;
            // Historique chronologique [rôle, statut] : permet au filtre Rôle du récapitulatif de
            // recalculer le dernier statut sur un sous-ensemble de rôles (le rôle est un texte libre).
            $adherents[$idProfil]->places[(int) $place->id_campagne][] = [(string) $place->role, (string) $place->statut];
        }

        usort($adherents, static fn ($a, $b) => strcasecmp($a->nom . ' ' . $a->prenom, $b->nom . ' ' . $b->prenom));

        return ['campagnes' => $campagnes, 'adherents' => $adherents];
    }

    /**
     * Retourne les adhérents ayant réservé une place sur une campagne (hors saison), sous la même
     * forme qu'un groupe issu de GroupesModel::getGroupesAvecAdherents() afin de pouvoir réutiliser
     * tel quel les layouts groupes.detail / groupes.vignette pour l'onglet "Suivi des inscriptions".
     */
    function getInscritsCampagne(int $id_campagne, string $titre): object
    {
        $db = $this->getDatabase();
        $statut_annulee = ReservationService::STATUT_ANNULEE;

        // Une ligne par PLACE (#__gda_reservation_places), pas par réservation : depuis la fusion
        // Formation/Loisir, une réservation peut porter plusieurs rôles à la fois, chacun avec
        // son propre statut. Un adhérent avec 2 places confirmées + 1 en attente apparaît donc en
        // 3 lignes ici, chacune avec son rôle/statut propre — layout groupes.detail inchangé, il
        // affiche déjà un rôle/statut par ligne. STATUT_REFUSEE (décision du responsable) apparaît
        // aussi ici, ainsi que STATUT_ANNULEE (désistement de l'adhérent), classé en dernier.
        $query = $db->createQuery()
            ->select([
                $db->quoteName('p.id_profil'),
                $db->quoteName('p.civilite'),
                $db->quoteName('p.nom'),
                $db->quoteName('p.prenom'),
                $db->quoteName('p.photo'),
                $db->quoteName('p.caci'),
                $db->quoteName('p.date_caci'),
                $db->quoteName('p.date_licence'),
                $db->quoteName('rp.id_place'),
                $db->quoteName('rp.role'),
                $db->quoteName('rp.statut'),
                $db->quoteName('rp.date_rang'),
                $db->quoteName('r.commentaire'),
            ])
            ->from($db->quoteName('#__gda_reservation_places', 'rp'))
            ->innerJoin($db->quoteName('#__gda_reservation', 'r') . ' ON ' . $db->quoteName('r.id_reservation') . ' = ' . $db->quoteName('rp.id_reservation'))
            ->innerJoin($db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('r.id_profil'))
            ->where($db->quoteName('rp.id_campagne') . ' = :id_campagne')
            ->order($db->quoteName('p.nom') . ' ASC, ' . $db->quoteName('p.prenom') . ' ASC, (' . $db->quoteName('rp.statut') . ' = :statut_annulee) ASC, ' . $db->quoteName('rp.role') . ' ASC')
            ->bind(':id_campagne', $id_campagne, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':statut_annulee', $statut_annulee);

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList() ?: [];
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        $groupe = new \stdClass();
        $groupe->id_groupe = 0;
        $groupe->groupe_name = $titre;
        $groupe->icon = '';
        $groupe->adherents = [];

        foreach ($rows as $row) {
            $adherent = new \stdClass();
            $adherent->id_profil = (int) $row->id_profil;
            $adherent->civilite = (string) ($row->civilite ?? '');
            $adherent->nom = (string) ($row->nom ?? '');
            $adherent->prenom = (string) ($row->prenom ?? '');
            $adherent->photo = $row->photo;
            $adherent->caci = $row->caci;
            $adherent->date_caci = $row->date_caci;
            $adherent->caci_status = AdhesionStatusHelper::getCaciFileStatus($row->caci, $row->date_caci);
            $adherent->date_licence = $row->date_licence;
            $adherent->licence_status = AdhesionStatusHelper::getLicenceValidityStatus($row->date_licence);
            $adherent->role = $row->role;
            $adherent->date_reservation = $row->date_rang;
            $adherent->id_place = (int) $row->id_place;
            $adherent->statut = (string) $row->statut;
            $adherent->commentaire = trim((string) ($row->commentaire ?? ""));

            $groupe->adherents[] = $adherent;
        }

        // Aperçu des brevets (badges) attendu par le layout groupes.detail réutilisé ici : sans
        // cette propriété, brevets_shortlist reste absente et le layout affiche silencieusement
        // "aucun brevet" pour tout le monde. Même motif que GroupesModel::enrichirBrevetsShortList()
        // (une seule requête groupée pour tous les inscrits, pas de N+1). array_unique : un même
        // adhérent peut désormais apparaître sur plusieurs lignes (plusieurs rôles/places).
        if (!empty($groupe->adherents)) {
            $idProfils = array_unique(array_map(static fn($adherent) => $adherent->id_profil, $groupe->adherents));
            $shortLists = $this->getBrevetService()->getBrevetsShortListProfils($idProfils);

            foreach ($groupe->adherents as $adherent) {
                $adherent->brevets_shortlist = $shortLists[$adherent->id_profil] ?? [];
            }
        }

        return $groupe;
    }

    /**
     * Change le statut d'une inscription (place) depuis l'onglet "Suivi des inscriptions" :
     * décision du responsable de campagne — voir ReservationService::changerStatutPlace().
     *
     * @param  int    $idPlace Place concernée (#__gda_reservation_places.id_place).
     * @param  string $statut  Nouveau statut, parmi ReservationService::STATUT_ATTENTE,
     *                         STATUT_CONFIRMEE, STATUT_REFUSEE.
     * @return void
     * @throws \InvalidArgumentException Si $statut n'est pas une des trois valeurs autorisées.
     * @throws \RuntimeException Si l'écriture en base échoue.
     */
    function changerStatutInscription(int $idPlace, string $statut): string
    {
        return $this->getReservationService()->changerStatutPlace($idPlace, $statut);
    }

    /**
     * Prévient l'adhérent que sa place vient d'être acceptée (mail « inscription validée », voir
     * NotificationMailService::sendReservationAcceptedEmail()). Si la campagne est liée à HelloAsso et
     * qu'aucun paiement n'est retrouvé pour l'adhérent (ReservationService::resolveIdOrder(), recherche
     * directe), le mail porte en plus le lien du formulaire HelloAsso.
     *
     * @param  int $idPlace Place acceptée.
     * @return bool True si le mail est parti.
     * @throws \RuntimeException Si la place est introuvable.
     */
    function notifierInscriptionAcceptee(int $idPlace): bool
    {
        $contexte = $this->getReservationService()->getPlaceContexte($idPlace);

        if ($contexte === null) {
            throw new \RuntimeException('Inscription introuvable pour la notification', 404);
        }

        return $this->getNotificationMailService()->sendReservationAcceptedEmail($idPlace, $this->getUrlPaiementHelloAsso($contexte));
    }

    /**
     * Prévient le responsable de la campagne (#__gda_campagnes.id_responsable) d'un mouvement sur
     * l'inscription d'un adhérent : inscription, désinscription, modification ou commentaire (voir
     * NotificationMailService::sendReservationActivityEmail()). Sans effet si la campagne n'a pas de
     * responsable désigné.
     *
     * @param  int         $idCampagne         Campagne concernée.
     * @param  int         $idProfil           Adhérent concerné.
     * @param  string      $evenement          'inscription' | 'desinscription' | 'modification' | 'commentaire'.
     * @param  array       $lignes             Par rôle modifié : role, avant et apres (statut => nombre de places).
     * @param  string|null $commentaire        Commentaire actuel de l'adhérent.
     * @param  bool        $commentaireModifie True si le commentaire vient d'être écrit ou changé.
     * @return bool True si le mail est parti.
     */
    function notifierActiviteInscription(int $idCampagne, int $idProfil, string $evenement, array $lignes, ?string $commentaire = null, bool $commentaireModifie = false): bool
    {
        return $this->getNotificationMailService()->sendReservationActivityEmail($idCampagne, $idProfil, $evenement, $lignes, $commentaire, $commentaireModifie);
    }

    /**
     * Lien HelloAsso à envoyer à l'adhérent, ou null s'il n'y a rien à payer : campagne sans lien
     * HelloAsso exploitable, ou paiement déjà retrouvé.
     *
     * @param  object $contexte Résultat de ReservationService::getPlaceContexte().
     * @return string|null URL du formulaire HelloAsso de la campagne, si le paiement reste à faire.
     */
    private function getUrlPaiementHelloAsso(object $contexte): ?string
    {
        $event = json_decode((string) $contexte->event_helloasso, true);

        if (!is_array($event) || empty($event['url']) || empty($event['formType']) || empty($event['formSlug'])) {
            return null;
        }

        $idOrder = $this->getReservationService()->resolveIdOrder(
            (int) $contexte->id_campagne,
            (int) $contexte->id_profil,
            (string) ($contexte->id_order ?? ''),
            (string) $event['formType'],
            (string) $event['formSlug'],
            (string) $contexte->username
        );

        return $idOrder === '' ? (string) $event['url'] : null;
    }

    /**
     * Service de notification mail (lazy, non partagé par le conteneur DI — même motif que
     * SecretariatModel::getNotificationMailService()).
     */
    private function getNotificationMailService(): NotificationMailService
    {
        return new NotificationMailService(
            $this->getDatabase(),
            Factory::getContainer()->get(MailerFactoryInterface::class),
            ConfHelper::getConfigService()
        );
    }

    /**
     * Getter pour obtenir le service Réservation (lazy loading, pas dans le conteneur DI du
     * composant). Même motif que getBrevetService() ci-dessous.
     */
    private function getReservationService(): ReservationService
    {
        if ($this->reservationService === null) {
            $this->reservationService = new ReservationService($this->getDatabase());
        }

        return $this->reservationService;
    }

    /**
     * Getter pour obtenir le service Brevet (lazy loading, pas dans le conteneur DI du composant).
     * Même motif que GroupesModel::getBrevetService() / ProfilModel::getBrevetService().
     */
    private function getBrevetService(): BrevetService
    {
        if ($this->brevetService === null) {
            $this->brevetService = new BrevetService($this->getDatabase());
        }

        return $this->brevetService;
    }

   /**
    ** charge le formulaire de campagne pour l'edition ou la creation
    ** @return Formulaire de campagne
    */

    public function getForm($data = array(), $loadData = true) : \Joomla\CMS\Form\Form
	{
		$form = $this->loadForm(
			'com_gdadhesions.campagnes',  // just a unique name to identify the form
			'campagnes',				// the filename of the XML form definition
										// Joomla will look in the site/forms folder for this file
			array(
				'control' => 'jform_campagne',	// the name of the array for the POST parameters
				'load_data' => $loadData        // if set to true, then there will be a callback to 
                                                // loadFormData to supply the data
			)
		);

		if (empty($form))
		{
             throw new \RuntimeException('Unable to load form: com_gdadhesions.campagnes', 500);
		}

		return $form;
	}

    /**
     * Activer ou desactiver une camapgne
     */
       function Activer () : int
    {
        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $data = $app->getUserState('campagne.activer');

        $db = $this->getDatabase();
        $query = $db->createQuery();

        $active_value =  intval($data['active']);
        $id_campagne_value = intval($data['id_campagne']);
     
        //  Fields to update.
            $fields = array(
                $db->quoteName('active') . ' = :active_value'
            );
            // Conditions for which records should be updated.
            $conditions = array(
                $db->quoteName('id_campagne') . ' = :id_campagne_value'
            );
            $query->update($db->quoteName('#__gda_campagnes'))->set($fields)->where($conditions);

            $query->bind(':active_value', $active_value);
            $query->bind(':id_campagne_value', $id_campagne_value); 

            

        $db->setQuery($query);

        try {
            $result = $db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        return $result;
    }


    /**
     * Sauver une camapgne
     */

   function Sauver ()
    {
                /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $data = $app->getUserState('campagne.sauver');

        $db = $this->getDatabase();
        $query = $db->createQuery();
     
        $value_id_groupes = ( isset($data['id_groupes'])) ? implode(',', $data['id_groupes']) : ''; 

        $value_date_debut =ToolsHelper::to_sqldate($data['date_debut']);
        $value_date_fin = ToolsHelper::to_sqldate($data['date_fin']);
        // Date de l'événement : datetime (heure comprise), donc convertisseur dédié.
        $value_date_evenement = ToolsHelper::to_sqldatetime($data['date_evenement'] ?? null);
        $value_event_helloasso = (isset($data['event_helloasso'])) ? ($data['event_helloasso']) : null;
        // Formation reste toujours 1 place = 1 rôle par adhérent ; Loisir laisse le Bureau
        // choisir. Forcé côté serveur (le formulaire le fait déjà côté client mais ce n'est pas
        // suffisant pour une règle métier bloquante, cf. contrôles serveur similaires dans
        // AdhesionController::save()).
        $idTypeFormation = (int) ConfHelper::getValue('IdTypeFormation');
        $value_reservation_multiple = ((int) $data['id_type'] === $idTypeFormation)
            ? 0
            : (empty($data['reservation_multiple']) ? 0 : 1);

        // Sous-type : propre à la nature Formation, valeur validée contre la liste fermée (l'id vient du navigateur).
        $value_sous_type = ((int) $data['id_type'] === $idTypeFormation && in_array($data['sous_type'] ?? '', self::SOUS_TYPES_FORMATION, true))
            ? $data['sous_type']
            : null;

        // Responsable prévenu des demandes d'inscription : sans objet pour Boutique (pas de
        // réservation), et refusé hors de la liste Bureau / Responsables de Groupe (l'id vient du
        // navigateur, la liste déroulante n'est pas une garantie).
        $value_id_responsable = null;
        $idResponsablePoste   = (int) ($data['id_responsable'] ?? 0);

        if ($idResponsablePoste > 0 && (int) $data['id_type'] !== (int) ConfHelper::getValue('IdTypeBoutique')) {
            if (!in_array($idResponsablePoste, array_map('intval', array_column(UsersHelper::getResponsablesCampagne(), 'id')), true)) {
                throw new \Exception(Text::_('COM_GDA_CAMPAGNE_RESPONSABLE_INVALIDE'), 400);
            }

            $value_id_responsable = $idResponsablePoste;
        }

       if ($data['id_campagne']) {
            // Update Item 
            $value_active = intval( $data['active']);
            $fields = array(
                $db->quoteName('titre') . '= :value_titre',
                $db->quoteName('description') . '= :value_description',
                $db->quoteName('event_helloasso') . '= :value_event_helloasso',
                $db->quoteName('date_debut') . '= :value_date_debut',
                $db->quoteName('date_fin') . '= :value_date_fin',
                $db->quoteName('date_evenement') . '= :value_date_evenement',
                $db->quoteName('id_article') . '= :value_id_article',
                $db->quoteName('id_type') . '= :value_id_type',
                $db->quoteName('sous_type') . '= :value_sous_type',
                $db->quoteName('id_groupes') . '= :value_id_groupes',
                $db->quoteName('id_responsable') . '= :value_id_responsable',
                $db->quoteName('nbr_place') . '= :value_nbr_place',
                $db->quoteName('reservation_multiple') . '= :value_reservation_multiple',
                $db->quoteName('active') . '= :value_active',
            );
            $conditions = array( $db->quoteName('id_campagne') . ' = :value_id_campagne');
            $query->update($db->quoteName('#__gda_campagnes'))->set($fields)->where($conditions);
            $query->bind(':value_id_campagne',  $data['id_campagne']);
       } else {
            // New Item
            $value_active = (int) 0;

                // Insert
            $columns = array('titre','description', 'event_helloasso','date_debut', 'date_fin', 'date_evenement', 'active', 'id_article','id_type','sous_type','id_groupes','id_responsable','nbr_place','reservation_multiple');
            $query->insert($db->quoteName('#__gda_campagnes'));
            $query->columns($db->quoteName($columns));
            $query->values(':value_titre, :value_description, :value_event_helloasso, :value_date_debut, :value_date_fin, :value_date_evenement, :value_active, :value_id_article, :value_id_type, :value_sous_type, :value_id_groupes, :value_id_responsable, :value_nbr_place, :value_reservation_multiple');
       }

        // Bind values
        $query->bind(':value_titre', $data['titre']);
        $query->bind(':value_description',  $data['description']);
        $query->bind(':value_event_helloasso',  $value_event_helloasso);
        $query->bind(':value_date_debut', $value_date_debut);
        $query->bind(':value_date_fin',  $value_date_fin);
        $query->bind(':value_date_evenement',  $value_date_evenement);
        $query->bind(':value_active',  $value_active);
        $query->bind(':value_id_article',  $data['id_article']);
        $query->bind(':value_id_type',  $data['id_type']);
        $query->bind(':value_sous_type', $value_sous_type);
        $query->bind(':value_id_groupes', $value_id_groupes);
        $query->bind(':value_id_responsable', $value_id_responsable, \Joomla\Database\ParameterType::INTEGER);

        // Champ retiré du formulaire (remplacé par la répartition par rôle ci-dessous, toujours
        // active) : absent du POST, d'où le repli à 0. Variable locale obligatoire :
        // DatabaseQuery::bind() attend une référence, une expression ?? ne l'est pas.
        $value_nbr_place = $data['nbr_place'] ?? 0;
        $query->bind(':value_nbr_place',  $value_nbr_place);
        $query->bind(':value_reservation_multiple',  $value_reservation_multiple);

        // $query->__toString()

        $db->setQuery($query);

        try {
            $result = $db->execute();
             $data['id_campagne'] =  (!$data['id_campagne']) ? $db->insertid() : $data['id_campagne'];
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        // Répartition par rôle : toujours active (Formation et Loisir demandent systématiquement
        // un rôle par place).
        $this->saveRolePlaces((int) $data['id_campagne'], $data['role_places'] ?? []);

        return  $data['id_campagne'];
    }

    function Effacer ()
    {

                /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $data = $app->getUserState('campagne.effacer');
 
        $db = $this->getDatabase();
        $query = $db->createQuery(); 


        $effacer_value =  1;
        $id_campagne_value = intval($data['id_campagne']);
     
        //  Fields to update.
            $fields = array(
                $db->quoteName('effacer') . ' = :effacer_value'
            );
            // Conditions for which records should be updated.
            $conditions = array(
                $db->quoteName('id_campagne') . ' = :id_campagne_value'
            );
            $query->update($db->quoteName('#__gda_campagnes'))->set($fields)->where($conditions);

            $query->bind(':effacer_value', $effacer_value);
            $query->bind(':id_campagne_value', $id_campagne_value); 

        $db->setQuery($query);

        try {
            $result = $db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        return $result;
    }

/**
 * S'inscrit à une campagne
 * @deprecated  Utiliser SouscriptionService::souscrire() à la place.
 */

    // function Souscrit()
    // {
    //     $app = Factory::getApplication();
    //     $data = $app->getUserState('campagne.souscrit');

    //     $service = new SouscriptionService($this->getDatabase());
    //     return $service->souscrire($data);
    // }


/**
 * Se de-inscrit à une campagne
 * @deprecated  Utiliser SouscriptionService::desouscrire() à la place.
 */
    //  function DeSouscrit()
    // {
    //     $app = Factory::getApplication();
    //     $data = $app->getUserState('campagne.desouscrit');
    //     $user = $app->getIdentity();

    //     $service = new SouscriptionService($this->getDatabase());
    //     return $service->desouscrire($data, $user->username);
    // }


    /**
     * Déterminer si une valeur de `#__gda_campagnes.event_helloasso` désigne un vrai lien HelloAsso.
     *
     * « Pas de lien » s'écrit de trois façons dans le système et les trois coexistent en base et
     * dans les formulaires : SQL `NULL`, chaîne vide, et la chaîne littérale `"null"` postée par le
     * navigateur pour l'option vide du champ. Point de test unique, utilisable aussi depuis un
     * layout (`layouts/campagnes/row.php`), pour éviter que chaque appelant réinvente la condition
     * et en oublie une forme.
     *
     * @param mixed $eventHelloAsso Valeur brute de la colonne ou du champ de formulaire.
     * @return bool True si la valeur désigne un formulaire HelloAsso.
     */
    public static function aUnLienHelloAsso($eventHelloAsso): bool
    {
        return !empty($eventHelloAsso) && (string) $eventHelloAsso !== 'null';
    }

    /**
     * Formulaire HelloAsso (formType/formSlug) de la campagne dont on génère le rapport, lu dans
     * l'état utilisateur posé par CampagnesController::rapport(). "null" (chaîne littérale postée
     * par le navigateur pour une campagne sans lien HelloAsso, ou une colonne SQL NULL) et tout
     * JSON sans formType/formSlug sont refusés ici, plutôt que de laisser un TypeError (HTTP 500,
     * message PHP interne exposé) remonter depuis HelloAssoService.
     *
     * @return object Objet décodé portant au moins formType et formSlug (chaînes non vides).
     * @throws \RuntimeException (404) Si la campagne n'est pas liée à un formulaire HelloAsso.
     */
    private function getFormHelloAssoDuRapport(): object
    {
        /** @var SiteApplication $app */
        $app  = Factory::getApplication();
        $data = $app->getUserState('campagne.rapport');
        $form = json_decode((string) ($data['event_helloasso'] ?? ''));

        if (!is_object($form) || empty($form->formType) || empty($form->formSlug)) {
            throw new \RuntimeException("Cette campagne n'est pas liée à un formulaire HelloAsso", 404);
        }

        return $form;
    }

    /**
    * Retourne le rapport HelloAsso pour une campagne
    * @throws \RuntimeException si la campagne n'est pas liée à un event HelloAsso ou en cas d'erreur de récupération des données
     * @return array Tableau associatif contenant les données du rapport HelloAsso
     * Chaque entrée du tableau correspond à une souscription et contient les clés suivantes :
     * - 'User' : Nom complet de l'utilisateur (prénom + nom)
     * - 'UserPaiment' : Nom complet de la personne ayant effectué le paiement (prénom + nom)
     * - 'EmailPaiment' : Adresse email de la personne ayant effectué le paiement
     * - 'Date' : Date du paiement au format UTC
    */
    function getRapportHelloAsso():array
    {
        $form = $this->getFormHelloAssoDuRapport();
        $data_response = [];

        $service = new \NCB\Component\Gda\Site\Service\HelloAssoService();
        $Items = $service->getFormsItems($form->formType, $form->formSlug);
        foreach ($Items as $key => $Item) {
                $User = $Item['user']['firstName'] . ' ' . $Item['user']['lastName'];
                $UserPaiment = $Item['payer']['lastName'] . ' ' . $Item['payer']['firstName'];
                $EmailPaiment = $Item['payer']['email'];
                $Date= ToolsHelper::isoToUtcFormatted($Item['order']['date']);

                $data_response[$key] = [
                    'User' => $User ?? '',
                    'UserPaiment' => $UserPaiment ?? '',
                    'EmailPaiment' => $EmailPaiment ?? '',
                    'Date' => $Date ?? '',
                ];
        }
        
        return $data_response;

    }

    /**
    * Retourne le rapport HelloAsso pour une campagne de nature Boutique (formulaire "Shop").
    * La structure des items retournés par l'API HelloAsso diffère de celle d'un formulaire "Event"
    * (getRapportHelloAsso()) : pas de bloc "user", un achat n'étant pas lié à un adhérent inscrit
    * mais à un produit acheté - d'où une méthode dédiée plutôt qu'une branche supplémentaire dans
    * getRapportHelloAsso(). Un item = une ligne de produit achetée (une commande peut contenir
    * plusieurs produits, donc plusieurs items partageant le même payeur/date de commande).
    * @throws \RuntimeException si la campagne n'est pas liée à un formulaire HelloAsso ou en cas d'erreur de récupération des données
     * @return array Tableau associatif contenant les données du rapport HelloAsso Boutique.
     * Chaque entrée du tableau correspond à un produit acheté et contient les clés suivantes :
     * - 'Acheteur' : Nom complet de l'acheteur (prénom + nom)
     * - 'EmailAcheteur' : Adresse email de l'acheteur
     * - 'Produit' : Nom du produit acheté
     * - 'Montant' : Montant payé pour ce produit, formaté en euros
     * - 'Date' : Date de la commande au format UTC
    */
    function getRapportHelloAssoBoutique(): array
    {
        $form = $this->getFormHelloAssoDuRapport();
        $data_response = [];

        $service = new \NCB\Component\Gda\Site\Service\HelloAssoService();
        $Items = $service->getFormsItems($form->formType, $form->formSlug);
        foreach ($Items as $key => $Item) {
            $Acheteur = trim(($Item['payer']['firstName'] ?? '') . ' ' . ($Item['payer']['lastName'] ?? ''));
            // Montant HelloAsso renvoyé en centimes (cf. cartographie §9 "ne pas confondre les unites").
            $Montant = number_format(((int) ($Item['amount'] ?? 0)) / 100, 2, ',', ' ') . ' €';

            $data_response[$key] = [
                'Acheteur'      => $Acheteur,
                'EmailAcheteur' => $Item['payer']['email'] ?? '',
                'Produit'       => $Item['name'] ?? '',
                'Montant'       => $Montant,
                'Date'          => ToolsHelper::isoToUtcFormatted($Item['order']['date'] ?? null),
            ];
        }

        return $data_response;
    }

    /**
     * Rapport rapide des réservations d'une campagne (hors HelloAsso, cf. getRapportHelloAsso).
     * Une ligne par PLACE non annulée (#__gda_reservation_places) : identité, niveau de plongée,
     * rôle et statut (attente / confirmee / refusee, décidé manuellement par le responsable de
     * campagne - plus de file d'attente automatique). Depuis la fusion Formation/Loisir, un
     * adhérent ayant réservé plusieurs rôles à la fois apparaît en plusieurs lignes.
     *
     * @return array<int, array{nom_complet: string, username: string, niveau: string, role: string,
     *                           date_reservation: ?string, statut: string}>
     */
    function getRapport(): array
    {
        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $data = $app->getUserState('campagne.rapport');
        $idCampagne = (int) ($data['id_campagne'] ?? 0);

        $db = $this->getDatabase();
        $statut_annulee = ReservationService::STATUT_ANNULEE;

        $query = $db->createQuery()
            ->select($db->quoteName([
                'rp.id_place', 'rp.role', 'rp.statut', 'rp.date_rang', 'r.commentaire',
                'p.id_profil', 'p.nom', 'p.prenom', 'u.username',
            ]))
            ->from($db->quoteName('#__gda_reservation_places', 'rp'))
            ->innerJoin($db->quoteName('#__gda_reservation', 'r') . ' ON ' . $db->quoteName('r.id_reservation') . ' = ' . $db->quoteName('rp.id_reservation'))
            ->innerJoin($db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('r.id_profil'))
            ->innerJoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.id_profil'))
            ->where($db->quoteName('rp.id_campagne') . ' = :id_campagne')
            // Ordre = ordre d'arrivée dans la file d'attente ; les désistements (annulee) en dernier.
            ->order('(' . $db->quoteName('rp.statut') . ' = :statut_annulee) ASC, ' . $db->quoteName('rp.date_rang') . ' ASC')
            ->bind(':id_campagne', $idCampagne, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':statut_annulee', $statut_annulee);

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList() ?: [];
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        if (empty($rows)) {
            return [];
        }

        $idsProfil = array_values(array_unique(array_map(fn($row) => (int) $row->id_profil, $rows)));
        $niveauxParProfil = $this->getNiveauxParProfil($idsProfil);

        $rapport = [];

        foreach ($rows as $row) {
            $rapport[] = [
                'nom_complet'      => trim($row->prenom . ' ' . $row->nom),
                'username'         => $row->username,
                'niveau'           => $niveauxParProfil[(int) $row->id_profil] ?? '',
                'role'             => $row->role,
                'date_reservation' => $row->date_rang,
                'statut'           => (string) $row->statut,
                'commentaire'      => trim((string) $row->commentaire),
            ];
        }

        return $rapport;
    }

    /**
     * Niveaux de plongée (codes de brevets) par profil, un aperçu par activité/rôle (même
     * réduction "plus fort poids" que partout ailleurs dans le composant).
     *
     * Ne lit plus #__gda_niveaux (table legacy, remplacée par #__gda_brevets/#__gda_mapping_brevets
     * et vidée depuis) : délègue à BrevetService::getBrevetsShortListProfils(), le point
     * d'extension standard déjà utilisé par getInscritsCampagne(), GroupesModel et UtilisateursModel.
     *
     * @param  int[] $idsProfil
     * @return array<int, string> id_profil => codes concaténés (ex: "N2, RIFAP")
     */
    private function getNiveauxParProfil(array $idsProfil): array
    {
        if (empty($idsProfil)) {
            return [];
        }

        $shortLists = $this->getBrevetService()->getBrevetsShortListProfils($idsProfil);

        return array_map(
            fn($brevets) => implode(', ', array_unique(array_filter(array_map(
                fn($brevet) => $brevet->code ?? '',
                $brevets
            )))),
            $shortLists
        );
    }

}
