<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
/**
 * @var array $displayData
 * - $displayData['campagne'] : campagne
 * - $displayData['task']  : Controler joomla task
 * - $displayData['form']  : Formulaire de la campagne
 */

$items = $displayData['campagnes'];
$task  = $displayData['task'];
$form  = $displayData['form'];



// $cssClass = $classes[$item->active] ?? 'campagne-default';

?>


<div class="table-responsive">
  <table class="table table-striped table-hover align-middle" id="table-campagne">
    <!-- <caption><?= Text::_('COM_GDA_CAMPAGNE_LIST');?></caption> -->
    <thead>
      <tr>
        <td></td>

        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_TITRE');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_DESCRIPTION');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_TYPE');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_OPENING');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_CLOSING');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_PLACES');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_ARTICLE');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_ACTIVE');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_COURANTE');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_HELLOASSO');?></td>
        <td></td>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item) :?>

      <?= LayoutHelper::render('campagnes.row', ['item' => $item,'task' => $task] ); ?>

      <?php endforeach;?>
    </tbody>
  </table>


  <!-- 
      Formulaire Modal pour Ajouter ou modifier une campagne
-->
  <div class="modal fade" id="modalForm" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" modal-title name="jform_campagne[modal-title]" id="jform_campagne_modal-title"></h5>
          <span class="invisible" id="default_modal-title"><?= Text::_('COM_GDA_CAMPAGNE_NEW'); ?></span>
          <button type="button" id="closeModalForm" class="btn-close" data-bs-dismiss="modal"
            aria-label="Close"></button>
        </div>
        <div class="modal-body" id="modalFormBody">
          <form method="post" name="form_<?= $task ?>" id="form_<?= $task ?>" enctype="multipart/form-data"
            class="form-validate">
            <!-- Ligne 01 -->
            <div class="row g-1">
              <div class="col-sm-12 col-md-6">
                <?= $form->renderField('titre');  ?>
              </div>
              <div class="col-sm-12 col-md-6">
                <?= $form->renderField('id_type');  ?>
              </div>
            </div>
            <!-- Ligne Hello Asso -->
            <div class="row g-1">
              <?= $form->renderField('event_helloasso');  ?>
            </div>
            <!-- Ligne 02 -->
            <div class="row g-1">
              <?= $form->renderField('description');  ?>
            </div>
            <!-- Ligne 03 -->
            <div class="row g-1">
              <div class="col-sm-12 col-md-6">
                <?= $form->renderField('id_article');  ?>
              </div>
              <div class="col-sm-12 col-md-6">
                <?= $form->renderField('id_groupes');  ?>
              </div>
            </div>
            <!-- Ligne 04 -->
            <div class="row g-1">
              <div class="col-md-6">
                <?= $form->renderField('date_debut');  ?>
              </div>
              <div class="col-md-6">
                <?= $form->renderField('date_fin');  ?>
              </div>
            </div>

            <?= $form->renderField('nbr_place');  ?>

            <!-- <button type="button" class="btn btn-primary float-end" onclick="Joomla.submitbutton('campagnes.save')">
                <span class="fa-solid fa-floppy-disk"></span> Sauver
            </button> -->
            <?= $form->renderField('id_campagne');  ?>
            <?= $form->renderField('active');  ?>
            <input type="hidden" name="task" value="campagnes.<?=$task?>" />

            <?= HtmlHelper::_('form.token'); ?>
          </form>



        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary float-end" id="saveCampagne"
            onclick="submitform(event,'form_<?= $task ?>',campagneAdmCB,'closeModalForm')">
            <span class="fa-solid fa-floppy-disk"></span> Sauver
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        </div>
      </div>
    </div>
  </div>



  <!-- 
      Formulaire Modal pour le rapport d'une campagne
-->
  <div class="modal fade" id="modalRapport" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <!-- Le contenu de ce modal est généré dynamiquement par le layout rapport  -->
        
      </div><!-- .modal-content -->
    </div><!-- .modal-dialog -->
  </div> <!-- #modalRapport -->