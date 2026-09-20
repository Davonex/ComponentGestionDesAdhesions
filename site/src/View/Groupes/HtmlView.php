<?php

namespace NCB\Component\Gda\Site\View\Groupes;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\GroupesModel;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        /** @var \Joomla\CMS\Application\CMSApplication $app */
        $app = Factory::getApplication();

        // Défense en profondeur : le niveau d'accès du menu ne protège que la navigation
        // via ce menu, pas un accès direct à l'URL du composant. Même garde que la popup
        // « fiche adhérent » ouverte depuis cette vue (ProfilController::showCard()).
        if (!UsersHelper::canViewMemberDetails()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
            $app->redirect(Route::_('index.php', false));
            return;
        }

        $this->saison = ConfHelper::getSaisonService()->getSaisonCourante();

        /** @var GroupesModel $model */
        $model = $this->getModel();
        $this->groupes = $this->saison
            ? $model->getGroupesAvecAdherents((int) $this->saison->id_campagne)
            : [];

        parent::display($tpl);
    }
}
