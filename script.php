<?php
// filepath: c:\WebSite\Joomla_5\administrator\components\com_gdadhesions\script.php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Table\User as UserTable;
use Joomla\CMS\Table\Usergroup as UsergroupTable;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

class com_gdadhesionsInstallerScript
{

    private $componentId = NULL;

    private $groupIdBureau = NULL;
    private $groupIdResponsableGroupe = NULL;
    private $groupIdMoniteur = NULL;

    private $accessLevelIdBureau = NULL;
    private $accessLevelIdResponsableGroupe = NULL;
    private $accessLevelIdMoniteur = NULL;
    private $accessLevelIdGroupes = NULL;


    private $data_adhesion = [];
    private $data_adherent = [];
    private $data_campagnes = [];
    private $data_profil = [];
    private $data_secretariat = [];
    private $data_groupes = [];
    private $data_utilisateurs = [];
    private $data_saisons = [];
    private $data_brevets = [];

    private $templateStyleId = NULL;



    public function install(InstallerAdapter $parent): bool
    {

        // === Récupération de l'ID du composant ===
        $this->componentId = $this->getComponentId();

        //== recuperation de l'id du template style par défaut du site (pour l'affecter au menu item) ===
        $this->templateStyleId = $this->getDefaultSiteTemplateStyleId();

        // === Création de la catégorie pour les articles de l'extension ===
        $categoryId = $this->createCategory();

        // === Création de l'article pour les adhésions fermées ===
        $articleId = $this->createArticle($categoryId);

        // === Insertion de la configuration dans la table dédiée ===
        $this->insertConf($articleId);

        // === Création des groupes specifique au NCB ===
        $this->groupIdBureau = $this->createUserGroup('Membre du Bureau', 2);
        $this->groupIdResponsableGroupe = $this->createUserGroup('Responsable de Groupe', 2);
        $this->groupIdMoniteur = $this->createUserGroup('Moniteur', 2);

        $this->accessLevelIdBureau = $this->createAccessLevel('NA Bureau', [$this->groupIdBureau]);
        $this->accessLevelIdResponsableGroupe = $this->createAccessLevel('NA Responsable de Groupe', [$this->groupIdResponsableGroupe]);
        $this->accessLevelIdMoniteur = $this->createAccessLevel('NA Moniteur', [$this->groupIdMoniteur]);
        $this->accessLevelIdGroupes = $this->createAccessLevel('NA Groupes', [$this->groupIdBureau, $this->groupIdMoniteur]);



        // -- creation menu pour l'ahésion
        $this->data_adhesion = [
            'title'        => 'Adhésion',
            'alias'        => 'adhesion',
            'menutype'     => 'mainmenu',
            'link'         => 'index.php?option=com_gdadhesions&view=adhesion',
            'type'         => 'component',
            'parent_id'    => 1,
            'component_id' => $this->componentId,
            'access'       => 1, // Accès public
            'params'       => [],
        ];

        // -- creation menu prive pour les adherents
        $this->createFrontendMenuItem($this->data_adhesion);

        $this->data_adherent = [
            'title'        => 'Adhérents',
            'alias'        => 'adherents',
            'menutype'     => 'mainmenu',
            'link'         => 'index.php?option=com_gdadhesions&view=accueil',
            'type'         => 'component',
            'parent_id'    => 1,
            'component_id' => $this->componentId,
            'access'       => 2, // Accès réservé aux adhérents (niveau d'accès 2)
            'params'       => ["menu_icon_css" => "fa-solid fa-circle-user"],

        ];


        $idMenuAdherent = $this->createFrontendMenuItem($this->data_adherent);

        //== creation menu pour la gestion des campagnes (visible uniquement par les membres du bureau) ===

        $this->data_campagnes = [
            'title'        => 'Campagnes',
            'alias'        => 'campagnes_mgt',
            'menutype'     => 'mainmenu',
            'link'         => 'index.php?option=com_gdadhesions&view=campagnes',
            'path'         => 'adherents/campagnes',
            'type'         => 'component',
            'parent_id'    => $idMenuAdherent,
            'level'        => 2,
            'component_id' => $this->componentId,
            'access'       => $this->accessLevelIdBureau, // Accès réservé aux membres (niveau d'accès 7)
            'params'       => [],
        ];

        $this->createFrontendMenuItem($this->data_campagnes);

        $this->data_profil = [
            'title'        => 'Profils',
            'alias'        => 'profils_mgt',
            'menutype'     => 'mainmenu',
            'link'         => 'index.php?option=com_gdadhesions&view=profil',
            'path'         => 'adherents/profils',
            'type'         => 'component',
            'parent_id'    => $idMenuAdherent,
            'level'        => 2,
            'component_id' => $this->componentId,
            'access'       => $this->accessLevelIdBureau, // Accès réservé aux membres (niveau d'accès 7)
            'language'     => '*',
            'client_id'    => 0,
            'menuordering' => 0,
            'params'       => [],
        ];

        $this->createFrontendMenuItem($this->data_profil);

         // -- creation menu prive pour les adherents

        $this->data_secretariat = [
            'title'        => 'Secrétariat',
            'alias'        => 'secretariat_mgt',
            'menutype'     => 'mainmenu',
            'link'         => 'index.php?option=com_gdadhesions&view=secretariat',
            'path'         => 'adherents/secretariat',
            'type'         => 'component',
            'parent_id'    => $idMenuAdherent,
            'level'        => 2,
            'component_id' => $this->componentId,
            'access'       => $this->accessLevelIdBureau, // Accès réservé aux membres (niveau d'accès 7)
            'language'     => '*',
            'client_id'    => 0,
            'menuordering' => 0,
            'params'       => [],
        ];

        $this->createFrontendMenuItem($this->data_secretariat);

        //== creation menu pour la gestion des groupes/acces des utilisateurs (Bureau uniquement) ===
        $this->createUtilisateursMenuItem($idMenuAdherent);

        //== creation menu pour la gestion des groupes (visible uniquement par les membres du bureau) ===
        $this->createGroupesMenuItem($idMenuAdherent);

        //== creation menu pour la gestion des saisons (visible uniquement par les membres du bureau) ===
        $this->createSaisonsMenuItem($idMenuAdherent);

        //== creation menu pour la gestion des brevets (visible uniquement par les membres du bureau) ===
        $this->createBrevetsMenuItem($idMenuAdherent);


        $data_dma = [
            'name' => 'MATHIEU Didier',
            'username' => 'A-03-062553',
            'password' => '$2y$12$8ZWR8yo32EffM4b/nHGCtObeDWgIf0UZKUN96ZdykgC2M.oogJmb2', // 🔥 hash Joomla
            'email' => 'davinox@free.fr',
        ];

        $this->createUser($data_dma, [2, $this->groupIdBureau]); // ex: Registered + Bureau



        return true;
    }

