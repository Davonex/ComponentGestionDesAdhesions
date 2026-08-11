<?php

/**
 * Layout : contenu de la modale de mise à jour du CACI (vue Profil)
 *
 * @var array $displayData
 * - $displayData['form'] : Joomla\CMS\Form\Form issu de ProfilModel::getCaciForm()
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Joomla\CMS\Form\Form $form */
$form = $displayData['form'] ?? null;

if ($form === null) {
    return;
}
?>

<!-- Zone de capture du drag and drop -->
<div class="drop-area" id="caciModalDropArea">
  <span class="icon-cloud-upload upload-icon" aria-hidden="true"></span>
  <p><?php echo Text::_('COM_GDA_DROP_CACI'); ?></p>
</div>
<div class="spinner-border js-loading text-danger visually-hidden" role="status" aria-hidden="true">
  <span class="visually-hidden">Loading...</span>
</div>
<input id="caciModalUpload" class="position-absolute invisible" type="file" accept="image/jpeg, image/png, application/pdf" />
<input type="hidden" id="caciModalFlag" value="<?php echo $form->getField('caci')->value ? '1' : '0'; ?>" />
<!--
  Input dédié à la prise de photo : les attributs accept="image/*" et capture="environment"
  doivent être présents statiquement dans le HTML pour que les navigateurs mobiles ouvrent
  directement l'appareil photo (les ajouter dynamiquement en JS n'est pas fiable partout).
-->
<input id="caciModalCameraInput" class="position-absolute invisible" type="file" accept="image/*" capture="environment" />
<!-- Fin Zone de capture du drag and drop -->

<form id="caciAdminForm" name="caciAdminForm" enctype="multipart/form-data">
  <div class="row">
    <div class="col-sm-12 col-md-6">
      <img src="" id="caciModalPreview" class="img-thumbnail rounded mx-auto d-block click-image" alt="CACI">
      <div class="d-flex gap-2 mt-2">
        <label for="caciModalUpload" class="click-image form-text"><?php echo Text::_('COM_GDA_DROP_CACI_DESC'); ?></label>
        <label for="caciModalCameraInput" class="gda-camera-trigger btn btn-outline-secondary btn-sm ms-auto mb-0">
          <i class="fa-solid fa-camera"></i> <?php echo Text::_('COM_GDA_PROFIL_CACI_TAKE_PHOTO'); ?>
        </label>
      </div>
      <?php
      $options = ["class" => "position-absolute invisible"];
      echo $form->renderField('upload.caci', null, null, $options);
      ?>
    </div>
    <div class="col-sm-12 col-md-6">
      <?php echo $form->renderField('date_caci'); ?>
    </div>
  </div>

  <?php echo $form->renderField('id_profil'); ?>
  <?php echo $form->renderField('caci'); ?>
  <input type="hidden" name="task" value="profil.saveCaci" />
  <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
</form>
