<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\UsersHelper;

class UtilisateursController extends BaseController
{
    /**
     * Vérifie que l'utilisateur connecté est membre du Bureau, sinon lève une exception.
     * Nécessaire car les tâches ajax ne sont pas protégées par le niveau d'accès du menu,
     * contrairement à l'affichage de la vue.
     */
    private function guardBureauMember(): void
    {
        if (!UsersHelper::isBureauMember()) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Nom d'affichage de l'utilisateur connecté qui effectue l'action (pour traçabilité des logs).
     */
    private function getActingUserName(): string
    {
        $actingUser = Factory::getApplication()->getIdentity();

        return $actingUser && $actingUser->id ? $actingUser->name : 'unknown';
    }

    /**
     * Ajax: met à jour les groupes club d'un utilisateur.
     */
    public function updateGroups(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $input = $app->input;
            $userId = $input->getInt('id_user', 0);
            $groupIds = $input->get('groups', [], 'array');
            $groupIds = array_map('intval', $groupIds);

            /** @var \NCB\Component\Gda\Site\Model\UtilisateursModel $model */
            $model = $this->getModel('utilisateurs', 'site');
            $model->updateUserGroups($userId, $groupIds);

            $response = new JsonResponse();
            $response->success = true;
            $response->message = Text::_('COM_GDA_UTILISATEURS_GROUPES_UPDATED');
        } catch (\Throwable $e) {
            $response = new JsonResponse();
            $response->success = false;
            $response->message = 'Erreur: ' . $e->getMessage();
            GdaLogger::error(
                '[' . ($app->getUserState('session')['name'] ?? 'unknown') . '] ' .
                    'Erreur lors de la mise à jour des groupes: ' . $e->getMessage()
            );
        }

        echo $response;
        $app->close();
    }

    /**
     * Ajax: met à jour la fonction (rôle libre) d'un membre.
     */
    public function updateFonction(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $input = $app->input;
            $userId = $input->getInt('id_user', 0);
            $fonction = $input->getString('fonction', '');

            /** @var \NCB\Component\Gda\Site\Model\UtilisateursModel $model */
            $model = $this->getModel('utilisateurs', 'site');
            $model->updateUserFonction($userId, $fonction);

            $response = new JsonResponse();
            $response->success = true;
            $response->message = Text::_('COM_GDA_UTILISATEURS_FONCTION_UPDATED');
        } catch (\Throwable $e) {
            $response = new JsonResponse();
            $response->success = false;
            $response->message = 'Erreur: ' . $e->getMessage();
            GdaLogger::error(
                '[' . ($app->getUserState('session')['name'] ?? 'unknown') . '] ' .
                    'Erreur lors de la mise à jour de la fonction: ' . $e->getMessage()
            );
        }