    public function update(InstallerAdapter $parent): bool
    {
        $this->removeObsoleteFiles();

        // creation des menus "Groupes" et "Utilisateurs" pour les sites déjà installés (< 0.8.0)
        $this->addGroupesMenuItemOnUpdate();
        $this->addUtilisateursMenuItemOnUpdate();

        // creation du menu "Saisons" pour les sites déjà installés (< 0.8.1)
        $this->addSaisonsMenuItemOnUpdate();

        // creation du menu "Brevets" pour les sites déjà installés (< 0.9.8)
        $this->addBrevetsMenuItemOnUpdate();

        return true;
    }

    /**
     * Ajoute le menu "Groupes" pour les sites déjà installés (< 0.8.0).
     * Les données de contexte (componentId, templateStyleId, accessLevelIdGroupes, ...) ne sont
     * pas initialisées dans update() comme elles le sont dans install() : on les reconstruit ici.
     * createFrontendMenuItem() / createUserGroup() / createAccessLevel() sont idempotents
     * (recherche par alias/titre avant création), donc un ré-appel à chaque mise à jour est sans risque.
     */
    private function addGroupesMenuItemOnUpdate(): void
    {
        $this->componentId = $this->componentId ?: $this->getComponentId();
        $this->templateStyleId = $this->templateStyleId ?: $this->getDefaultSiteTemplateStyleId();

        $this->groupIdBureau = $this->groupIdBureau ?: $this->createUserGroup('Membre du Bureau', 2);
        $this->groupIdMoniteur = $this->groupIdMoniteur ?: $this->createUserGroup('Moniteur', 2);
        $this->accessLevelIdGroupes = $this->accessLevelIdGroupes
            ?: $this->createAccessLevel('NA Groupes', [$this->groupIdBureau, $this->groupIdMoniteur]);

        $idMenuAdherent = $this->getMenuIdByAlias('adherents');

        if ($idMenuAdherent === null) {
            Factory::getApplication()->enqueueMessage(
                'Menu parent "adherents" introuvable, impossible de créer le menu "Groupes"',
                'warning'
            );
            return;
        }

        $this->createGroupesMenuItem($idMenuAdherent);
    }

