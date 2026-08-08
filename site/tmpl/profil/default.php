<?php
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use Joomla\CMS\HTML\Helpers\Bootstrap;
use Joomla\CMS\Layout\LayoutHelper;

// Bootstrap::framework();

/** @var \Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
/** @var \Joomla\CMS\Document\HtmlDocument $doc */
$doc = $app->getDocument();
$wa = $doc->getWebAssetManager();


// $wa->useScript('core');
// $wa->useScript('keepalive');
// $wa->useScript('field.modal-fields');

$wa->useScript('com_gdadhesions.file_upload');
$wa->useScript('com_gdadhesions.form_modal');
$wa->useScript('com_gdadhesions.spinner');

$wa->useStyle('com_gdadhesions.gda');
$wa->useStyle('com_gdadhesions.file_upload');
$wa->useStyle('com_gdadhesions.spinner');




  // HTMLHelper::_('script', 'media/system/js/joomla-dialog.min.js', ['version' => 'auto', 'relative' => true]);
/** @var \NCB\Component\Gda\Site\Model\ProfilModel $model */
$model = $this->getModel();

/** @var \stdClass $profil */
$profil = $this->item;

/** @var array $profilsOB */
$profilsOB = $this->itemsOB;

//   $ProfilPhotoPath = ConfHelper::GetKey("ProfilPhotoPath");
//   if ($ProfilPhotoPath === false) {
//     $ProfilPhotoPath = "\\images\\";
//   }



// // Test item <> null
if ($this->item !== null):



?>


  <!-- Carte de de profil -->

  <div class="row">

    <?php
    // le profil du user 
    // si profile exsite on l'affiche sinon on affiche un message
    if ($profil === null) {
      echo '<p>' . Text::_('COM_GDA_NO_PROFILE') . '</p>';
    } else {
      echo $model->showCardProfil($profil);
      echo LayoutHelper::render('profil.card_caci', ['profil' => $profil]);
    }
    //les profil OB
    foreach ($profilsOB as $profilOB) {
      echo $model->showCardProfil($profilOB, false);
      echo LayoutHelper::render('profil.card_caci', ['profil' => $profilOB]);
    }
    ?>




  </div> <!--  class="row" -->


  <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"> -->


  <!-- 
