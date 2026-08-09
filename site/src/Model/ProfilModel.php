<?php

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;

use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;



class ProfilModel extends ListModel
{
    /**
     * Blocs de contenu affichés par le layout profil.card_profil.
     * Seul endroit à modifier pour ajuster la composition de la fiche allégée
     * (popup "fiche adhérent" côté Groupe) selon les retours des beta-testeurs.
     */
    const CARD_FIELDS_FULL  = ['photo', 'coordonnees', 'telephone', 'email', 'urgence'];
    const CARD_FIELDS_LIGHT = ['photo','telephone', 'email', 'urgence'];

    protected $_item = null;
    protected $_itemsOB = null;

    /**
     * Propriété privée pour stocker l'instance de l'application
     */
    private $app = null;

    /**
     * Getter pour obtenir l'instance de l'application (lazy loading)
     */
    private function getApp()
    {
        if ($this->app === null) {
            $this->app = Factory::getApplication();
        }
        return $this->app;
    }

    /**
     *  Recupe un profil avec id = pk
     */
    function getItem($pk = null, $reload = false)
    {
        $app = $this->getApp();
        // Get ID of camapgne in the session so that
        $session = $this->getApp()->getUserState('session');

        $username = (string) $pk ?: (string) $session['username'];
        if (!$username) {
            // Creation d'un nouveau user
            $this->_item = null;
            // throw new \Exception('User n\'existe pas  id', 404);
        }

        if (!$reload && $this->_item !== null && $this->_item->licence != $username) {
            return $this->_item;
        }

        if ($username) {
            // lister le Profil $Username = licence
            $db = $this->getDatabase();
            $query = $db->getQuery(true);

            //$query->select('profils.id_profil')
            $query->select($this->getSelectItemFields($db));
            $query->from($db->quoteName('#__users', 'u'));
            $query->leftjoin($db->quoteName('#__gda_profils', 'p'), 'p.id_profil = u.id');
            // $query->where($db->quoteName('u.username') . ' = ' . $db->quote($profil['username']));
            $query->where($db->quoteName('u.username') . ' = :username_key')
                ->bind(':username_key', $username);

            $db->setQuery($query);
            // $query->__toString()
            try {
                $item = $db->loadObject();
            } catch (\RuntimeException $e) {
                throw new \Exception($e->getMessage(), 500);
            }

            //  $item->date_de_naissance = ToolsHelper::from_sqldate($item->date_de_naissance);

            $this->_item = $item;
        }


        return $this->_item;
    }

