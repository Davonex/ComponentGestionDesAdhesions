<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * @var array $displayData
 * - $displayData['item'] : objet saison (campagne de type Saison), réutilisé par la liste
 *   de l'onglet Historique et par les réponses ajax toggleActive/toggleCourante.
 */

$item = $displayData['item'];

?>
<tr id="saison-<?= (int) $item->id_campagne ?>">
    <td><?= $this->escape($item->titre) ?></td>
    <td><?= HTMLHelper::_('date', $item->date_debut, 'd/m/Y') ?></td>
    <td><?= HTMLHelper::_('date', $item->date_fin, 'd/m/Y') ?></td>
    <td>
        <span class="badge <?= $item->active ? 'bg-success' : 'bg-secondary' ?> js-toggle-active-saison"
            role="button" tabindex="0" style="cursor:pointer"
            data-id-campagne="<?= (int) $item->id_campagne ?>"
            data-active="<?= $item->active ? '1' : '0' ?>"
            title="<?= Text::_('COM_GDA_SAISONS_TOGGLE_ACTIVE_HINT') ?>">
            <?= $item->active ? Text::_('COM_GDA_SAISONS_STATUS_OPEN') : Text::_('COM_GDA_SAISONS_STATUS_CLOSED') ?>
        </span>
    </td>
    <td>
        <button type="button"
            class="btn btn-sm <?= $item->courante ? 'btn-warning' : 'btn-outline-secondary' ?> js-toggle-courante-saison"
            data-id-campagne="<?= (int) $item->id_campagne ?>"
            data-courante="<?= $item->courante ? '1' : '0' ?>"
            data-titre="<?= $this->escape($item->titre) ?>"
            title="<?= Text::_('COM_GDA_SAISONS_TOGGLE_COURANTE_HINT') ?>">
            <span class="<?= $item->courante ? 'fa-solid' : 'fa-regular' ?> fa-star"></span>
            <?= $item->courante ? Text::_('COM_GDA_SAISONS_STATUS_CURRENT') : Text::_('COM_GDA_SAISONS_STATUS_NOT_CURRENT') ?>
        </button>
    </td>
</tr>