        echo $response;
        $app->close();
    }

    /**
     * Ajax: met à jour l'ordre d'affichage d'un membre dans le trombinoscope du Bureau.
     */
    public function updateOrdre(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $input = $app->input;
            $userId = $input->getInt('id_user', 0);
            $ordreRaw = $input->getString('ordre', '');
            $ordre = $ordreRaw === '' ? null : (int) $ordreRaw;

            /** @var \NCB\Component\Gda\Site\Model\UtilisateursModel $model */
            $model = $this->getModel('utilisateurs', 'site');
            $model->updateOrdreBureau($userId, $ordre);

            $response = new JsonResponse();
            $response->success = true;
            $response->message = Text::_('COM_GDA_UTILISATEURS_ORDRE_UPDATED');
        } catch (\Throwable $e) {
            $response = new JsonResponse();
            $response->success = false;
            $response->message = 'Erreur: ' . $e->getMessage();
            GdaLogger::error(
                '[' . ($app->getUserState('session')['name'] ?? 'unknown') . '] ' .
                    'Erreur lors de la mise à jour de l\'ordre bureau: ' . $e->getMessage()
            );
        }

        echo $response;
        $app->close();
    }

    /**
     * Ajax: active ou bloque un compte utilisateur.
     */
    public function toggleBlock(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $input = $app->input;
            $userId = $input->getInt('id_user', 0);
            $blocked = (bool) $input->getInt('blocked', 0);

            /** @var \NCB\Component\Gda\Site\Model\UtilisateursModel $model */
            $model = $this->getModel('utilisateurs', 'site');
            $model->updateUserBlockStatus($userId, $blocked);

            $response = new JsonResponse();
            $response->success = true;
            $response->message = $blocked
                ? Text::_('COM_GDA_UTILISATEURS_BLOCKED')
                : Text::_('COM_GDA_UTILISATEURS_UNBLOCKED');
        } catch (\Throwable $e) {
            $response = new JsonResponse();
            $response->success = false;
            $response->message = 'Erreur: ' . $e->getMessage();
            GdaLogger::error(
                '[' . ($app->getUserState('session')['name'] ?? 'unknown') . '] ' .
                    'Erreur lors de la mise à jour du statut: ' . $e->getMessage()
            );
        }

        echo $response;
        $app->close();
    }

    /**
     * Ajax: suppression définitive d'un adhérent (profil + user + fichiers associés). Réutilise
     * SecretariatModel::deleteAdherentDefinitif() : la mécanique (transaction, dépendances FK,
     * nettoyage photo/CACI) est déjà en place pour la vue Secrétariat, pas de raison de la dupliquer.
     */
    public function deleteAdherent(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        $idProfil = 0;

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $idProfil = $app->input->getInt('id_profil', 0);

            if ($idProfil <= 0) {
                throw new \InvalidArgumentException('Identifiant profil invalide.');
            }

            $currentUser = $app->getIdentity();
            if ($currentUser && (int) $currentUser->id === $idProfil) {
                throw new \RuntimeException(Text::_('COM_GDA_UTILISATEURS_DELETE_SELF_FORBIDDEN'));
            }

            /** @var \NCB\Component\Gda\Site\Model\SecretariatModel $secretariatModel */
            $secretariatModel = $this->getModel('secretariat', 'site');
            $deletedInfo = $secretariatModel->deleteAdherentDefinitif($idProfil);

            GdaLogger::info(
                '[' . $this->getActingUserName() . '] ' .
                    'Adherent supprimé depuis la vue Utilisateurs (id_profil=' . $idProfil . '): ' .
                    ($deletedInfo['display_name'] ?? '') . ' (' . ($deletedInfo['username'] ?? '') . ')'
            );

            $response = new JsonResponse();
            $response->success = true;
            $response->message = Text::sprintf(
                'COM_GDA_UTILISATEURS_DELETE_SUCCESS',
                $deletedInfo['display_name'] ?? '',
                $deletedInfo['username'] ?? ''
            );
        } catch (\Throwable $e) {
            $response = new JsonResponse();
            $response->success = false;
            $response->message = 'Erreur: ' . $e->getMessage();
            GdaLogger::error(
                '[' . $this->getActingUserName() . '] ' .
                    'Erreur lors de la suppression de l\'adhérent (id_profil=' . $idProfil . '): ' . $e->getMessage()
            );
        }

        echo $response;
        $app->close();
    }

    /**
     * Ajax : réinitialise le mot de passe d'un compte (mot de passe temporaire, changement forcé
     * à la prochaine connexion) et envoie le nouveau mot de passe par email au membre.
     */
    public function resetPassword(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        $idUser = 0;

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $idUser = $app->input->getInt('id_user', 0);

            if ($idUser <= 0) {
                throw new \InvalidArgumentException('Identifiant utilisateur invalide.');
            }

            $currentUser = $app->getIdentity();
            if ($currentUser && (int) $currentUser->id === $idUser) {
                throw new \RuntimeException(Text::_('COM_GDA_UTILISATEURS_RESET_PASSWORD_SELF_FORBIDDEN'));
            }

            /** @var \NCB\Component\Gda\Site\Model\UtilisateursModel $model */
            $model = $this->getModel('utilisateurs', 'site');
            $resetInfo = $model->resetUserPassword($idUser);

            $response = new JsonResponse();
            $response->success = true;
            $response->message = Text::sprintf('COM_GDA_UTILISATEURS_RESET_PASSWORD_SUCCESS', $resetInfo['display_name']);
        } catch (\Throwable $e) {
            $response = new JsonResponse();
            $response->success = false;
            $response->message = 'Erreur: ' . $e->getMessage();
            GdaLogger::error(
                '[' . ($app->getUserState('session')['name'] ?? 'unknown') . '] ' .
                    'Erreur lors de la réinitialisation du mot de passe (id_user=' . $idUser . '): ' . $e->getMessage()
            );
        }

        echo $response;
        $app->close();
    }
}
