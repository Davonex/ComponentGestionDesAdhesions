<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\HTML\Helpers\Bootstrap;

Bootstrap::carousel();

/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();

$wa->useStyle('com_gdadhesions.gda');
$wa->useStyle('com_gdadhesions.trombinoscope');
$wa->useScript('com_gdadhesions.trombinoscope');

/** @var array $membresBureau */
$membresBureau = $this->membresBureau;
/** @var array $encadrantsPlongee */
$encadrantsPlongee = $this->encadrantsPlongee;
/** @var array $encadrantsApnee */
$encadrantsApnee = $this->encadrantsApnee;
?>

<div id="trombinoscopeCarousel" class="carousel slide gda-trombinoscope card shadow-lg p-4">

    <!-- Barre de navigation des types de trombinoscope -->
    <nav class="nav nav-fill mb-4 wizard-nav" id="trombinoscopeNav">
        <button type="button" class="nav-link active" data-bs-target="#trombinoscopeCarousel" data-bs-slide-to="0">
            <i class="fa-solid fa-user-tie me-1" aria-hidden="true"></i>
            <?= Text::_('COM_GDA_TROMBINOSCOPE_TAB_BUREAU') ?>
        </button>
        <button type="button" class="nav-link" data-bs-target="#trombinoscopeCarousel" data-bs-slide-to="1">
            <i class="fa-solid fa-chalkboard-user me-1" aria-hidden="true"></i>
            <?= Text::_('COM_GDA_TROMBINOSCOPE_TAB_ENCADRANTS_PLONGEE') ?>
        </button>
        <button type="button" class="nav-link" data-bs-target="#trombinoscopeCarousel" data-bs-slide-to="2">
            <i class="fa-solid fa-person-swimming me-1" aria-hidden="true"></i>
            <?= Text::_('COM_GDA_TROMBINOSCOPE_TAB_ENCADRANTS_APNEE') ?>
        </button>
    </nav>

    <div class="carousel-inner">

        <div class="carousel-item active" id="trombinoscope-bureau">
            <?= LayoutHelper::render('trombinoscope.bureau', ['membres' => $membresBureau]) ?>
        </div>

        <div class="carousel-item gda-trombi-pane--manta" id="trombinoscope-encadrants-plongee">
            <?= LayoutHelper::render('trombinoscope.encadrants_plongee', ['membres' => $encadrantsPlongee]) ?>
        </div>

        <div class="carousel-item" id="trombinoscope-encadrants-apnee">
            <?= LayoutHelper::render('trombinoscope.encadrants_apnee', ['membres' => $encadrantsApnee]) ?>
        </div>

    </div>

</div>
