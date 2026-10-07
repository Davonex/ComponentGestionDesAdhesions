<?php

/**
 * Layout : popup d'export Excel de l'onglet "Profils" de la vue Utilisateurs. Formulaire POST
 * classique (téléchargement) vers la task utilisateurs.exportProfils ; les identifiants des lignes
 * filtrées (ids[]) sont ajoutés au moment de l'envoi par utilisateurs.js.
 *
 * @var array $displayData
 * - $displayData['champs'] : array groupes de colonnes (voir ExportProfilsService::getChampsDisponibles())
 * - $displayData['itemId'] : int menu courant (retour sur la page en cas d'erreur)
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$groupes = $displayData['champs'] ?? [];
$itemId = (int) ($displayData['itemId'] ?? 0);
?>
<div class="modal fade" id="exportProfilsModal" tabindex="-1" aria-labelledby="exportProfilsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-md-down">
        <form class="modal-content" id="exportProfilsForm" method="post"
            action="<?= $this->escape(Uri::base() . 'index.php?option=com_gdadhesions') ?>">
            <div class="modal-header bg-gda-header text-header">
                <h5 class="modal-title mb-0" id="exportProfilsModalTitle">
                    <i class="fa-solid fa-file-excel me-2" aria-hidden="true"></i>
                    <?= Text::_('COM_GDA_UTILISATEURS_EXPORT_MODAL_TITLE') ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <?= Text::_('COM_GDA_UTILISATEURS_EXPORT_MODAL_INTRO') ?>
                    <strong class="js-export-nb-lignes"></strong>
                </p>
                <div class="row g-3">
                    <?php foreach ($groupes as $codeGroupe => $groupe) : ?>
                        <fieldset class="col-12 col-md-4 js-export-groupe">
                            <legend class="fs-6 fw-semibold d-flex align-items-center gap-2">
                                <input type="checkbox" class="form-check-input mt-0 js-export-tout-cocher"
                                    id="exportGroupe<?= $this->escape($codeGroupe) ?>"
                                    title="<?= $this->escape(Text::_('COM_GDA_UTILISATEURS_EXPORT_TOUT_COCHER')) ?>">
                                <label for="exportGroupe<?= $this->escape($codeGroupe) ?>"><?= $this->escape($groupe['label']) ?></label>
                            </legend>
                            <?php foreach ($groupe['champs'] as $cle => $libelle) : ?>
                                <div class="form-check">
                                    <input class="form-check-input js-export-champ" type="checkbox" name="champs[]"
                                        value="<?= $this->escape($cle) ?>" id="exportChamp_<?= $this->escape($cle) ?>">
                                    <label class="form-check-label" for="exportChamp_<?= $this->escape($cle) ?>"><?= $this->escape($libelle) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
                <div class="alert alert-warning d-none mt-3 mb-0 js-export-erreur" role="alert"></div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="task" value="utilisateurs.exportProfils">
                <input type="hidden" name="Itemid" value="<?= $itemId ?>">
                <?= HTMLHelper::_('form.token') ?>
                <div class="js-export-ids"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('COM_GDA_CANCEL') ?></button>
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-download me-1" aria-hidden="true"></i>
                    <?= Text::_('COM_GDA_UTILISATEURS_EXPORT_SUBMIT') ?>
                </button>
            </div>
        </form>
    </div>
</div>