    /**
     * Créé le menu frontend "Groupes" (accès réservé au Bureau + Moniteurs) sous le menu parent donné.
     *
     * @param int $parentMenuId Identifiant du menu parent (ex: "Adhérents").
     */
    private function createGroupesMenuItem(int $parentMenuId): void
    {
        $this->data_groupes = [
            'title'        => 'Groupes',
            'alias'        => 'groupes_mgt',
            'menutype'     => 'mainmenu',
            'link'         => 'index.php?option=com_gdadhesions&view=groupes',
            'path'         => 'adherents/groupes',
            'type'         => 'component',
            'parent_id'    => $parentMenuId,
            'level'        => 2,
            'component_id' => $this->componentId,
            'access'       => $this->accessLevelIdGroupes, // Accès réservé au Bureau et aux Moniteurs
            'params'       => [],
        ];

        $this->createFrontendMenuItem($this->data_groupes);
    }

    /**
     * Ajoute le menu "Utilisateurs" pour les sites déjà installés (mise à jour depuis une version
     * antérieure à cette fonctionnalité). accessLevelIdBureau existe déjà depuis l'installation
     * initiale (utilisé par Secrétariat/Campagnes/Profils) ; on le reconstruit ici au cas où
     * update() est exécuté isolément (voir addGroupesMenuItemOnUpdate() pour le même besoin).
     */
    private function addUtilisateursMenuItemOnUpdate(): void
    {
        $this->componentId = $this->componentId ?: $this->getComponentId();
        $this->templateStyleId = $this->templateStyleId ?: $this->getDefaultSiteTemplateStyleId();

        $this->groupIdBureau = $this->groupIdBureau ?: $this->createUserGroup('Membre du Bureau', 2);
        $this->accessLevelIdBureau = $this->accessLevelIdBureau
            ?: $this->createAccessLevel('NA Bureau', [$this->groupIdBureau]);

        $idMenuAdherent = $this->getMenuIdByAlias('adherents');

        if ($idMenuAdherent === null) {
            Factory::getApplication()->enqueueMessage(
                'Menu parent "adherents" introuvable, impossible de créer le menu "Utilisateurs"',
                'warning'
            );
            return;
        }

        $this->createUtilisateursMenuItem($idMenuAdherent);
    }

    /**
     * Créé le menu frontend "Utilisateurs" (accès réservé au Bureau) sous le menu parent donné.
     * Permet au Bureau de consulter/modifier les groupes club et l'activation des comptes déclarés.
     *
     * @param int $parentMenuId Identifiant du menu parent (ex: "Adhérents").
     */
    private function createUtilisateursMenuItem(int $parentMenuId): void
    {
        $this->data_utilisateurs = [
            'title'        => 'Utilisateurs',
            'alias'        => 'utilisateurs_mgt',
            'menutype'     => 'mainmenu',
            'link'         => 'index.php?option=com_gdadhesions&view=utilisateurs',
            'path'         => 'adherents/utilisateurs',
            'type'         => 'component',
            'parent_id'    => $parentMenuId,
            'level'        => 2,
            'component_id' => $this->componentId,
            'access'       => $this->accessLevelIdBureau, // Accès réservé au Bureau
            'params'       => [],
        ];

        $this->createFrontendMenuItem($this->data_utilisateurs);
    }

