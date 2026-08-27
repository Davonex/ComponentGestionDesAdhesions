<?php

namespace NCB\Component\Gda\Site\View\Accueil;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\GenericDataException;

class HtmlView extends BaseHtmlView
{
    // public $item;

    public function display($tpl=null): void
    {
        $app = Factory::getApplication();

        /** @var AccueileModel $accueilModel */
        $accueilModel = $this->getModel();
        // $layout = $this->getLayout();
        // $model = $this->getModel();

        $this->user = $app->getIdentity();

        //$user =  $app->getIdentity();


        // Campagnes Formation et Loisir ouvertes, avec l'état de réservation de l'adhérent connecté.
        $this->formations = $accueilModel->getCampagnesReservables($this->user);

        //$this->MenuItemId = $app->getMenu()->getActive()->id;


        /** @var AdhesionModel $adhesionModel */
        $adhesionModel = new \NCB\Component\Gda\Site\Model\AdhesionModel();
        $this->profil = $adhesionModel->getProfil();


        parent::display($tpl);
    }
}

