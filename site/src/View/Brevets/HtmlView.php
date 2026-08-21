<?php

namespace NCB\Component\Gda\Site\View\Brevets;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\BrevetsModel;

/**
 * Vue « Brevets » (Bureau) : référentiel FFESSM et rattachement des brevets adhérents.
 */
class HtmlView extends BaseHtmlView
{
    /** @var object[] Référentiel FFESSM (onglet 1), trié par activité / rôle / poids */
    public array $mappings = [];

    /**
     * @var array<string, object[]> Le même référentiel groupé par activité (chaque groupe trié
     *      par libellé), pour l'éditeur de rattachement de l'onglet 2, rendu en <optgroup>.
     *
     *      La liste y compte ~80 entrées : l'ordre métier (activité, rôle, poids) rendrait la
     *      recherche d'un libellé précis pénible. Elle reste en revanche TOUJOURS complète, sans
     *      lien avec le filtre Activité du tableau — un brevet non rattaché n'a justement aucune
     *      activité, filtrer la liste ferait disparaître les seules entrées utiles pour le
     *      corriger.
     */
    public array $mappingsParActivite = [];

    /** @var object[] Brevets de tous les adhérents (onglet 2) */
    public array $brevets = [];

    /** @var string[] Activités distinctes du référentiel, pour les filtres et le formulaire d'ajout */
    public array $activites = [];

    /** @var int Nombre de brevets adhérents non rattachés au référentiel */
    public int $nbNonRattaches = 0;

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

        /** @var BrevetsModel $model */
        $model = $this->getModel();

        $this->mappings  = $model->getMappings();
        $this->brevets   = $model->getBrevetsAvecMapping();
        $this->activites = $model->getActivites();

        // Regroupement par activité, obtenu sans requête supplémentaire : le référentiel est déjà
        // en mémoire et ne fait qu'une centaine de lignes. Les groupes sortent déjà dans l'ordre
        // alphabétique des activités (ORDER BY activite ASC de getMappings()), il ne reste qu'à
        // trier les libellés à l'intérieur de chaque groupe.
        //
        // Comparaison sur la forme sans accents et en majuscules plutôt que via strcoll(), dont
        // le résultat dépend de la locale LC_COLLATE du serveur — « Éducateur » se retrouverait
        // sinon rejeté en fin de liste.
        $this->mappingsParActivite = [];

        foreach ($this->mappings as $mapping) {
            $this->mappingsParActivite[$mapping->activite][] = $mapping;
        }

        foreach ($this->mappingsParActivite as &$groupe) {
            usort(
                $groupe,
                static fn(object $a, object $b) => strcmp(
                    ToolsHelper::removeAccentsAndUppercase($a->label_ffessm),
                    ToolsHelper::removeAccentsAndUppercase($b->label_ffessm)
                )
            );
        }

        unset($groupe);

        // Compteur dérivé de la collection déjà chargée : inutile de payer une requête COUNT
        // supplémentaire pour une information déjà présente en mémoire.
        $this->nbNonRattaches = \count(array_filter(
            $this->brevets,
            static fn(object $brevet) => $brevet->id_mapping === null
        ));

        parent::display($tpl);
    }
}
