<?php

\defined('_JEXEC');

use Joomla\CMS\Language\Text;
// use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\AdhesionHelper;

use Joomla\CMS\HTML\Helpers\Bootstrap;

Bootstrap::carousel();
Bootstrap::tooltip();



/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();

$wa->useStyle('com_gdadhesions.gda');

$wa->useStyle('com_gdadhesions.adhesions');
// JS pour gerer l'upload de la photo et du Caci par drag and drop
$wa->useScript('com_gdadhesions.file_upload');
$wa->useStyle('com_gdadhesions.file_upload');
// QR code scanner
$wa->useScript('com_gdadhesions.html5-qrcode');
$wa->useStyle('com_gdadhesions.html5-qrcode');
$wa->useScript('com_gdadhesions.qr_scanner');
// FFESSM Scrap
$wa->useScript('com_gdadhesions.scrap-ffessm');
// Main Adhesion JS
$wa->useScript('com_gdadhesions.adhesions');
// Form Validator 
$wa->useScript('com_gdadhesions.form-validator');
// Submit Form JS
$wa->useScript('com_gdadhesions.form_modal');

// Code JS pour gerer la Liste dynamique répétable  (Dynamic Form Fiels)
$wa->useScript('com_gdadhesions.brevets');

// tom-select
$wa->useStyle('com_gdadhesions.tom-select');
$wa->useScript('com_gdadhesions.tom-select');

Text::script('COM_GDADHESIONS_DROIT_IMAGE_OUI');
Text::script('COM_GDADHESIONS_DROIT_IMAGE_NON');
Text::script('COM_GDADHESIONS_PAS_DE_LICENCE');
Text::script('COM_GDA_ADHESION_RECAP_AUCUN_GROUPE');
Text::script('COM_GDA_ADHESION_RECAP_AUCUN_BREVET');
Text::script('COM_GDA_ADHESION_RECAP_CACI_CHARGE');
Text::script('COM_GDA_ADHESION_RECAP_CACI_NON_CHARGE');
Text::script('COM_GDA_ADHESION_RECAP_CACI_NON_RENSEIGNE');
// Statut "Absent" du contrôle de validité de la date CACI (voir renderCaciFieldStatus() dans
// adhesions.js) : seul état affichable sans appel serveur, les autres (Insuffisant/Valide)
// dépendent de la règle métier SouscriptionService::isDateCaciValidable() (AJAX form.checkCaci).
Text::script('COM_GDA_ADHESION_CACI_DATE_MISSING_SHORT');
Text::script('COM_GDA_ADHESION_CACI_DATE_MISSING_MESSAGE');

// Message de confirmation du scan de la carte licence (construit côté JS avec le porteur et le
// nombre de brevets renvoyés par l'ajax ; le reste des popups - alerte, âge minimum, réduction
// Famille - est rendu côté serveur par le layout adhesion.alert, cf. #adhesionAlertModal).
Text::script('COM_GDA_ADHESION_SCAN_CONFIRM');
Text::script('COM_GDA_ADHESION_SCAN_CONFIRM_WARN');
// Repli si le scan échoue par erreur réseau (pas de réponse serveur à rendre).
Text::script('COM_GDA_ADHESION_SCAN_NOT_FOUND');

Text::script('COM_GDA_ADHESION_HEADER_STEP1');
Text::script('COM_GDA_ADHESION_HEADER_STEP2');
Text::script('COM_GDA_ADHESION_HEADER_STEP3');



// Passer les brevets en JSON à JavaScript
$brevetData = isset($this->brevets) && is_array($this->brevets) ? json_encode($this->brevets) : json_encode([]);
$app->getDocument()->addScriptOptions('com_gdadhesions.brevets', $brevetData);

