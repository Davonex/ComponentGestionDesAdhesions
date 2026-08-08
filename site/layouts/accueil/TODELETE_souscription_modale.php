<?php

use Joomla\CMS\Language\Text;

use Joomla\CMS\HTML\HTMLHelper;



$task = $displayData['task'];
// $form = $displayData['form'];

?>
<!-- nouveau formulaire -->
<!-- Formulaire Modal pour S'inscrire ou se desinscrire -->

<!-- <div class="modal fade" id="modalSign" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" modal-title name="jform_souscription[modal-title]" id="jform_souscription_modal_title"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body"></div>
      <div class="modal-footer">
         
      </div>
    </div>
  </div>
</div> -->




<div class="modal fade" id="modalSign" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">


      <!-- Formulaire     -->
      <form method="post" name="form_<?=$task?>" id="form_<?=$task?>" enctype="multipart/form-data">




        <div class="modal-header">
          <!-- // class="modal-header" -->
          <h5 data-bs class="modal-title" id="jform_souscription_titre" class="card-title"></h5>
          <button type="button" id="closeSignIn" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div> <!-- // class="modal-header" -->


        <div class="modal-body">
          <!-- // class="modal-body" -->
            
            <textarea data-bs id="jform_souscription_description" name="jform_souscription[description]" class="card-text"></textarea>
            <input data-bs type="hidden" id="jform_souscription_id_profil" name="jform_souscription[id_profil]" value="" />
            <input type="hidden" name="task" value="campagnes.<?=$task?>" />
            <?= HtmlHelper::_('form.token')?>

        </div> <!-- // class="modal-body" -->
        <div class="modal-footer">
          <button id="submitSignIn" type="button" class="btn btn-success float-end"
            onclick="submitform(event,'form_<?=$task?>',campagneCB,'closeSignIn')">
            <i class="fa-solid fa-check me-2"></i><?=Text::_('COM_GDA_CAMPAGNE_SOUSCRIT')?>
          </button>

        </div>
        <!-- // class="modal-footer" -->

      </form> <!-- End form -->
    </div>
    <!-- // class="modal-content" -->
  </div>
  <!-- // class="modal-dialog modal-lg" -->
</div>