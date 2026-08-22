<?php

/**  Model d'adhésion */

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
// use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailerFactoryInterface;
// use Joomla\Plugin\Fields\Integer\Extension\Integer;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\GdaLogger;
// use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
// use NCB\Component\Gda\Site\Service\GdaConfigService;
use NCB\Component\Gda\Site\Service\BrevetService;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Service\NotificationMailService;
use NCB\Component\Gda\Site\Service\SaisonService;
use NCB\Component\Gda\Site\Service\SouscriptionService;


class AdhesionModel extends FormModel
{
    /**
     * Propriété privée pour stocker l'instance de l'application
     */
    private $app = null;

    /**
     * Propriété privée pour cacher le profil chargé
     */
    private ?\stdClass $profil = null;
    private  $compositionGroupes = [];
    private $brevets = [];
    private ?SouscriptionService $souscriptionService = null;
    private ?BrevetService $brevetService = null;

    /**
     * Getter pour obtenir le service Saison (singleton partagé)
     */
    private function getSaisonService(): SaisonService
    {
        return ConfHelper::getSaisonService();
    }

    /**
     * Getter pour obtenir le service de notification mail.
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
     * Getter pour obtenir le service Brevet (lazy loading).
     *
     * Instanciation directe et non résolution via le conteneur : services/provider.php enregistre
     * dans le conteneur du *composant*, alors que Factory::getContainer() renvoie le conteneur
     * *global* de Joomla — la résolution y échouerait ("has not been registered with the
     * container"). Même approche que getSouscriptionService() et ConfHelper.
     */
    private function getBrevetService(): BrevetService
    {
        if ($this->brevetService === null) {
            $this->brevetService = new BrevetService($this->getDatabase());
        }

        return $this->brevetService;
    }

    /**
     * Getter pour obtenir le service Souscription (lazy loading)
     */
    private function getSouscriptionService(): SouscriptionService
    {
        if ($this->souscriptionService === null) {
            $this->souscriptionService = new SouscriptionService($this->getDatabase());
        }

        return $this->souscriptionService;
    }

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
     * Getter pour obtenir la key (lazy loading)
     */
    private function getKey()
    {
        if ($this->app === null) {
            $this->app = Factory::getApplication();
        }

        // Garde-fou: sans key explicite dans l'URL, on interdit toute réédition invitée.
        $requestKey = trim((string) $this->app->getInput()->getString('key', ''));

        if ($requestKey === '') {
            return null;
        }

        $sessionKey = (string) $this->app->getUserState('adhesion.key');

        if ($sessionKey === '' || $sessionKey !== $requestKey) {
            return null;
        }

        return $sessionKey;
    }

