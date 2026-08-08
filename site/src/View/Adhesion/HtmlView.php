<?php

namespace NCB\Component\Gda\Site\View\Adhesion;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
// use Joomla\CMS\Application\CMSApplication;
// use NCB\Component\Gda\Site\Model\AdhesionModel;
use NCB\Component\Gda\Site\Helper\ConfHelper;

class HtmlView extends BaseHtmlView
{

    public function display($tpl = null): void
    {

        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        /** @var AdhesionModel $model */
        $model = $this->getModel();

        $this->form = $model->getForm();
        $this->brevets = $model->getBrevets();
        $this->profil = $model->getProfil();
        $this->composition_groupes = $model->getCompositionGroupes();


        $this->session = $app->getUserState('session');
        $this->saison = ConfHelper::getSaisonService()->getSaisonOuverte();


        // \NCB\Component\Gda\Site\Helper\ToolsHelper::debug('profil', $this->profil);

        // $model->testmail();


        // $this->MenuItemId = $app->getMenu()->getActive()->id;
        parent::display($tpl);
    }
}