Fenêtre modale Bootstrap +++
-->
  <div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalLabel">Editer <?php echo Text::_($this->form->getFieldsets()['profil']->label) ?></h5>
          <button type="button" id="btnClose" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div> <!--  class="modal-header" -->
        <div class="modal-body" id="modalFormBody">
          <!-- Zone de capture du drag and drop -->
          <div class="drop-area" id="drop-area">
            <span class="icon-cloud-upload upload-icon" aria-hidden="true"></span>
            <p><?php echo Text::_('COM_GDA_DROP_PHOTO'); ?></p>
          </div>
          <div class="spinner-border js-loading text-danger visually-hidden" role="status" aria-hidden="true">
            <span class="visually-hidden">Loading...</span>
          </div>
          <input id="image-upload" class="position-absolute invisible" type="file" accept="image/jpeg, image/png" />
          <input type="hidden" id="photoFlag" value="<?php echo $profil !== null && $profil->photo ? '1' : '0'; ?>" />
          <form action="<?php echo Route::_('index.php?option=com_gdadhesions&Itemid=' . $this->MenuItemId); ?>"
            method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

            <fieldset class="profil-edit">
              <!-- <h2><?php //echo Text::_($this->form->getFieldsets()['profil']->label) 
                        ?></h2> -->
              <!-- Row : M. Nom Prénom -->
              <div class="row">
                <div class="col-sm-12 col-md-4">


                  <?php echo $this->form->renderField('civilite');  ?>
                </div>
                <div class="col-sm-12 col-md-4">
                  <?php echo $this->form->renderField('nom');  ?>
                </div>
                <div class="col-sm-12 col-md-4">
                  <?php echo $this->form->renderField('prenom');  ?>
                </div>
              </div>
              <!-- Row : Adresse -->
              <div class="row">
                <div class="col-sm-12 ">
                  <?php echo $this->form->renderField('adresse');  ?>
                </div>
              </div>
              <!-- Row : CP Ville -->
              <div class="row">
                <div class="col-sm-12 col-md-6">
                  <?php echo $this->form->renderField('code_postal');  ?>
                </div>
                <div class="col-sm-12 col-md-6">
                  <?php echo $this->form->renderField('ville');  ?>
                </div>
              </div>
              <!-- Row :Photo -->
              <div class="row">
                <div class="col-sm-12 col-md-6">
                  <!-- champ qui premet de gerer le click sur l'image -->
                  <img src="" id="image-preview" class="img-thumbnail rounded mx-auto d-block click-image" alt="photo">
                  <label for="image-upload" class="click-image form-text"><?php echo Text::_('COM_GDA_PROFIL_DROP_PHOTO'); ?></label>
                  <?php
                  $options = ["class" => "position-absolute invisible"];
                  // $options = [];
                  echo $this->form->renderField('upload.photo', null, null, $options);
                  ?>

                </div>
                <div class="col-sm-12 col-md-6">
                  <?php echo $this->form->renderField('telephone');  ?>
                  <?php echo $this->form->renderField('email');  ?>
                  <?php echo $this->form->renderField('date_de_naissance');  ?>
                </div>
                <div>
                  <!-- Row :Personne à prevenir -->
                  <div class="row">
                    <div class="col-sm-12 col-md-6">
                      <?php echo $this->form->renderField('a_prevenir');  ?>
                    </div>
                    <div class="col-sm-12 col-md-6">
                      <?php echo $this->form->renderField('a_prevenir_tel');  ?>
                    </div>
                  </div>




            </fieldset>
            <input type="hidden" name="task" value="profil.save" />
            <?php echo $this->form->renderField('photo');  ?>
            <?php echo $this->form->renderField('id_profil');  ?>
            <?php echo $this->form->renderField('licence');  ?>
            <?php echo HtmlHelper::_('form.token'); ?>
          </form>
        </div> <!--  class="modal-body" -->
        <div class="modal-footer">
          <button id="SaveModalForm" type="button" class="btn btn-secondary float-end">
            <!-- onclick="Joomla.submitbutton('profil.save')" -->
            <i class="fa-solid fa-floppy-disk"></i> <?php echo Text::_('COM_GDA_SAVE'); ?>
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('COM_GDA_CANCEL'); ?></button>
        </div> <!--  class="modal-footer" -->
      </div> <!--  class="modal-content" -->
    </div><!--  class="modal-dialog" -->
  </div> <!--  class="modal fade" -->


  <!--
Fenêtre modale Bootstrap : mise à jour du CACI
-->
  <div class="modal fade" id="caciModal" tabindex="-1" aria-labelledby="caciModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="caciModalLabel"><?php echo Text::_('COM_GDA_PROFIL_CACI_MODAL_TITLE'); ?></h5>
          <button type="button" id="btnCloseCaci" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div> <!--  class="modal-header" -->
        <div class="modal-body" id="caciModalBody">
          <?php echo LayoutHelper::render('profil.mgn_caci', ['form' => $this->formCaci]); ?>
        </div> <!--  class="modal-body" -->
        <div class="modal-footer">
          <button id="SaveCaciModalForm" type="button" class="btn btn-secondary float-end">
            <i class="fa-solid fa-floppy-disk"></i> <?php echo Text::_('COM_GDA_SAVE'); ?>
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('COM_GDA_CANCEL'); ?></button>
        </div> <!--  class="modal-footer" -->
      </div> <!--  class="modal-content" -->
    </div><!--  class="modal-dialog" -->
  </div> <!--  class="modal fade" -->
<?php
else:
  echo "UserName inconnue";
endif; ?>


<?php


Joomla\CMS\Language\Text::script('COM_GDA_DROP_PHOTO');

?>