    /**
     * Ajoute le menu "Saisons" pour les sites déjà installés (mise à jour depuis une version
     * antérieure à cette fonctionnalité). accessLevelIdBureau existe déjà depuis l'installation
     * initiale (utilisé par Secrétariat/Campagnes/Profils/Utilisateurs) ; on le reconstruit ici
     * au cas où update() est exécuté isolément (voir addUtilisateursMenuItemOnUpdate() pour le
     * même besoin).
     */
    private function addSaisonsMenuItemOnUpdate(): void
    {
        $this->componentId = $this->componentId ?: $this->getComponentId();
        $this->templateStyleId = $this->templateStyleId ?: $this->getDefaultSiteTemplateStyleId();

        $this->groupIdBureau = $this->groupIdBureau ?: $this->createUserGroup('Membre du Bureau', 2);
        $this->accessLevelIdBureau = $this->accessLevelIdBureau
            ?: $this->createAccessLevel('NA Bureau', [$this->groupIdBureau]);

        $idMenuAdherent = $this->getMenuIdByAlias('adherents');

        if ($idMenuAdherent === null) {
            Factory::getApplication()->enqueueMessage(
                'Menu parent "adherents" introuvable, impossible de créer le menu "Saisons"',
                'warning'
            );
            return;
        }

        $this->createSaisonsMenuItem($idMenuAdherent);
    }

    /**
     * Créé le menu frontend "Saisons" (accès réservé au Bureau) sous le menu parent donné.
     * Permet au Bureau de gérer la saison courante et l'historique des saisons, séparément
     * de la vue "Campagnes" qui gère les autres types de campagnes.
     *
     * @param int $parentMenuId Identifiant du menu parent (ex: "Adhérents").
     */
    private function createSaisonsMenuItem(int $parentMenuId): void
    {
        $this->data_saisons = [
            'title'        => 'Saisons',
            'alias'        => 'saisons_mgt',
            'menutype'     => 'mainmenu',
            'link'         => 'index.php?option=com_gdadhesions&view=saisons',
            'path'         => 'adherents/saisons',
            'type'         => 'component',
            'parent_id'    => $parentMenuId,
            'level'        => 2,
            'component_id' => $this->componentId,
            'access'       => $this->accessLevelIdBureau, // Accès réservé au Bureau
            'params'       => [],
        ];

        $this->createFrontendMenuItem($this->data_saisons);
    }

    /**
     * Ajoute le menu "Brevets" pour les sites déjà installés (mise à jour depuis une version
     * antérieure à 0.9.8). Même mécanique que addSaisonsMenuItemOnUpdate() : les données de
     * contexte ne sont pas initialisées dans update() comme elles le sont dans install(), et
     * createUserGroup() / createAccessLevel() / createFrontendMenuItem() sont idempotents.
     */
    private function addBrevetsMenuItemOnUpdate(): void
    {
        $this->componentId = $this->componentId ?: $this->getComponentId();
        $this->templateStyleId = $this->templateStyleId ?: $this->getDefaultSiteTemplateStyleId();

        $this->groupIdBureau = $this->groupIdBureau ?: $this->createUserGroup('Membre du Bureau', 2);
        $this->accessLevelIdBureau = $this->accessLevelIdBureau
            ?: $this->createAccessLevel('NA Bureau', [$this->groupIdBureau]);

        $idMenuAdherent = $this->getMenuIdByAlias('adherents');

        if ($idMenuAdherent === null) {
            Factory::getApplication()->enqueueMessage(
                'Menu parent "adherents" introuvable, impossible de créer le menu "Brevets"',
                'warning'
            );
            return;
        }

        $this->createBrevetsMenuItem($idMenuAdherent);
    }

