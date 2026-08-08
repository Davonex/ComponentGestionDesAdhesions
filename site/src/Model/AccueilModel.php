<?php

/**  Mdel d'acueil */

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Service\SaisonService;



class AccueilModel extends ListModel
{

    protected $_items = null;


    /**
     * Récupère le formulaire de souscription.
     * 
     * @return \Joomla\CMS\Form\Form
     * @throws \RuntimeException si le formulaire ne peut pas être chargé
     */
    public function getForm()
	{
		$form = $this->loadForm(
			'com_gdadhesions.souscription',  // just a unique name to identify the form
			'souscription',				// the filename of the XML form definition
										// Joomla will look in the site/forms folder for this file
			array(
				'control' => 'jform_souscription',	// the name of the array for the POST parameters
				'load_data' => false        // if set to true, then there will be a callback to 
                                                // loadFormData to supply the data
			)
		);

		if (empty($form))
		{
             throw new \RuntimeException('Unable to load form: com_gdadhesions.souscription', 500);
		}

		return $form;
	}


    /**
     *  Recupère la liste des campagnes souscrivables 
     * 
     * @return array  Liste d'objets campagne
     * @throws \RuntimeException en cas d'erreur SQL
     */
    function getCampagnes($user = null,$reload = false)
    {
        /** les campagne souscribable sont 
         * - celles qui sont actives
         * - celles qui ont un dateDeDebut < Maintenant < dateDeFin
         * - celles ou ou je (ou mes adh rattaché) n'ai pas encore souscrit 
         */
        /** @var \Joomla\CMS\Application\SiteApplication $app */
         $app = Factory::getApplication();
        // Get ID of camapgne in the session so that
        $data = $app->getUserState('session');

        $username = (string) $user->username ?: (string) $data['username'];
        if (!$username) {
            $this->_items = null;
             throw new \Exception('User n\'existe pas  id', 404);
        } else  {
                $db = $this->getDatabase();
                $query = $db->getQuery(true);

            $active_value = 1; // Campagne active

            // $query->select(
            //     $db->quoteName(['profils.id_profil', 'profils.licence', 'profils.civilite', 'profils.nom'
            // , 'profils.prenom', 'profils.date_de_naissance', 'profils.adresse', 'profils.ville', 'profils.code_postal', 'profils.telephone'
            // , 'profils.a_prevenir', 'profils.a_prevenir_tel', 'profils.photo'])
            //     );
            $query->select('cp.*')
                ->select('COALESCE(COUNT(sc.id_profil), 0) AS nbr_souscriptions')
                ->select('case when inscit.id_profil = :id_profil_value then 1 else 0 end as deja_inscrit ')
                ->select ('tc.*')
                ->from($db->quoteName('#__gda_campagnes','cp'))
                ->join('left', $db->quoteName('#__gda_type_de_campagne', 'tc'), $db->quoteName('cp.id_type') . ' = ' . $db->quoteName('tc.id_type'))
                ->join('left', $db->quoteName('#__gda_souscriptions', 'sc'), $db->quoteName('cp.id_campagne') . ' = ' . $db->quoteName('sc.id_campagne'))
                ->join('left', $db->quoteName('#__gda_souscriptions', 'inscit'), $db->quoteName('cp.id_campagne') . ' = ' . $db->quoteName('inscit.id_campagne') . " AND " . $db->quoteName('inscit.id_profil') . " = :id_profil_value"  )
                ->where($db->quoteName('active') . ' = :active_value')
                ->group($db->quoteName('cp.id_campagne'));
              $query->bind(':active_value', $active_value);
              $query->bind(':id_profil_value', $user->id);  
            $db->setQuery($query);
            try {
                $this->_items = $db->loadObjectList();
            } catch (\RuntimeException $e) {
                throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
                // $query->__toString()
                // Factory::getApplication()->enqueueMessage("Erreur de chargement des campagne, Contacter votre administrateur", 'error');
            }
        }
        
        // $query->__toString()

        return $this->_items;

    }

    /**
     * Recupère une campagne souscribable
     * 
     * @param int $id_campagne  Identifiant de la campagne
     * @param string|null $username  Nom d'utilisateur pour vérifier la souscription
     * @return object|null  Objet campagne ou null si non trouvée
     * @throws \RuntimeException en cas d'erreur SQL ou de vérification
     */
    function getCampagne($id_campagne, $username = null)
    {

        /** @var \Joomla\CMS\Application\SiteApplication $app */
         $app = Factory::getApplication();
        // Get ID of camapgne in the session so that
        $session = $app->getUserState('session') ;
        // $user = $app->getIdentity();

        if ($session['username'] !== $username) {
           throw new \RuntimeException( "le Username ".$session['username']." et la licence  (" .$username. ")  sont différents /!\ " , 500); 
        // if (!$username) {
        //     $this->_items = null;
        //      throw new \Exception('User n\'existe pas  id', 404);
        } else  {
            $db = $this->getDatabase();
            $query = $db->getQuery(true);

            $active_value = 1; // Campagne active

            // $query->select(
            //     $db->quoteName(['profils.id_profil', 'profils.licence', 'profils.civilite', 'profils.nom'
            // , 'profils.prenom', 'profils.date_de_naissance', 'profils.adresse', 'profils.ville', 'profils.code_postal', 'profils.telephone'
            // , 'profils.a_prevenir', 'profils.a_prevenir_tel', 'profils.photo'])
            //     );
            $query->select('cp.*');
            $query->select('COALESCE(COUNT(sc.id_profil), 0) AS nbr_souscriptions');
            $query->select('case when inscit.id_profil = :id_profil_value then 1 else 0 end as deja_inscrit ');
            $query    ->select ('tc.*');
                $query->from($db->quoteName('#__gda_campagnes','cp'));
                $query->join('left', $db->quoteName('#__gda_type_de_campagne', 'tc'), $db->quoteName('cp.id_type') . ' = ' . $db->quoteName('tc.id_type'));

                $query->join('left', $db->quoteName('#__gda_souscriptions', 'sc'), $db->quoteName('cp.id_campagne') . ' = ' . $db->quoteName('sc.id_campagne'));
                $query->join('left', $db->quoteName('#__gda_souscriptions', 'inscit'), $db->quoteName('cp.id_campagne') . ' = ' . $db->quoteName('inscit.id_campagne') . " AND " . $db->quoteName('inscit.id_profil') . " = :id_profil_value"  );
                $query->where($db->quoteName('active') . ' = :active_value');
                $query->where($db->quoteName('cp.id_campagne') . ' = :id_campagne_value');
                $query->group($db->quoteName('cp.id_campagne'));
              $query->bind(':active_value', $active_value);
              $query->bind(':id_profil_value', $session['id']);  
              $query->bind(':id_campagne_value', $id_campagne);  
            $db->setQuery($query);
            try {
                $this->_items = $db->loadObjectList();
            } catch (\RuntimeException $e) {
                throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
                // $query->__toString()
                // Factory::getApplication()->enqueueMessage("Erreur de chargement des campagne, Contacter votre administrateur", 'error');
            }
        }

        if (count($this->_items) !== 1) {
            throw new \Exception("Bizard  il y a 0 ou plusieurs campagne avec l'ID:".$id_campagne, 500);
        }
        
        // $query->__toString()

        return $this->_items[0];

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