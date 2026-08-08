<?php

/**
 * Layout : Affichage du statut de souscription et suivi CACI
 * 
 * @var array $displayData
 * - $displayData['souscription'] : objet souscription ou null
 * - $displayData['statusEnum']   : code statut (NOT_SUBSCRIBED, etc.)
 * - $displayData['user']         : objet user Joomla
 * - $displayData['itemid']       : Itemid du menu
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;

$souscription = $displayData['souscription'] ?? null;
$statusEnum = $displayData['statusEnum'] ?? AdhesionStatusHelper::STATUS_NOT_SUBSCRIBED;
$user = $displayData['user'] ?? null;
$itemid = $displayData['itemid'] ?? 0;

$action = AdhesionStatusHelper::buildActionLink($statusEnum, $souscription);
$statusLabel = AdhesionStatusHelper::getStatusLabel($statusEnum);
$badgeClass = 'bg-' . AdhesionStatusHelper::getStatusBadgeClass($statusEnum);

// Déterminer si pas d'action requise
$isCompleted = $statusEnum === AdhesionStatusHelper::STATUS_COMPLETED;
?>
<div class="col-12 col-md-8 col-lg-6">
  <div class="card bg-gda-white">


    <div class="card-header">
      <span class="me-2"></span> Suivi Adhésion
      <span class="badge <?= $badgeClass; ?> float-end">
        <?= $statusLabel; ?>
      </span>
    </div>

    <div class="card-body">

      <!-- SECTION 1 : Timeline des étapes -->
      <div class="mb-4">
        <h6 class="mb-3 text-decoration-underline">Progression adhésion :</h6>
        <div class="progress-steps">
          <?php
          // Définir les étapes et leur statut
          $steps = [
            ['label' => Text::_('COM_GDA_STEP_SUBSCRIPTION'), 'icon' => 'fa-check-circle', 'done' => $souscription !== null],
            ['label' => Text::_('COM_GDA_STEP_CACI'), 'icon' => 'fa-file-medical', 'done' => $souscription !== null && $souscription->caci_check],
            ['label' => Text::_('COM_GDA_STEP_PAYMENT'), 'icon' => 'fa-credit-card', 'done' => $souscription !== null && $souscription->cotisation_check],
            ['label' => Text::_('COM_GDA_STEP_LICENCE'), 'icon' => 'fa-id-card', 'done' => $souscription !== null && $souscription->licence_check],
          ];

          // Test si l'utilisateur est bloqué ou pas!
        $isActive = !UsersHelper::isBlocked($user->username);
        
          foreach ($steps as $index => $step) {
          //   $isActive = false;
            // Déterminer quelle étape est bloquante
            // if ($statusEnum === AdhesionStatusHelper::STATUS_NOT_SUBSCRIBED && $index === 0) {
            //   $isActive = true;
            // } elseif ($statusEnum === AdhesionStatusHelper::STATUS_CACI_REQUIRED && $index === 1) {
            //   $isActive = true;
            // } elseif ($statusEnum === AdhesionStatusHelper::STATUS_PAYMENT_REQUIRED && $index === 2) {
            //   $isActive = true;
            // } elseif ($statusEnum === AdhesionStatusHelper::STATUS_LICENCE_REQUIRED && $index === 3) {
            //   $isActive = true;
            // }

            $classStep = $step['done'] ? 'text-success' : ($isActive ? 'text-danger' : 'text-muted');
            $classIcon = $step['done'] ? 'fa-check-circle' : ($isActive ? 'fa-exclamation-circle' : 'fa-circle');
          ?>
            <div class="step-item d-inline-flex align-items-center me-3 mb-2">
              <i class="fa <?= $classIcon; ?> <?= $classStep; ?> me-1"></i>
              <small class="<?= $classStep; ?>"><?= $step['label']; ?></small>
              <?php if ($index < count($steps) - 1) { ?>
                <span class="text-muted mx-2">→</span>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      </div>

     
      <!-- SECTION 2 : Commentaire ou des precicions -->

      <?php $description = AdhesionStatusHelper::getStatusDescription($statusEnum, $souscription); ?>
      <div class="mb-4">
        <!-- <h6 class="mb-3 text-decoration-underline">Description :</h6> -->
        <div class="alert alert-<?= $description['type']; ?> d-flex align-items-center mb-0">
          <i class="fa <?= $description['icon']; ?> me-2"></i>
          <span><?= $description['message']; ?></span>
        </div>
      </div>


      <!-- SECTION 3 : Action pour l'inscription -->
      <?php if ($action !== null) { ?>
        <div class="d-grid gap-2">
          <?php
          $btnClasses = 'btn btn-' . ($action['color'] ?? 'primary');
          $icon = $action['icon'] ?? 'fa-arrow-right';
          $label = Text::_($action['label']);
          ?>
          <?php if (($action['type'] ?? '') === 'ajax_modal') : ?>
            <button type="button"
              class="<?= $btnClasses ?> btn-lg js-show-payement"
              data-item-id="<?= (int) ($action['id_profil'] ?? 0) ?>"
              data-item-campagne="<?= (int) ($action['id_campagne'] ?? 0) ?>"
              data-item-order="<?= htmlspecialchars((string) ($action['id_order'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
              data-item-username="<?= htmlspecialchars((string) ($action['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
              data-item-cotisation="<?= (int) ($action['cotisation'] ?? 0) ?>">
              <i class="fa <?= $icon ?> me-2"></i> <?= $label ?>
            </button>
          <?php elseif (($action['type'] ?? '') === 'external_link') : ?>
            <a href="<?= $this->escape((string) $action['url']) ?>" class="<?= $btnClasses ?> btn-lg" target="_blank" rel="noopener noreferrer">
              <?= $label ?>
              <img src="https://api.helloasso.com/v5/img/logo-ha.svg" alt="HelloAsso" width="20" height="20" class="me-2"> 
            </a>
          <?php else : ?>
            <a href="<?= Route::_($action['url'], false) ?>" class="<?= $btnClasses ?> btn-lg">
              <i class="fa <?= $icon ?> me-2"></i> <?= $label ?>
            </a>
          <?php endif; ?>
        </div>
      <?php }  ?>

      <!-- Modal paiement HelloAsso (contenu injecté via AJAX) -->
      <div class="modal fade" id="payementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
          <div class="modal-content" id="payementModalcontent">
            <!-- contenu chargé dynamiquement -->
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<style>
  .progress-steps {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
  }

  .step-item {
    white-space: nowrap;
  }

  @media (max-width: 576px) {
    .step-item {
      font-size: 0.875rem;
    }

    .step-item .fa {
      font-size: 1rem;
    }
  }
</style>