    /**
     * Créé le menu frontend "Brevets" (accès réservé au Bureau) sous le menu parent donné.
     * Donne accès au référentiel FFESSM (#__gda_mapping_brevets) et au rattachement des brevets
     * saisis par les adhérents qu'il n'a pas su reconnaître automatiquement.
     *
     * @param int $parentMenuId Identifiant du menu parent (ex: "Adhérents").
     */
    private function createBrevetsMenuItem(int $parentMenuId): void
    {
        $this->data_brevets = [
            'title'        => 'Brevets',
            'alias'        => 'brevets_mgt',
            'menutype'     => 'mainmenu',
            'link'         => 'index.php?option=com_gdadhesions&view=brevets',
            'path'         => 'adherents/brevets',
            'type'         => 'component',
            'parent_id'    => $parentMenuId,
            'level'        => 2,
            'component_id' => $this->componentId,
            'access'       => $this->accessLevelIdBureau, // Accès réservé au Bureau
            'params'       => [],
        ];

        $this->createFrontendMenuItem($this->data_brevets);
    }

    /**
     * Supprime les fichiers/dossiers devenus obsolètes lors d'une mise à jour.
     * Le programme d'installation Joomla copie les nouveaux fichiers mais ne
     * supprime jamais ceux retirés du package : ce nettoyage doit être fait ici.
     */
    private function removeObsoleteFiles(): void
    {
        $adminPath = JPATH_ADMINISTRATOR . '/components/com_gdadhesions';
        $sitePath  = JPATH_SITE . '/components/com_gdadhesions';
        $mediaPath = JPATH_ROOT . '/media/com_gdadhesions';

        // Vue helloasso renommée en configuration (0.7.16)
        $obsoleteFiles = [
            $adminPath . '/forms/helloasso.xml',
            $adminPath . '/src/Model/HelloassoModel.php',
            $adminPath . '/src/Controller/HelloassoController.php',
            $adminPath . '/src/View/Helloasso/HtmlView.php',
            $adminPath . '/tmpl/helloasso/default.php',

            // Réservations aux campagnes (0.9.5) : la souscription générique du dashboard a été
            // remplacée par un layout dédié à chaque nature de campagne (accueil/dash_formation),
            // s'appuyant sur #__gda_reservation au lieu de #__gda_souscriptions. CampagnesHelper
            // ne servait plus qu'à ce parcours et n'était plus appelé par personne.
            $sitePath . '/layouts/accueil/dash_campagnes.php',
            $sitePath . '/layouts/accueil/dashboard_campagnes.php',
            $sitePath . '/layouts/accueil/TODELETE_card.php',
            $sitePath . '/layouts/accueil/TODELETE_souscription_modale.php',
            $sitePath . '/layouts/secretariat/payement_sav.php',
            $sitePath . '/src/Helper/CampagnesHelper.php',

            // Layouts de mail du cycle de vie du profil, remplacés par mail/adhesion_*
            // (NotificationMailService::sendProfileLifecycleEmail()).
            $sitePath . '/layouts/mail/profile_lifecycle_html.php',
            $sitePath . '/layouts/mail/profile_lifecycle_text.php',

            // Vue Niveau (jamais reliée à un menu, ni appelée par Adhesion/Profil/Saisons/
            // Secretariat : l'extraction des brevets FFESSM passe par AdhesionModel::saveInBrevets()
            // / #__gda_brevets, pas par cette vue). Renommée en TODELETE_ pour vérification avant
            // suppression définitive au prochain cycle de mise à jour.
            // Les deux jeux de noms sont listés : le renommage TODELETE_ n'a eu lieu que dans le
            // dépôt, les sites installés avant ce renommage portent encore les noms d'origine et
            // ne voyaient donc jamais leurs fichiers supprimés.
            $sitePath . '/src/Controller/TODELETE_NiveauController.php',
            $sitePath . '/src/Controller/NiveauController.php',
            $sitePath . '/src/Model/TODELETE_NiveauModel.php',
            $sitePath . '/src/Model/NiveauModel.php',
            $sitePath . '/src/View/Niveau/TODELETE_HtmlView.php',
            $sitePath . '/src/View/Niveau/HtmlView.php',
            $sitePath . '/tmpl/niveau/TODELETE_default.php',
            $sitePath . '/tmpl/niveau/TODELETE_default.xml',
            $sitePath . '/tmpl/niveau/default.php',
            $sitePath . '/tmpl/niveau/default.xml',
            $mediaPath . '/js/TODELETE_niveau.js',
            $mediaPath . '/js/niveau.js',
        ];

        foreach ($obsoleteFiles as $file) {
            if (is_file($file)) {
                File::delete($file);
            }
        }

        $obsoleteFolders = [
            $adminPath . '/src/View/Helloasso',
            $adminPath . '/tmpl/helloasso',
            $sitePath . '/src/View/Niveau',
            $sitePath . '/tmpl/niveau',
        ];

        foreach ($obsoleteFolders as $folder) {
            if (is_dir($folder)) {
                Folder::delete($folder);
            }
        }
    }




