<?php

/**  Mdel d'acueil */

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Model\CampagnesModel;
use NCB\Component\Gda\Site\Service\ReservationService;
use NCB\Component\Gda\Site\Service\HelloAssoService;



class AccueilModel extends ListModel
{

    protected $_items = null;

    private ?ReservationService $reservationService = null;

    private ?HelloAssoService $helloAssoService = null;

    /**
     * Campagnes Formation et Loisir ouvertes, triées par date de fin croissante (les plus
     * urgentes en premier), enrichies de l'état de réservation de l'adhérent connecté.
     *
     * Lit #__gda_reservation(_places) : #__gda_souscriptions est réservée aux souscriptions de la
     * saison (workflow CACI / cotisation / licence).
     *
     * Chaque ligne porte en plus :
     *  - places_occupees : places confirmées, tous rôles confondus (hors réservations annulées)
     *  - capacite_totale : somme des capacités par rôle (#__gda_campagne_roles)
     *  - mes_places      : mes places pour cette campagne (array de {id_place, role, statut,
     *                      date_rang, tri}, voir ReservationService::getReservation()) — statut
     *                      STATUT_ATTENTE = en attente de validation par le responsable de
     *                      campagne, STATUT_CONFIRMEE = validée. Une réservation Loisir pouvant
     *                      mélanger plusieurs rôles à statuts différents, un simple statut global
     *                      ne suffit pas.
     *  - places_par_role : disponibilité de chaque rôle configuré pour cette campagne (array de
     *                      {role, total, disponible}, disponible = null si illimité) — permet à
     *                      l'affichage de détailler une ligne par rôle dès qu'il y en a plus d'un
     *                      (le compteur global places_occupees/capacite_totale reste pertinent
     *                      seul quand la campagne n'a qu'un rôle).
     *  - paiement_helloasso : 'paye' | 'non_paye' | null (non applicable — pas de lien HelloAsso,
     *                      ou aucune place confirmée), pour le badge € du dashboard. Voir
     *                      resoudrePaiementHelloAsso().
     *
     * @param  \Joomla\CMS\User\User $user
     * @return object[]
     */
    public function getCampagnesReservables($user): array
    {
        if ($user === null || (int) $user->id <= 0) {
            return [];
        }

        $idProfil        = (int) $user->id;
        $idTypeFormation = (int) ConfHelper::getValue('IdTypeFormation');
        $idTypeLoisir    = (int) ConfHelper::getValue('IdTypeLoisir');

        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query->select('cp.*')
            ->select('tc.type_name, tc.type_image, tc.type_class')
            ->select(ReservationService::getSelectPlacesOccupeesTotal($db, 'cp') . ' AS places_occupees')
            ->select(ReservationService::getSelectCapaciteTotale($db, 'cp') . ' AS capacite_totale')
            ->from($db->quoteName('#__gda_campagnes', 'cp'))
            ->join('LEFT', $db->quoteName('#__gda_type_de_campagne', 'tc'),
                $db->quoteName('cp.id_type') . ' = ' . $db->quoteName('tc.id_type'))
            ->whereIn($db->quoteName('cp.id_type'), [$idTypeFormation, $idTypeLoisir], \Joomla\Database\ParameterType::INTEGER)
            ->where($db->quoteName('cp.active') . ' = 1')
            ->where($db->quoteName('cp.effacer') . ' = 0')
            ->where($db->quoteName('cp.date_debut') . ' <= CURDATE()')
            ->where($db->quoteName('cp.date_fin') . ' >= CURDATE()')
            ->order($db->quoteName('cp.date_fin') . ' ASC');

        $db->setQuery($query);

        try {
            $campagnes = $db->loadObjectList() ?: [];
        } catch (\RuntimeException $e) {
            throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
        }

        // Mes places par campagne : nombre de campagnes affiché borné (dashboard d'un seul
        // adhérent, quelques campagnes ouvertes à la fois), pas de N+1 problématique à réutiliser
        // ReservationService::getReservation() plutôt que de dupliquer sa logique en SQL agrégé.
        foreach ($campagnes as $campagne) {
            $idCampagne = (int) $campagne->id_campagne;

            // Places annulées comprises : l'adhérent voit « Annulée » (et la réservation reste verrouillée).
            $maReservation = $this->getReservationService()->getReservation($idCampagne, $idProfil, true);
            $campagne->mes_places = $maReservation !== null ? $maReservation->places : [];

            $campagne->places_par_role = [];
            foreach ($this->getReservationService()->getCapacitesParRole($idCampagne) as $role => $capaciteRole) {
                $campagne->places_par_role[] = (object) [
                    'role'       => $role,
                    'total'      => $capaciteRole,
                    'disponible' => $this->getReservationService()->getPlacesDisponiblesParRole($idCampagne, $role, $capaciteRole),
                ];
            }

            $campagne->paiement_helloasso = $this->resoudrePaiementHelloAsso(
                $campagne,
                $maReservation,
                $idCampagne,
                $idProfil,
                (string) $user->username
            );
        }

        return $campagnes;
    }

