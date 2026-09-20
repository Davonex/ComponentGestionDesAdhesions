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
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;

$souscription = $displayData['souscription'] ?? null;
$statusEnum = $displayData['statusEnum'] ?? AdhesionStatusHelper::STATUS_NOT_SUBSCRIBED;
$user = $displayData['user'] ?? null;
$itemid = $displayData['itemid'] ?? 0;

$statusLabel = AdhesionStatusHelper::getStatusLabel($statusEnum);
$badgeClass = 'bg-' . AdhesionStatusHelper::getStatusBadgeClass($statusEnum);
?>
<div class="card bg-gda-white">

  <div class="card-header d-flex align-items-center">
    <button class="btn btn-sm p-0 me-2 toggle-card"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#suiviAdhesionCard"
      aria-expanded="true">
      <i class="fa-solid fa-chevron-right"></i>
    </button>
    <i class="fa-solid fa-id-card me-2" aria-hidden="true"></i>Suivi Adhésion
    <span class="badge <?= $badgeClass; ?> ms-auto">
      <?= $statusLabel; ?>
    </span>
  </div>

  <div class="collapse show" id="suiviAdhesionCard">
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

      <!-- SECTION 2 : Commentaires ou précisions -->

      <?php
      // Un message par étape encore en attente (CACI, Paiement, Licence), pas seulement celui de
      // l'étape actuellement bloquante : l'adhérent doit pouvoir anticiper un paiement manquant
      // avant même que sa CACI soit validée par le secrétariat.
      $descriptions = AdhesionStatusHelper::getPhaseDescriptions($souscription);
      ?>
      <div class="mb-4 d-flex flex-column gap-2">
        <?php foreach ($descriptions as $description) : ?>
          <?php $descAction = $description['action'] ?? null; ?>
          <div class="alert alert-<?= $description['type']; ?> mb-0">
            <div class="row align-items-center g-2">
              <div class="<?= $descAction !== null ? 'col-9 col-md-9' : 'col-12'; ?> d-flex align-items-center">
                <i class="fa <?= $description['icon']; ?> me-2"></i>
                <span><?= $description['message']; ?></span>
              </div>

              <?php if ($descAction !== null) : ?>
                <div class="col-3 col-md-3 text-end">
                  <?php
                  $descBtnClasses = 'btn btn-sm btn-' . ($descAction['color'] ?? 'primary');
                  $descIcon = $descAction['icon'] ?? 'fa-arrow-right';
                  $descLabel = Text::_($descAction['label']);
                  ?>
                  <?php if (($descAction['type'] ?? '') === 'ajax_modal') : ?>
                    <button type="button"
                      class="<?= $descBtnClasses ?> js-show-payement"
                      data-item-id="<?= (int) ($descAction['id_profil'] ?? 0) ?>"
                      data-item-campagne="<?= (int) ($descAction['id_campagne'] ?? 0) ?>"
                      data-item-order="<?= $this->escape((string) ($descAction['id_order'] ?? '0')) ?>">
                      <i class="fa <?= $descIcon ?> me-1"></i> <?= $descLabel ?>
                    </button>
                  <?php elseif (($descAction['type'] ?? '') === 'external_link') : ?>
                    <a href="<?= $this->escape((string) $descAction['url']) ?>" class="<?= $descBtnClasses ?>" target="_blank" rel="noopener noreferrer">
                      <?= $descLabel ?>
                      <img src="<?= FileHelper::getHelloAssoLogoSrc() ?>" alt="HelloAsso" width="16" height="16" class="ms-1">
                    </a>
                  <?php else : ?>
                    <a href="<?= Route::_($descAction['url'], false) ?>" class="<?= $descBtnClasses ?>">
                      <i class="fa <?= $descIcon ?> me-1"></i> <?= $descLabel ?>
                    </a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

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
