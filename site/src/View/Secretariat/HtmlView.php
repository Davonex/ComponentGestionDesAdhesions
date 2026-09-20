<?php

namespace NCB\Component\Gda\Site\View\Secretariat;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\SecretariatModel;

class HtmlView extends BaseHtmlView
{
    // public $item;

    public function display($tpl=null): void
    {
        /** @var \Joomla\CMS\Application\CMSApplication $app */
        $app = Factory::getApplication();

        // Défense en profondeur : le niveau d'accès du menu ne protège que la navigation
        // via ce menu, pas un accès direct à l'URL du composant.
        if (!UsersHelper::isBureauMember()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
            $app->redirect(Route::_('index.php', false));
            return;
        }

        $this->user = $app->getIdentity();

        // Saison de suivi courante (indépendante de l'ouverture des inscriptions) :
        // le secrétariat doit pouvoir traiter les adhésions en cours même une fois les inscriptions fermées.
        $this->saison = ConfHelper::getSaisonService()->getSaisonCourante();

        if ($this->saison === null) {
            $app->enqueueMessage(Text::_('COM_GDA_SECRETARIAT_AUCUNE_SAISON_COURANTE'), 'warning');
            $this->items = [];
        } else {
            /** @var SecretariatModel $model */
            $model = $this->getModel();
            $this->items = $model->getSouscriptionsAValider(
                (int) $this->saison->id_campagne,
                ['cotisation_check' => false, 'caci_check' => false, 'licence_check' => false]
            );
        }

        parent::display($tpl);
    }
}