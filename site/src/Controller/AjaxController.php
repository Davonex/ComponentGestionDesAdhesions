<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use NCB\Component\Gda\Site\Helper\GdaLogger;
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

            // Requête dépassant post_max_size : PHP vide tout le POST, jeton compris. Ce n'est pas
            // une session expirée (cas typique : photo/CACI trop lourds), le message doit le dire.
            $tropVolumineuse = $this->isRequeteTropVolumineuse();
            $message = Text::_($tropVolumineuse ? 'COM_GDA_REQUETE_TROP_VOLUMINEUSE' : 'COM_GDA_SESSION_EXPIREE');

            $serveur = $this->input->server;
            GdaLogger::warning(sprintf(
                'Jeton CSRF refusé (%s) : task=%s ; user_joomla=%d ; content_length=%d ; post_max_size=%s ; ip=%s ; user_agent=%s',
                $tropVolumineuse ? 'requête trop volumineuse' : 'session expirée ou jeton absent',
                $this->input->getCmd('task', ''),
                (int) $app->getIdentity()->id,
                $serveur->getInt('CONTENT_LENGTH', 0),
                (string) ini_get('post_max_size'),
                $serveur->getString('REMOTE_ADDR', ''),
                $serveur->getString('HTTP_USER_AGENT', '')
            ));

            $app->setHeader('status', 403, true);
            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            $app->sendHeaders();
            echo new JsonResponse(null, $message, true);
            $app->close();
        }

        return true;
    }

    /**
     * Indique si la requête a été vidée par PHP pour dépassement de post_max_size.
     *
     * @return bool True si le corps annoncé dépasse post_max_size et que le POST est vide.
     */
    private function isRequeteTropVolumineuse(): bool
    {
        $contentLength = $this->input->server->getInt('CONTENT_LENGTH', 0);
        $postMaxSize = ini_parse_quantity((string) ini_get('post_max_size'));

        return $postMaxSize > 0 && $contentLength > $postMaxSize && empty($_POST);
    }
}
