<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
/**
 * @var array $displayData
 * - $displayData['campagnes'] : liste des campagnes
 * - $displayData['task']  : Controler joomla task
 * - $displayData['form']  : Formulaire de la campagne
 * - $displayData['types'] : natures distinctes présentes dans $displayData['campagnes']
 * - $displayData['roles'] : id_type => liste de rôles PAR DÉFAUT (préremplissage à la création)
 */

$items = $displayData['campagnes'];
$task  = $displayData['task'];
$form  = $displayData['form'];
$types = $displayData['types'] ?? [];
$roles = $displayData['roles'] ?? [];

// Métadonnées par nature (nom + rôles par défaut), lues en JS pour préremplir les lignes
// rôle+capacité et adapter le switch reservation_multiple selon la nature sélectionnée.
$typesMeta = [];
foreach ($types as $type) {
    $typesMeta[(int) $type->id_type] = [
        'name'  => $type->type_name,
        'roles' => $roles[(int) $type->id_type] ?? [],
    ];
}

/**
 * Rend un champ du formulaire en reprenant la structure du layout Joomla (joomla.form.renderfield :
 * control-group > control-label + controls), mais sans le bloc de description sous le champ (qui
 * prend beaucoup de place) : la description devient une icône "?" en survol, placée *dans* le
 * control-label juste après le libellé, pour qu'elle suive le flux du label au lieu de flotter
 * après le control-group.
 *
 * $inline = true (champs switch) ajoute la classe gda-field-inline, qui met le libellé, l'icône
 * et le switch sur une même ligne.
 */
$renderFieldHint = function (string $fieldName, bool $inline = false) use ($form) {
    $field = $form->getField($fieldName);
    $description = $field->description ? Text::_($field->description) : '';

    $hint = $description === '' ? '' : ' <i class="fa-solid fa-circle-question gda-field-hint-icon" title="'
        . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '"></i>';

    // gda-field-hint-label garde le libellé et l'icône sur une même ligne : sans elle, le
    // control-label reste un bloc dont le <label> occupe toute la largeur, ce qui renvoie
    // l'icône à la ligne suivante.
    return '<div class="control-group' . ($inline ? ' gda-field-inline' : '') . '">'
        . '<div class="control-label gda-field-hint-label">' . $field->label . $hint . '</div>'
        . '<div class="controls">' . $field->input . '</div>'
        . '</div>';
};

?>


<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div class="btn-group" role="group" aria-label="<?= $this->escape(Text::_('COM_GDA_CAMPAGNE_FILTER_STATUT')); ?>">
    <input type="radio" class="btn-check" name="campagneFilterActive" id="campagneFilterActiveAll" value="all" checked>
    <label class="btn btn-outline-secondary btn-sm" for="campagneFilterActiveAll"><?= Text::_('COM_GDA_CAMPAGNE_FILTER_TOUTES'); ?></label>

    <input type="radio" class="btn-check" name="campagneFilterActive" id="campagneFilterActiveOuvertes" value="1">
    <label class="btn btn-outline-success btn-sm" for="campagneFilterActiveOuvertes"><?= Text::_('COM_GDA_CAMPAGNE_FILTER_OUVERTES'); ?></label>

    <input type="radio" class="btn-check" name="campagneFilterActive" id="campagneFilterActiveFermees" value="0">
    <label class="btn btn-outline-dark btn-sm" for="campagneFilterActiveFermees"><?= Text::_('COM_GDA_CAMPAGNE_FILTER_FERMEES'); ?></label>
  </div>

  <div class="d-flex align-items-center gap-2">
    <select class="form-select form-select-sm" id="campagneFilterType" style="width:auto;">
      <option value=""><?= Text::_('COM_GDA_CAMPAGNE_FILTER_TOUTES_NATURES'); ?></option>
      <?php foreach ($types as $type) : ?>
        <option value="<?= (int) $type->id_type; ?>"><?= $this->escape($type->type_name); ?></option>
      <?php endforeach; ?>
    </select>

    <a class="btn btn-success" type="button"
        id="openForm"
        data-bs-toggle="modal" data-bs-target="#modalForm"
        data-toggle="tooltip" data-placement="top"
        data-empty
        title="<?php echo Text::_('COM_GDA_CAMPAGNE_NEW_TOOLTIP'); ?>"
        href="#">
        <span class="fa-solid fa-plus"></span> <?php echo Text::_('COM_GDA_CAMPAGNE_NEW'); ?>
    </a>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-striped table-hover align-middle" id="table-campagne">
    <!-- <caption><?= Text::_('COM_GDA_CAMPAGNE_LIST');?></caption> -->
    <thead>
      <tr>
        <td></td>

        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_TITRE');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_DATE_EVENEMENT');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_OPENING');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_CLOSING');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_PLACES');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_ARTICLE');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_ACTIVE');?></td>
        <td><?= Text::_('COM_GDA_CAMPAGNE_LIST_RAPPORT');?></td>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item) :?>

      <?= LayoutHelper::render('campagnes.row', ['item' => $item,'task' => $task] ); ?>

      <?php endforeach;?>
    </tbody>
  </table>
</div>

  <!--
      Formulaire Modal pour Ajouter ou modifier une campagne