// Popup d'erreur caméra (scanner QR) : rendue une fois ici (message statique) via le layout
// adhesion.alert, pour rester sur le même mécanisme que les autres popups de la vue - pas de
// round-trip serveur possible, l'échec d'accès à la caméra est purement côté navigateur.
$cameraErrorAlertHtml = base64_encode(LayoutHelper::render('adhesion.alert', ['alerts' => [
    ['title' => Text::_('COM_GDA_ADHESION_SCAN_TITLE'), 'message' => Text::_('COM_GDA_QRCODE_CAMERA_ERROR')],
]]));
$app->getDocument()->addScriptOptions('com_gdadhesions.cameraErrorAlert', $cameraErrorAlertHtml);

// Popup "formulaire incomplet" (garde de navigation du wizard) : même principe, message statique
// rendu une seule fois ici plutôt qu'à chaque changement d'étape.
$stepInvalidAlertHtml = base64_encode(LayoutHelper::render('adhesion.alert', ['alerts' => [
    ['title' => Text::_('COM_GDA_ADHESION_STEP_INVALID_TITLE'), 'message' => Text::_('COM_GDA_ADHESION_STEP_INVALID_MESSAGE')],
]]));
$app->getDocument()->addScriptOptions('com_gdadhesions.stepInvalidAlert', $stepInvalidAlertHtml);

// Titre de la popup d'erreur d'upload (photo/CACI) : le message lui-même est dynamique (nom de
// fichier, taille), construit côté JS - cf. showAdhesionAlertText() dans adhesions.js.
Text::script('COM_GDA_ADHESION_UPLOAD_ERROR_TITLE');



// definir le chemin des images
// La photo de profil
$pathPhoto = FileHelper::getImageSrc($this->form->getField('photo')->value, "ProfilPhotoPath", "DefaultProfilPhoto");
// le Caci
$pathCaci = FileHelper::getImageSrc($this->form->getField('caci')->value, "CaciPath", "DefaultCaci");





// $new_adhesion = $this->form->getValue('new_adhesion');

?>




