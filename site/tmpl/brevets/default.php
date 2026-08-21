<?php

/**
 * Template de la vue « Brevets » (Bureau) : ossature des deux onglets.
 *
 * Onglet 1 — Référentiel FFESSM (#__gda_mapping_brevets) : administration des correspondances.
 * Onglet 2 — Brevets des adhérents (#__gda_brevets) : rattachement des libellés non reconnus.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\Helpers\Bootstrap;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

Bootstrap::tab();

/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();

$wa->useStyle('com_gdadhesions.gda');
$wa->useStyle('com_gdadhesions.brevets_mgt');
$wa->useScript('core');
$wa->useScript('simple-datatables');
$wa->useStyle('simple-datatables');

// form_modal fournit simpleCallAjax(), le transport ajax commun à toutes les vues du composant ;
// dialog fournit GdaDialog, pour que la confirmation de suppression ait le même style que les
// autres boîtes de dialogue du composant.
$wa->useScript('com_gdadhesions.form_modal');
$wa->useScript('com_gdadhesions.dialog');
$wa->useScript('com_gdadhesions.brevets_mgt');

Text::script('COM_GDA_BREVETS_MAPPING_DELETE_TITLE');
Text::script('COM_GDA_BREVETS_MAPPING_DELETE_CONFIRM');
Text::script('COM_GDA_BREVETS_MAPPING_DELETE_IMPACT');
Text::script('COM_GDA_BREVETS_MAPPING_DELETE_NO_IMPACT');
// Libellés des boutons de GdaDialog (dialog.js).
Text::script('COM_GDA_CANCEL');
Text::script('COM_GDA_CONFIRM');

/** @var object[] $mappings */
$mappings = $this->mappings;
/** @var object[] $brevets */
$brevets = $this->brevets;
/** @var string[] $activites */
$activites = $this->activites;
?>

<div class="gda-brevets card shadow-lg p-4">
    <!-- <h3 class="mb-3"><?= Text::_('COM_GDA_VIEW_BREVETS_MENU_LABEL') ?></h3> -->

    <ul class="nav nav-tabs" id="brevetsTabNav" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="brevets-tab-mapping" data-bs-toggle="tab"
                data-bs-target="#brevets-pane-mapping" type="button" role="tab"
                aria-controls="brevets-pane-mapping" aria-selected="true">
                <?= Text::_('COM_GDA_BREVETS_TAB_MAPPING') ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="brevets-tab-adherents" data-bs-toggle="tab"
                data-bs-target="#brevets-pane-adherents" type="button" role="tab"
                aria-controls="brevets-pane-adherents" aria-selected="false">
                <?= Text::_('COM_GDA_BREVETS_TAB_ADHERENTS') ?>
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3" id="brevetsTabContent">
        <div class="tab-pane fade show active" id="brevets-pane-mapping" role="tabpanel"
            aria-labelledby="brevets-tab-mapping">
            <?= LayoutHelper::render('brevets.mapping_table', [
                'mappings'  => $mappings,
                'activites' => $activites,
            ]) ?>
        </div>
        <div class="tab-pane fade" id="brevets-pane-adherents" role="tabpanel"
            aria-labelledby="brevets-tab-adherents">
            <?= LayoutHelper::render('brevets.adherents_table', [
                'brevets'        => $brevets,
                'mappings'       => $this->mappingsParActivite,
                'activites'      => $activites,
                'nbNonRattaches' => $this->nbNonRattaches,
            ]) ?>
        </div>
    </div>
</div>
