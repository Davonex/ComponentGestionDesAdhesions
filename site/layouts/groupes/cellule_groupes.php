<?php

/**
 * Layout : cellule « Groupes » d'un adhérent (onglet « Tous les groupes » de la vue Groupes).
 * Rendue par groupes.detail et, après modification, par GroupesController::updateGroupesAdherent().
 *
 * @var array $displayData
 * - $displayData['adherent'] : object{id_profil, groupes: object[]{id_groupe, groupe_name, icon},
 *   licence_seule?: bool (adhérent sans groupe ayant choisi l'option « Licence seule »)}
 * - $displayData['editable'] : bool, cellule modifiable au clic (Responsables de Groupe, voir groupes.js)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$adherent = $displayData['adherent'];
$editable = $displayData['editable'] ?? false;
$groupes = $adherent->groupes ?? [];
?>
<td
    class="col-secretariat-md<?= $editable ? ' js-editable-groupes' : '' ?>"
    <?php if ($editable) : ?>
    data-id-profil="<?= (int) $adherent->id_profil ?>"
    data-id-groupes="<?= $this->escape(implode(',', array_column($groupes, 'id_groupe'))) ?>"
    title="<?= $this->escape(Text::_('COM_GDA_GROUPES_COMPOSITION_EDIT_HINT')) ?>"
    tabindex="0"
    <?php endif; ?>>
    <span class="js-groupes-affichage">
        <?php if (empty($groupes) && !empty($adherent->licence_seule)) : ?>
            <span class="badge text-bg-secondary"><?= Text::_('COM_GDA_GROUPES_LICENCE_SEULE') ?></span>
        <?php elseif (empty($groupes)) : ?>
            <span class="text-muted">&mdash;</span>
        <?php else : ?>
            <?php foreach ($groupes as $groupe) : ?>
                <span class="badge gda-badge-groupe me-1 mb-1">
                    <?php if (!empty($groupe->icon)) : ?>
                        <i class="fa-solid <?= $this->escape($groupe->icon) ?> me-1" aria-hidden="true"></i>
                    <?php endif; ?>
                    <?= $this->escape($groupe->groupe_name) ?>
                </span>
            <?php endforeach; ?>
        <?php endif; ?>
    </span>
</td>
