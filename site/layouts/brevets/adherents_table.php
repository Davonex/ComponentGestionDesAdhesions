<?php

/**
 * Layout : onglet « Brevets des adhérents » de la vue Brevets. Compteur des brevets non
 * rattachés, filtres (statut / activité / recherche) et table simple-datatables.
 *
 * @var array $displayData
 * - $displayData['brevets']        : object[] tous les brevets, avec identité et mapping résolu
 * - $displayData['mappings']       : array<string, object[]> référentiel complet **groupé par
 *                                    activité**, pour l'éditeur de rattachement rendu en
 *                                    <optgroup> (HtmlView::$mappingsParActivite)
 * - $displayData['activites']      : string[] activités distinctes
 * - $displayData['nbNonRattaches'] : int nombre de brevets sans correspondance
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var object[] $brevets */
$brevets = $displayData['brevets'];
/** @var object[] $mappings */
$mappings = $displayData['mappings'];
/** @var string[] $activites */
$activites = $displayData['activites'];
$nbNonRattaches = (int) $displayData['nbNonRattaches'];
?>

<?php if ($nbNonRattaches > 0) : ?>
    <div class="alert alert-warning d-flex align-items-center gap-2" role="status">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span><?= Text::plural('COM_GDA_BREVETS_COUNT_NON_RATTACHES', $nbNonRattaches) ?></span>
    </div>
<?php else : ?>
    <div class="alert alert-success d-flex align-items-center gap-2" role="status">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span><?= Text::_('COM_GDA_BREVETS_COUNT_TOUS_RATTACHES') ?></span>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="d-flex align-items-center gap-2">
        <label for="filterBrevetStatut" class="form-label mb-0">
            <?= Text::_('COM_GDA_BREVETS_FILTER_STATUT') ?>
        </label>
        <select id="filterBrevetStatut" class="form-select form-select-sm w-auto">
            <option value=""><?= Text::_('COM_GDA_BREVETS_FILTER_STATUT_ALL') ?></option>
            <option value="map:oui"><?= Text::_('COM_GDA_BREVETS_FILTER_STATUT_MAPPED') ?></option>
            <option value="map:non"><?= Text::_('COM_GDA_BREVETS_FILTER_STATUT_UNMAPPED') ?></option>
        </select>
    </div>
    <div class="d-flex align-items-center gap-2">
        <label for="filterBrevetActivite" class="form-label mb-0">
            <?= Text::_('COM_GDA_BREVETS_FILTER_ACTIVITE') ?>
        </label>
        <?= HTMLHelper::_(
            'select.genericlist',
            array_merge(
                [HTMLHelper::_('select.option', '', Text::_('COM_GDA_BREVETS_FILTER_ACTIVITE_ALL'))],
                array_map(
                    static fn(string $activite) => HTMLHelper::_('select.option', 'act:' . $activite, $activite),
                    $activites
                )
            ),
            'filterBrevetActivite',
            // genericlist dérive l'id du name : ne pas le repasser en attribut (id en double).
            ['class' => 'form-select form-select-sm w-auto'],
            'value',
            'text',
            ''
        ) ?>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle gda-table-compact" id="tableBrevetsAdherents">
        <thead>
            <tr>
                <th><?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_ADHERENT') ?></th>
                <th><?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_NOM_SAISI') ?></th>
                <th><?= Text::_('COM_GDA_BREVETS_TABLE_HEADER_LABEL') ?></th>
            </tr>
        </thead>
        <tbody id="tbodyBrevetsAdherents">
            <?php foreach ($brevets as $brevet) : ?>
                <?= LayoutHelper::render('brevets.adherents_row', ['brevet' => $brevet]) ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!--
    Éditeur de rattachement unique, déplacé dans la cellule au double-clic par brevets_mgt.js.
    Rendu une seule fois : dupliqué sur chaque ligne, il produirait des dizaines de milliers
    d'<option> pour un seul utilisé à la fois.

    La sélection ne déclenche AUCUN enregistrement : la liste est longue, une erreur de clic est
    vite arrivée et écraserait le libellé saisi par l'adhérent. La sauvegarde passe donc par le
    bouton dédié, comme sur l'onglet Référentiel.
-->
<div class="input-group input-group-sm d-none" id="mappingEditor">
    <?php
    /*
     * Liste groupée en <optgroup> par activité, et TOUJOURS complète : elle n'est pas restreinte
     * par le filtre Activité du tableau. Un brevet non rattaché n'a précisément aucune activité,
     * filtrer la liste ferait donc disparaître les entrées nécessaires pour le corriger.
     */
    ?>
    <select class="form-select form-select-sm" id="mappingEditorSelect">
        <option value=""><?= Text::_('COM_GDA_BREVETS_MAPPING_SELECT_EMPTY') ?></option>
        <?php foreach ($mappings as $activite => $mappingsActivite) : ?>
            <optgroup label="<?= $this->escape($activite) ?>">
                <?php foreach ($mappingsActivite as $mapping) : ?>
                    <option value="<?= (int) $mapping->id ?>">
                        <?= $this->escape($mapping->label_ffessm) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-success" id="mappingEditorSave"
        title="<?= $this->escape(Text::_('COM_GDA_SAVE')) ?>"
        aria-label="<?= $this->escape(Text::_('COM_GDA_SAVE')) ?>">
        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
    </button>
    <button type="button" class="btn btn-outline-secondary" id="mappingEditorCancel"
        title="<?= $this->escape(Text::_('COM_GDA_CANCEL')) ?>"
        aria-label="<?= $this->escape(Text::_('COM_GDA_CANCEL')) ?>">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
</div>
