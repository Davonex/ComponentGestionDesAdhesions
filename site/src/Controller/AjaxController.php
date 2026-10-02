<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
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
     * Si la page appelante avait été affichée à un utilisateur connecté (en-tête X-Gda-Connecte,
     * posé par form_modal.js), la réponse porte en plus l'URL de l'accueil dans data.redirect et le
     * message « reconnectez-vous » est mis en file pour s'afficher sur cette page d'accueil.
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

        if (!$this->isJetonValide($method)) {
            /** @var SiteApplication $app */
            $app = $this->app;

            // Requête dépassant post_max_size : PHP vide tout le POST, jeton compris. Ce n'est pas
            // une session expirée (cas typique : photo/CACI trop lourds), le message doit le dire.
            $tropVolumineuse = $this->isRequeteTropVolumineuse();
            $message = Text::_($tropVolumineuse ? 'COM_GDA_REQUETE_TROP_VOLUMINEUSE' : 'COM_GDA_SESSION_EXPIREE');
            $redirigerAccueil = !$tropVolumineuse
                && $this->input->server->getString('HTTP_X_GDA_CONNECTE', '') === '1';

            // Messages en file ignorés : JsonResponse viderait sinon celui destiné à l'accueil.
            $reponse = new JsonResponse($redirigerAccueil ? ['redirect' => Uri::root()] : null, $message, true, true);

            if ($redirigerAccueil) {
                // Persisté dans la (nouvelle) session, comme le fait CMSApplication::redirect(), pour
                // être affiché par la page d'accueil vers laquelle le navigateur est renvoyé.
                $app->getSession()->set('application.queue', [[
                    'message' => Text::_('COM_GDA_SESSION_EXPIREE_RECONNEXION'),
                    'type'    => 'warning',
                ]]);
            }

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
            echo $reponse;
            $app->close();
        }

        return true;
    }

    /**
     * Contrôle le jeton CSRF sans passer par Session::checkToken(), qui redirige vers index.php
     * quand la session est neuve (session expirée) : le navigateur suivait alors cette redirection
     * en GET vers index.php?option=com_gdadhesions&format=json, sans task, et le client recevait
     * une erreur d'asset du DisplayController au lieu du message de session expirée.
     *
     * Même règle que Session::checkToken() : en-tête X-CSRF-Token, sinon champ portant le jeton.
     *
     * @param string $method Méthode de la requête portant le jeton.
     * @return bool True si le jeton est valide.
     */
    private function isJetonValide(string $method): bool
    {
        $jeton = Session::getFormToken();

        if ($jeton === $this->input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum')) {
            return true;
        }

        return (bool) $this->input->$method->get($jeton, '', 'alnum');
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