    /**
     * Vérifie qu'une clé d'adhésion existe bien dans la table profils.
     */
    public function isAdhesionKeyValid(string $key): bool
    {
        $key = trim($key);

        if ($key === '' or $key === null) {
            return false;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__gda_profils'))
            ->where($db->quoteName('key') . ' = :adhesion_key')
            ->bind(':adhesion_key', $key);

        $db->setQuery($query, 0, 1);

        return (bool) $db->loadResult();
    }



    /**
     * Retourne le formulaire XML
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_gdadhesions.adhesion',  // juste un nom unique pour identifier le formulaire
            'adhesion',                // le nom de fichier de la définition du formulaire XML
            // Joomla cherchera ce fichier dans le dossier com_gdadhesions/models/forms
            array(
                'control' => 'jform',    // the name of the array for the POST parameters
                'load_data' => $loadData // if set to true, then there will be a callback to 
                // loadFormData to supply the data
            )
        );
        return $form;
    }


    /**
     * Charge les données du formulaire
     */
    protected function loadFormData()
    {
        $session = $this->getApp()->getUserState('session');
        /** @var \stdClass $objData */
        $profil = $this->getProfil();
        $saison = $this->getSaisonService()->getSaisonOuverte();
        $objData = ($profil  === null) ? new \stdClass() : $profil;
        $profilId = isset($profil->id) ? (int) $profil->id : 0;


        // Charger les groupes sélectionnés
        $composition = $this->getCompositionGroupes();
        if ($composition && is_array($composition)) {
            $groupIds = array_map(function ($group) {
                return $group->id_groupe;
            }, $composition);
            $objData->id_groupes = $groupIds;
        } else {
            $objData->id_groupes = [];
        }

        if ($profilId > 0 && $saison !== null) {
            $dataSouscription =  $this->getSouscriptionService()->getSouscription((int) $saison->id_campagne, $profilId);

            if ($dataSouscription === null) {
                // il n'y a pas de souscription pour ce profil et cette campagne
                $objData->helloasso = "0";
            } elseif ($dataSouscription->id_order === "0") {
                // il y a une souscription, mais elle n'est pas encore liée à une commande HelloAsso (id_order = 0)
                $objData->helloasso = $this->getSouscriptionHelloAsso();
            } else {
                // il y a une souscriotion et elle est liée a une commande HelloAsso (id_order != 0 and id_order != null)
                $objData->helloasso = $dataSouscription->id_order;
            }
        } else {
            $objData->helloasso = "0";
        }


        
        
        //Get the cotisation code and montant
        $Cotisation = $this->getCotisation($profil);
        $objData->cotisation_code = $Cotisation['code'];
        $objData->cotisation_montant = $Cotisation['montant'];
        // }
        return $objData;
    }

    /**
     * 
     */
    public function getSouscriptionHelloAsso(): string
    {
        $profil = $this->getProfil();
        if ($profil === null) {
            return '0';
        }

        $saison = $this->getSaisonService()->getSaisonOuverte();
        if ($saison === null || empty($saison->formType) || empty($saison->formSlug)) {
            return '0';
        }

        // Force refresh : l'utilisateur vient de réaliser son paiement, le cache serait périmé
        try {
            $helloAsso = new \NCB\Component\Gda\Site\Service\HelloAssoService();
            $idOrder = $helloAsso->findOrderByUsername($saison->formType, $saison->formSlug, $profil->username, true);
        } catch (\Throwable $e) {
            // Ne doit jamais bloquer la vue Adhesion (ex: HelloAsso indisponible, VPN refusé, ...) :
            // on se contente de journaliser et de traiter la campagne comme non payée pour l'instant.
            GdaLogger::warning(sprintf(
                'AdhesionModel::getSouscriptionHelloAsso() - Echec recherche HelloAsso pour username "%s" (campagne %d) : %s',
                $profil->username,
                (int) $saison->id_campagne,
                $e->getMessage()
            ));
            return '0';
        }

        if ($idOrder !== null) {
            $this->getSouscriptionService()->updateIdOrder(
                (int) $profil->id,
                (int) $saison->id_campagne,
                $idOrder
            );
            return $idOrder;
        }

        return '0';
    }


    /**
     * Get profile of one username
     *
     * @return  \stdClass|null
     *
     * @since   4.0.0
     */
    public function getProfil()

    {
        // Retourner le profil en cache s'il existe déjà
        if ($this->profil !== null) {
            return $this->profil;
        }

        $session = $this->getApp()->getUserState('session');

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        if (empty($session) || !$session['username']) {

            if (is_null($this->getKey())) {
                $objData = new \stdClass();
                $objData->key = ToolsHelper::generatorUID();
                $objData->username = '';
                $objData->id = '0';
                $this->profil = $objData;
                return $this->profil;
            } else {
                $value_key = $this->getKey();

                $query->select('*');
                $query->from($db->quoteName('#__users', 'u'));
                $query->leftjoin($db->quoteName('#__gda_profils', 'p'), 'p.id_profil = u.id');
                $query->where($db->quoteName('p.key') . ' = :value_key')
                    ->bind(':value_key', $value_key);
            }
        } else {

            $query->select('*');
            $query->from($db->quoteName('#__users', 'u'));
            $query->leftjoin($db->quoteName('#__gda_profils', 'p'), 'p.id_profil = u.id');
            // $query->where($db->quoteName('u.username') . ' = ' . $db->quote($profil['username']));
            $query->where($db->quoteName('u.username') . ' LIKE :value_id_profil')
                ->bind(':value_id_profil', $session['username']);
        }

        $db->setQuery($query);
        // $query->__toString()
        try {
            $item = $db->loadObject();
            // Factory::getApplication()->setUserState('profil', $item);
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        if ($item === null) {
            $session = $this->getApp()->getUserState('session');

            if (!empty($this->getKey())) {
                $this->getApp()->setUserState('adhesion.key', null);
            }

            $item = new \stdClass();
            $item->key = ToolsHelper::generatorUID();
            $item->username = $session['username'] ?? '';
            $item->id = $session['id'] ?? '0';
            $item->email = $session['email'] ?? '';
        }

        //  $item->date_de_naissance = ToolsHelper::from_sqldate($item->date_de_naissance);

        // Mettre en cache le profil
        $this->profil = $item;

        return $item;
    }



    /**
     * Get profile of one username
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public function getBrevets()
    {

        // Retourner le profil en cache s'il existe déjà
        if ($this->brevets !== []) {
            return $this->brevets;
        }

        $session = $this->getApp()->getUserState('session');


        // $db = $this->getDbo(); // deprecated
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        if (empty($session) || !$session['username']) {
            if (is_null($this->getKey())) {
                return null;
            } else {
                // recuperer les brevet avec la key
                $value_key = $this->getKey();

                $query->select('b.nom,b.obtention,b.lieu');
                $query->from($db->quoteName('#__gda_brevets', 'b'));
                $query->leftjoin($db->quoteName('#__gda_profils', 'p'), 'p.id_profil = b.id_profil');
                $query->where($db->quoteName('p.key') . ' = :value_key')
                    ->order($db->quoteName('b.obtention') . ' DESC')
                    ->bind(':value_key', $value_key);
            }
        } else {

            $query->select('b.nom,b.obtention,b.lieu');
            $query->from($db->quoteName('#__gda_brevets', 'b'));
            $query->where($db->quoteName('id_profil') . ' = :value_id_profil')
                ->order($db->quoteName('b.obtention') . ' DESC')
                ->bind(':value_id_profil', $session['id']);
        }

        $db->setQuery($query);
        // $query->__toString()
        try {
            $brevets = $db->loadObjectList(); //$db->loadObjectList();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        //  $brevets->date_de_naissance = ToolsHelper::from_sqldate($brevets->date_de_naissance);

        // Mettre en cache les brevets
        $this->brevets = $brevets;

        return $brevets;
    }

    /**
     * Get profile of one username
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public function getCompositionGroupes()
    {

        // Retourner le profil en cache s'il existe déjà
        if ($this->compositionGroupes !== []) {
            return $this->compositionGroupes;
        }

        $session = $this->getApp()->getUserState('session');
        $app = $this->getApp();
        $saison = $this->getSaisonService()->getSaisonOuverte();

        $db = $this->getDatabase();
        $query = $db->getQuery(true);


        if (empty($session) || !$session['username']) {
            if (is_null($this->getKey())) {
                return null;
            } else {
                // recuperer les brevet avec la key
                $value_key = $this->getKey();

                $query->select('g.*');
                $query->from($db->quoteName('#__gda_composition_groupes', 'g'));
                $query->leftjoin($db->quoteName('#__gda_profils', 'p'), 'p.id_profil = g.id_profil');
                $query->where($db->quoteName('id_campagne') . ' = :id_campagne')
                    ->where($db->quoteName('p.key') . ' = :value_key')
                    ->bind(':value_key', $value_key)
                    ->bind(':id_campagne', $saison->id_campagne);
            }
        } else {

            $query->select('*');
            $query->from($db->quoteName('#__gda_composition_groupes', 'g'));

            $query->where($db->quoteName('id_profil') . ' = :value_id_profil' .  ' AND ' . $db->quoteName('id_campagne') . ' = :id_campagne')
                ->bind(':value_id_profil', $session['id'])
                ->bind(':id_campagne', $saison->id_campagne);
        }

        $db->setQuery($query);
        // $query->__toString()
        try {
            $groupes = $db->loadObjectList(); //$db->loadObjectList();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        //  $brevets->date_de_naissance = ToolsHelper::from_sqldate($brevets->date_de_naissance);

        // Mettre en cache la composition des groupes
        $this->compositionGroupes = $groupes;

        return $groupes;
    }



    /**
     * Vérifie si l'adhésion est valide
     */
    function isProfilExiste()
    {

        $app = $this->getApp();

        $data = $app->getUserState('adhesion.save');
        // if id_profil not empty
        // if (!$data['id'] || empty($data['id'])) {
        //     throw new \Exception('l\'ID EST VIDE ET C\'EST UN GROS PROBLEME.');
        // }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from($db->quoteName('#__gda_profils', 'profils'));
        $query->where($db->quoteName('profils.id_profil') . ' = :id')
            ->bind(':id', $data['id']);

        $db->setQuery($query);
        // if id_profil exist
        $existingId = $db->loadResult();
        if (count($db->loadObjectList()) !== 1) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Vérifie si l'adhésion est valide
     */
    function isCheckCreation()
    {
        // Suite a une erreur le User et/ou le profil prevent être creer alor que l'ID=0

        if (!is_null($this->getKey())) { // Pas de token 
            $db = $this->getDatabase();
            $query = $db->getQuery(true);
            $value_key = $this->getKey();

            $query->select('*');
            $query->from($db->quoteName('#__users', 'u'));
            $query->leftjoin($db->quoteName('#__gda_profils', 'p'), 'p.id_profil = u.id');
            $query->where($db->quoteName('p.key') . ' = :value_key')
                ->bind(':value_key', $value_key);

            $db->setQuery($query);

            if (count($db->loadObjectList()) !== 1) {
                return false;
            }
        }
        return true;
    }


    /**
     * Assainir et mettre à jour les données d'adhésion
     *
     * @param array $data Les données d’adhésion à vérifier.
     * @return array Les données d’adhésion assainies et correctement formatées.
     *
     * @since 1.0
     */
    function fixdata($data): array
    {

        $data['date_de_naissance'] = !empty($data['date_de_naissance']) ? ToolsHelper::to_sqldate($data['date_de_naissance']) : null;
        $data['date_caci'] = !empty($data['date_caci']) ? ToolsHelper::to_sqldate($data['date_caci']) : null;
        $data['date_licence'] = !empty($data['date_licence']) ? ToolsHelper::to_sqldate($data['date_licence']) : null;
        $data['telephone'] = ToolsHelper::to_sqltel($data['telephone']);
        $data['a_prevenir_tel'] = ToolsHelper::to_sqltel($data['a_prevenir_tel']);
        $data['nom'] = ToolsHelper::removeAccentsAndUppercase($data['nom']);
        $data['prenom'] = ToolsHelper::removeAccentsAndUppercasefirst($data['prenom']);
        $data['ville'] = ToolsHelper::removeAccentsAndUppercasefirst($data['ville']);
        $data['adresse'] = ToolsHelper::removeAccents($data['adresse']);

        $data['nbr_plongee'] = isset($data['nbr_plongee']) ? (int)$data['nbr_plongee'] : 0;
        $data['nbr_plongee_35'] = isset($data['nbr_plongee_35']) ? (int)$data['nbr_plongee_35'] : 0;
        $data['nbr_plongee_auto'] = isset($data['nbr_plongee_auto']) ? (int)$data['nbr_plongee_auto'] : 0;
        $data['ffessm_token'] = !empty($data['ffessm_token']) ? $data['ffessm_token'] : '';
        $data['droit_img'] = isset($data['droit_img']) ? (int)$data['droit_img'] : 0;

        $data['last_update'] = ToolsHelper::now();

        


        return $data;
    }

    /**
     * Mettre à jour le profil d’un utilisateur existant
     * 
     * @return  mixed
     */
    function  UpdateProfil(): bool
    {
        $app = $this->getApp();

        $data = $app->getUserState('adhesion.save');

        // if id_profil not empty


        $db = $this->getDatabase();
        $query = $db->getQuery(true);


        $fields = array(
            //$db->quoteName('licence') . '= :value_licence',
            // $db->quoteName('id') . '= :value_id',
            $db->quoteName('civilite') . '= :value_civilite',
            $db->quoteName('nom') . '= :value_nom',
            $db->quoteName('prenom') . '= :value_prenom',
            $db->quoteName('date_de_naissance') . '= :value_date_de_naissance',
            $db->quoteName('adresse') . '= :value_adresse',
            $db->quoteName('ville') . '= :value_ville',
            $db->quoteName('code_postal') . '= :value_code_postal',
            $db->quoteName('telephone') . '= :value_telephone',
            $db->quoteName('a_prevenir') . '= :value_a_prevenir',
            $db->quoteName('a_prevenir_tel') . '= :value_a_prevenir_tel',
            $db->quoteName('photo') . '= :value_photo',
            $db->quoteName('caci') . '= :value_caci',
            $db->quoteName('ffessm_token') . '= :value_token',
            $db->quoteName('droit_img') . '= :value_droit_img',
            $db->quoteName('reduction') . '= :value_reduction',
            $db->quoteName('date_caci') . '= :value_date_caci',
            $db->quoteName('date_licence') . '= :value_date_licence',
            $db->quoteName('modified_at') . '= NOW()',
            $db->quoteName('nbr_plongee') . '= :value_nbr_plongee',
            $db->quoteName('nbr_plongee_35') . '= :value_nbr_plongee_35',
            $db->quoteName('nbr_plongee_auto') . '= :value_nbr_plongee_auto'
        );
        $conditions = array($db->quoteName('id_profil') . ' = :value_id');
        $query->update($db->quoteName('#__gda_profils'))
            ->set($fields)
            ->where($conditions)
            ->bind(':value_id',  $data['id'])
            ->bind(':value_civilite',  $data['civilite'])
            ->bind(':value_nom',  $data['nom'])
            ->bind(':value_prenom',  $data['prenom'])
            ->bind(':value_date_de_naissance',  $data['date_de_naissance'])
            ->bind(':value_adresse',  $data['adresse'])
            ->bind(':value_ville',  $data['ville'])
            ->bind(':value_code_postal',  $data['code_postal'])
            ->bind(':value_telephone', $data['telephone'])
            ->bind(':value_a_prevenir',  $data['a_prevenir'])
            ->bind(':value_a_prevenir_tel', $data['a_prevenir_tel'])
            ->bind(':value_photo',  $data['photo'])
            ->bind(':value_caci',  $data['caci'])
            ->bind(':value_token',  $data['ffessm_token'])
            ->bind(':value_droit_img',  $data['droit_img'])
            ->bind(':value_reduction',  $data['reduction'])
            ->bind(':value_date_caci',  $data['date_caci'])
            ->bind(':value_date_licence',  $data['date_licence'])
            ->bind(':value_nbr_plongee',  $data['nbr_plongee'])
            ->bind(':value_nbr_plongee_35',  $data['nbr_plongee_35'])
            ->bind(':value_nbr_plongee_auto',  $data['nbr_plongee_auto'])

        ;


        //str_to_date($data['date_de_naissance'],'%Y/%m/%d')
        // $query->__toString()

        $db->setQuery($query);

        try {
            $result = $db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        return $result;
    }




    /**
     * Mettre à jour l'utilisateur Joomla
     * 
     * @return  true
     */
    function UpdateUser()
    {
        $app = $this->getApp();
        $data = $app->getUserState('adhesion.save');
        $Currentuser = $app->getIdentity();
        $UserData = array('name' => trim($data['prenom'] . ' ' . $data['nom']), 'email' => $data['email']);

        // si le username =! vide et que le username connecter === au username fourni par le formulaire  
        if (!empty($data['username']) && $data['username'] === $Currentuser->username) {
            $Currentuser->bind($UserData);
            $Currentuser->save();
            if ($Currentuser->getError()) {
                throw new \Exception($Currentuser->getError(), 500);
            }
        } else {
            $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
            $User = $userFactory->loadUserByUsername($data['username']) ?: null;
            if ($User->id === (int)$data['id']) {
                $User->bind($UserData);
                $User->save();
            }
            ## User on behalf
        }

        return true;
    }





    /**
     * create profile of one username
     *
     * @return  array
     *
     * @since   1.0.0
     */
    function createUser()
    {

        $app = $this->getApp();
        $data = $app->getUserState('adhesion.save');

        // tester si le nouveau a une lic FFESSM et/ou un déjà present dans la base
        if ($data['username'] && UsersHelper::userExists($data['username'])) {
            // throw new \Exception('Le nom d\'utilisateur ' . $data['username'] . ' existe déjà. Veuillez en choisir un autre.', 500);
            return false;
        } else if ($data['email'] && UsersHelper::mailExists($data['email'])) {
           // le user est deja creer ! probablement l'id n'pas été mis a jour !
            return false;
        }
        // Créer l'utilisateur Joomla
        // Avec un mot de passe par défaut, l'utilisateur pourra le changer plus tard via la fonction "Mot de passe oublié" de Joomla.
        // et le compte n'est pas actif 
        // Appartient au group registered (2) par défaut, mais peut être modifié plus tard par un administrateur.
        $data_user = [
            'name' => $data['prenom'] . ' ' . $data['nom'],
            'username' => $data['username'],
            'password' => '$2y$12$Woekzi0KEq4NA/OZxLIpwOuI4V26koSJuBzFyzjpgwxQLhCv28l/u', // 🔥 hash Joomla
            'email' => $data['email'],
            'block' => 1, // Bloquer le compte jusqu'à ce que l'utilisateur le débloque via un lien de confirmation par e-mail.
            'groups' => [2] // Appartient au group registered (2) par défaut
        ];
        $user = UsersHelper::createUserName($data_user);
        $data['username'] = $user->username;
        $data['id'] = $user->id;
        // $data['new_adhesion'] = 0;



        $app->setUserState('adhesion.save', $data);
        return true;
    }

    /**
     * Créer le profil d’un nouvel utilisateur
     * 
     * @return  bool  True si le profil a été créé avec succès, false sinon
     */
    function createProfil(): bool
    {
        

        $app = $this->getApp();
        $data = $app->getUserState('adhesion.save');

        if((int) $data['id'] === 0) {
            // le profil a déjà été créé.
            throw new \Exception('Erreur createProfil(): ID de profil invalide.', 500);
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $columns = array(
            $db->quoteName('id_profil'),
            $db->quoteName('civilite'),
            $db->quoteName('nom'),
            $db->quoteName('prenom'),
            $db->quoteName('date_de_naissance'),
            $db->quoteName('adresse'),
            $db->quoteName('ville'),
            $db->quoteName('code_postal'),
            $db->quoteName('telephone'),
            $db->quoteName('a_prevenir'),
            $db->quoteName('a_prevenir_tel'),
            $db->quoteName('photo'),
            $db->quoteName('caci'),
            $db->quoteName('ffessm_token'),
            $db->quoteName('droit_img'),
            $db->quoteName('reduction'),
            $db->quoteName('date_caci'),
            $db->quoteName('date_licence'),
            $db->quoteName('modified_at'),
            $db->quoteName('nbr_plongee'),
            $db->quoteName('nbr_plongee_35'),
            $db->quoteName('nbr_plongee_auto'),
            $db->quoteName('key')
        );

        $values = array(
            $db->quote($data['id']),
            $db->quote($data['civilite']),
            $db->quote($data['nom']),
            $db->quote($data['prenom']),
            $db->quote($data['date_de_naissance']),
            $db->quote($data['adresse']),
            $db->quote($data['ville']),
            $db->quote($data['code_postal']),
            $db->quote($data['telephone']),
            $db->quote($data['a_prevenir']),
            $db->quote($data['a_prevenir_tel']),
            $db->quote(!empty($data['photo']) ? $data['photo'] : ''),
            $db->quote(!empty($data['caci']) ? $data['caci'] : ''),
            $db->quote($data['ffessm_token']),
            $db->quote($data['droit_img']),
            $db->quote($data['reduction']),
            ($data['date_caci'] !== null ? $db->quote($data['date_caci']) : 'NULL'),
            ($data['date_licence'] !== null ? $db->quote($data['date_licence']) : 'NULL'),
            'NOW()',
            $db->quote($data['nbr_plongee']),
            $db->quote($data['nbr_plongee_35']),
            $db->quote($data['nbr_plongee_auto']),
            $db->quote($data['key'])
        );

        $query->insert($db->quoteName('#__gda_profils'))
            ->columns($columns)
            ->values(implode(',', $values));

        $db->setQuery($query);

        try {
            $result = $db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception('Erreur createProfil(): ' . $e->getMessage(), 500);
        }

        return $result;
    }



    /**
     * Enregistrer les brevets associés au profil
     * @return  void
     */
    function saveInBrevets(): bool
    {
        $app = $this->getApp();
        $brevets = $app->getUserState('adhesion.brevets');
        $adhesion = $app->getUserState('adhesion.save');

        // Vérifier que brevets est un array
        if (empty($brevets) || !is_array($brevets)) {
            return true; // Pas de brevets à enregistrer
        }

        // La règle "annule et remplace" vit dans BrevetService, partagée avec l'édition des
        // brevets depuis la fiche Profil (ProfilController::saveBrevets).
        $this->getBrevetService()->replaceBrevets((int) $adhesion['id'], $brevets);

        return true;
    }


    /**
     * Enregistrer les groupes associés au profil
     * @return  bool
     */
    function saveInGroupes(): bool
    {
        $app = $this->getApp();
        $saison = $this->getSaisonService()->getSaisonOuverte();
        $adhesion = $app->getUserState('adhesion.save');

        // Vérifier que groupes est un array
        if (empty($adhesion['id_groupes']) || !is_array($adhesion['id_groupes'])) {
            return true; // Pas de brevets à enregistrer
        }

        $db = $this->getDatabase();


        try {
            // Supprimer les anciens groupes pour l'utilisateur $adhesion['id']
            $query = $db->getQuery(true);
            $query->delete($db->quoteName('#__gda_composition_groupes'))
                ->where($db->quoteName('id_profil') . ' = :id_profil' .  ' AND ' . $db->quoteName('id_campagne') . ' = :id_campagne')
                ->bind(':id_profil', $adhesion['id'])
                ->bind(':id_campagne', $saison->id_campagne);
            $db->setQuery($query);
            $db->execute();
            foreach ($adhesion['id_groupes'] as $id_groupe) {
                $query = $db->getQuery(true);
                $columns = array(
                    $db->quoteName('id_profil'),
                    $db->quoteName('id_groupe'),
                    $db->quoteName('id_campagne')
                );

                $values = array(
                    $db->quote($adhesion['id']),
                    $db->quote($id_groupe),
                    $db->quote($saison->id_campagne)
                );

                $query->insert($db->quoteName('#__gda_composition_groupes'))
                    ->columns($columns)
                    ->values(implode(',', $values));

                $db->setQuery($query);
                $db->execute();
            }

            return true;
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }
    }

    /**
     * Sauvarder la souscription de l'utilisateur pour la saison en cours
     * @return  void
     */
    function saveSouscription(): void
    {

        // 
        $app = $this->getApp();
        $saison = $this->getSaisonService()->getSaisonOuverte();
        $adhesion = $app->getUserState('adhesion.save');

        $categorie = CotisationService::GetCategorie(
            (string) ($adhesion['cotisation_code'] ?? ''),
            (string) ($adhesion['date_de_naissance'] ?? '')
        );

        $dataSouscription = [
            'id_campagne' => (int) $saison->id_campagne,
            'id_profil' => (int) $adhesion['id'],
            'date_souscription' => $adhesion['last_update'],
            'cotisation_code' => $adhesion['cotisation_code'],
            'id_order' => $adhesion['helloasso'] ?? 0,
            'categorie' => $categorie
        ];
        $this->getSouscriptionService()->souscrire($dataSouscription);
    }



    /**
     * Calculer la cotisation en fonction du profil
     * @param   \stdClass $profil Le profil de l'utilisateur pour lequel calculer la cotisation.
     * @return  array
     */

    function GetCotisation($profil): array
    {
        // $app = $this->getApp();

        // $saison = $this->getSaisonService()->getSaisonOuverte();
        //$adhesion = $app->getUserState('adhesion.save');
        $result = [];

        $data = [
            'dateDeNaissance' => $profil->date_de_naissance  ?? '',
            'codePostal' => $profil->code_postal  ?? '',
            'reduction' => $profil->reduction  ?? 0
        ];

        $service = new CotisationService($this->getDatabase(), $data);

        $result['code'] =  $service->getCode();
        $result['montant']=  CotisationService::getMontant($result['code'], $this->getDatabase());

        return $result;
    }

    /**
     * Envoyer un mail de bienvenue avec un lien de modification du profil
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    public function sendWelcomeMail()
    {
        $app = $this->getApp();
        $data = $app->getUserState('adhesion.save');

        if (empty($data['email'])) {
            throw new \Exception('L\'adresse email est vide', 500);
        }

        if (empty($data['id'])) {
            throw new \Exception('Identifiant profil manquant pour l\'envoi du mail de création.', 500);
        }

        try {
            return $this->getNotificationMailService()->sendProfileWelcomeEmail(
                (int) $data['id'],
                (string) ($data['helloasso'] ?? '0'),
                (string) ($data['cotisation_code'] ?? '')
            );
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de l\'envoi du mail: ' . $e->getMessage(), 500);
        }
    }





    /**
     * Envoyer un mail de bienvenue avec un lien de modification du profil
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    public function sendUpdateMail()
    {
        $app = $this->getApp();
        $data = $app->getUserState('adhesion.save');

        if (empty($data['email'])) {
            throw new \Exception('L\'adresse email est vide', 500);
        }

        if (empty($data['id'])) {
            throw new \Exception('Identifiant profil manquant pour l\'envoi du mail de mise à jour.', 500);
        }

        try {
            return $this->getNotificationMailService()->sendProfileUpdateEmail(
                (int) $data['id'],
                (string) ($data['helloasso'] ?? '0'),
                (string) ($data['cotisation_code'] ?? '')
            );
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * Méthode de test pour l'envoi d'un mail
     *
     * @return  void
     *
     * @since   1.0.0
     */
    function testmail()
    {
        $app = $this->getApp();

        $mail = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
        // $mail->addRecipient($data['email']);
        // $mail->addRecipient('didier.math@hotmail.com'); // A mettre  le vrai mail lor du passage en production
        $mail->addRecipient('davinox@free.fr'); // A mettre  le vrai mail lor du passage en production

        $mail->setSubject('Bienvenue - Modification de votre profil');

        $body = '<html><body>';
        $body .= '<h2>Bienvenue !</h2>';
        $body .= '<p>Bonjour Didier MATHIEU </p>';
        $body .= '<hr>';
        $body .= '<p>Cordialement,<br>L\'équipe du Neptune Club de Brunoy</p>';
        $body .= '</body></html>';

        $mail->setFrom('webmaster@neptune-club-brunoy.fr');

        $mail->isHTML(true);
        $mail->setBody($body);

        $mail->send();

        ToolsHelper::debug('testmail envoyé', $mail);
    }
}