    /**
     * Statut de paiement HelloAsso affiché en badge sur le dashboard, pour une réservation avec
     * au moins une place confirmée sur une campagne liée à un événement HelloAsso (même
     * condition que le popup de paiement post-réservation, voir
     * ReservationController::reserver()). Recherche et persiste l'id_order manquant en direct
     * (ReservationService::resolveIdOrder(), sans le cache de 30 minutes de HelloAssoService),
     * pour que le badge passe au vert dès le paiement plutôt que d'attendre.
     *
     * @param  object      $campagne      Campagne courante (doit exposer event_helloasso).
     * @param  object|null $maReservation Réservation de l'adhérent pour cette campagne (voir
     *                                    ReservationService::getReservation()), ou null.
     * @param  int         $idCampagne    Campagne concernée.
     * @param  int         $idProfil      Adhérent concerné.
     * @param  string      $username      Username Joomla de l'adhérent.
     * @return string|null 'paye', 'non_paye', ou null si non applicable.
     */
    private function resoudrePaiementHelloAsso(object $campagne, ?object $maReservation, int $idCampagne, int $idProfil, string $username): ?string
    {
        if ($maReservation === null || $maReservation->annulee) {
            return null;
        }

        $aUnePlaceConfirmee = false;

        foreach ($maReservation->places as $place) {
            if ($place->statut === ReservationService::STATUT_CONFIRMEE) {
                $aUnePlaceConfirmee = true;
                break;
            }
        }

        if (!$aUnePlaceConfirmee || empty($campagne->event_helloasso)) {
            return null;
        }

        $eventHelloAsso = json_decode((string) $campagne->event_helloasso, true);
        $formType       = $eventHelloAsso['formType'] ?? '';
        $formSlug       = $eventHelloAsso['formSlug'] ?? '';

        if ($formType === '' || $formSlug === '') {
            return null;
        }

        $idOrder = $this->getReservationService()->resolveIdOrder(
            $idCampagne,
            $idProfil,
            (string) ($maReservation->id_order ?? ''),
            $formType,
            $formSlug,
            $username
        );

        return $idOrder !== '' ? 'paye' : 'non_paye';
    }

    /**
     * Getter pour obtenir le service Réservation (lazy loading, pas dans le conteneur DI du composant).
     * Même motif que CampagnesModel::getBrevetService() / ProfilModel::getBrevetService().
     *
     * @return ReservationService Instance partagée pour la durée de la requête HTTP.
     */
    private function getReservationService(): ReservationService
    {
        if ($this->reservationService === null) {
            $this->reservationService = new ReservationService($this->getDatabase());
        }

        return $this->reservationService;
    }

