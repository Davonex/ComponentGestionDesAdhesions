<?php

/**
 * Layout : Affichage du suivi CACI
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
$badgeClass = AdhesionStatusHelper::getStatusBadgeClass($statusEnum);

// Déterminer si pas d'action requise
$isCompleted = $statusEnum === AdhesionStatusHelper::STATUS_COMPLETED;
?>



<div class="col-12 col-md-8 col-lg-6">
  <div class="card bg-gda-white">
    <div class="card-header">
      <span class="me-2">⚠️</span> Suivi Adhésion
      <span class="badge <?= $badgeClass; ?> float-end">
        <?= $statusLabel; ?>
      </span>
    </div>

    <div class="card-body">




      <!-- SECTION 2 : Détails CACI -->
      <?php if ($souscription !== null) { ?>
        <div class="mb-4 p-3 bg-light rounded">
          <h6 class="mb-3">Certificat Médical (CACI)</h6>

          <?php if ($souscription->caci_check && !empty($souscription->date_caci)) { ?>
            <?php
            $daysBeforeExpiry = AdhesionStatusHelper::getDaysBeforeExpiry($souscription->date_caci);
            $isExpired = $daysBeforeExpiry < 0;
            $isExpiringSoon = $daysBeforeExpiry >= 0 && $daysBeforeExpiry < 90; // < 3 mois

            $badgeCaci = $isExpired ? 'bg-danger' : ($isExpiringSoon ? 'bg-warning' : 'bg-success');
            $iconCaci = $isExpired ? 'fa-times-circle' : ($isExpiringSoon ? 'fa-exclamation-triangle' : 'fa-check-circle');
            ?>
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <p class="mb-1">
                  <strong>Valide jusqu'au :</strong>
                  <span class="text-primary"><?= ToolsHelper::from_sqldate($souscription->date_caci); ?></span>
                </p>
                <p class="mb-1 small text-muted">
                  <?php if ($isExpired) { ?>
                    <i class="fa <?= $iconCaci; ?> text-danger me-1"></i> CACI EXPIRÉ
                  <?php } elseif ($isExpiringSoon) { ?>
                    <i class="fa <?= $iconCaci; ?> text-warning me-1"></i>
                    Expire dans <?= $daysBeforeExpiry; ?> jours
                  <?php } else { ?>
                    <i class="fa <?= $iconCaci; ?> text-success me-1"></i> CACI VALIDE
                  <?php } ?>
                </p>
              </div>
              <?php if ($isExpired || $isExpiringSoon) { ?>
                <a href="<?= Route::_('index.php?option=com_gdadhesions&view=profil&Itemid=' . $itemid); ?>" class="btn btn-sm btn-warning">
                  <i class="fa fa-pencil me-1"></i> Mettre à jour
                </a>
              <?php } ?>
            </div>
          <?php } else { ?>
            <p class="text-danger mb-0">
              <i class="fa fa-exclamation-circle me-1"></i>
              <strong>CACI manquant ou non validé</strong>
            </p>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div class="alert alert-info mb-4">
          <i class="fa fa-info-circle me-2"></i>
          Complétez votre adhésion pour accéder à tous les services.
        </div>
      <?php } ?>

    </div>
  </div>
</div>