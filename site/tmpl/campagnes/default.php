<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\HTML\Helpers\Bootstrap;

Bootstrap::tab();
Bootstrap::modal();

/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();


$wa->useStyle('com_gdadhesions.gda');
$wa->useScript('core');
$wa->useScript('com_gdadhesions.form_modal');
$wa->useScript('com_gdadhesions.campagne');
$wa->useScript('form.validate');

// Réutilisé pour le rendu "Suivi des inscriptions" (onglet Formation) : gère la
// prévisualisation photo/CACI en modal (délégation d'évènements, fonctionne aussi pour le
// contenu injecté en ajax).
$wa->useScript('com_gdadhesions.groupes');

// Réutilisé pour l'ouverture de l'article lié à une campagne dans #articleModal (même mécanisme
// que le dashboard adhérent, générique : ReservationController::showArticle() + .js-show-article).
$wa->useScript('com_gdadhesions.reservation');

// tom-select
$wa->useStyle('com_gdadhesions.tom-select');
$wa->useScript('com_gdadhesions.tom-select');

//datatables
$wa->useScript('simple-datatables');
$wa->useStyle('simple-datatables');

$task = 'sauver';

$layoutData = [
    'campagnes' => $this->lstCampagnes,
    'types'     => $this->types,
    'roles'     => $this->roles,
    'task'      => $task,
    'form'      => $this->form,
];
?>

<div class="gda-campagnes card shadow-lg p-4">

    <ul class="nav nav-tabs" id="campagnesTabNav" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="campagnes-tab-gestion" data-bs-toggle="tab"
                data-bs-target="#campagnes-pane-gestion" type="button" role="tab"
                aria-controls="campagnes-pane-gestion" aria-selected="true">
                <?= Text::_('COM_GDA_CAMPAGNES_TAB_GESTION') ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="campagnes-tab-suivi" data-bs-toggle="tab"
                data-bs-target="#campagnes-pane-suivi" type="button" role="tab"
                aria-controls="campagnes-pane-suivi" aria-selected="false">
                <?= Text::_('COM_GDA_CAMPAGNES_TAB_SUIVI') ?>
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3" id="campagnesTabContent">

        <div class="tab-pane fade show active" id="campagnes-pane-gestion" role="tabpanel"
            aria-labelledby="campagnes-tab-gestion">
            <?= LayoutHelper::render('campagnes.table', $layoutData) ?>
        </div>

        <div class="tab-pane fade" id="campagnes-pane-suivi" role="tabpanel"
            aria-labelledby="campagnes-tab-suivi">

            <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
                <div class="btn-group" role="group" aria-label="<?= $this->escape(Text::_('COM_GDA_CAMPAGNE_FILTER_STATUT')) ?>">
                    <input type="radio" class="btn-check" name="campagneSuiviFilterActive" id="campagneSuiviFilterActiveAll" value="all" checked>
                    <label class="btn btn-outline-secondary btn-sm" for="campagneSuiviFilterActiveAll"><?= Text::_('COM_GDA_CAMPAGNE_FILTER_TOUTES') ?></label>

                    <input type="radio" class="btn-check" name="campagneSuiviFilterActive" id="campagneSuiviFilterActiveOuvertes" value="1">
                    <label class="btn btn-outline-success btn-sm" for="campagneSuiviFilterActiveOuvertes"><?= Text::_('COM_GDA_CAMPAGNE_FILTER_OUVERTES') ?></label>

                    <input type="radio" class="btn-check" name="campagneSuiviFilterActive" id="campagneSuiviFilterActiveFermees" value="0">
                    <label class="btn btn-outline-dark btn-sm" for="campagneSuiviFilterActiveFermees"><?= Text::_('COM_GDA_CAMPAGNE_FILTER_FERMEES') ?></label>
                </div>

                <div class="col-sm-6 col-md-4">
                    <label for="campagneSuiviSelect" class="form-label"><?= Text::_('COM_GDA_CAMPAGNES_SUIVI_SELECT_LABEL') ?></label>
                    <select class="form-select" id="campagneSuiviSelect"
                        data-empty-label="<?= $this->escape(Text::_('COM_GDA_CAMPAGNES_SUIVI_EMPTY')) ?>">
                        <option value=""><?= Text::_('COM_GDA_CAMPAGNES_SUIVI_SELECT_EMPTY') ?></option>
                        <?php foreach ($this->lstFormations as $campagne) : ?>
                            <option value="<?= (int) $campagne->id_campagne ?>" data-active="<?= (int) $campagne->active ?>">
                                <?= $this->escape($campagne->titre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="campagneSuiviContent">
                <p class="text-muted"><?= Text::_('COM_GDA_CAMPAGNES_SUIVI_EMPTY') ?></p>
            </div>
        </div>

    </div>

    <!-- Modals réutilisés par l'onglet Suivi (prévisualisation photo/CACI, fiche adhérent) -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-body text-center p-2">
                    <img id="imagePreviewImage" src="" alt="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profilCardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" id="profilCardModalContent">
                <!-- Le contenu de la modal est chargé dynamiquement via ajax -->
            </div>
        </div>
    </div>

    <!-- Article lié à une campagne (colonne Titre du tableau de gestion) -->
    <div class="modal fade" id="articleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content" id="articleModalContent">
                <!-- Le contenu de la modal est chargé dynamiquement via ajax -->
            </div>
        </div>
    </div>

</div>

<script type="module">
    document.addEventListener('DOMContentLoaded', function() {

        LstModal("modalForm", "campagne");
        multiselectInit('jform_campagne_id_groupes')

    });
</script>
