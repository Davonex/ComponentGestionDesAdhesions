<?php

/**  Mdel d'acueil */

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Service\ReservationService;



class AccueilModel extends ListModel
{

    protected $_items = null;

    private ?ReservationService $reservationService = null;

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
     *                      date_rang, tri, rang}, voir ReservationService::getReservation()) —
     *                      une réservation Loisir pouvant mélanger plusieurs rôles à statuts
     *                      différents, un simple statut/rang global ne suffit plus.
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
        $query = $db->getQuery(true);

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

            $maReservation = $this->getReservationService()->getReservation($idCampagne, $idProfil);
            $campagne->mes_places = ($maReservation !== null && !$maReservation->annulee) ? $maReservation->places : [];

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
        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('s.id_campagne'),
            $db->quoteName('s.id_profil'),
            $db->quoteName('s.date_souscription'),
            $db->quoteName('s.cotisation_code'),
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