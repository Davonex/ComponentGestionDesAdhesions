<?php

/**
 * Layout : onglet « Référentiel FFESSM » de la vue Brevets. Filtre par activité, recherche,
 * table simple-datatables du référentiel (#__gda_mapping_brevets) et ligne d'ajout.
 *
 * @var array $displayData
 * - $displayData['mappings']  : object[] lignes du référentiel
 * - $displayData['activites'] : string[] activités distinctes, pour le filtre et le formulaire
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var object[] $mappings */
$mappings = $displayData['mappings'];
/** @var string[] $activites */
$activites = $displayData['activites'];

// Options réutilisées par le filtre et par la ligne d'ajout : construites une seule fois.
$optionsActivite = array_map(
    static fn(string $activite) => HTMLHelper::_('select.option', $activite, $activite),
    $activites
);
?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="d-flex align-items-center gap-2">
        <label for="filterMappingActivite" class="form-label mb-0">
            <?= Text::_('COM_GDA_BREVETS_FILTER_ACTIVITE') ?>
        </label>
        <?= HTMLHelper::_(
            'select.genericlist',
            array_merge(
                [HTMLHelper::_('select.option', '', Text::_('COM_GDA_BREVETS_FILTER_ACTIVITE_ALL'))],
                $optionsActivite
            ),
            'filterMappingActivite',
            // genericlist dérive l'id du name : ne pas le repasser en attribut (id en double).
            ['class' => 'form-select form-select-sm w-auto'],
            'value',
            'text',
            ''
        ) ?>
    </div>
    <div class="d-flex align-items-center gap-2">
        <label for="filterMappingRole" class="form-label mb-0">
            <?= Text::_('COM_GDA_BREVETS_FILTER_ROLE') ?>
        </label>
        <?php
        /*
         * Les valeurs du filtre sont les libellés traduits, pas les codes : simple-datatables
         * filtre sur le TEXTE des cellules, et la colonne Rôle affiche le badge traduit.
         */
        ?>
        <select id="filterMappingRole" class="form-select form-select-sm w-auto">
            <option value=""><?= Text::_('COM_GDA_BREVETS_FILTER_ROLE_ALL') ?></option>
            <option value="<?= $this->escape(Text::_('COM_GDA_BREVETS_ROLE_PRATIQUANT')) ?>">
                <?= Text::_('COM_GDA_BREVETS_ROLE_PRATIQUANT') ?>
            </option>
            <option value="<?= $this->escape(Text::_('COM_GDA_BREVETS_ROLE_ENCADRANT')) ?>">
                <?= Text::_('COM_GDA_BREVETS_ROLE_ENCADRANT') ?>
            </option>
        </select>
    </div>
    <button type="button" class="btn btn-sm btn-outline-success ms-auto" id="btnAjouterMapping">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <?= Text::_('COM_GDA_BREVETS_MAPPING_ADD') ?>
    </button>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle gda-table-compact" id="tableMappingBrevets">
        <thead>
            <tr>
                <th><?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_LABEL') ?></th>
                <th title="<?= $this->escape(Text::_('COM_GDA_BREVETS_TABLE_HEADER_LABEL_AFFICHAGE_TOOLTIP')) ?>">
                    <?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_LABEL_AFFICHAGE') ?>
                    <i class="fa-solid fa-circle-info text-muted small" aria-hidden="true"></i>
                </th>
                <th><?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_ACTIVITE') ?></th>
                <th><?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_ROLE') ?></th>
                <th title="<?= $this->escape(Text::_('COM_GDA_BREVETS_TABLE_HEADER_CODE_TOOLTIP')) ?>">
                    <?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_CODE') ?>
                    <i class="fa-solid fa-circle-info text-muted small" aria-hidden="true"></i>
                </th>
                <th class="text-center"><?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_POIDS') ?></th>
                <th class="text-center" data-sortable="false"><?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_DELETE') ?></th>
            </tr>
        </thead>
        <tbody id="tbodyMappingBrevets">
            <?php foreach ($mappings as $mapping) : ?>
                <?= LayoutHelper::render('brevets.mapping_row', ['mapping' => $mapping]) ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!--
    Ligne de saisie d'une nouvelle correspondance : hors du <table> piloté par simple-datatables,
    qui réordonne et pagine son <tbody> — une ligne de formulaire y disparaîtrait au premier tri.
-->
<div class="border rounded p-3 mt-3 d-none" id="mappingAddRow">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1" for="newMappingLabel">
                <?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_LABEL') ?>
            </label>
            <input type="text" class="form-control form-control-sm" id="newMappingLabel" maxlength="150">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1" for="newMappingActivite">
                <?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_ACTIVITE') ?>
            </label>
            <?= HTMLHelper::_(
                'select.genericlist',
                $optionsActivite,
                'newMappingActivite',
                ['class' => 'form-select form-select-sm'],
                'value',
                'text',
                ''
            ) ?>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1" for="newMappingRole">
                <?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_ROLE') ?>
            </label>
            <select class="form-select form-select-sm" id="newMappingRole">
                <option value="pratiquant"><?= Text::_('COM_GDA_BREVETS_ROLE_PRATIQUANT') ?></option>
                <option value="encadrant"><?= Text::_('COM_GDA_BREVETS_ROLE_ENCADRANT') ?></option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small mb-1" for="newMappingCode">
                <?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_CODE') ?>
            </label>
            <input type="text" class="form-control form-control-sm" id="newMappingCode" maxlength="20">
        </div>
        <div class="col-md-1">
            <label class="form-label small mb-1" for="newMappingPoids">
                <?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_POIDS') ?>
            </label>
            <input type="number" class="form-control form-control-sm text-center" id="newMappingPoids"
                min="0" max="255" value="0">
        </div>
        <div class="col-md-1 d-flex gap-1 justify-content-end">
            <button type="button" class="btn btn-sm btn-success" id="btnSaveMapping"
                aria-label="<?= $this->escape(Text::_('COM_GDA_SAVE')) ?>">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCancelMapping"
                aria-label="<?= $this->escape(Text::_('COM_GDA_CANCEL')) ?>">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>
