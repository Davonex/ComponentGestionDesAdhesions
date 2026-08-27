<?php

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;

use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Service\BrevetService;
use NCB\Component\Gda\Site\Service\ReservationService;
use NCB\Component\Gda\Site\Service\SouscriptionService;


class CampagnesModel extends ListModel
{

    protected $_item = null;

    private ?BrevetService $brevetService = null;

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
             throw new \Exception("Bizard  il y a 0 ou plusieurs campagne avec l'ID:".$id_campagne, 500);
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

        $select = $db->getQuery(true);

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

        // Capacité par rôle, pour préremplir la modal d'édition de chaque ligne : une seule
        // requête groupée plutôt qu'un appel par campagne (pas de N+1).
        if (!empty($this->_items)) {
            $idsCampagne = array_map(static fn($item) => (int) $item->id_campagne, $this->_items);
            $rolesParCampagne = $this->getRolesCapacite($idsCampagne);

            foreach ($this->_items as $item) {
                $item->role_places = $rolesParCampagne[(int) $item->id_campagne] ?? [];
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

        $query = $db->getQuery(true)
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

        $delete = $db->getQuery(true)
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
            $insert = $db->getQuery(true)
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
     * Retourne les adhérents ayant réservé une place sur une campagne (hors saison), sous la même
     * forme qu'un groupe issu de GroupesModel::getGroupesAvecAdherents() afin de pouvoir réutiliser
     * tel quel les layouts groupes.detail / groupes.vignette pour l'onglet "Suivi des inscriptions".
     */
    function getInscritsCampagne(int $id_campagne, string $titre): object
    {
        $db = $this->getDatabase();
        $statut_annulee = ReservationService::STATUT_ANNULEE;
        $statut_attente = ReservationService::STATUT_ATTENTE;

        // Une ligne par PLACE (#__gda_reservation_places), pas par réservation : depuis la fusion
        // Formation/Loisir, une réservation peut porter plusieurs rôles à la fois, chacun avec
        // son propre statut. Un adhérent avec 2 places confirmées + 1 en attente apparaît donc en
        // 3 lignes ici, chacune avec son rôle/statut propre — layout groupes.detail inchangé, il
        // affiche déjà un rôle/statut par ligne.
        $query = $db->getQuery(true)
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
            ])
            ->from($db->quoteName('#__gda_reservation_places', 'rp'))
            ->innerJoin($db->quoteName('#__gda_reservation', 'r') . ' ON ' . $db->quoteName('r.id_reservation') . ' = ' . $db->quoteName('rp.id_reservation'))
            ->innerJoin($db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('r.id_profil'))
            ->where($db->quoteName('rp.id_campagne') . ' = :id_campagne')
            ->where($db->quoteName('rp.statut') . ' != :statut_annulee')
            ->order($db->quoteName('p.nom') . ' ASC, ' . $db->quoteName('p.prenom') . ' ASC, ' . $db->quoteName('rp.role') . ' ASC')
            ->bind(':id_campagne', $id_campagne, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':statut_annulee', $statut_annulee);

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList() ?: [];
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        // Rang de file d'attente : indépendant de l'ordre alphabétique d'affichage ci-dessous.
        $rangAttenteParPlace = ReservationService::calculerRangsAttente($rows);

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
            $adherent->en_attente = $row->statut === $statut_attente;
            $adherent->rang_attente = $rangAttenteParPlace[(int) $row->id_place] ?? null;

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
        // Formation reste toujours 1 place = 1 rôle par adhérent ; Loisir laisse le Bureau
        // choisir. Forcé côté serveur (le formulaire le fait déjà côté client mais ce n'est pas
        // suffisant pour une règle métier bloquante, cf. contrôles serveur similaires dans
        // AdhesionController::save()).
        $idTypeFormation = (int) ConfHelper::getValue('IdTypeFormation');
        $value_reservation_multiple = ((int) $data['id_type'] === $idTypeFormation)
            ? 0
            : (empty($data['reservation_multiple']) ? 0 : 1);

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
                $db->quoteName('active') . '= :value_active',
            );
            $conditions = array( $db->quoteName('id_campagne') . ' = :value_id_campagne');
            $query->update($db->quoteName('#__gda_campagnes'))->set($fields)->where($conditions);
            $query->bind(':value_id_campagne',  $data['id_campagne']);
       } else {
            // New Item
            $value_active = (int) 0;

                // Insert
            $columns = array('titre','description', 'event_helloasso','date_debut', 'date_fin', 'date_evenement', 'active', 'id_article','id_type','id_groupes','nbr_place','reservation_multiple');
            $query->insert($db->quoteName('#__gda_campagnes'));
            $query->columns($db->quoteName($columns));
            $query->values(':value_titre, :value_description, :value_event_helloasso, :value_date_debut, :value_date_fin, :value_date_evenement, :value_active, :value_id_article, :value_id_type, :value_id_groupes, :value_nbr_place, :value_reservation_multiple');
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
     * Une ligne par PLACE non annulée (#__gda_reservation_places) : identité, niveau de plongée,
     * rôle et rang dans la liste d'attente le cas échéant. Depuis la fusion Formation/Loisir, un
     * adhérent ayant réservé plusieurs rôles à la fois apparaît en plusieurs lignes.
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

        $db = $this->getDatabase();
        $statut_annulee = ReservationService::STATUT_ANNULEE;
        $statut_attente = ReservationService::STATUT_ATTENTE;

        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'rp.id_place', 'rp.role', 'rp.statut', 'rp.date_rang',
                'p.id_profil', 'p.nom', 'p.prenom', 'u.username',
            ]))
            ->from($db->quoteName('#__gda_reservation_places', 'rp'))
            ->innerJoin($db->quoteName('#__gda_reservation', 'r') . ' ON ' . $db->quoteName('r.id_reservation') . ' = ' . $db->quoteName('rp.id_reservation'))
            ->innerJoin($db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('r.id_profil'))
            ->innerJoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.id_profil'))
            ->where($db->quoteName('rp.id_campagne') . ' = :id_campagne')
            ->where($db->quoteName('rp.statut') . ' != :statut_annulee')
            // Ordre = ordre d'arrivée dans la file d'attente, pour un affichage chronologique.
            ->order($db->quoteName('rp.date_rang') . ' ASC')
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

        $rangAttenteParPlace = ReservationService::calculerRangsAttente($rows);

        $rapport = [];

        foreach ($rows as $row) {
            $rapport[] = [
                'nom_complet'      => trim($row->prenom . ' ' . $row->nom),
                'username'         => $row->username,
                'niveau'           => $niveauxParProfil[(int) $row->id_profil] ?? '',
                'role'             => $row->role,
                'date_reservation' => $row->date_rang,
                'en_attente'       => $row->statut === $statut_attente,
                'rang_attente'     => $rangAttenteParPlace[(int) $row->id_place] ?? null,
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
