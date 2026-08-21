<?php

/**
 * @package     com_gdadhesions
 * @subpackage  components
 * @copyright   Copyright (C) 2024 GD Adhesions. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Service\BrevetService;
use NCB\Component\Gda\Site\Service\NotificationMailService;

/**
 * Modèle de la vue "Utilisateurs" (réservée au Bureau) : liste des comptes déclarés et
 * gestion de leurs groupes club / statut d'activation.
 *
 * @since  1.0.0
 */
class UtilisateursModel extends ListModel
{
    /**
     * Model context string.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $context = 'com_gdadhesions.utilisateurs';

    private ?BrevetService $brevetService = null;

    private ?NotificationMailService $notificationMailService = null;

    /**
     * Liste des comptes déclarés (hors comptes d'administration Joomla, i.e. groupe "Super Users"),
     * avec leur(s) groupe(s) club actuellement assignés.
     *
     * @return array
     *
     * @since  1.0.0
     */
    public function getUtilisateurs(): array
    {
        $db = $this->getDatabase();
        $superUsersGroupId = UsersHelper::getSuperUsersGroupId();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('u.id'),
                $db->quoteName('u.username'),
                $db->quoteName('u.name'),
                $db->quoteName('u.email'),
                $db->quoteName('u.block'),
                $db->quoteName('u.lastvisitDate'),
                $db->quoteName('p.civilite'),
                $db->quoteName('p.nom'),
                $db->quoteName('p.prenom'),
                $db->quoteName('p.photo'),
                $db->quoteName('p.fonction'),
                $db->quoteName('p.ordre_bureau'),
                $db->quoteName('p.caci'),
                $db->quoteName('p.date_caci'),
                $db->quoteName('p.date_licence'),
            ])
            ->from($db->quoteName('#__users', 'u'))
            ->join('LEFT', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('u.id'))
            ->order($db->quoteName('p.nom') . ' ASC, ' . $db->quoteName('p.prenom') . ' ASC');

        if ($superUsersGroupId !== null) {
            $query->where(
                $db->quoteName('u.id') . ' NOT IN (' .
                    'SELECT ' . $db->quoteName('user_id') .
                    ' FROM ' . $db->quoteName('#__user_usergroup_map') .
                    ' WHERE ' . $db->quoteName('group_id') . ' = :super_users_group_id' .
                    ')'
            )->bind(':super_users_group_id', $superUsersGroupId);
        }

        $db->setQuery($query);
        $utilisateurs = $db->loadObjectList() ?: [];

        if (empty($utilisateurs)) {
            return [];
        }

        $userIds = array_map(static fn ($item) => (int) $item->id, $utilisateurs);
        $clubGroupIds = array_filter(UsersHelper::getClubGroupIds());

        $groupsByUser = [];

        if (!empty($clubGroupIds)) {
            $groupsQuery = $db->getQuery(true)
                ->select([
                    $db->quoteName('m.user_id'),
                    $db->quoteName('g.id', 'id_groupe'),
                    $db->quoteName('g.title'),
                ])
                ->from($db->quoteName('#__user_usergroup_map', 'm'))
                ->join('INNER', $db->quoteName('#__usergroups', 'g') . ' ON ' . $db->quoteName('g.id') . ' = ' . $db->quoteName('m.group_id'))
                ->whereIn($db->quoteName('m.user_id'), $userIds)
                ->whereIn($db->quoteName('m.group_id'), array_values($clubGroupIds));

            $db->setQuery($groupsQuery);
            $groupsRows = $db->loadObjectList() ?: [];

            foreach ($groupsRows as $groupRow) {
                $groupsByUser[(int) $groupRow->user_id][] = [
                    'id_groupe' => (int) $groupRow->id_groupe,
                    'title' => (string) $groupRow->title,
                ];
            }
        }

        foreach ($utilisateurs as $utilisateur) {
            $utilisateur->groupes_club = $groupsByUser[(int) $utilisateur->id] ?? [];
        }

        $souscriptionsByUser = $this->getSouscriptionsCourantes($userIds);

        foreach ($utilisateurs as $utilisateur) {
            $utilisateur->adhesion_status = AdhesionStatusHelper::getStatusEnum(
                $souscriptionsByUser[(int) $utilisateur->id] ?? null
            );
        }

        // Brevets "importants" (plus fort poids par activité/rôle) en une seule requête groupée,
        // pour l'aperçu de l'onglet Profils. Réutilise BrevetService::getBrevetsShortListProfils(),
        // même motif que GroupesModel::enrichirBrevetsShortList() (nom de propriété brevets_shortlist
        // repris à l'identique, cf. layouts/groupes/detail.php).
        $shortLists = $this->getBrevetService()->getBrevetsShortListProfils($userIds);

        foreach ($utilisateurs as $utilisateur) {
            $utilisateur->brevets_shortlist = $shortLists[(int) $utilisateur->id] ?? [];
        }

        return $utilisateurs;
    }

    /**
     * Getter pour obtenir le service Brevet (lazy loading, pas dans le conteneur DI du composant).
     * Même motif que ProfilModel::getBrevetService().
     */
    private function getBrevetService(): BrevetService
    {
        if ($this->brevetService === null) {
            $this->brevetService = new BrevetService($this->getDatabase());
        }

        return $this->brevetService;
    }

    /**
     * Getter pour obtenir le service de notification mail (lazy loading). Même motif que
     * AdhesionModel::getNotificationMailService() / SecretariatModel::getNotificationMailService().
     */
    private function getNotificationMailService(): NotificationMailService
    {
        if ($this->notificationMailService === null) {
            $this->notificationMailService = new NotificationMailService(
                $this->getDatabase(),
                Factory::getContainer()->get(MailerFactoryInterface::class),
                ConfHelper::getConfigService()
            );
        }

        return $this->notificationMailService;
    }

    /**
     * Récupère en une seule requête les souscriptions de la saison courante pour un ensemble
     * d'utilisateurs (évite une requête par ligne dans getUtilisateurs()).
     *
     * @param int[] $userIds Identifiants des utilisateurs (id_profil).
     *
     * @return array<int, object> Souscriptions indexées par id_profil.
     */
    private function getSouscriptionsCourantes(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $saisonCourante = ConfHelper::getSaisonService()->getSaisonCourante();

        if ($saisonCourante === null) {
            return [];
        }

        $db = $this->getDatabase();
        $idCampagne = (int) $saisonCourante->id_campagne;

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('s.id_campagne'),
                $db->quoteName('s.id_profil'),
                $db->quoteName('s.cotisation_code'),
                $db->quoteName('s.caci_check'),
                $db->quoteName('s.cotisation_check'),
                $db->quoteName('s.licence_check'),
                $db->quoteName('s.id_order'),
                $db->quoteName('p.caci'),
                $db->quoteName('p.date_caci'),
                $db->quoteName('u.username'),
            ])
            ->from($db->quoteName('#__gda_souscriptions', 's'))
            ->join('LEFT', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('s.id_profil'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('s.id_profil'))
            ->where($db->quoteName('s.id_campagne') . ' = :id_campagne')
            ->whereIn($db->quoteName('s.id_profil'), $userIds)
            ->bind(':id_campagne', $idCampagne);

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $souscriptionsByUser = [];

        foreach ($rows as $row) {
            $souscriptionsByUser[(int) $row->id_profil] = $row;
        }

        return $souscriptionsByUser;
    }

    /**
     * Met à jour les groupes club d'un utilisateur. Les ids fournis sont strictement limités
     * à l'ensemble {Registered, Moniteur, Responsable de Groupe, Membre du Bureau} : tout id hors
     * de cet ensemble (ex: Manager, Administrator, Super Users) est rejeté. Registered est
     * systématiquement conservé pour ne pas priver l'utilisateur de son accès de base au site.
     *
     * @param int   $userId   Identifiant de l'utilisateur Joomla.
     * @param int[] $groupIds Ids des groupes club à assigner.
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function updateUserGroups(int $userId, array $groupIds): bool
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        $allowedGroupIds = array_values(array_filter(UsersHelper::getClubGroupIds()));
        $allowedGroupIds[] = 2; // Registered

        $groupIds = array_values(array_unique(array_map('intval', $groupIds)));

        foreach ($groupIds as $groupId) {
            if (!in_array($groupId, $allowedGroupIds, true)) {
                throw new \InvalidArgumentException('Groupe non autorisé pour cette action: ' . $groupId);
            }
        }

        if (!in_array(2, $groupIds, true)) {
            $groupIds[] = 2;
        }

        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
        $user = $userFactory->loadUserById($userId);

        if (!$user || (int) $user->id !== $userId) {
            throw new \RuntimeException('Utilisateur Joomla introuvable.');
        }

        $userData = ['groups' => $groupIds];
        $user->bind($userData);

        if (!$user->save()) {
            throw new \RuntimeException('Erreur lors de la mise à jour des groupes: ' . $user->getError());
        }

        GdaLogger::info(
            '[' . $this->getActingUserName() . '] Groupes mis à jour pour ' . $user->username . ' (id=' . $userId . '): [' . implode(',', $groupIds) . ']'
        );

        return true;
    }

    /**
     * Active ou bloque un compte utilisateur.
     *
     * @param int  $userId  Identifiant de l'utilisateur Joomla.
     * @param bool $blocked Vrai pour bloquer le compte, faux pour l'activer.
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function updateUserBlockStatus(int $userId, bool $blocked): bool
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
        $user = $userFactory->loadUserById($userId);

        if (!$user || (int) $user->id !== $userId) {
            throw new \RuntimeException('Utilisateur Joomla introuvable.');
        }

        $user->set('block', $blocked ? 1 : 0);

        if (!$user->save()) {
            throw new \RuntimeException('Erreur lors de la mise à jour du statut: ' . $user->getError());
        }

        GdaLogger::info(
            '[' . $this->getActingUserName() . '] Statut mis à jour pour ' . $user->username . ' (id=' . $userId . '): ' . ($blocked ? 'bloqué' : 'activé')
        );

        return true;
    }

    /**
     * Génère un mot de passe temporaire pour un compte, force son changement à la prochaine
     * connexion, et retourne les informations nécessaires à l'envoi du mail (le mot de passe en
     * clair n'est jamais journalisé ni renvoyé au navigateur, seulement transmis à l'appelant pour
     * l'envoi immédiat de l'email).
     *
     * Attention : Joomla\CMS\User\User::bind() remet automatiquement `requireReset` à 0 dès qu'un
     * mot de passe est modifié sur un compte existant (il part du principe qu'un changement de mot
     * de passe satisfait l'exigence en cours). Il faut donc forcer `requireReset = 1` par une
     * requête directe *après* la sauvegarde - exactement le motif utilisé par le cœur Joomla
     * (administrator/components/com_users/src/Model/UserModel.php, action "Forcer la
     * réinitialisation").
     *
     * @param int $userId Identifiant de l'utilisateur Joomla.
     *
     * @return array{username: string, display_name: string, email: string, temp_password: string}
     *
     * @since  1.0.0
     */
    public function resetUserPassword(int $userId): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
        $user = $userFactory->loadUserById($userId);

        if (!$user || (int) $user->id !== $userId) {
            throw new \RuntimeException('Utilisateur Joomla introuvable.');
        }

        $tempPassword = UserHelper::genRandomPassword(12);
        $userData = ['password' => $tempPassword, 'password2' => $tempPassword];
        $user->bind($userData);

        if (!$user->save()) {
            throw new \RuntimeException('Erreur lors de la réinitialisation du mot de passe: ' . $user->getError());
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__users'))
            ->set($db->quoteName('requireReset') . ' = 1')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $userId, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        GdaLogger::info(
            '[' . $this->getActingUserName() . '] Mot de passe réinitialisé pour ' . $user->username
                . ' (id=' . $userId . '), changement forcé à la prochaine connexion'
        );

        $this->getNotificationMailService()->sendPasswordResetEmail(
            (string) $user->email,
            (string) $user->name,
            (string) $user->username,
            $tempPassword
        );

        return [
            'username' => (string) $user->username,
            'display_name' => (string) $user->name,
            'email' => (string) $user->email,
        ];
    }

    /**
     * Met à jour la fonction (rôle libre, ex: Trésorier, Responsable Communication) d'un membre.
     *
     * @param int    $userId   Identifiant de l'utilisateur (id_profil).
     * @param string $fonction Libellé de la fonction (100 caractères max). Chaîne vide = efface la valeur.
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function updateUserFonction(int $userId, string $fonction): bool
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        $fonction = trim($fonction);

        if (mb_strlen($fonction) > 100) {
            throw new \InvalidArgumentException('La fonction ne peut pas dépasser 100 caractères.');
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__gda_profils'))
            ->where($db->quoteName('id_profil') . ' = :id_profil')
            ->bind(':id_profil', $userId);

        if ($fonction === '') {
            $query->set($db->quoteName('fonction') . ' = NULL');
        } else {
            $query->set($db->quoteName('fonction') . ' = :fonction')
                ->bind(':fonction', $fonction);
        }

        $db->setQuery($query);
        $db->execute();

        if ((int) $db->getAffectedRows() === 0) {
            throw new \RuntimeException('Aucun profil trouvé pour cet utilisateur.');
        }

        GdaLogger::info(
            '[' . $this->getActingUserName() . '] Fonction mise à jour (id=' . $userId . '): "' . $fonction . '"'
        );

        return true;
    }

    /**
     * Met à jour l'ordre d'affichage d'un membre dans le trombinoscope du Bureau.
     *
     * @param int      $userId Identifiant de l'utilisateur (id_profil).
     * @param int|null $ordre  Rang d'affichage (0-999). Null efface la valeur : le membre
     *                         retombe alors sur le tri alphabétique (nom, prenom) en repli.
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function updateOrdreBureau(int $userId, ?int $ordre): bool
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        if ($ordre !== null && ($ordre < 0 || $ordre > 999)) {
            throw new \InvalidArgumentException('L\'ordre doit être compris entre 0 et 999.');
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__gda_profils'))
            ->where($db->quoteName('id_profil') . ' = :id_profil')
            ->bind(':id_profil', $userId);

        if ($ordre === null) {
            $query->set($db->quoteName('ordre_bureau') . ' = NULL');
        } else {
            $query->set($db->quoteName('ordre_bureau') . ' = :ordre_bureau')
                ->bind(':ordre_bureau', $ordre, \Joomla\Database\ParameterType::INTEGER);
        }

        $db->setQuery($query);
        $db->execute();

        if ((int) $db->getAffectedRows() === 0) {
            throw new \RuntimeException('Aucun profil trouvé pour cet utilisateur.');
        }

        GdaLogger::info(
            '[' . $this->getActingUserName() . '] Ordre bureau mis à jour (id=' . $userId . '): ' . ($ordre ?? 'NULL')
        );

        return true;
    }

    /**
     * Nom d'affichage de l'utilisateur connecté qui effectue l'action (pour traçabilité des logs).
     */
    private function getActingUserName(): string
    {
        $actingUser = Factory::getApplication()->getIdentity();

        return $actingUser && $actingUser->id ? $actingUser->name : 'unknown';
    }
}
