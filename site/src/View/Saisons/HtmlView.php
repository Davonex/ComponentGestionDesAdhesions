<?php

namespace NCB\Component\Gda\Site\View\Saisons;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\SaisonsModel;
use NCB\Component\Gda\Site\Service\GroupesService;

class HtmlView extends BaseHtmlView
{
    public ?object $saisonCourante = null;
    public array $listeSaisons = [];
    public array $groupes = [];
    public array $activites = [];
    public ?\Joomla\CMS\Form\Form $formCourante = null;
    public ?\Joomla\CMS\Form\Form $formAjout = null;

    public function display($tpl = null): void
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

        /** @var SaisonsModel $model */
        $model = $this->getModel();
        $groupesService = new GroupesService(Factory::getContainer()->get(DatabaseInterface::class));

        $this->saisonCourante = $model->getSaisonCourante();
        $this->listeSaisons   = $model->getListeSaisons();
        $this->groupes        = $groupesService->getAllGroupes();
        $this->activites      = $groupesService->getActivitesDisponibles();
        $this->formCourante   = $model->getFormCourante($this->buildDataCourante($this->saisonCourante));
        $this->formAjout      = $model->getFormAjout();

        parent::display($tpl);
    }

    /**
     * Convertit la saison courante (objet issu de la base) en tableau adapté au bind() du
     * formulaire. Les dates sont transmises telles que stockées en base (format SQL Y-m-d) :
     * c'est le champ calendar lui-même (filterformat="d/m/Y") qui se charge de les reformater
     * pour l'affichage — les lui fournir déjà en d/m/Y le fait planter (il retente un parsing
     * ISO/USER_UTC en interne, `Factory::getDate('31/12/2026')` n'est pas une date valide).
     */
    private function buildDataCourante(?object $saison): array
    {
        if ($saison === null) {
            return [];
        }

        return [
            'id_campagne'     => $saison->id_campagne,
            'titre'           => $saison->titre,
            'description'     => $saison->description,
            'date_debut'      => $saison->date_debut,
            'date_fin'        => $saison->date_fin,
            'id_article'      => $saison->id_article,
            'event_helloasso' => $saison->event_helloasso,
        ];
    }
}
