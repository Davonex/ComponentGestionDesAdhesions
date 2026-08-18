<?php

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;

use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Service\SouscriptionService;


class CampagnesModel extends ListModel
{

    protected $_item = null;

    /**
     * Expression SELECT des places occupées d'une campagne, à utiliser avec une jointure sur
     * #__gda_reservation aliasée `r` et un paramètre lié :statut_annulee.
     *
     * On additionne les PLACES accordées (nbr_places_confirmees) et non le nombre de lignes :
     * depuis la mise en place des réservations, un adhérent peut occuper plusieurs places. Les
     * réservations annulées et les places encore en liste d'attente n'occupent rien.
     */
    private function getSelectPlacesOccupees($db): string
    {
        return 'COALESCE(SUM(CASE WHEN ' . $db->quoteName('r.statut') . ' != :statut_annulee'
            . ' THEN ' . $db->quoteName('r.nbr_places_confirmees') . ' ELSE 0 END), 0) AS places_occupees';
    }


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
            $select = $db->getQuery(true);

            $statut_annulee = \NCB\Component\Gda\Site\Service\ReservationService::STATUT_ANNULEE;

            $select->select('c.*');
            $select->select('tc.*');
            $select->select($this->getSelectPlacesOccupees($db));
            $select->from($db->quoteName('#__gda_campagnes', 'c'));
            $select->join('left', $db->quoteName('#__gda_type_de_campagne', 'tc'), $db->quoteName('c.id_type') . ' = ' . $db->quoteName('tc.id_type'));
            $select->join('left', $db->quoteName('#__gda_reservation', 'r'), $db->quoteName('c.id_campagne') . ' = ' . $db->quoteName('r.id_campagne'));

            $select->where($db->quoteName('c.id_campagne') . '= :value_id_campagne');

            $select->bind(':value_id_campagne', $id_campagne);
            $select->bind(':statut_annulee', $statut_annulee);
            $select->group($db->quoteName('c.id_campagne'));

            $db->setQuery($select);
            try {
                    $item = $db->loadObjectList();
                } catch (\RuntimeException $e) {
                    throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
                    // $query->__toString()
                    // Factory::getApplication()->enqueueMessage("Erreur de chargement des campagne, Contacter votre administrateur", 'error');
                }
            if (count($item) !== 1) {
             throw new \Exception("Bizard  il y a 0 ou plusieurs campagne avec l'ID:".$id_campagne, 500);
            }
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

        $select = $db->getQuery(true);

        $statut_annulee = \NCB\Component\Gda\Site\Service\ReservationService::STATUT_ANNULEE;

        $select->select('c.*');
        $select->select('tc.*');
        $select->select($this->getSelectPlacesOccupees($db));
        $select->from($db->quoteName('#__gda_campagnes', 'c'));
        $select->join('left', $db->quoteName('#__gda_type_de_campagne', 'tc'), $db->quoteName('c.id_type') . ' = ' . $db->quoteName('tc.id_type'));
        $select->join('left', $db->quoteName('#__gda_reservation', 'r'), $db->quoteName('c.id_campagne') . ' = ' . $db->quoteName('r.id_campagne'));

        $select->where($db->quoteName('c.effacer') . '= 0');
        $select->where($db->quoteName('c.id_type') . ' != :id_type_saison');
        $select->bind(':id_type_saison', $id_type_saison);
        $select->bind(':statut_annulee', $statut_annulee);
        $select->group($db->quoteName('c.id_campagne'));

