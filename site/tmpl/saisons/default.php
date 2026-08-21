<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\HTML\Helpers\Bootstrap;

Bootstrap::tab();
Bootstrap::modal();

/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();

$wa->useStyle('com_gdadhesions.gda');
$wa->useStyle('com_gdadhesions.saisons');
$wa->useScript('core');
$wa->useScript('com_gdadhesions.form_modal');
$wa->useScript('com_gdadhesions.saisons');
$wa->useScript('form.validate');

Text::script('COM_GDA_SAISONS_COURANTE_CONFIRM_DECLARER');
Text::script('COM_GDA_SAISONS_COURANTE_CONFIRM_RETIRER');

?>

<div class="gda-saisons card shadow-lg p-4">

    <ul class="nav nav-tabs" id="saisonsTabNav" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="saisons-tab-courante" data-bs-toggle="tab"
                data-bs-target="#saisons-pane-courante" type="button" role="tab"
                aria-controls="saisons-pane-courante" aria-selected="true">
                <?= Text::_('COM_GDA_SAISONS_TAB_COURANTE') ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="saisons-tab-historique" data-bs-toggle="tab"
                data-bs-target="#saisons-pane-historique" type="button" role="tab"
                aria-controls="saisons-pane-historique" aria-selected="false">
                <?= Text::_('COM_GDA_SAISONS_TAB_HISTORIQUE') ?>
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3" id="saisonsTabContent">
        <div class="tab-pane fade show active" id="saisons-pane-courante" role="tabpanel"
            aria-labelledby="saisons-tab-courante">
            <?= LayoutHelper::render('saisons.courante', [
                'saison'    => $this->saisonCourante,
                'form'      => $this->formCourante,
                'groupes'   => $this->groupes,
                'activites' => $this->activites,
            ]) ?>
        </div>
        <div class="tab-pane fade" id="saisons-pane-historique" role="tabpanel"
            aria-labelledby="saisons-tab-historique">
            <?= LayoutHelper::render('saisons.liste', [
                'saisons'   => $this->listeSaisons,
                'formAjout' => $this->formAjout,
            ]) ?>
        </div>
    </div>

</div>