<script>
  document.addEventListener('DOMContentLoaded', function() {

    /**
     * Gestion de la fenetre Modal
     */
    var myModal = document.getElementById('myModal')

    myModal.addEventListener('show.bs.modal', function(event) {
      var Id = event.relatedTarget.getAttribute('data-bs-id_profil')
      var ProfilCard = document.querySelector('#' + Id);
      openModal(ProfilCard, myModal, "jform_Profil")
    });
    myModal.addEventListener('hide.bs.modal', function(e) {
      console.log(myModal.classList.contains('is-loading'))
      if (myModal.classList.contains('is-loading')) {
        e.preventDefault();
        e.stopPropagation();
      }

    });
    // btn-close  btnClose



    /**
     * Creation de la zone de drag&drop pour la photo via la factory générique
     * FileUpload.create() (media/com_gdadhesions/js/file_upload.js).
     * L'instance est accessible via window.GdaFileUploads.photo
     */
    FileUpload.create('photo', {
      mediaBrowserId: 'modalFormBody',
      dropAreaId: 'drop-area',
      previewId: 'image-preview',
      inputId: 'image-upload',
      flagId: 'photoFlag',
    });

    /**
     * Gestion de la fenetre Modal CACI
     */
    var caciModal = document.getElementById('caciModal')

    caciModal.addEventListener('show.bs.modal', function(event) {
      var Id = event.relatedTarget.getAttribute('data-bs-id_profil')
      var CaciCard = document.querySelector('#' + Id);
      openModal(CaciCard, caciModal, "jform_Caci", '#caciModalPreview')
    });

    /**
     * Creation de la zone de drag&drop pour le CACI via la factory générique,
     * avec un bouton "prendre une photo" qui ouvre l'appareil photo natif (mobile).
     * L'instance est accessible via window.GdaFileUploads.caci
     */
    FileUpload.create('caci', {
      mediaBrowserId: 'caciModalBody',
      dropAreaId: 'caciModalDropArea',
      previewId: 'caciModalPreview',
      inputId: 'caciModalUpload',
      flagId: 'caciModalFlag',
      captureInputId: 'caciModalCameraInput',
    });

    /**
     * Sauvegarde du CACI (simpleCallAjax gère l'URL, le CSRF, le parsing JSON et les messages d'erreur)
     */
    document.getElementById('SaveCaciModalForm').addEventListener('click', function(e) {

      e.preventDefault();
      showspinner(document.getElementById('caciModal'));

      const formData = new FormData(document.getElementById("caciAdminForm"));
      const upCaci = window.GdaFileUploads.caci;
      if (upCaci && upCaci.File !== undefined) {
        formData.append('jform_Caci[upload.caci]', upCaci.File);
      }

      simpleCallAjax(formData, (response) => {
        hidespinner(document.getElementById('caciModal'));
        // Le serveur renvoie la carte CACI ré-rendue (image + date + statut à jour)
        const idProfil = document.querySelector('#jform_Caci_id_profil').value;
        const oldCard = document.querySelector('#caci_' + idProfil);
        if (oldCard) {
          oldCard.outerHTML = decodeURIComponent(escape(atob(response.data)));
        }
        document.getElementById('btnCloseCaci').click();
      }, false, () => hidespinner(document.getElementById('caciModal')));

    });



    /**
     * Sauvegarde du profil (simpleCallAjax gère l'URL, le CSRF, le parsing JSON et les messages d'erreur)
     */
    document.getElementById('SaveModalForm').addEventListener('click', function(e) {

      e.preventDefault();
      showspinner(document.getElementById('myModal'));

      const formData = new FormData(document.getElementById("adminForm"));
      const upPhoto = window.GdaFileUploads.photo;
      if (upPhoto && upPhoto.File !== undefined) {
        formData.append('jform_Profil[upload]', upPhoto.File);
      }

      simpleCallAjax(formData, (response) => {
        hidespinner(document.getElementById('myModal'));
        var Id = document.querySelector('#jform_Profil_id_profil').value
        var ProfilCard = document.querySelector('#id_' + Id);
        // Refresh image
        refreshPreview(ProfilCard.querySelector('.img-thumbnail'));
        potsCloseModal(myModal, ProfilCard, "jform_Profil", ['upload_photo'])
        document.getElementById('btnClose').click();
      }, false, () => hidespinner(document.getElementById('myModal')));

    });


  });
</script>