<?php

\defined('_JEXEC');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\HTML\Helpers\Bootstrap;


Bootstrap::carousel();

Bootstrap::tooltip();

/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();

$wa->useStyle('com_gdadhesions.gda');

//datatables
$wa->useScript('simple-datatables');
$wa->useStyle('simple-datatables');

// Submit Form JS
$wa->useScript('com_gdadhesions.form_modal');

// Spinner reutilisable
$wa->useStyle('com_gdadhesions.spinner');
$wa->useScript('com_gdadhesions.spinner');

// Main Secretariat JS
$wa->useScript('com_gdadhesions.secretariat');



?>
<div id="wizardSecretariat" class="carousel slide shadow-lg p-4">

  <!-- Barre de navigation des étapes -->
  <nav class="nav nav-fill mb-4 wizard-nav" id="wizardNav">
    <button type="button" class="nav-link active" data-bs-target="#wizardSecretariat" data-bs-slide-to="0">
      <i class="fa-solid fa-user me-1" aria-hidden="true"></i>
      <span class="wizard-step-full"><?= Text::_('COM_GDA_SECRETARIAT_STEP_1') ?? 'Verification phase I' ?></span>
      <span class="wizard-step-short" aria-hidden="true">1</span>
    </button>
    <button type="button" class="nav-link" data-bs-target="#wizardSecretariat" data-bs-slide-to="1">
      <i class="fa-solid fa-id-card me-1" aria-hidden="true"></i>
      <span class="wizard-step-full"><?= Text::_('COM_GDA_SECRETARIAT_STEP_2') ?? 'Verification phase II' ?></span>
      <span class="wizard-step-short" aria-hidden="true">2</span>
    </button>
    <button type="button" class="nav-link" id="btnStepRecap" data-bs-target="#wizardSecretariat" data-bs-slide-to="2">
      <i class="fa-solid fa-check-circle me-1" aria-hidden="true"></i>
      <span class="wizard-step-full"><?= Text::_('COM_GDA_SECRETARIAT_STEP_3') ?? 'Verification phase III' ?></span>
      <span class="wizard-step-short" aria-hidden="true">3</span>
    </button>
    <button type="button" class="nav-link" data-bs-target="#wizardSecretariat" data-bs-slide-to="3">
      <i class="fa-solid fa-flag-checkered me-1" aria-hidden="true"></i>
      <span class="wizard-step-full"><?= Text::_('COM_GDA_SECRETARIAT_STEP_4') ?? 'Adhesions finalisees' ?></span>
      <span class="wizard-step-short" aria-hidden="true">4</span>
    </button>
  </nav>

  <div class="carousel-inner">

    <!-- STEP 0 Carousel  -->
    <div class="carousel-item active" id="step-0">
      <?= LayoutHelper::render(
        'secretariat.step_one',
        ['items' => $this->items]
      ); ?>
    </div>

    <!-- STEP 1 Carousel  -->
    <div class="carousel-item" id="step-1">
        toto
    </div>

    <!-- STEP 2 Carousel  -->
    <div class="carousel-item" id="step-3">
    </div>

    <!-- STEP 3 Carousel  -->
    <div class="carousel-item" id="step-4">
    </div>

  </div>

  <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-body text-center p-2">
          <img id="imagePreviewImage" src="" alt="" class="img-fluid">
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="licenceFinalizeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?= Text::_('COM_GDA_SECRETARIAT_LICENCE_MODAL_TITLE') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
        </div>
        <div class="modal-body">
          <p id="licenceFinalizeMessage" class="mb-3"></p>
          <input type="hidden" id="licenceFinalizeProfilId" value="0">
          <input type="hidden" id="licenceFinalizeCampagneId" value="0">
          <div class="mb-3">
            <label for="licenceFinalizeInput" class="form-label"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_LICENCE') ?></label>
            <input
              type="text"
              class="form-control"
              id="licenceFinalizeInput"
              name="licence"
              placeholder="A-00-000000"
              pattern="A-[0-9]{2}-[0-9]{6,7}"
              inputmode="text"
              autocomplete="off">
            <div class="form-text"><?= Text::_('COM_GDA_SECRETARIAT_LICENCE_MODAL_HELP') ?></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('JCANCEL') ?></button>
          <button type="button" class="btn btn-primary" id="licenceFinalizeSubmit"><?= Text::_('COM_GDA_SECRETARIAT_LICENCE_MODAL_SUBMIT') ?></button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="deleteAdherentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?= Text::_('COM_GDA_SECRETARIAT_DELETE_MODAL_TITLE') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
        </div>
        <div class="modal-body">
          <p id="deleteAdherentMessage" class="mb-0"></p>
          <input type="hidden" id="deleteAdherentProfilId" value="0">
          <input type="hidden" id="deleteAdherentCampagneId" value="0">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">NON</button>
          <button type="button" class="btn btn-danger" id="deleteAdherentSubmit">OUI</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="payementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content" id="payementModalcontent">
        <!-- Le contenu de la modal est charge dynamiquement via AJAX -->
      </div>
    </div>
  </div>
</div>