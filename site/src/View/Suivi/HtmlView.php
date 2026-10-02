<?php

namespace NCB\Component\Gda\Site\View\Suivi;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Uri\Uri;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\SuiviModel;

/**
 * Vue Suivi : évaluation des compétences des élèves en formation, un onglet par groupe évalué.
 * Le tableau de chaque onglet est chargé en ajax (SuiviController::tableau()).
 */
class HtmlView extends BaseHtmlView
{
    /** @var object|null Saison courante. */
    public ?object $saison = null;

    /** @var array<int, object> Groupes évalués {id_groupe, groupe_name, icon}. */
    public array $groupes = [];

    /** @var bool Vrai si l'utilisateur peut administrer les compétences (onglet réservé aux Responsables de Groupe). */
    public bool $peutGererCompetences = false;

    /**
     * Affiche la vue.
     *
     * @param string|null $tpl Nom du template.
     * @return void
     */
    public function display($tpl = null): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        // Défense en profondeur : le niveau d'accès du menu ne protège que la navigation via ce
        // menu, pas un accès direct à l'URL du composant. Même garde que la vue Groupes.
        if (!UsersHelper::canViewMemberDetails()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
            $app->redirect(Uri::root());
            return;
        }

        $this->saison = ConfHelper::getSaisonService()->getSaisonCourante();

        /** @var SuiviModel $model */
        $model = $this->getModel();
        $this->groupes = $this->saison ? $model->getGroupesEvalues() : [];
        $this->peutGererCompetences = UsersHelper::isResponsableGroupe();

        parent::display($tpl);
    }
}
