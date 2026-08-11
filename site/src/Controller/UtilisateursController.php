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
}