-->
  <div class="modal fade" id="modalForm" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-gda-header text-header">
          <h5 class="modal-title mb-0">
            <i class="fa-solid fa-flag me-2"></i>
            <span modal-title name="jform_campagne[modal-title]" id="jform_campagne_modal-title"></span>
          </h5>
          <span class="invisible" id="default_modal-title"><?= Text::_('COM_GDA_CAMPAGNE_NEW'); ?></span>
          <button type="button" id="closeModalForm" class="btn-close btn-close-white" data-bs-dismiss="modal"
            aria-label="Close"></button>
        </div>
        <div class="modal-body p-4" id="modalFormBody">
          <form method="post" name="form_<?= $task ?>" id="form_<?= $task ?>" enctype="multipart/form-data"
            class="form-validate">

            <!-- <h6 class="text-uppercase text-muted small fw-bold mb-3"><?= Text::_('COM_GDA_CAMPAGNE_SECTION_INFOS'); ?></h6> -->

            <!-- Chaque ligne aligne un champ de gauche avec son équivalent de droite, plutôt
                 que deux longues colonnes empilées indépendamment (qui ne s'alignaient plus
                 dès que l'une des deux devenait plus haute que l'autre). -->
            <div class="row g-3 align-items-start">
              <div class="col-12 col-lg-6">
                <?= $form->renderField('titre');  ?>
              </div>
              <div class="col-12 col-lg-6">
                <?= $form->renderField('id_type');  ?>
              </div>
            </div>

            <!-- Date de l'événement à la place de l'ancien encart de description de nature
                 (retiré pour réduire la hauteur globale du formulaire) : distincte de la période
                 de souscription ci-dessous. -->
            <div class="row g-3 align-items-start mb-3">
              <div class="col-12 col-lg-6">
                <?= $form->renderField('description');  ?>
              </div>
              <div class="col-12 col-lg-6">
                <?= $form->renderField('date_evenement');  ?>
              </div>
            </div>

            <div class="row g-3 align-items-start">
              <div class="col-6">
                <?= $form->renderField('date_debut');  ?>
              </div>
              <div class="col-6">
                <?= $form->renderField('date_fin');  ?>
              </div>
            </div>

            <!-- Rôles et capacité : Formation et Loisir demandent toutes deux systématiquement un
                 rôle par place (voir ReservationService) ; liste librement ajoutable/renommable/
                 supprimable, préremplie des rôles par défaut de la nature à la création (voir
                 campagne.js). #jform_campagne_role_places ne fait que recevoir en JSON (via
                 LstModal/openModal) la répartition déjà enregistrée pour la campagne éditée ;
                 il n'est jamais soumis lui-même. data-type-meta porte les métadonnées par nature
                 (nom + rôles par défaut) lues par campagne.js. -->
            <div class="row g-3 align-items-start" id="fieldRolePlaces"
                data-type-meta='<?= htmlspecialchars(json_encode($typesMeta), ENT_QUOTES, 'UTF-8'); ?>'>
              <div class="col-12">
                <label class="control-label gda-field-hint-label mb-2"><?= Text::_('COM_GDA_CAMPAGNE_ROLE_PLACES'); ?></label>
                <div id="jform_campagne_role_places_rows"></div>
                <button type="button" class="btn btn-sm btn-outline-success mt-1" id="jform_campagne_role_places_add">
                  <span class="fa-solid fa-plus"></span> <?= Text::_('COM_GDA_CAMPAGNE_ROLE_ADD'); ?>
                </button>
                <div id="jform_campagne_role_places" class="d-none"></div>
              </div>
            </div>

            <?= LayoutHelper::render('campagnes.role_row_template'); ?>

            <!-- Le champ switch occupe une demi-largeur : libellé et switch étant sur la même
                 ligne (gda-field-inline), une colonne plus étroite le ferait passer à la ligne.
                 Placé sous la ligne des rôles (et non à côté des dates) car sa valeur en dépend
                 (forcé à Non pour Formation, voir campagne.js). -->
            <div class="row g-3 align-items-center">
              <div class="col-12 col-lg-6" id="fieldReservationMultiple">
                <?= $renderFieldHint('reservation_multiple', true);  ?>
              </div>
            </div>

            <hr class="my-4">

            <!-- <h6 class="text-uppercase text-muted small fw-bold mb-3"><?= Text::_('COM_GDA_CAMPAGNE_SECTION_PUBLIC'); ?></h6> -->

            <div class="row g-3 align-items-start mb-2">
              <div class="col-12 col-md-6">
                <?= $form->renderField('id_groupes');  ?>
              </div>
              <div class="col-12 col-md-6">
                <?= $renderFieldHint('id_article');  ?>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <?= HTMLHelper::_('image', FileHelper::getHelloAssoLogoSrc(), 'HelloAsso', ['width' => '24', 'height' => '24']); ?>
              <div class="flex-grow-1"><?= $renderFieldHint('event_helloasso');  ?></div>
            </div>

            <?= $form->renderField('id_campagne');  ?>
            <?= $form->renderField('active');  ?>
            <input type="hidden" name="task" value="campagnes.<?=$task?>" />

            <?= HtmlHelper::_('form.token'); ?>
          </form>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary float-end" id="saveCampagne"
            onclick="submitform(event,'form_<?= $task ?>',campagneAdmCB,'closeModalForm')">
            <span class="fa-solid fa-floppy-disk"></span> <?= Text::_('COM_GDA_SAVE') ?>
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('COM_GDA_CANCEL'); ?></button>
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