    /**
     * Campagnes Boutique actives (pas de mécanisme de réservation, voir
     * CampagnesModel::getHelloAssoFormTypeParNature()), enrichies des articles proposés à la
     * vente sur le formulaire "Shop" HelloAsso lié (photo, prix, disponibilité).
     *
     * @param  bool $forceRefresh Ignore le cache fichier de 30 min (HelloAssoService::getFormsPublicCached()/
     *                            getFormsStatsCached()) - bouton "Rafraîchir" de l'encart (AccueilController::refreshBoutique()).
     * @return object[] Campagnes triées par date de fin croissante, chacune enrichie de :
     *  - articles    : array de resoudreArticlesBoutique() - vide si pas d'event HelloAsso
     *                  configuré ou si l'appel API échoue (l'encart reste affiché avec ses
     *                  dates, seule la liste d'articles est alors absente).
     *  - url_boutique : lien HelloAsso de la boutique (pour le bouton "Acheter"), ou null.
     */
    public function getCampagnesBoutique(bool $forceRefresh = false): array
    {
        $idTypeBoutique = (int) ConfHelper::getValue('IdTypeBoutique');

        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query->select('cp.*')
            ->select('tc.type_name, tc.type_image, tc.type_class')
            ->from($db->quoteName('#__gda_campagnes', 'cp'))
            ->join('LEFT', $db->quoteName('#__gda_type_de_campagne', 'tc'),
                $db->quoteName('cp.id_type') . ' = ' . $db->quoteName('tc.id_type'))
            ->where($db->quoteName('cp.id_type') . ' = :id_type_boutique')
            ->where($db->quoteName('cp.active') . ' = 1')
            ->where($db->quoteName('cp.effacer') . ' = 0')
            ->where($db->quoteName('cp.date_debut') . ' <= CURDATE()')
            ->where($db->quoteName('cp.date_fin') . ' >= CURDATE()')
            ->order($db->quoteName('cp.date_fin') . ' ASC')
            ->bind(':id_type_boutique', $idTypeBoutique, \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $campagnes = $db->loadObjectList() ?: [];
        } catch (\RuntimeException $e) {
            throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
        }

        foreach ($campagnes as $campagne) {
            $campagne->articles     = [];
            $campagne->url_boutique = null;

            if (!CampagnesModel::aUnLienHelloAsso($campagne->event_helloasso)) {
                continue;
            }

            $eventHelloAsso = json_decode((string) $campagne->event_helloasso, true);
            $formType = is_array($eventHelloAsso) ? ($eventHelloAsso['formType'] ?? '') : '';
            $formSlug = is_array($eventHelloAsso) ? ($eventHelloAsso['formSlug'] ?? '') : '';

            if ($formType === '' || $formSlug === '') {
                continue;
            }

            // Une Boutique indisponible (API HelloAsso en erreur) ne doit pas casser tout le
            // dashboard : l'encart reste affiché avec ses dates, sans liste d'articles.
            try {
                $formPublic = $this->getHelloAssoService()->getFormsPublicCached($formType, $formSlug, $forceRefresh);
            } catch (\Throwable $e) {
                // Déjà journalisé une fois par HelloAssoService::getCachedOrFetch() : ne pas le refaire
                // ici, l'échec mémorisé serait sinon reloggé à chaque affichage du dashboard.
                continue;
            }

            // Le stock (maxEntries/entriesTaken) n'est pas sur le formulaire public mais sur
            // /stats (getFormsStatsCached()) : un échec ici ne doit pas priver l'adhérent de la
            // liste d'articles elle-même, juste du détail de quantité (voir resoudreStatsParTier()).
            try {
                $formStats = $this->getHelloAssoService()->getFormsStatsCached($formType, $formSlug, $forceRefresh);
            } catch (\Throwable $e) {
                $formStats = [];
            }

            $campagne->url_boutique = $formPublic['url'] ?? null;
            $campagne->articles     = $this->resoudreArticlesBoutique($formPublic['tiers'] ?? [], $this->resoudreStatsParTier($formStats));
        }

        return $campagnes;
    }

