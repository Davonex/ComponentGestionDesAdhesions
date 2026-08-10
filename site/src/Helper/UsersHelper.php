<?php

namespace NCB\Component\Gda\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\User;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

class UsersHelper
{
    /**<summary> Check if a user exists by username </summary> */
    public static function userExists($username)
    {
        $user = User::getInstance($username);
        return ($user && $user->id) ? true : false;
    }

    /**<summary> Check if a user exists by email address </summary> */
    public static function mailExists($usermail)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select('COUNT(*)')
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('email') . ' = :email')
            ->bind(':email', $usermail);

        $db->setQuery($query);
        return $db->loadResult() > 0;
    }

    /**
     *   creation d'un user Joomla 
     *   @param array $data : tableau contenant les données de l'adhésion
     *  @return User : l'objet utilisateur créé
     */
    public static function createUserName($data = array())
    {
        // Générer un username aléatoire si non fourni
        $username = !empty($data['username']) ? $data['username'] : '';

        if (empty($username)) {
            $year = date('y');  // YY de l'année actuelle (26 pour 2026)
            $randomNumber = str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
            $username = 'N-' . $year . '-' . $randomNumber;

            // Vérifier que le username généré n'existe pas déjà
            while (self::userExists($username)) {
                $randomNumber = str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
                $username = 'A-' . $year . '-' . $randomNumber;
            }
        }

        // Vérifier que l'email n'existe pas déjà
        if (self::mailExists($data['email'])) {
            throw new \Exception('L\'email ' . $data['email'] . ' existe déjà', 400);
        }

        // Vérifier que le username n'existe pas déjà
        if (self::userExists($username)) {
            throw new \Exception('Le username ' . $username . ' existe déjà', 400);
        }

        try {
            // Créer un nouvel utilisateur
            $user = new User();
            // https://api.joomla.org/cms-5/classes/Joomla-CMS-User-User.html
            // force le changement de mot de passe à la première connexion
            $user->requireReset = true; // force le changement de mot de passe à la première connexion
            
            $userData = array(
                'name'     => ToolsHelper::removeAccentsAndUppercase($data['name']),  // Nom d'affichage
                'username' => $username, // licence Provisoire
                'email'    => $data['email'],   // Adresse e-mail
                'password' => $data['password'],       
                'groups'   => $data['groups'] ?? array(1),   // Groupe par défaut (public)
                'block'    => $data['block'] ?? 1           // Utilisateur inactif
            );

            // Bind les données
            $user->bind($userData);

            // Sauvegarder l'utilisateur
            if (!$user->save()) {
                throw new \Exception('Erreur lors de la sauvegarde de l\'utilisateur: ' . $user->getError(), 500);
            }

            return $user;
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la création du nouvel utilisateur: ' . $e->getMessage(), $e->getCode());
        }
    }

    /**
     * <summary> Check if a user is blocked by username </summary>
     * <param name="username">The username of the user to check</param>
     */
    public static function isBlocked($username)
    {
        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
        $user = $userFactory->loadUserByUsername($username);
        return ($user && $user->id && $user->block == 1) ? true : false;
    }

    /**
     * Vérifie si l'utilisateur connecté est autorisé pour un niveau d'accès Joomla donné (recherché par
     * titre, car les ID de groupes/niveaux d'accès "NA Bureau"/"NA Responsable de Groupe"/"NA Moniteur"
     * sont générés dynamiquement à l'installation par administrator/components/com_gdadhesions/script.php
     * et ne sont donc pas des constantes fiables.
     */
    private static function userHasViewLevel(string $viewLevelTitle): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__viewlevels'))
            ->where($db->quoteName('title') . ' = :title')
            ->bind(':title', $viewLevelTitle);

        $db->setQuery($query);
        $levelId = (int) $db->loadResult();

        if ($levelId <= 0) {
            return false;
        }

        $user = Factory::getApplication()->getIdentity();

        if ($user === null) {
            return false;
        }

        // array_map(intval) : getAuthorisedViewLevels() peut renvoyer des entiers ou des chaînes numériques
        // selon le contexte ; on normalise pour une comparaison stricte fiable.
        return in_array($levelId, array_map('intval', $user->getAuthorisedViewLevels()), true);
    }

    /**
     * Vrai si l'utilisateur connecté est membre du Bureau (niveau d'accès "NA Bureau").
     */
    public static function isBureauMember(): bool
    {
        return self::userHasViewLevel('NA Bureau');
    }

    /**
     * Vrai si l'utilisateur connecté peut consulter la fiche d'un adhérent (Moniteur, Responsable de
     * Groupe ou membre du Bureau) — utilisé pour protéger la popup "fiche adhérent" (Groupe/Secretariat),
     * les tâches ajax n'étant pas protégées par le niveau d'accès du menu contrairement aux vues.
     */
    public static function canViewMemberDetails(): bool
    {
        return self::isBureauMember()
            || self::userHasViewLevel('NA Responsable de Groupe')
            || self::userHasViewLevel('NA Moniteur');
    }

    /**
     * Infos d'affichage (clé de langue + icône Font Awesome) du rôle de l'utilisateur connecté,
     * par priorité Bureau > Responsable de Groupe > Moniteur > Adhérent (groupe Registered, rôle
     * de base retourné si aucun des 3 rôles métier n'est présent).
     *
     * @return array{label: string, icon: string}
     */
    public static function getCurrentUserRole(): array
    {
        if (self::isBureauMember()) {
            return ['label' => 'COM_GDA_ROLE_BUREAU', 'icon' => 'fa-solid fa-user-tie'];
        }

        if (self::userHasViewLevel('NA Responsable de Groupe')) {
            return ['label' => 'COM_GDA_ROLE_RESPONSABLE_GROUPE', 'icon' => 'fa-solid fa-people-group'];
        }

        if (self::userHasViewLevel('NA Moniteur')) {
            return ['label' => 'COM_GDA_ROLE_MONITEUR', 'icon' => 'fa-solid fa-chalkboard-user'];
        }

        return ['label' => 'COM_GDA_ROLE_ADHERENT', 'icon' => 'fa-solid fa-user'];
    }

    /**
     * Résout par titre l'id d'un groupe Joomla (#__usergroups). Les ids sont générés
     * dynamiquement à l'installation (voir administrator/components/com_gdadhesions/script.php)
     * et ne sont donc pas des constantes fiables.
     */
    private static function getGroupIdByTitle(string $groupTitle): ?int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('title') . ' = :title')
            ->bind(':title', $groupTitle);

        $db->setQuery($query);
        $id = (int) $db->loadResult();

        return $id > 0 ? $id : null;
    }

    /**
     * Ids des 3 groupes métier du club (Bureau, Responsable de Groupe, Moniteur), résolus par
     * titre. Clés stables ('bureau', 'responsable', 'moniteur') utilisables par le Model/Controller
     * de la vue Utilisateurs pour valider les groupes attribuables.
     *
     * @return array<string, int|null>
     */
    public static function getClubGroupIds(): array
    {
        return [
            'bureau' => self::getGroupIdByTitle('Membre du Bureau'),
            'responsable' => self::getGroupIdByTitle('Responsable de Groupe'),
            'moniteur' => self::getGroupIdByTitle('Moniteur'),
        ];
    }

    /**
     * Id du groupe Joomla natif "Super Users", utilisé pour exclure les comptes d'administration
     * de la liste des utilisateurs gérés par le Bureau (vue Utilisateurs).
     */
    public static function getSuperUsersGroupId(): ?int
    {
        return self::getGroupIdByTitle('Super Users');
    }
}
