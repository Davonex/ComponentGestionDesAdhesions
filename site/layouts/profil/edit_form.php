<?php

/**
 * Layout : contenu du formulaire d'édition du profil (champs + zone de dépôt de la photo).
 * Réutilisé par :
 * - la vue Profil (`tmpl/profil/default.php`, modal #myModal statique)
 * - le popup d'édition de la vue Utilisateurs (`ProfilController::showEditForm()`, injecté en ajax
 *   dans #profilCardModal, réservé au Bureau)
 *
 * Les ids DOM (drop-area, image-upload, photoFlag, adminForm) sont fixes : les deux contextes
 * d'utilisation ne coexistent jamais sur la même page.
 *
 * @var array $displayData
 * - $displayData['form']      : Joomla\CMS\Form\Form - formulaire jform_Profil déjà prérempli
 * - $displayData['photoFlag'] : bool - une photo existe déjà pour ce profil
 * - $displayData['photoSrc']  : string - URL initiale de #image-preview. Laissé vide dans le contexte
 *   de la modale statique #myModal (tmpl/profil/default.php) : c'est openModal() (form_modal.js) qui
 *   la renseigne dynamiquement à l'ouverture, en copiant l'<img data-bs> de la carte cliquée.
 * - $displayData['itemid']    : int - Itemid utilisé pour construire l'URL d'action du formulaire
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\CMS\Form\Form $form */
$form = $displayData['form'];
$photoFlag = $displayData['photoFlag'] ?? false;
$photoSrc = $displayData['photoSrc'] ?? '';
$itemid = (int) ($displayData['itemid'] ?? 0);
?>
<!-- Zone de capture du drag and drop -->
<div class="drop-area" id="drop-area">
  <span class="icon-cloud-upload upload-icon" aria-hidden="true"></span>
  <p><?php echo Text::_('COM_GDA_DROP_PHOTO'); ?></p>
</div>
<div class="spinner-border js-loading text-danger visually-hidden" role="status" aria-hidden="true">
  <span class="visually-hidden">Loading...</span>
</div>
<input id="image-upload" class="position-absolute invisible" type="file" accept="image/jpeg, image/png" />
<input type="hidden" id="photoFlag" value="<?php echo $photoFlag ? '1' : '0'; ?>" />
<form action="<?php echo Route::_('index.php?option=com_gdadhesions&Itemid=' . $itemid); ?>"
  method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

  <fieldset class="profil-edit">
    <!-- Row : M. Nom Prénom -->
    <div class="row">
      <div class="col-sm-12 col-md-4">
        <?php echo $form->renderField('civilite');  ?>
      </div>
      <div class="col-sm-12 col-md-4">
        <?php echo $form->renderField('nom');  ?>
      </div>
      <div class="col-sm-12 col-md-4">
        <?php echo $form->renderField('prenom');  ?>
      </div>
    </div>
    <!-- Row : Adresse -->
    <div class="row">
      <div class="col-sm-12 ">
        <?php echo $form->renderField('adresse');  ?>
      </div>
    </div>
    <!-- Row : CP Ville -->
    <div class="row">
      <div class="col-sm-12 col-md-6">
        <?php echo $form->renderField('code_postal');  ?>
      </div>
      <div class="col-sm-12 col-md-6">
        <?php echo $form->renderField('ville');  ?>
      </div>
    </div>
    <!-- Row :Photo -->
    <div class="row">
      <div class="col-sm-12 col-md-6">
        <!-- champ qui premet de gerer le click sur l'image -->
        <img src="<?php echo $this->escape($photoSrc); ?>" id="image-preview" class="img-thumbnail rounded mx-auto d-block click-image" alt="photo">
        <label for="image-upload" class="click-image form-text"><?php echo Text::_('COM_GDA_PROFIL_DROP_PHOTO'); ?></label>
        <?php
        $options = ["class" => "position-absolute invisible"];
        echo $form->renderField('upload.photo', null, null, $options);
        ?>
      </div>
      <div class="col-sm-12 col-md-6">
        <?php echo $form->renderField('telephone');  ?>
        <?php echo $form->renderField('email');  ?>
        <?php echo $form->renderField('date_de_naissance');  ?>
      </div>
      <div>
        <!-- Row :Personne à prevenir -->
        <div class="row">
          <div class="col-sm-12 col-md-6">
            <?php echo $form->renderField('a_prevenir');  ?>
          </div>
          <div class="col-sm-12 col-md-6">
            <?php echo $form->renderField('a_prevenir_tel');  ?>
          </div>
        </div>
      </div>
    </div>
  </fieldset>
  <input type="hidden" name="task" value="profil.save" />
  <?php echo $form->renderField('photo');  ?>
  <?php echo $form->renderField('id_profil');  ?>
  <?php echo $form->renderField('licence');  ?>
  <?php echo HtmlHelper::_('form.token'); ?>
</form>
