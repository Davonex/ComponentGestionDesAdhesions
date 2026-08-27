<?php

/**
 * Layout : badges Licence FFESSM / CACI affichés dans le WelcomeCard de la vue Accueil.
 * Même motif visuel (badge coloré = date, tooltip = statut) que layouts/groupes/detail.php et
 * layouts/utilisateurs/row_profils.php, sans aperçu image (pas de modale sur cette page).
 *
 * @var array $displayData
 * - $displayData['profil'] : object #__gda_profils + #__users (caci, date_caci, date_licence)
 * - $displayData['itemid'] : Itemid du menu, pour le lien vers la vue Profil
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

$profil = $displayData['profil'] ?? null;
$itemid = $displayData['itemid'] ?? 0;

$licenceStatusEnum = AdhesionStatusHelper::getLicenceValidityStatus($profil->date_licence ?? null);
$licenceStatusLabel = AdhesionStatusHelper::getStatusLabel($licenceStatusEnum);
$licenceStatusClass = AdhesionStatusHelper::getStatusBadgeClass($licenceStatusEnum);
$dateLicenceAffiche = ToolsHelper::from_sqldate($profil->date_licence ?? null);

$caciStatusEnum = AdhesionStatusHelper::getCaciFileStatus($profil->caci ?? null, $profil->date_caci ?? null);
$caciStatusLabel = AdhesionStatusHelper::getStatusLabel($caciStatusEnum);
$caciStatusClass = AdhesionStatusHelper::getStatusBadgeClass($caciStatusEnum);
$dateCaciAffiche = ToolsHelper::from_sqldate($profil->date_caci ?? null);

$needsUpdate = !in_array($licenceStatusEnum, [AdhesionStatusHelper::STATUS_LICENCE_VALID], true)
    || !in_array($caciStatusEnum, [AdhesionStatusHelper::STATUS_CACI_VALID], true);
?>
<div class="d-flex flex-wrap align-items-center gap-4 mt-3 gda-welcome-status">
  <div class="d-flex align-items-center gap-2">
    <span class="text-muted">
      <i class="fa-solid fa-id-card me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_LICENCE') ?>
    </span>
    <span class="badge bg-<?= $this->escape($licenceStatusClass) ?>" title="<?= $this->escape($licenceStatusLabel) ?>">
      <?= $dateLicenceAffiche !== '' ? $this->escape($dateLicenceAffiche) : '&mdash;' ?>
    </span>
  </div>

  <div class="d-flex align-items-center gap-2">
    <span class="text-muted">
      <i class="fa-solid fa-file-medical me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI') ?>
    </span>
    <span class="badge bg-<?= $this->escape($caciStatusClass) ?>" title="<?= $this->escape($caciStatusLabel) ?>">
      <?= $dateCaciAffiche !== '' ? $this->escape($dateCaciAffiche) : '&mdash;' ?>
    </span>
  </div>

  <?php if ($needsUpdate) : ?>
    <a href="<?= Route::_('index.php?option=com_gdadhesions&view=profil&Itemid=' . (int) $itemid) ?>" class="btn btn-sm btn-outline-warning ms-auto">
      <i class="fa-solid fa-pen me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_ACCUEIL_UPDATE_PROFIL_LINK') ?>
    </a>
  <?php endif; ?>
</div>
