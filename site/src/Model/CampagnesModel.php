<?php

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;

use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Service\SouscriptionService;


class CampagnesModel extends ListModel
{

    protected $_item = null;


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
        return $this->_items;
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
        $value_event_helloasso = (isset($data['event_helloasso'])) ? ($data['event_helloasso']) : null;

       if ($data['id_campagne']) {
            // Update Item 
            $value_active = intval( $data['active']);
            $fields = array(
                $db->quoteName('titre') . '= :value_titre',
                $db->quoteName('description') . '= :value_description',
                $db->quoteName('event_helloasso') . '= :value_event_helloasso',
                $db->quoteName('date_debut') . '= :value_date_debut',
                $db->quoteName('date_fin') . '= :value_date_fin',
                $db->quoteName('id_article') . '= :value_id_article',
                $db->quoteName('id_type') . '= :value_id_type',
                $db->quoteName('id_groupes') . '= :value_id_groupes',
                $db->quoteName('nbr_place') . '= :value_nbr_place',
                $db->quoteName('active') . '= :value_active',
            );
            $conditions = array( $db->quoteName('id_campagne') . ' = :value_id_campagne');
            $query->update($db->quoteName('#__gda_campagnes'))->set($fields)->where($conditions);
            $query->bind(':value_id_campagne',  $data['id_campagne']);
       } else {
            // New Item
            $value_active = (int) 0;

                // Insert
            $columns = array('titre','description', 'event_helloasso','date_debut', 'date_fin', 'active', 'id_article','id_type','id_groupes','nbr_place');
            $query->insert($db->quoteName('#__gda_campagnes'));
            $query->columns($db->quoteName($columns));
            $query->values(':value_titre, :value_description, :value_event_helloasso, :value_date_debut, :value_date_fin, :value_active, :value_id_article, :value_id_type, :value_id_groupes, :value_nbr_place');
       }

        // Bind values
        $query->bind(':value_titre', $data['titre']);
        $query->bind(':value_description',  $data['description']);
        $query->bind(':value_event_helloasso',  $value_event_helloasso);
        $query->bind(':value_date_debut', $value_date_debut); 
        $query->bind(':value_date_fin',  $value_date_fin);
        $query->bind(':value_active',  $value_active);
        $query->bind(':value_id_article',  $data['id_article']);
        $query->bind(':value_id_type',  $data['id_type']);
        $query->bind(':value_id_groupes', $value_id_groupes);
        
        $query->bind(':value_nbr_place',  $data['nbr_place']);

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
     * Retourne la liste des souscriptions d'une campagne pour construire le rapport de la campagne
     */

    function getRapport()
    {
         /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $data = $app->getUserState('campagne.rapport');


        $db = $this->getDatabase();
        $query =$db->getQuery(true);

        $query->select([
        $db->quoteName('u.id'),
        "TRIM(CONCAT(COALESCE(" . $db->quoteName('p.prenom') . ", ''), ' ', COALESCE(" . $db->quoteName('p.nom') . ", ''))) AS " . $db->quoteName('User'),
        "'' AS " . $db->quoteName('UserPaiment'),
        $db->quoteName('u.email') . ' AS ' . $db->quoteName('EmailPaiment'),
            ])
            ->from($db->quoteName('#__gda_souscriptions', 's'))
            ->join('INNER', $db->quoteName('#__gda_profils', 'p'),
                $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('s.id_profil'))
            ->join('INNER', $db->quoteName('#__users', 'u'),
                $db->quoteName('u.id') . ' = ' . $db->quoteName('p.id_profil'))
            ->where($db->quoteName('s.id_campagne') . ' = :id_campagne')
            ->bind(':id_campagne', $data['id_campagne'], \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($query);

    try {
        return $db->loadAssocList();
    } catch (\RuntimeException $e) {
        throw new \Exception($e->getMessage(), 500);
    }


    } 

}