    private function createCategory()
    {
        $app = Factory::getApplication();

        /** @var \Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface $catComponent */
        $catComponent = $app->bootComponent('com_categories');
        /** @var \Joomla\Component\Categories\Administrator\Model\CategoryModel $model */
        $model = $catComponent->getMVCFactory()
            ->createModel('Category', 'Administrator', ['ignore_request' => true]);

        // Vérifier si existe déjà
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where('title = ' . $db->quote('GestionDesAdhésions'))
            ->where('extension = ' . $db->quote('com_content'));

        $db->setQuery($query);
        $existingId = $db->loadResult();

        if ($existingId) {
            // Factory::getApplication()->enqueueMessage('La catégorie "GestionDesAdhésions" existe déjà (id=' . $existingId . ')', 'info');
            return (int) $existingId;
        }

        $data = [
            'title' => 'GestionDesAdhésions',
            'alias' => 'gestion-des-adhesions',
            'extension' => 'com_content',
            'published' => 1,
            'access' => 1,
            'language' => '*',
            'parent_id' => 1
        ];

        $model->save($data);
        Factory::getApplication()->enqueueMessage('Creation de l\'article pour les adhésions fermées', 'info');
        return $model->getState($model->getName() . '.id');
    }




    private function createArticle($categoryId)
    {
        $app = Factory::getApplication();

        /** @var \Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface $contentComponent */
        $contentComponent = $app->bootComponent('com_content');
        /** @var \Joomla\Component\Content\Administrator\Model\ArticleModel $model */
        $model = $contentComponent->getMVCFactory()
            ->createModel('Article', 'Administrator', ['ignore_request' => true]);

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        // Vérifier si existe déjà
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__content')
            ->where('alias = ' . $db->quote('les-adhesions-sont-fermees'));

        $db->setQuery($query);
        $existingId = $db->loadResult();

        if ($existingId) {
            // Factory::getApplication()->enqueueMessage('L\'article pour les adhésions fermées existe déjà (id=' . $existingId . ')', 'info');
            return (int) $existingId;
        }

        $data = [
            'title' => 'Les Adhésions sont fermées',
            'alias' => 'les-adhesions-sont-fermees',
            'introtext' => '<p>Les adhésions sont fermées pour le moment.<br>Merci de revenir plus tard !</p>',
            'catid' => $categoryId,
            'state' => 1,
            'access' => 1,
            'language' => '*'
        ];

        $model->save($data);

        Factory::getApplication()->enqueueMessage('Creation de l\'article pour les adhésions fermées:' . $model->getName() . '.id', 'info');

        return $model->getState($model->getName() . '.id');
    }