        $db->setQuery($select);
            try {
                $this->_items = $db->loadObjectList();
            } catch (\RuntimeException $e) {
                throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
                // $select->__toString()
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

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gda_type_de_campagne'))
            ->where($db->quoteName('id_type') . ' != :id_type_saison')
            ->order($db->quoteName('type_name') . ' ASC')
            ->bind(':id_type_saison', $id_type_saison);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Liste fixe des rôles proposés par nature de campagne (ex: Formation -> Encadrants/Participants),
     * pour affichage en lecture seule dans le formulaire quand "role_actif" est activé. Non
     * configurable pour l'instant (#__gda_role_de_campagne n'a pas encore d'écran d'administration).
     *
     * @return array<int, string[]> id_type => liste des rôles
     */
    function getRolesDeCampagne(): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
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
     * Retourne les adhérents ayant réservé une place sur une campagne (hors saison), sous la même
     * forme qu'un groupe issu de GroupesModel::getGroupesAvecAdherents() afin de pouvoir réutiliser
     * tel quel les layouts groupes.detail / groupes.vignette pour l'onglet "Suivi des inscriptions".
     */
    function getInscritsCampagne(int $id_campagne, string $titre): object
    {
        $db = $this->getDatabase();
        $statut_annulee = \NCB\Component\Gda\Site\Service\ReservationService::STATUT_ANNULEE;

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('p.id_profil'),
                $db->quoteName('p.civilite'),
                $db->quoteName('p.nom'),
                $db->quoteName('p.prenom'),
                $db->quoteName('p.photo'),
                $db->quoteName('p.caci'),
                $db->quoteName('p.date_caci'),
            ])
            ->from($db->quoteName('#__gda_reservation', 'r'))
            ->innerJoin($db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('r.id_profil'))
            ->where($db->quoteName('r.id_campagne') . ' = :id_campagne')
            ->where($db->quoteName('r.statut') . ' != :statut_annulee')
            ->order($db->quoteName('p.nom') . ' ASC, ' . $db->quoteName('p.prenom') . ' ASC')
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

            $groupe->adherents[] = $adherent;
        }

        return $groupe;
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
        $query = $db->getQuery(true);

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
        $query = $db->getQuery(true);
     
        $value_id_groupes = ( isset($data['id_groupes'])) ? implode(',', $data['id_groupes']) : ''; 

        $value_date_debut =ToolsHelper::to_sqldate($data['date_debut']);
        $value_date_fin = ToolsHelper::to_sqldate($data['date_fin']);
        // Date de l'événement : datetime (heure comprise), donc convertisseur dédié.
        $value_date_evenement = ToolsHelper::to_sqldatetime($data['date_evenement'] ?? null);
        $value_event_helloasso = (isset($data['event_helloasso'])) ? ($data['event_helloasso']) : null;
        $value_reservation_multiple = !empty($data['reservation_multiple']) ? 1 : 0;
        $value_role_actif = !empty($data['role_actif']) ? 1 : 0;

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
                $db->quoteName('id_groupes') . '= :value_id_groupes',
                $db->quoteName('nbr_place') . '= :value_nbr_place',
                $db->quoteName('reservation_multiple') . '= :value_reservation_multiple',
                $db->quoteName('role_actif') . '= :value_role_actif',
                $db->quoteName('active') . '= :value_active',
            );
            $conditions = array( $db->quoteName('id_campagne') . ' = :value_id_campagne');
            $query->update($db->quoteName('#__gda_campagnes'))->set($fields)->where($conditions);
            $query->bind(':value_id_campagne',  $data['id_campagne']);
       } else {
            // New Item
            $value_active = (int) 0;

                // Insert
            $columns = array('titre','description', 'event_helloasso','date_debut', 'date_fin', 'date_evenement', 'active', 'id_article','id_type','id_groupes','nbr_place','reservation_multiple','role_actif');
            $query->insert($db->quoteName('#__gda_campagnes'));
            $query->columns($db->quoteName($columns));
            $query->values(':value_titre, :value_description, :value_event_helloasso, :value_date_debut, :value_date_fin, :value_date_evenement, :value_active, :value_id_article, :value_id_type, :value_id_groupes, :value_nbr_place, :value_reservation_multiple, :value_role_actif');
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
        $query->bind(':value_id_groupes', $value_id_groupes);

        $query->bind(':value_nbr_place',  $data['nbr_place']);
        $query->bind(':value_reservation_multiple',  $value_reservation_multiple);
        $query->bind(':value_role_actif',  $value_role_actif);

        // $query->__toString()

        $db->setQuery($query);

        try {
            $result = $db->execute();
             $data['id_campagne'] =  (!$data['id_campagne']) ? $db->insertid() : $data['id_campagne'];
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        return  $data['id_campagne'];
    }

    function Effacer ()
    {

                /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $data = $app->getUserState('campagne.effacer');
 
        $db = $this->getDatabase();
        $query = $db->getQuery(true); 


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
               /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $data = $app->getUserState('campagne.rapport');
        if ( !$data['event_helloasso']) {
            throw new \RuntimeException("Cette campagne n'est pas liée à un event HelloAsso", 404);
        }
        $form= json_decode($data['event_helloasso']);
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
     * Rapport rapide des réservations d'une campagne (hors HelloAsso, cf. getRapportHelloAsso).
     * Une ligne par réservation non annulée : identité, niveau de plongée, rôle choisi (si la
     * campagne le demande) et rang dans la liste d'attente le cas échéant.
     *
     * @return array<int, array{nom_complet: string, username: string, niveau: string, role: string,
     *                           date_reservation: ?string, en_attente: bool, rang_attente: ?int}>
     */
    function getRapport(): array
    {
        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $data = $app->getUserState('campagne.rapport');
        $idCampagne = (int) ($data['id_campagne'] ?? 0);
        $roleActif = !empty($data['role_actif']);

        $db = $this->getDatabase();
        $statut_annulee = \NCB\Component\Gda\Site\Service\ReservationService::STATUT_ANNULEE;
        $statut_attente = \NCB\Component\Gda\Site\Service\ReservationService::STATUT_ATTENTE;

        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'r.id_reservation', 'r.id_profil', 'r.date_reservation', 'r.statut',
                'p.nom', 'p.prenom', 'u.username',
            ]))
            ->from($db->quoteName('#__gda_reservation', 'r'))
            ->innerJoin($db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('r.id_profil'))
            ->innerJoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.id_profil'))
            ->where($db->quoteName('r.id_campagne') . ' = :id_campagne')
            ->where($db->quoteName('r.statut') . ' != :statut_annulee')
            // Ordre = ordre d'arrivée dans la file d'attente : sert à calculer le rang des places
            // encore en attente ci-dessous.
            ->order($db->quoteName('r.date_reservation') . ' ASC')
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

        $idsReservation = array_map(fn($row) => (int) $row->id_reservation, $rows);
        $rolesParReservation = $roleActif ? $this->getRolesParReservation($idsReservation) : [];

        $rangAttente = 0;
        $rapport = [];

        foreach ($rows as $row) {
            $enAttente = $row->statut === $statut_attente;
            $rangAttente += $enAttente ? 1 : 0;

            $rapport[] = [
                'nom_complet'      => trim($row->prenom . ' ' . $row->nom),
                'username'         => $row->username,
                'niveau'           => $niveauxParProfil[(int) $row->id_profil] ?? '',
                'role'             => $rolesParReservation[(int) $row->id_reservation] ?? '',
                'date_reservation' => $row->date_reservation,
                'en_attente'       => $enAttente,
                'rang_attente'     => $enAttente ? $rangAttente : null,
            ];
        }

        return $rapport;
    }

    /**
     * Niveaux de plongée (codes de brevets) par profil, du plus récemment obtenu au plus ancien.
     *
     * @param  int[] $idsProfil
     * @return array<int, string> id_profil => codes concaténés (ex: "N2, RIFAP")
     */
    private function getNiveauxParProfil(array $idsProfil): array
    {
        if (empty($idsProfil)) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id_profil', 'code']))
            ->from($db->quoteName('#__gda_niveaux'))
            ->whereIn($db->quoteName('id_profil'), $idsProfil)
            ->order($db->quoteName('obtention') . ' DESC');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $codesParProfil = [];
        foreach ($rows as $row) {
            $codesParProfil[(int) $row->id_profil][] = $row->code;
        }

        return array_map(
            fn($codes) => implode(', ', array_unique(array_filter($codes))),
            $codesParProfil
        );
    }

    /**
     * Rôles choisis par réservation (une campagne peut réserver plusieurs places, une place = un
     * rôle). Ne concerne que les campagnes avec role_actif = 1.
     *
     * @param  int[] $idsReservation
     * @return array<int, string> id_reservation => rôles concaténés
     */
    private function getRolesParReservation(array $idsReservation): array
    {
        if (empty($idsReservation)) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id_reservation', 'role']))
            ->from($db->quoteName('#__gda_reservation_places'))
            ->whereIn($db->quoteName('id_reservation'), $idsReservation)
            ->order($db->quoteName('tri') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $rolesParReservation = [];
        foreach ($rows as $row) {
            $rolesParReservation[(int) $row->id_reservation][] = $row->role;
        }

        return array_map(fn($roles) => implode(', ', $roles), $rolesParReservation);
    }

}