    /**
     * Indexe par id de tier les statistiques de vente d'un formulaire Boutique (FormStatsModel,
     * HelloAssoService::getFormsStatsCached()) - unGroupedTiers et additionalOptions confondus, le
     * calcul de stock restant étant le même pour les deux.
     *
     * @param  array $formStats Réponse brute de getFormsStatsCached() ('unGroupedTiers', 'additionalOptions').
     * @return array<int, array{maxEntries: ?int, entriesTaken: int, isEnabled: bool}> id du tier => stats.
     */
    private function resoudreStatsParTier(array $formStats): array
    {
        $statsParTier = [];

        foreach (array_merge($formStats['unGroupedTiers'] ?? [], $formStats['additionalOptions'] ?? []) as $tierStats) {
            if (!isset($tierStats['id'])) {
                continue;
            }

            $statsParTier[(int) $tierStats['id']] = [
                'maxEntries'   => isset($tierStats['maxEntries']) ? (int) $tierStats['maxEntries'] : null,
                'entriesTaken' => (int) ($tierStats['entriesTaken'] ?? 0),
                'isEnabled'    => (bool) ($tierStats['isEnabled'] ?? true),
            ];
        }

        return $statsParTier;
    }

    /**
     * Met en forme les articles ("tiers") d'un formulaire Boutique HelloAsso pour l'affichage.
     *
     * La disponibilité combine trois sources, la première applicable l'emportant :
     *  1. isEnabled = false (tier désactivé côté HelloAsso) -> "termine"
     *  2. stock épuisé (maxEntries défini et maxEntries - entriesTaken <= 0, cf. /stats) -> "termine"
     *  3. fenêtre de vente du tier (saleStartDate/saleEndDate, cf. /public) -> "bientot"/"termine"
     * Un tier sans aucune limite ni date est considéré disponible en continu.
     *
     * @param  array $tiers        Tableau 'tiers' brut d'un FormPublicModel (HelloAssoService::getFormsPublicCached()).
     * @param  array $statsParTier Stats indexées par id de tier (resoudreStatsParTier()), [] si /stats indisponible.
     * @return array<int, array{label: string, description: string, prix: string, photoUrl: ?string, disponibilite: string, quantite: ?int}>
     *   disponibilite : 'disponible' | 'bientot' | 'termine'. quantite : places restantes si un maximum est configuré, sinon null (illimité).
     */
    private function resoudreArticlesBoutique(array $tiers, array $statsParTier = []): array
    {
        $now      = new \DateTimeImmutable();
        $articles = [];

        foreach ($tiers as $tier) {
            $stats      = $statsParTier[(int) ($tier['id'] ?? 0)] ?? null;
            $quantite   = null;
            $stockEpuise = false;

            if ($stats !== null && $stats['maxEntries'] !== null) {
                $quantite    = max(0, $stats['maxEntries'] - $stats['entriesTaken']);
                $stockEpuise = $quantite <= 0;
            }

            $disponibilite = 'disponible';

            try {
                $debut = !empty($tier['saleStartDate']) ? new \DateTimeImmutable((string) $tier['saleStartDate']) : null;
                $fin   = !empty($tier['saleEndDate']) ? new \DateTimeImmutable((string) $tier['saleEndDate']) : null;

                if ($debut !== null && $now < $debut) {
                    $disponibilite = 'bientot';
                } elseif ($fin !== null && $now > $fin) {
                    $disponibilite = 'termine';
                }
            } catch (\Exception $e) {
                // Date HelloAsso non parseable : on garde le défaut "disponible" plutôt que de
                // masquer l'article ou de faire échouer tout l'encart pour une seule date invalide.
            }

            if ($stockEpuise || ($stats !== null && !$stats['isEnabled'])) {
                $disponibilite = 'termine';
            }

            $prix      = (int) ($tier['price'] ?? 0);
            $minAmount = (int) ($tier['minAmount'] ?? 0);

            // Prix HelloAsso renvoyé en centimes (cf. cartographie §9 "ne pas confondre les unites").
            if ($prix > 0) {
                $prixAffiche = number_format($prix / 100, 2, ',', ' ') . ' €';
            } elseif ($minAmount > 0) {
                $prixAffiche = Text::sprintf('COM_GDA_ACCUEIL_BOUTIQUE_PRIX_LIBRE', number_format($minAmount / 100, 2, ',', ' '));
            } else {
                $prixAffiche = Text::_('COM_GDA_ACCUEIL_BOUTIQUE_GRATUIT');
            }

            $articles[] = [
                'label'         => (string) ($tier['label'] ?? ''),
                'description'   => (string) ($tier['description'] ?? ''),
                'prix'          => $prixAffiche,
                'photoUrl'      => $tier['picture']['publicUrl'] ?? null,
                'disponibilite' => $disponibilite,
                'quantite'      => $quantite,
            ];
        }

        return $articles;
    }

