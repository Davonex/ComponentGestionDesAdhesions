<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;

/**
 * @var array $displayData
 * - $displayData['saison']  : objet campagne (saison courante), ou null si aucune n'est déclarée
 * - $displayData['form']    : Form de la saison courante
 * - $displayData['groupes']   : liste des groupes du club (id_groupe, groupe_name, activite,
 *                               groupe_tri, icon, published)
 * - $displayData['activites'] : liste fermée des activités proposées pour un groupe
 */

$saison    = $displayData['saison'];
$form      = $displayData['form'];
$groupes   = $displayData['groupes'];
$activites = $displayData['activites'];

?>

<?php if ($saison === null) : ?>

    <div class="alert alert-info">
        <?= Text::_('COM_GDA_SAISONS_NO_ACTIVE_SEASON') ?>
    </div>

<?php else : ?>

    <div class="d-flex justify-content-end mb-3">
        <button type="button"
            class="btn <?= $saison->active ? 'btn-success' : 'btn-dark' ?> js-toggle-active-courante"
            id="btnToggleActiveSaisonCourante"
            data-id-campagne="<?= (int) $saison->id_campagne ?>"
            data-active="<?= $saison->active ? '1' : '0' ?>"
            data-label-open="<?= Text::_('COM_GDA_SAISONS_OPEN_BUTTON') ?>"
            data-label-close="<?= Text::_('COM_GDA_SAISONS_CLOSE_BUTTON') ?>">
            <span class="js-toggle-active-courante-icon fa-solid <?= $saison->active ? 'fa-door-open' : 'fa-lock' ?>"></span>
            <span class="js-toggle-active-courante-label"><?= $saison->active ? Text::_('COM_GDA_SAISONS_CLOSE_BUTTON') : Text::_('COM_GDA_SAISONS_OPEN_BUTTON') ?></span>
        </button>
    </div>

    <form id="form_saison_courante" name="form_saison_courante" class="form-validate">

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <h3 class="h6"><?= Text::_('COM_GDA_SAISONS_DEFINITION_TITLE') ?></h3>

                <?= $form->renderField('titre') ?>

                <div class="row g-1">
                    <div class="col-md-6"><?= $form->renderField('date_debut') ?></div>
                    <div class="col-md-6"><?= $form->renderField('date_fin') ?></div>
                </div>

                <?= $form->renderField('description') ?>
                <?= $form->renderField('id_article') ?>

                <div class="d-flex align-items-center gap-2">
                    <?= HTMLHelper::_('image', FileHelper::getHelloAssoLogoSrc(), 'HelloAsso', ['width' => '24', 'height' => '24']) ?>
                    <div class="flex-grow-1"><?= $form->renderField('event_helloasso') ?></div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <h3 class="h6"><?= Text::_('COM_GDA_SAISONS_GROUPES_TITLE') ?></h3>
                <?= LayoutHelper::render('saisons.groupes', ['groupes' => $groupes, 'activites' => $activites]) ?>
            </div>
        </div>

        <?= $form->renderField('id_campagne') ?>
        <?= HTMLHelper::_('form.token') ?>
        <input type="hidden" name="task" value="saisons.sauvegarderCourante" />

        <div class="d-flex justify-content-end mt-3">
            <button type="button" id="btnSaveSaisonCourante" class="btn btn-primary d-none">
                <span class="fa-solid fa-floppy-disk"></span> <?= Text::_('COM_GDA_SAISONS_SAVE_BUTTON') ?>
            </button>
        </div>
    </form>

    <!-- Modal de confirmation de sauvegarde -->
    <div class="modal fade" id="modalConfirmSaison" tabindex="-1" aria-labelledby="modalConfirmSaisonLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalConfirmSaisonLabel"><?= Text::_('COM_GDA_SAISONS_SAVE_CONFIRM_TITLE') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= Text::_('COM_GDA_SAISONS_SAVE_CONFIRM_MESSAGE') ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="btnConfirmSaveSaisonCourante" data-bs-dismiss="modal">
                        <?= Text::_('COM_GDA_SAVE') ?>
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('COM_GDA_CANCEL') ?></button>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>