<div id="wizardInscription" class="carousel slide shadow-lg p-4">

  <!-- Barre de navigation des étapes -->
  <nav class="nav nav-fill mb-4 wizard-nav" id="wizardNav">
    <button type="button" class="nav-link active" data-bs-target="#wizardInscription" data-bs-slide-to="0">
      <span class="d-md-none"><?= Text::_('COM_GDA_ADHESION_STEP_PROFIL_SM') ?></span>
      <span class="d-none d-md-inline"><?= Text::_('COM_GDA_ADHESION_STEP_PROFIL_MD') ?></span>
    </button>
    <button type="button" class="nav-link" data-bs-target="#wizardInscription" data-bs-slide-to="1">
      <span class="d-md-none"><?= Text::_('COM_GDA_ADHESION_STEP_LICENCE_SM') ?></span>
      <span class="d-none d-md-inline"><?= Text::_('COM_GDA_ADHESION_STEP_LICENCE_MD') ?></span>
    </button>
    <button type="button" class="nav-link" id="btnStepRecap" data-bs-target="#wizardInscription" data-bs-slide-to="2">
      <span class="d-md-none"><?= Text::_('COM_GDA_ADHESION_STEP_RECAP_SM') ?></span>
      <span class="d-none d-md-inline"><?= Text::_('COM_GDA_ADHESION_STEP_RECAP_MD') ?></span>
    </button>
  </nav>

  <div class="carousel-inner">

    <!-- Step indicators -->
    <!-- <div class="mb-4 d-flex justify-content-between">
        <span class="badge bg-primary" id="step-indicator-1">1</span>
        <span class="badge bg-secondary" id="step-indicator-2">2</span>
    </div> -->

    <form id="form-adhesion" enctype="multipart/form-data">

      <!-- Header -->
      <div class=" mb-3 d-flex justify-content-between align-items-center">
        <!-- header -->
        <div>
          <h3 class="mb-0" id="headerStep"><?= Text::_('COM_GDA_ADHESION_HEADER_STEP1') ?></h3>
        </div>
        <!-- Bouton / icône pour ouvrir le scan -->
        <div>
          <button id="openQrScanner" class="btn btn-outline-secondary" type="button">
            <i class="fa fa-qrcode"></i> <?php echo Text::_('COM_GDA_SCAN_QRCODE'); ?>
          </button>
        </div>

        <!-- Fenêtre plein écran pour le scanner -->

        <div id="qrModal" class="qr-modal">
          <button type="button" id="closeQrScanner" class="btn btn-danger qr-close">Fermer</button>
          <div id="qrReader" style="width:100%;max-width:600px;margin:auto;"></div>
          
        </div>


        <div>


        </div>
      </div> <!-- container header -->



      <!-- STEP 0 Carousel  -->
      <div class="carousel-item active" id="step-0">
        <!-- Zone de capture du drag and drop -->
        <div class="drop-area" id="photoDropArea">
          <span class="icon-cloud-upload upload-icon" aria-hidden="true"></span>
          <p><?= Text::_('COM_GDA_DROP_PHOTO'); ?></p>
        </div>
        <div class="spinner-border js-loading text-danger visually-hidden" role="status" aria-hidden="true">
          <span class="visually-hidden"><?= Text::_('COM_GDA_LOADING'); ?></span>
        </div>
        <input id="photoUpload" class="position-absolute invisible" type="file" accept="image/jpeg, image/png" />
        <!-- Fin Zone de capture du drag and drop -->

        <div class="row">
          <!-- Ligne 01-->

          <div class="col-sm-12 col-md-3">
            <?= AdhesionHelper::renderField($this->form->getField('civilite'));  ?>
          </div>

          <div class="col-sm-12 col-md-4">
            <?= AdhesionHelper::renderField($this->form->getField('prenom'));  ?>
          </div>

          <div class="col-sm-12 col-md-5">
            <?= AdhesionHelper::renderField($this->form->getField('nom'));  ?>
          </div>
        </div>

        <div class="row">
          <!-- Ligne 02-->
          <div class="col-sm-12 col-md-12">
            <?= AdhesionHelper::renderField($this->form->getField('adresse'));  ?>
          </div>
        </div>
        <div class="row">
          <!-- Ligne 03-->
          <div class="col-sm-12 col-md-6">
            <?= AdhesionHelper::renderField($this->form->getField('code_postal'));  ?>
          </div>
          <div class="col-sm-12 col-md-6">
            <?= AdhesionHelper::renderField($this->form->getField('ville'));  ?>
          </div>
        </div>




        <!-- Row :Photo -->
        <div class="row">
          <!-- Ligne 04-->
          <div class="col-sm-12 col-md-6">
            <!-- champ qui premet de gerer le click sur l'image -->
            <label for="photoUpload" class="click-image form-text input-group-text"><?php echo Text::_('COM_GDA_PROFIL_DROP_PHOTO'); ?></label>
            <img src="<?= $pathPhoto ?>" id="photoPreview" class="img-thumbnail rounded mx-auto d-block click-image" alt="photo">
            <input type="hidden" id="photoFlag" value="<?= $this->form->getField('photo')->value ? '1' : '0' ?>" />

            <?php
            $options = ["class" => "position-absolute invisible"];
            echo $this->form->renderField('upload.photo', null, null, $options);
            ?>
            <!-- getPhotoSrc -->
          </div>
          <div class="col-sm-12 col-md-6">
            <?= AdhesionHelper::renderField($this->form->getField('date_de_naissance'));  ?>
            <?= AdhesionHelper::renderField($this->form->getField('telephone'));  ?>
            <?= AdhesionHelper::renderField($this->form->getField('email'));  ?>
            <h4><?= Text::_('COM_GDA_EMERGENCY_CONTACT') ?></h4>
            <?= AdhesionHelper::renderField($this->form->getField('a_prevenir'));  ?>
            <?= AdhesionHelper::renderField($this->form->getField('a_prevenir_tel'));  ?>
          </div>
        </div>

        <!-- Row :Droit image + reduction -->
        <div class="row">
          <!-- Ligne 04 - droit Images-->
          <div class="col-sm-12 col-md-6">
            <?= AdhesionHelper::renderField($this->form->getField('droit_img'));  ?>
          </div>
          <div class="col-sm-12 col-md-6">
            <?= AdhesionHelper::renderField($this->form->getField('reduction'));  ?>
          </div>
        </div>

        <!-- Bouton suivant -->
        <!-- <button class="btn btn-primary float-end" data-bs-target="#wizardInscription"
            data-bs-slide="next">Suivant</button> -->

      </div>
      <!--STEP 0-->

      <!-- STEP 1 du carousel avec les CACI et le brevet -->
      <!-- STEP 1 -->
      <div class="carousel-item" id="step-1">

        <!-- Zone de capture du drag and drop -->
        <div class="drop-area" id="caciDropArea">
          <span class="icon-cloud-upload upload-icon" aria-hidden="true"></span>
          <p><?php echo Text::_('COM_GDA_DROP_CACI'); ?></p>
        </div>
        <div class="spinner-border js-loading text-danger visually-hidden" role="status" aria-hidden="true">
          <span class="visually-hidden"> Loading...</span>
        </div>
        <input id="caciUpload" class="position-absolute invisible" type="file" accept="image/jpeg, image/png, application/pdf" />
        <!-- Fin Zone de capture du drag and drop -->

        <div class="row">
          <!-- ligne lisence -->
          <div class="col-sm-12 col-md-6">
            <?= AdhesionHelper::renderField($this->form->getField('username'));  ?>
          </div>
          <div class="col-sm-12 col-md-6">
            <?= AdhesionHelper::renderField($this->form->getField('date_licence'));  ?>

          </div>
        </div>


        <div class="row">
          <!-- Row select groupes -->
          <div class="col-sm-12 col-md-12">
            <?= AdhesionHelper::renderField($this->form->getField('id_groupes'));  ?>
          </div>
        </div>


        <div class="row">
          <!-- Row :CACI -->
          <!-- Ligne CACI-->
          <div class="col-sm-12 col-md-6">
            <!-- champ qui premet de gerer le click sur l'image -->
            <label for="caciUpload" class="click-image form-text"><?php echo Text::_('COM_GDA_DROP_CACI_DESC'); ?></label>
            <img src="<?= $pathCaci ?>" id="caciPreview" class="img-thumbnail rounded mx-auto d-block click-image" alt="Caci">
            <input type="hidden" id="caciFlag" value="<?= $this->form->getField('caci')->value ? '1' : '0' ?>" />
            <?php
            $options = ["class" => "position-absolute invisible"];
            echo $this->form->renderField('upload.caci', null, null, $options);
            ?>
          </div>
          <div class="col-sm-12 col-md-6">
            <?= AdhesionHelper::renderField(
              $this->form->getField('date_caci'),
              null,
              '<span id="caciDateValidityMessage" class="align-self-center ms-2 small fw-semibold"></span>'
            );  ?>
            <?= AdhesionHelper::renderField($this->form->getField('nbr_plongee'));  ?>
            <?= AdhesionHelper::renderField($this->form->getField('nbr_plongee_35'));  ?>
            <?= AdhesionHelper::renderField($this->form->getField('nbr_plongee_auto'));  ?>
          </div>
        </div> <!-- End CACI-->


        <div class="row">
          <!-- Row :Brevets -->
          <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0">Brevets acquis</h5>
              <button type="button" class="btn btn-sm btn-primary" id="add-brevet-btn">
                <i class="bi bi-plus-circle"></i> Ajouter un brevet
              </button>
            </div>
            <div class="card-body" id="brevets-container">
              <!-- Les lignes de brevets seront ajoutées ici -->
            </div>
          </div>

        </div><!-- /Row :Brevets -->

        <!-- <button class="btn btn-primary float-start" data-bs-target="#wizardInscription"
          data-bs-slide="prev">Précédent</button>
        <button class="btn btn-primary float-end" id="btnNextStep2" data-bs-target="#wizardInscription"
          data-bs-slide="next">Suivant</button> -->


      </div> <!-- step 1 -->


      <!-- STEP 2 Carousel  recap de tous les element avant validation-->
      <!-- STEP 2 -->
      <div class="carousel-item" id="step-2">
        <div class="card mb-3 shadow-sm">
          <div class="card-body">
            <!-- ligne avec la bouton valider -->
            <div class="row">
              <div class="col-8">
                <span class="text-verif fw-bold">Merci de vérifier que toutes les informations sont correctes avant de valider votre adhésion.</span>
              </div>
              <div class="col-4">
                <button id="btnValider" class="btn btn-success float-end d-none btn-lg" onclick='submitform(event,"form-adhesion",CBsubmitformAdhesion,null)'>
                  <i class="fa-solid fa-check me-2"></i> Valider
                </button>
              </div>
            </div>

            <div class="row border-top mt-3 pt-3">
              <!-- col 1  Photo -->
              <div class="col-sm-12 col-md-6 col-lg-4">
                <p><img id="recap_photo" src="" class="img-thumbnail rounded mx-auto d-block"></p>

              </div>
              <!-- col 2  Nom Preno etc..-->
              <div class="col-sm-12 col-md-6 col-lg-4">
                <p>
                  <span class="label-recap">Nom Prénom:</span>
                  <span class="text-recap" id="recap_civilite"></span>
                  <span class="text-recap" id="recap_nom"></span>
                  <span class="text-recap" id="recap_prenom"></span>
                </p>
                <p>
                  <span class="label-recap">Adresse :</span>
                  <span class="text-recap" id="recap_adresse"></span>
                  <span class="text-recap" id="recap_code_postal"></span> <span class="text-recap"
                    id="recap_ville"></span>
                </p>
                <p>
                  <span class="label-recap">Date de naissance :</span>
                  <span class="text-recap" id="recap_date_de_naissance"></span>
                </p>
                <p>
                  <span class="label-recap">Email :</span>
                  <span class="text-recap" id="recap_email"></span>
                </p>
                <p>
                  <span class="label-recap">Téléphone :</span>
                  <span class="text-recap" id="recap_telephone"></span>
                </p>

                <p>
                  <span class="label-recap">Personne à prevenir :</span>
                  <span class="text-recap" id="recap_a_prevenir"></span> au <span class="text-recap" id="recap_a_prevenir_tel"></span>
                </p>
              </div><!-- fin col 2  Nom Preno etc..-->

              <!-- col 3  Licence + Brevets + Choix-->
              <div class="col-sm-12 col-md-6 col-lg-4">
                <p>
                  <span class="label-recap">Licence :</span>
                  <span class="text-recap" id="recap_licence"></span>
                </p>
                <p> <!-- recap CACI -->
                  <span class="label-recap">CACI :</span>
                  <span class="text-recap" id="recap_caci"></span>

                </p>
                <p>
                  <span class="label-recap">Vous avez fait </span>
                  <span class="text-recap" id="recap_nbr_plongee"></span> Plongée(s) dont
                  <span class="text-recap" id="recap_nbr_plongee_auto"></span> autonomie(s) et
                  <span class="text-recap" id="recap_nbr_plongee_35"></span> sous 35 mètres
                </p>
                <p>
                  <span class="label-recap"><?php echo Text::_('COM_GDA_ADHESION_RECAP_GROUPES'); ?></span>
                <p class="list-recap" id="recap_groupes"></p>
                </p>


              </div> <!-- fin col 3  Licence + Brevets + Choix-->


            </div> <!-- fin row - recap-->
            <!-- row droit à l'image + HelloAsso -->
            <div class="row">
              <div class="col-5">
                <p><span id="recap_droit_img" class="text-recap"></span></p>
              </div>
              <div class="col-7">
                <p>
                  <span class="label-recap">Cotisation :</span>
                  <span class="text-recap" id="recap_cotisation">
                    <?= sprintf(Text::_('COM_GDA_COTISATION_TARIF_' . $this->form->getField('cotisation_code')->value), $this->form->getField('cotisation_montant')->value) ?>


                  </span>
                </p>
                <p>
                  <span class="label-recap">Inscription sur HelloAsso :</span>
                  <span class="text-recap" id="recap_helloasso">
                    <?= $this->form->getField('helloasso')->value !== "0" ? Text::_('COM_GDA_ADHESION_RECAP_HELLOASSO_OUI') : Text::_('COM_GDA_ADHESION_RECAP_HELLOASSO_NON') ?>
                  </span>

                </p>
              </div>
            </div>

            <div class="row">
              <div class="col-12">
                <p>
                  <span class="label-recap">Vous avez aquis les brevets suivants :</span>
                <p class="list-recap" id="recap_brevets"></p>
                </p>
              </div>
            </div>

          </div> <!-- fin card body - recap-->
        </div>


        <!-- <button class="btn btn-primary float-start" data-bs-target="#wizardInscription" data-bs-slide="prev">
          Précédent
        </button> -->

      </div> <!-- FIN STEP 2 Carousel  recap de tous les element avant validation-->


      <!-- Hidden fields -->
      <?= $this->form->renderField('key'); ?>
      <?= $this->form->renderField('id'); ?>
      <?= $this->form->renderField('photo'); ?>
      <?= $this->form->renderField('caci'); ?>
      <?= $this->form->renderField('ffessm_token'); ?>
      <?= $this->form->renderField('cotisation_code'); ?>
      <?= $this->form->renderField('cotisation_montant'); ?>
      <?= $this->form->renderField('helloasso'); ?>
      <!-- <?= HTMLHelper::_('form.token'); ?> -->
      <input type="hidden" name="task" value="adhesion.save" />





    </form>
  </div> <!-- Close carousel inner -->

  <!-- Navigation Précédent/Suivant en bas de page : contrairement aux flèches natives du
       carousel Bootstrap (.carousel-control-prev/next, positionnées en absolu au milieu de la
       slide), ces boutons restent dans le flux normal du document, donc sous le contenu de
       chaque étape, atteignables sans remonter en haut de page sur mobile. -->
  <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top gda-wizard-footer-nav">
    <button type="button" id="btnFooterPrev" class="btn btn-outline-secondary invisible">
      <i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i><?= Text::_('COM_GDA_WIZARD_PRECEDENT') ?>
    </button>
    <button type="button" id="btnFooterNext" class="btn btn-primary">
      <?= Text::_('COM_GDA_WIZARD_SUIVANT') ?><i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i>
    </button>
  </div>