    private function getComponentId()
    {
        /*
     *  Récupérer l'ID du composant
     */
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('extension_id')
            ->from('#__extensions')
            ->where('element = ' . $db->quote('com_gdadhesions'));

        $db->setQuery($query);
        return (int) $db->loadResult();
    }



    private function insertConf($articleId)
    {
        /*
     *  Insertion 'IdArticleAdhesionClos' dans #__gda_conf
     */

        $app = Factory::getApplication();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        try {
            // if exists, update, else insert
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__gda_conf'))
                ->where($db->quoteName('key') . ' = ' . $db->quote('IdArticleAdhesionClos'));

            $db->setQuery($query);
            $existingId = $db->loadResult();

            if ($existingId) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__gda_conf'))
                    ->set($db->quoteName('value') . ' = ' . (int) $articleId)
                    ->where($db->quoteName('id') . ' = ' . (int) $existingId);
            } else {
                $columns = ['key', 'value'];
                $values  = [$db->quote('IdArticleAdhesionClos'), (int) $articleId];

                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__gda_conf'))
                    ->columns($db->quoteName($columns))
                    ->values(implode(',', $values));
            }
            $db->setQuery($query)->execute();
            Factory::getApplication()->enqueueMessage('Enregistrement de la conf IdArticleAdhesionClos', 'info');
        } catch (\Exception $e) {
            $app->enqueueMessage('Erreur insertion configuration : ' . $e->getMessage(), 'error');
        }
    }


    private function createFrontendMenuItem($data)
    {
        $app = Factory::getApplication();

        $defaults = [
            'published'    => 1,
            'language'     => '*',
            'client_id'    => 0,
            'menuordering' => 0,
            'template_style_id'  => $this->templateStyleId
        ];

        $data = array_merge($data, $defaults);


        /** @var \Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface $menusComponent */
        $menusComponent = $app->bootComponent('com_menus');
        /** @var \Joomla\Component\Menus\Administrator\Model\ItemModel $model */
        $model = $menusComponent->getMVCFactory()
            ->createModel('Item', 'Administrator', ['ignore_request' => true]);

        /*
        *  Créer le menu item $data['title'] dans le mainmenu
        */


        $menuId = $this->getMenuIdByAlias($data['alias']);
        if ($menuId === null) {
            $model->save($data);
            $menuId = $model->getState($model->getName() . '.id');
            Factory::getApplication()->enqueueMessage('Creation de l\'item de menu "' . $data['title'] . '" [' . $menuId . ']', 'info');
        } 
        // else {
        //     Factory::getApplication()->enqueueMessage('L\'item de menu "' . $data['title'] . '" existe déjà [' . $menuId . ']', 'info');
        // }

        return $menuId;
    }


    private function getMainMenuId()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote('mainmenu'));

        $db->setQuery($query);
        $menuId = $db->loadResult();

        return $menuId ? (int) $menuId : null;
    }

    private function getMenuIdByAlias(string $alias): ?int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote($alias));

        $db->setQuery($query);
        $menuId = $db->loadResult();

        return $menuId ? (int) $menuId : null;
    }





    private function createUserGroup(string $title, int $parentId)
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        /*
     * 1️⃣ Vérifier si déjà existant
     */
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__usergroups')
            ->where('title = ' . $db->quote($title));

        $db->setQuery($query);
        $existingId = $db->loadResult();

        if ($existingId) {
            // Factory::getApplication()->enqueueMessage('Le groupe "' . $title . '" existe déjà (id=' . $existingId . ')', 'info');
            return (int) $existingId;
        }

        /*
     * 2️⃣ Créer le groupe proprement (nested set auto)
     */
        $dbUg = Factory::getContainer()->get(DatabaseInterface::class);
        $table = new UsergroupTable($dbUg);

        $data = [
            'title'     => $title,
            'parent_id' => $parentId
        ];

        $table->bind($data);
        $table->check();
        $table->store();
        $id = $table->id;
        Factory::getApplication()->enqueueMessage('Creation du UserGroup "' . $title . '" [' . $id . ']', 'info');
        return (int) $id;
    }


    /**
     * Returns the value of the property with the given name from the internal
     * script.
     *
     * @param   string $title Titre du niveau d'acces
     * @param   array  $groupIds  Les IDs des groupes ayant accès à ce niveau
     * @return  mixed
     * @since   4.2.0
     */
    private function createAccessLevel(string $title, array $groupIds)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        /*
     * 1️⃣ Vérifier si déjà existant
     */
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__viewlevels')
            ->where('title = ' . $db->quote($title));

        $db->setQuery($query);
        $existingId = $db->loadResult();

        if ($existingId) {
            // Factory::getApplication()->enqueueMessage('Le niveau d\'accès "' . $title . '" existe déjà (id=' . $existingId . ')', 'info');
            return (int) $existingId;
        }

        /*
     * 2️⃣ Insérer le niveau
     */
        $columns = ['title', 'ordering', 'rules'];
        $values  = [
            $db->quote($title),
            0,
            $db->quote(json_encode($groupIds))
        ];

        $query = $db->getQuery(true)
            ->insert('#__viewlevels')
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query);
        $db->execute();
        $id = $db->insertid();
        Factory::getApplication()->enqueueMessage('Creation de l\'AccessLevel "' . $title . '" [' . $id . ']', 'info');
        return $id;
    }





    /**
     * 
     */
    private function getDefaultSiteTemplateStyleId()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__template_styles')
            ->where('client_id = 0')   // 0 = site
            ->where('home = 1');       // template par défaut

        $db->setQuery($query);

        return (int) $db->loadResult();
    }


    /**
     * Returns the variable from the internal script.
     * @param   array $data  Tableau contenant au moins 'username' et/ou 'email' pour identifier l'utilisateur
     * @return  int|null    ID de l'utilisateur existant, ou null s'il n'existe pas
     */
    private function getExistingUserId(array $data): ?int
    {
        $username = (string) ($data['username'] ?? '');
        $email = (string) ($data['email'] ?? '');

        if ($username === '' && $email === '') {
            return null;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'));

        if ($username !== '' && $email !== '') {
            $query->where(
                '(' .
                    $db->quoteName('username') . ' = ' . $db->quote($username) .
                    ' OR ' .
                    $db->quoteName('email') . ' = ' . $db->quote($email) .
                    ')'
            );
        } elseif ($username !== '') {
            $query->where($db->quoteName('username') . ' = ' . $db->quote($username));
        } else {
            $query->where($db->quoteName('email') . ' = ' . $db->quote($email));
        }

        $db->setQuery($query);
        $existingId = (int) $db->loadResult();

        return $existingId > 0 ? $existingId : null;
    }

    private function createUser(array $data, array $groupIds = []): int
    {
        $existingUserId = $this->getExistingUserId($data);

        if ($existingUserId !== null) {
            foreach ($groupIds as $groupId) {
                UserHelper::addUserToGroup($existingUserId, (int) $groupId);
            }

            Factory::getApplication()->enqueueMessage('Utilisateur déjà existant [' . $existingUserId . ']', 'info');
            return $existingUserId;
        }

        $dbU = Factory::getContainer()->get(DatabaseInterface::class);
        $table = new UserTable($dbU);

        $defaults = [
            'block'        => 0,
            'sendEmail'    => 0,
            'registerDate' => Factory::getDate()->toSql(),
            'params'       => '{}', // important: évite "Field 'params' doesn't have a default value"
        ];

        $data = array_merge($defaults, $data);
        try {
            $table->bind($data);
            $table->check();
            $table->store(true); // force insert
            $userId = (int) $table->id;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Erreur création utilisateur ' . $data['username'] . ' : ' . $e->getMessage(), 'error');
            return 0;
        }

        foreach ($groupIds as $groupId) {
            UserHelper::addUserToGroup($userId, (int) $groupId);
        }
        Factory::getApplication()->enqueueMessage('Utilisateur ' . $data['username'] . ' créé, id=' . $userId, 'info');
        return $userId;
    }
}