    /**
     * Récupère un profil par id_profil (indépendamment de la session courante) — utilisé par la popup
     * "fiche adhérent" (ProfilController::showCard()) pour afficher le profil d'un AUTRE adhérent.
     */
    function getProfilById(int $idProfil): ?object
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($this->getSelectItemFields($db));
        $query->from($db->quoteName('#__users', 'u'));
        $query->leftjoin($db->quoteName('#__gda_profils', 'p'), 'p.id_profil = u.id');
        $query->where($db->quoteName('u.id') . ' = :id_profil')
            ->bind(':id_profil', $idProfil, \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            return $db->loadObject() ?: null;
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }
    }

    /**
     * Recupe tous les  profils avec id on behalf
     *
     */
    function getItemsOB($pk = null)
    {
        $app = $this->getApp();
        // Get ID of camapgne in the session so that
        $session = $this->getApp()->getUserState('session');

        $username = (string) $pk ?: (string) $session['username'];
        if (!$username) {
            // Creation d'un nouveau user
            //$this->_item = null;
            throw new \Exception('User n\'existe pas  id', 404);
        }
        if ($username) {
            // lister le Profil $Username = licence
            $db = $this->getDatabase();
            $query = $db->getQuery(true);

            $query->select(
                $db->quoteName([
                    'profils.id_profil',
                    'profils.civilite',
                    'profils.nom',
                    'users.username',
                    'users.email',
                    'profils.prenom',
                    'profils.date_de_naissance',
                    'profils.adresse',
                    'profils.ville',
                    'profils.code_postal',
                    'profils.telephone',
                    'profils.a_prevenir',
                    'profils.a_prevenir_tel',
                    'profils.photo',
                    'profils.caci',
                    'profils.date_caci',
                    'on_behalf'
                ])
            );
            $query->from($db->quoteName('#__gda_profils', 'profils'));
            $query->leftjoin($db->quoteName('#__users', 'users'), 'profils.id_profil = users.id');
            // $query->leftjoin($db->quoteName('#__users','users'),'profils.licence = users.username');
            $query->where($db->quoteName('on_behalf') . ' = :username_key')
                ->bind(':username_key', $username);

            $db->setQuery($query);

            try {
                $items = $db->loadObjectList();
            } catch (\RuntimeException $e) {
                throw new \Exception($e->getMessage(), 500);
            }

            // Convert les date du SQL vers le format Francais
            foreach ($items as &$item) {
                $item->date_de_naissance = ToolsHelper::from_sqldate($item->date_de_naissance);
            }
            // $item->date_de_naissance = ToolsHelper::from_sqldate($item->date_de_naissance);
            $this->_itemsOB = $items;
        }

        return $this->_itemsOB;
    }


    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm(
            'com_gda.profil',  // just a unique name to identify the form
            'profil',                // the filename of the XML form definition
            // Joomla will look in the site/forms folder for this file
            array(
                'control' => 'jform_Profil',    // the name of the array for the POST parameters
                'load_data' => $loadData        // if set to true, then there will be a callback to 
                // loadFormData to supply the data
            )
        );

        if (empty($form)) {
            $errors = $this->getErrors();
            throw new \Exception(implode("\n", $errors), 500);
        }

        return $form;
    }

    /**
     * Charge le formulaire dédié à la mise à jour du CACI (modale distincte du formulaire profil).
     */
    public function getCaciForm()
    {
        $form = $this->loadForm(
            'com_gda.profilcaci',
            'caci',
            array(
                'control' => 'jform_Caci',
                'load_data' => false
            )
        );

        if (empty($form)) {
            $errors = $this->getErrors();
            throw new \Exception(implode("\n", $errors), 500);
        }

        return $form;
    }

    /**
     * Sets the variable on the internal script.
     *
     * 
     * @return  void
     * @since   1.0
     * @throws  
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        // $data = Factory::getApplication()->getUserState(
        // 	'Profil',	// a unique name to identify the data in the session
        // 	array()// if no data in session then use the prefill data below ...
        // );

        //La 1er fois 
        // a voir quand les données sont poster suite à un submit  
        $data = $this->_item;

        return $data;
    }

    function isCheckProfil()
    {

        $app = $this->getApp();

        $data = $app->getUserState('profil.save');
        // if id_profil not empty
        if (!$data['id_profil'] || empty($data['id_profil'])) {
            throw new \Exception('id_profil vide');
        }

        // $db = $this->getDatabase();
        // $query = $db->getQuery(true);
        // $query->select('*');
        // $query->from($db->quoteName('#__gda_profils', 'profils'));
        // $query->where($db->quoteName('profils.id_profil') . ' = :id_profil')
        //     ->bind(':id_profil', $data['id_profil']);

        // $db->setQuery($query);
        // // if id_profil exist
        // if (count($db->loadObjectList()) !== 1) {
        //     throw new \Exception('Profil inexistant');
        // }
        return true;
    }

    function UploadImage()
    {
        // Model to upload File 
        $app = $this->getApp();
        $data = $app->getUserState('profil.save');
        $file = $app->getUserState('profil.file');

        $ProfilPhotoPath = ConfHelper::GetKey("ProfilPhotoPath");
        // if ($ProfilPhotoPath === false OR is_dir ($ProfilPhotoPath) === false) {
        //     throw new \Exception("ProfilPhotoPath introuvable", 500);
        // }

        $Photo =  FileHelper::UploadFile((string) $data["photo"], (string) $ProfilPhotoPath, $file);

        if (! is_null($Photo) and  ! empty($Photo)) {
            $data["photo"] = $Photo;
            $app->setUserState('profil.save', $data);
        }
    }

    /**
     * Enregistre le fichier CACI uploadé depuis la modale de mise à jour du CACI.
     */
    function UploadCaci()
    {
        $app = $this->getApp();
        $data = $app->getUserState('profil.caci.save');
        $file = $app->getUserState('profil.caci.file');

        $CaciPath = ConfHelper::getValue("CaciPath");

        $Caci = FileHelper::UploadFile((string) $data["caci"], (string) $CaciPath, $file);

        if (! is_null($Caci) and ! empty($Caci)) {
            $data["caci"] = $Caci;
            $app->setUserState('profil.caci.save', $data);
        }
    }

    /**
     * Met à jour le CACI (fichier + date de validité) d'un profil déjà existant.
     */
    function saveCaci(): bool
    {
        $app = $this->getApp();
        $data = $app->getUserState('profil.caci.save');
        $data['date_caci'] = !empty($data['date_caci']) ? ToolsHelper::to_sqldate($data['date_caci']) : null;

        $db = $this->getDatabase();

        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__gda_profils'))
            ->set($db->quoteName('caci') . ' = :value_caci')
            ->set($db->quoteName('date_caci') . ' = :value_date_caci')
            ->where($db->quoteName('id_profil') . ' = :value_id_profil')
            ->bind(':value_caci', $data['caci'])
            ->bind(':value_date_caci', $data['date_caci'])
            ->bind(':value_id_profil', $data['id_profil']);

        $db->setQuery($query);

        try {
            return (bool) $db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }
    }

    /**
     * Retourne true si le profil a été sauvegardé avec succès ou mise à jour, sinon false.
     */

    function  saveProfil(): bool
    {
        $app = $this->getApp();

        $data = $app->getUserState('profil.save');

        $db = $this->getDatabase();

        $data['date_de_naissance'] = ToolsHelper::to_sqldate($data['date_de_naissance']);
        $data['telephone'] = ToolsHelper::to_sqltel($data['telephone']);
        $data['a_prevenir_tel'] = ToolsHelper::to_sqltel($data['a_prevenir_tel']);
        $data['nom'] = strtoupper($data['nom']);
        $data['ville'] = strtoupper($data['ville']);

        $query = $db->getQuery(true);
        $exists = (bool) $this->existingProfil();

        if ($exists) {
            $query->update($db->quoteName('#__gda_profils'))
                ->set($db->quoteName('civilite') . ' = :value_civilite')
                ->set($db->quoteName('nom') . ' = :value_nom')
                ->set($db->quoteName('prenom') . ' = :value_prenom')
                ->set($db->quoteName('date_de_naissance') . ' = :value_date_de_naissance')
                ->set($db->quoteName('adresse') . ' = :value_adresse')
                ->set($db->quoteName('ville') . ' = :value_ville')
                ->set($db->quoteName('code_postal') . ' = :value_code_postal')
                ->set($db->quoteName('telephone') . ' = :value_telephone')
                ->set($db->quoteName('photo') . ' = :value_photo')
                ->set($db->quoteName('a_prevenir') . ' = :value_a_prevenir')
                ->set($db->quoteName('a_prevenir_tel') . ' = :value_a_prevenir_tel')
                ->where($db->quoteName('id_profil') . ' = :value_id_profil');
        } else {
            $columns = [
                $db->quoteName('id_profil'),
                $db->quoteName('civilite'),
                $db->quoteName('nom'),
                $db->quoteName('prenom'),
                $db->quoteName('date_de_naissance'),
                $db->quoteName('adresse'),
                $db->quoteName('ville'),
                $db->quoteName('code_postal'),
                $db->quoteName('telephone'),
                $db->quoteName('photo'),
                $db->quoteName('a_prevenir'),
                $db->quoteName('a_prevenir_tel'),
            ];

            $values = [
                ':value_id_profil',
                ':value_civilite',
                ':value_nom',
                ':value_prenom',
                ':value_date_de_naissance',
                ':value_adresse',
                ':value_ville',
                ':value_code_postal',
                ':value_telephone',
                ':value_photo',
                ':value_a_prevenir',
                ':value_a_prevenir_tel',
            ];

            $query->insert($db->quoteName('#__gda_profils'))
                ->columns($columns)
                ->values(implode(',', $values));
        }

        $query
            ->bind(':value_id_profil',  $data['id_profil'])
            ->bind(':value_photo',  $data['photo'])
            ->bind(':value_civilite',  $data['civilite'])
            ->bind(':value_nom',  $data['nom'])
            ->bind(':value_prenom',  $data['prenom'])
            ->bind(':value_date_de_naissance',  $data['date_de_naissance'])
            ->bind(':value_adresse',  $data['adresse'])
            ->bind(':value_ville',  $data['ville'])
            ->bind(':value_code_postal',  $data['code_postal'])
            ->bind(':value_telephone', $data['telephone'])
            ->bind(':value_a_prevenir',  $data['a_prevenir'])
            ->bind(':value_a_prevenir_tel', $data['a_prevenir_tel']);

        $db->setQuery($query);

        try {
            return (bool) $db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }
    }


    function saveUser()
    {
        $app = $this->getApp();
        $data = $app->getUserState('profil.save');
        $Currentuser = $app->getIdentity();
        $UserData = array('name' => strtoupper($data['nom']) . ' ' . $data['prenom'], 'email' => $data['email']);


        if ($data['licence'] === $Currentuser->username) {
            $Currentuser->bind($UserData);
            $Currentuser->save();
            if ($Currentuser->getError()) {
                throw new \Exception($Currentuser->getError(), 500);
            }
        } else {
            $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
            $User = $userFactory->loadUserByUsername($data['licence']) ?: null;
            if ($User->id) {
                $User->bind($UserData);
                $User->save();
            }
            ## User on behalf
        }

        return true;
    }


    /**
     * Retourne les champs à sélectionner pour la requete de récupération d'un profil
     * @param \Joomla\Database\DatabaseInterface $db
     * return array
     */
    private function getSelectItemFields(\Joomla\Database\DatabaseInterface $db): array
    {
        return [
            $db->quoteName('u.username'),
            $db->quoteName('u.email'),

            $db->quoteName('p.civilite'),
            $db->quoteName('p.nom'),
            $db->quoteName('p.prenom'),
            $db->quoteName('p.date_de_naissance'),

            $db->quoteName('p.adresse'),
            $db->quoteName('p.ville'),
            $db->quoteName('p.code_postal'),
            $db->quoteName('p.telephone'),
            $db->quoteName('p.statut'),
            $db->quoteName('p.a_prevenir'),
            $db->quoteName('p.a_prevenir_tel'),
            $db->quoteName('p.photo'),
            $db->quoteName('p.caci'),
            $db->quoteName('p.date_caci'),
            $db->quoteName('p.ffessm_token'),
            $db->quoteName('p.on_behalf'),
            $db->quoteName('p.modified_at'),
            $db->quoteName('p.date_licence'),
            $db->quoteName('p.nbr_plongee'),
            $db->quoteName('p.nbr_plongee_35'),
            $db->quoteName('p.nbr_plongee_auto'),
            $db->quoteName('p.key'),

            // id profil doit $etre celui de la table user
            'COALESCE(' . $db->quoteName('p.id_profil') . ', ' . $db->quoteName('u.id') . ') AS ' . $db->quoteName('id_profil'),
        ];
    }



    private function existingProfil()
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        /** @var \Joomla\CMS\Application\SiteApplication $app */
         $app = Factory::getApplication();

        $data = $app->getUserState('profil.save');

        $conditions = array($db->quoteName('id_profil') . ' = :value_id_profil');

        $query = $db->getQuery(true)
            ->select('id_profil')
            ->from($db->quoteName('#__gda_profils'))
            ->where($conditions)
            ->bind(':value_id_profil',  $data['id_profil']);


        $db->setQuery($query);
        $existingId = $db->loadResult();
        return $existingId;
    }
}
