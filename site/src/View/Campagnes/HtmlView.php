<?php

namespace NCB\Component\Gda\Site\View\Campagnes;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;

class HtmlView extends BaseHtmlView
{
    public $state;
    public $items=[];
    public $pagination;
    public array $types = [];
    public array $roles = [];
    public array $lstFormations = [];

    public function display($tpl=null): void
    {

        /** @var CMSApplication $app */
        $app = Factory::getApplication();

        // Défense en profondeur : le niveau d'accès du menu ne protège que la navigation
        // via ce menu, pas un accès direct à l'URL du composant.
        if (!UsersHelper::isBureauMember() && !UsersHelper::isResponsableGroupe()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
            $app->redirect(Route::_('index.php', false));
            return;
        }

        /** @var AdhesionModel $model */
        $model = $this->getModel();


        $layout = $this->getLayout();


        $this->state      = $this->get('State');
        // $this->items      = $this->get('Items');  //deprecated
        $this->lstCampagnes = $model->getCampagnes();
        $this->types = $model->getTypes();
        $this->roles = $model->getRolesDeCampagne();

        // Onglet "Réservations formation" : seule nature avec un rendu de suivi réel pour l'instant
        // (les autres tombent sur le placeholder "à venir" côté contrôleur), inutile de les proposer.
        $idTypeFormation = (int) ConfHelper::getValue('IdTypeFormation');
        $this->lstFormations = array_values(array_filter(
            $this->lstCampagnes,
            fn($campagne) => (int) $campagne->id_type === $idTypeFormation
        ));

        $this->form = $model->getForm();

        $this->MenuItemId = $app->getMenu()->getActive()->id;
        parent::display($tpl);
    // }
        //$this->form = $this->getModel()->getForm();

    }
}

