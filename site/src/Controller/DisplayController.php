<?php

namespace NCB\Component\Gda\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
// use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use NCB\Component\Gda\Site\Helper\ConfHelper;
// use NCB\Component\Gda\Site\Helper\CryptoHelper;
// use Joomla\CMS\MVC\View\HtmlView;
use NCB\Component\Gda\Site\Helper\GdaLogger;


class DisplayController extends BaseController
{

    protected $default_view = 'gdadhesion';
    protected $Registred = 2;
    protected $Bureau = 7;
    protected $Responsable = 8;


    /**
     * Override the display method to handle view rendering and user session management.
     * 
     */
    public function display($cachable = false, $urlparams = array())
    {



        // activation du logger
        GdaLogger::init();

        /** @var SiteApplication $app */
        $app = $this->app;
        $document   = $app->getDocument();

        $viewType   = $document->getType();


        // $plainClientSecret = "";
        // $encrypted = \NCB\Component\Gda\Site\Helper\CryptoHelper::encrypt($plainClientSecret);
        // $service = new \NCB\Component\Gda\Site\Service\HelloAssoService();
        // $service->getAccessToken();
        // // $forms = $service->getForms('asso-didou');
        // $Asso='asso-didou';
        // $FormType='event';
        // $FormName ='test-de-l-api';

        // $data = $service->getFormsOrders($Asso, $FormType, $FormName);



        // $viewName   = $this->input->get('view', $this->default_view); // Deprecated
        $viewName   = $this->app->getInput()->get('view', $this->default_view);

        $user =  $this->app->getIdentity();
        $levels = $user->getAuthorisedViewLevels();

        /* stoke les infos de l'utilsateur Connecter */
        $session = $this->input->getArray(array('Itemid' => 'INT'));
        $session['username'] = $user->username;
        $session['name'] = $user->name;
        $session['id'] = $user->id;
        $session['email'] = $user->email;

        $app->setUserState('session', $session);

        /**
         *  Gda.js est un fichier javascript pour toutes les vues
         */
        $wa = $document->getWebAssetManager();
        $wa->useScript('com_gdadhesions.gda');

        // $viewLayout = $this->input->get('layout', 'default', 'string');
        /** @var HtmlView $view */
        $view = $this->getView($viewName, $viewType);
        $model = $this->getModel($viewName);

        // $view->setLayout($viewLayout);
        if ($model) {
            $view->setModel($model, true);
        }

        // $campagneModel = $this->getModel('Campagnes');
        // /** @var CampagnesModel $campagneModel */
        // $saison = $campagneModel->SaisonOuverte();
        // $saisonState = (is_array($saison) && isset($saison[0])) ? $saison[0] : new \stdClass();
        // $app->setUserState('saison', $saisonState);

        // charge la saison ouverte
        $saisonService = ConfHelper::getSaisonService();

        // Page adhesion
        if ($viewName === 'adhesion') {
            if (!$saisonService->getSaisonOuverte()) {
                // il n'y a pas de saison ouverte
                $app->enqueueMessage(Text::_('COM_GDA_CAMPAGNE_ERR01_OPEN'));
                $IdArticleAdhesionClos = ConfHelper::getValue('IdArticleAdhesionClos');
                $this->setRedirect(Route::_('index.php?option=com_content&view=article&id=' . $IdArticleAdhesionClos, false));
            } else {
                // verifier si il y a un key dans l'url
                // enregistrer la key dans la session
                $key = $this->app->getInput()->getString('key', null);
                $adhesionModel = $this->getModel('Adhesion');

                if ($key !== null && $key !== '') {
                    if ($adhesionModel && method_exists($adhesionModel, 'isAdhesionKeyValid') && $adhesionModel->isAdhesionKeyValid($key)) {
                        $app->setUserState('adhesion.key', $key);
                    } else {
                        $app->setUserState('adhesion.key', null);
                        // Retire la clé de l'URL après traitement pour éviter de l'exposer.
                        $cleanUri = Uri::getInstance();
                        $cleanUri->delVar('key');
                        $this->setRedirect(Route::_($cleanUri->toString(), false));
                        return;
                    }
                } else {
                    // Sans key dans l'URL, on force un nouveau parcours d'adhésion.
                    $app->setUserState('adhesion.key', null);
                }

                $view->display();
            }
            // page autres
        } else if ($viewName === 'trombinoscope' || in_array($this->Registred, $levels)) {
            // Le trombinoscope du Bureau est une vue publique, en lecture seule.
            $view->display();
        } else {
            // Redirection explicite vers l'Itemid de la page d'accueil : sans Itemid précisé,
            // le routeur Joomla retombe sur l'Itemid courant de la requête (celui de la vue
            // refusée), ce qui provoque une boucle de redirection infinie.
            $default = $app->getMenu()->getDefault();
            $redirectUrl = 'index.php' . ($default ? '?Itemid=' . $default->id : '');
            $this->setRedirect(Route::_($redirectUrl, false));
        }
    }
}
