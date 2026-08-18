<?php

/**
 * Layout : carte "CACI" de la vue Profil
 *
 * @var array $displayData
 * - $displayData['profil'] : objet profil (id_profil, caci, date_caci)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;

$profil = $displayData['profil'] ?? null;

if ($profil === null) {
    return;
}

$statusEnum = AdhesionStatusHelper::getCaciFileStatus($profil->caci ?? null, $profil->date_caci ?? null);
$statusLabel = AdhesionStatusHelper::getStatusLabel($statusEnum);
$badgeClass = AdhesionStatusHelper::getStatusBadgeClass($statusEnum);
$dateCaciAffiche = !empty($profil->date_caci) ? ToolsHelper::from_sqldate($profil->date_caci) : '-';
?>

<div id="caci_<?php echo (int) $profil->id_profil; ?>" class="h-100 <?php echo $displayData['taille'] ?? 'col-md-6 col-sm-12'; ?>">
  <div class="card text-bg-gda">
    <div class="card-header">
      <p class="pt-2 float-start"><?php echo $this->escape(Text::_('COM_GDA_PROFIL_CACI_CARD_TITLE')); ?></p>
      <button
        type="button"
        class="btn btn-success float-end"
        data-bs-id_profil="caci_<?php echo (int) $profil->id_profil; ?>"
        data-bs-toggle="modal"
        data-bs-target="#caciModal"
        data-toggle="tooltip"
        data-placement="top"
        title="<?php echo $this->escape(Text::_('COM_GDA_ACTION_UPDATE_CACI')); ?>">
        <i class="fa-solid fa-file-medical"></i> <?php echo $this->escape(Text::_('COM_GDA_ACTION_UPDATE_CACI')); ?>
      </button>
    </div> <!-- class="card-header" -->
    <div class="row g-0">
      <div class="col-md-5">
        <img
          data-bs
          name="Srcphoto"
          src="<?php echo FileHelper::getImageSrc($profil->caci ?? null, 'CaciPath', 'DefaultCaci'); ?>"
          class="img-thumbnail rounded mx-auto d-block"
          alt="CACI">
      </div>
      <div class="col-md-7">
        <div class="card-body">
          <p class="mb-2">
            <strong><?php echo $this->escape(Text::_('COM_GDA_DATE_CACI_LABEL')); ?> :</strong>
            <?php echo $this->escape($dateCaciAffiche); ?>
          </p>
          <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span>

          <!-- Champs cachés utilisés par openModal() (form_modal.js) pour préremplir la modale CACI -->
          <span class="position-absolute invisible" data-bs name="id_profil"><?php echo (int) $profil->id_profil; ?></span>
          <span class="position-absolute invisible" data-bs name="date_caci"><?php echo $this->escape($dateCaciAffiche !== '-' ? $dateCaciAffiche : ''); ?></span>
          <span class="position-absolute invisible" data-bs name="caci"><?php echo $this->escape($profil->caci ?? ''); ?></span>
        </div> <!-- class="card-body" -->
      </div>
    </div> <!-- class="row g-0" -->
  </div> <!-- class="card" -->
</div>
