<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use NCB\Component\Gda\Site\Helper\UsersHelper;

/**
 * Contrôleur de base des tasks ajax (format=json) du composant.
 *
 * Deux rôles :
 * - rendre l'expiration de session lisible : le checkToken() natif de Joomla redirige vers la page
 *   précédente (le navigateur suit la redirection et le client reçoit du HTML au lieu de JSON), ce
 *   qui produit des erreurs incompréhensibles pour l'utilisateur ;
 * - porter la garde « membre du Bureau » commune aux contrôleurs Secretariat, Saisons, Brevets et
 *   Utilisateurs, les tasks ajax n'étant pas protégées par le niveau d'accès du menu.
 */
abstract class AjaxController extends BaseController
{
    /**
     * Refuse la requête si l'utilisateur connecté n'est pas membre du Bureau.
     *
     * Les tasks ajax ne sont pas protégées par le niveau d'accès de l'item de menu, contrairement
     * à l'affichage de la vue : chaque task réservée au Bureau doit appeler cette garde.
     *
     * @return void
     * @throws \RuntimeException 403 si l'utilisateur n'est pas membre du Bureau.
     */
    protected function guardBureauMember(): void
    {
        if (!UsersHelper::isBureauMember()) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Vérifie le jeton CSRF ; sur une requête JSON invalide (session expirée), répond directement
     * en JSON avec un HTTP 403 et un message explicite, au lieu de rediriger.
     *
     * @param string $method   Méthode de la requête portant le jeton ('post' par défaut).
     * @param bool   $redirect Comportement natif (redirection) pour les requêtes non JSON.
     * @return bool Toujours true pour une requête JSON (sinon la réponse est envoyée et l'application arrêtée).
     */
    public function checkToken($method = 'post', $redirect = true)
    {


        if ($this->input->get('format', '', 'cmd') !== 'json') {
            return parent::checkToken($method, $redirect);
        }

        if (!Session::checkToken($method)) {
            /** @var SiteApplication $app */
            $app = $this->app;
            $app->setHeader('status', 403, true);
            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            $app->sendHeaders();
            echo new JsonResponse(null, Text::_('COM_GDA_SESSION_EXPIREE'), true);
            $app->close();
        }

        return true;
    }
}