    /**
     * Getter pour obtenir le service HelloAsso (lazy loading, pas dans le conteneur DI du
     * composant). Même motif que getReservationService().
     *
     * @return HelloAssoService Instance partagée pour la durée de la requête HTTP.
     */
    private function getHelloAssoService(): HelloAssoService
    {
        if ($this->helloAssoService === null) {
            $this->helloAssoService = new HelloAssoService();
        }

        return $this->helloAssoService;
    }


    /**
     * Récupère le statut de souscription d'un utilisateur pour une campagne.
     *
     * @param int $userId Identifiant de l'utilisateur (id_profil)
     * @param int $idCampagne Identifiant de la campagne
     * @return object|null Objet souscription enrichi avec profil/dates, ou null si pas de souscription
     * @throws \RuntimeException en cas d'erreur SQL
     */
    public function getAdhesionStatus(int $userId, int $idCampagne): ?object
    {
        if ($userId <= 0 || $idCampagne <= 0) {
            return null;
        }

        $db = $this->getDatabase();
        $query = $db->createQuery();

        $query->select([
            $db->quoteName('s.id_campagne'),
            $db->quoteName('s.id_profil'),
            $db->quoteName('s.date_souscription'),
            $db->quoteName('s.cotisation_code'),
            $db->quoteName('s.cotisation_montant'),
            $db->quoteName('s.caci_check'),
            $db->quoteName('s.date_caci_check'),
            $db->quoteName('s.cotisation_check'),
            $db->quoteName('s.date_cotisation_check'),
            $db->quoteName('s.licence_check'),
            $db->quoteName('s.date_licence_check'),
            $db->quoteName('s.id_order'),
            $db->quoteName('s.last_update'),
            $db->quoteName('s.categorie'),
            $db->quoteName('p.caci'),
            $db->quoteName('p.date_caci'),
            $db->quoteName('p.date_licence'),
            $db->quoteName('p.ffessm_token'),
            $db->quoteName('u.username'),
        ])
        ->from($db->quoteName('#__gda_souscriptions', 's'))
        ->leftJoin($db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('s.id_profil') . ' = ' . $db->quoteName('p.id_profil'))
        ->leftJoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('u.id'))
        ->where($db->quoteName('s.id_profil') . ' = :userId')
        ->where($db->quoteName('s.id_campagne') . ' = :idCampagne')
        ->bind(':userId', $userId)
        ->bind(':idCampagne', $idCampagne);

        $db->setQuery($query);

        try {
            return $db->loadObject();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Erreur récupération statut adhésion: ' . $e->getMessage(), 500, $e);
        }
    }

     /**
     * retourner le code HTMH d'une Campagne
     *
     * @param   object   $profil         Tous les donnée du profil utilisateu
     * @param   boolean  $principale         The path  to move the uploaded file to
     
     *
     * @return  string  texte html d'un profil
     *
     * @since   1.0
     * @throws  
     */


}