</div> <!-- Close carousel slide -->


<!-- Template brevet caché (partagé avec la modale d'édition des brevets de la vue Profil) -->
<?= LayoutHelper::render('brevets.row_template'); ?>


















<!-- Popup de confirmation d'adhésion (contenu : layout adhesion.popup, injecté par CBsubmitformAdhesion) -->
<div class="modal fade" id="adhesionPopupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" id="adhesionPopupModalContent">
      <!-- Contenu chargé dynamiquement (layout adhesion.popup) -->
    </div>
  </div>
</div>

<!-- Popup d'alerte métier (contenu : layout adhesion.alert, injecté au fil des contrôles - scan, âge minimum, réduction Famille) -->
<div class="modal fade" id="adhesionAlertModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" id="adhesionAlertModalContent">
      <!-- Contenu chargé dynamiquement (layout adhesion.alert) -->
    </div>
  </div>
</div>

<!-- Confirmation du scan de la carte licence : cas particulier piloté entièrement en JS (message
     construit avec le porteur/nombre de brevets renvoyés par l'ajax), boutons fixes comme pour
     #deleteAdherentModal côté Secrétariat. -->
<div class="modal fade" id="adhesionScanConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= Text::_('COM_GDA_ADHESION_SCAN_TITLE') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
      </div>
      <div class="modal-body">
        <p id="adhesionScanConfirmMessage" class="mb-2"></p>
        <p id="adhesionScanConfirmWarning" class="text-warning small mb-0"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('COM_GDA_CANCEL') ?></button>
        <button type="button" class="btn btn-primary" id="adhesionScanConfirmSubmit"><?= Text::_('COM_GDA_CONFIRM') ?></button>
      </div>
    </div>
  </div>
</div>