<?php

/**  Mdel d'acueil */

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\ConfHelper;



class AccueilModel extends ListModel
{

    protected $_items = null;

    /**
     * Campagnes de type Formation ouvertes, triées par date de fin croissante (les plus urgentes
     * en premier), enrichies de l'état de réservation de l'adhérent connecté.
     *
     * Lit #__gda_reservation : #__gda_souscriptions est désormais réservée aux souscriptions de la
     * saison (workflow CACI / cotisation / licence). Les natures Sortie, Soirée et Boutique auront
     * chacune leur méthode et leur layout dédiés.
     *
     * Chaque ligne porte en plus :
     *  - places_occupees    : places déjà accordées (hors réservations annulées)
     *  - ma_reservation     : id_reservation de l'adhérent, ou NULL
     *  - mon_statut         : confirmee | attente | annulee, ou NULL
     *  - mes_places         : places demandées par l'adhérent
     *  - mon_commentaire    : son commentaire
     *
     * @param  \Joomla\CMS\User\User $user
     * @return object[]
     */
    public function getFormations($user): array
    {
        if ($user === null || (int) $user->id <= 0) {
            return [];
        }

        $idProfil      = (int) $user->id;
        $idTypeFormation = (int) ConfHelper::getValue('IdTypeFormation');
        $statutAnnulee = \NCB\Component\Gda\Site\Service\ReservationService::STATUT_ANNULEE;

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('cp.*')
            ->select('tc.type_name, tc.type_image, tc.type_class')
            // Places occupées : somme sur toutes les réservations non annulées de la campagne.
            ->select('COALESCE(SUM(CASE WHEN ' . $db->quoteName('r.statut') . ' != :statut_annulee'
                . ' THEN ' . $db->quoteName('r.nbr_places_confirmees') . ' ELSE 0 END), 0) AS places_occupees')
            // Ma réservation : jointure dédiée sur mon profil, pour ne pas dépendre du GROUP BY.
            ->select($db->quoteName('moi.id_reservation', 'ma_reservation'))
            ->select($db->quoteName('moi.statut', 'mon_statut'))
            ->select($db->quoteName('moi.nbr_places', 'mes_places'))
            ->select($db->quoteName('moi.commentaire', 'mon_commentaire'))
            ->from($db->quoteName('#__gda_campagnes', 'cp'))
            ->join('LEFT', $db->quoteName('#__gda_type_de_campagne', 'tc'),
                $db->quoteName('cp.id_type') . ' = ' . $db->quoteName('tc.id_type'))
            ->join('LEFT', $db->quoteName('#__gda_reservation', 'r'),
                $db->quoteName('cp.id_campagne') . ' = ' . $db->quoteName('r.id_campagne'))
            ->join('LEFT', $db->quoteName('#__gda_reservation', 'moi'),
                $db->quoteName('cp.id_campagne') . ' = ' . $db->quoteName('moi.id_campagne')
                . ' AND ' . $db->quoteName('moi.id_profil') . ' = :id_profil')
            ->where($db->quoteName('cp.id_type') . ' = :id_type_formation')
            ->where($db->quoteName('cp.active') . ' = 1')
            ->where($db->quoteName('cp.effacer') . ' = 0')
            ->where($db->quoteName('cp.date_debut') . ' <= CURDATE()')
            ->where($db->quoteName('cp.date_fin') . ' >= CURDATE()')
            ->group($db->quoteName('cp.id_campagne'))
            ->order($db->quoteName('cp.date_fin') . ' ASC')
            ->bind(':statut_annulee', $statutAnnulee)
            ->bind(':id_profil', $idProfil, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':id_type_formation', $idTypeFormation, \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            return $db->loadObjectList() ?: [];
        } catch (\RuntimeException $e) {
            throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
        }
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