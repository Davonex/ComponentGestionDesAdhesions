<?php

namespace NCB\Component\Gda\Site\View\Utilisateurs;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\UtilisateursModel;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        // Défense en profondeur : le niveau d'accès du menu ne protège que la navigation
        // via ce menu, pas un accès direct à l'URL du composant.
        if (!UsersHelper::isBureauMember()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
            $app->redirect(Route::_('index.php', false));
            return;
        }

        /** @var UtilisateursModel $model */
        $model = $this->getModel();
        $this->utilisateurs = $model->getUtilisateurs();

        parent::display($tpl);
    }
}
