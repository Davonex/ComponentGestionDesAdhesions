<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * @var array $displayData
 * - $displayData['saisons']   : liste des saisons (campagnes de type Saison)
 * - $displayData['formAjout'] : Form d'ajout d'une saison
 */

$saisons = $displayData['saisons'];
$form    = $displayData['formAjout'];

?>

<div class="d-flex justify-content-end mb-3">
    <a class="btn btn-success" type="button" id="openFormAjoutSaison"
        data-bs-toggle="modal" data-bs-target="#modalAjoutSaison"
        title="<?= Text::_('COM_GDA_SAISONS_NEW_TOOLTIP') ?>" href="#">
        <span class="fa-solid fa-plus"></span> <?= Text::_('COM_GDA_SAISONS_NEW') ?>
    </a>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover align-middle" id="table-historique-saisons">
        <thead>
            <tr>
                <td><?= Text::_('COM_GDA_SAISONS_LIST_TITRE') ?></td>
                <td><?= Text::_('COM_GDA_SAISONS_LIST_DEBUT') ?></td>
                <td><?= Text::_('COM_GDA_SAISONS_LIST_FIN') ?></td>
                <td><?= Text::_('COM_GDA_SAISONS_LIST_ACTIVE') ?></td>
                <td><?= Text::_('COM_GDA_SAISONS_LIST_COURANTE') ?></td>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($saisons as $item) : ?>
                <?= LayoutHelper::render('saisons.ligne', ['item' => $item]) ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Ajouter une saison -->
<div class="modal fade" id="modalAjoutSaison" tabindex="-1" aria-labelledby="modalAjoutSaisonLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAjoutSaisonLabel"><?= Text::_('COM_GDA_SAISONS_NEW') ?></h5>
                <button type="button" id="closeModalAjoutSaison" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" name="form_ajout_saison" id="form_ajout_saison" class="form-validate">
                    <?= $form->renderField('titre') ?>
                    <div class="row g-1">
                        <div class="col-md-6"><?= $form->renderField('date_debut') ?></div>
                        <div class="col-md-6"><?= $form->renderField('date_fin') ?></div>
                    </div>
                    <input type="hidden" name="task" value="saisons.ajouter" />
                    <?= HTMLHelper::_('form.token') ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="saveAjoutSaison"
                    onclick="submitform(event,'form_ajout_saison',saisonsAjoutCB,'closeModalAjoutSaison')">
                    <span class="fa-solid fa-floppy-disk"></span> <?= Text::_('COM_GDA_SAVE') ?>
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('COM_GDA_CANCEL') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation avant de déclarer/retirer une saison courante -->
<div class="modal fade" id="modalConfirmCourante" tabindex="-1" aria-labelledby="modalConfirmCouranteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfirmCouranteLabel"><?= Text::_('COM_GDA_SAISONS_COURANTE_CONFIRM_TITLE') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="modalConfirmCouranteMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnConfirmToggleCourante" data-bs-dismiss="modal">
                    <?= Text::_('COM_GDA_SAVE') ?>
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('COM_GDA_CANCEL') ?></button>
            </div>
        </div>
    </div>
</div>
