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
}
