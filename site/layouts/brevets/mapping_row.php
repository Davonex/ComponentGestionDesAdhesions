<?php

/**
 * Layout : une ligne du référentiel FFESSM (onglet « Référentiel » de la vue Brevets).
 *
 * Rendu au chargement de la page ET renvoyé seul par BrevetsController::saveMapping() pour
 * remplacer la ligne de saisie après un ajout — d'où l'isolement dans son propre layout.
 *
 * @var array $displayData
 * - $displayData['mapping'] : object {id, code, activite, role, label_ffessm, label_affichage, poids}
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var object $mapping */
$mapping = $displayData['mapping'];

$roleLabel = $mapping->role === 'encadrant'
    ? Text::_('COM_GDA_BREVETS_ROLE_ENCADRANT')
    : Text::_('COM_GDA_BREVETS_ROLE_PRATIQUANT');
?>
<tr class="js-mapping-row" data-id-mapping="<?= (int) $mapping->id ?>">
    <td><?= $this->escape($mapping->label_ffessm) ?></td>
    <td class="js-editable-label-affichage" title="<?= $this->escape(Text::_('COM_GDA_BREVETS_EDIT_HINT')) ?>">
        <span class="label-affichage-display"><?= $this->escape((string) ($mapping->label_affichage ?? '')) ?></span>
        <input type="text" class="form-control form-control-sm label-affichage-input d-none"
            maxlength="150"
            value="<?= $this->escape((string) ($mapping->label_affichage ?? '')) ?>"
            data-current-label-affichage="<?= $this->escape((string) ($mapping->label_affichage ?? '')) ?>">
    </td>
    <td><?= $this->escape($mapping->activite) ?></td>
    <td>
        <?php // Mêmes couleurs de rôle que les badges de brevets de la vue Utilisateurs. ?>
        <span class="badge bg-<?= $this->escape($mapping->role) ?>">
            <?= $this->escape($roleLabel) ?>
        </span>
    </td>
    <td class="js-editable-code" title="<?= $this->escape(Text::_('COM_GDA_BREVETS_EDIT_HINT')) ?>">
        <span class="code-display"><?= $this->escape($mapping->code) ?></span>
        <input type="text" class="form-control form-control-sm code-input d-none"
            maxlength="20"
            value="<?= $this->escape($mapping->code) ?>"
            data-current-code="<?= $this->escape($mapping->code) ?>">
    </td>
    <td class="js-editable-poids text-center" title="<?= $this->escape(Text::_('COM_GDA_BREVETS_EDIT_HINT')) ?>">
        <span class="poids-display"><?= (int) $mapping->poids ?></span>
        <input type="number" class="form-control form-control-sm poids-input d-none text-center"
            min="0" max="255"
            value="<?= (int) $mapping->poids ?>"
            data-current-poids="<?= (int) $mapping->poids ?>">
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger js-delete-mapping"
            data-label="<?= $this->escape($mapping->label_ffessm) ?>"
            aria-label="<?= $this->escape(Text::_('COM_GDA_BREVETS_MAPPING_DELETE')) ?>">
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
        </button>
    </td>
</tr>
