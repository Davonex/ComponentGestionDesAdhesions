<?php

use Joomla\CMS\Language\Text;

/**
 * @var array $displayData
 * - $displayData['groupes'] : liste des groupes du club (id_groupe, groupe_name, groupe_tri, icon, published)
 *
 * Panneau de gestion des groupes du club (table globale #__gda_groupes, indépendante de la
 * saison). Réutilisé au chargement initial de la page ET pour rafraîchir le panneau après une
 * sauvegarde ajax réussie (voir SaisonsController::sauvegarderCourante()).
 */

$groupes = $displayData['groupes'];

?>
<div id="saisons-groupes-panel">
    <table class="table table-sm align-middle" id="table-groupes-club">
        <thead>
            <tr>
                <th><?= Text::_('COM_GDA_SAISONS_GROUPES_NAME') ?></th>
                <th class="text-center" style="width:6rem"><?= Text::_('COM_GDA_SAISONS_GROUPES_TRI') ?></th>
                <th class="text-center"><?= Text::_('COM_GDA_SAISONS_GROUPES_ICON') ?></th>
                <th class="text-center" style="width:6rem"><?= Text::_('COM_GDA_SAISONS_GROUPES_PUBLISHED') ?></th>
            </tr>
        </thead>
        <tbody id="tbody-groupes-club">
            <?php foreach ($groupes as $index => $groupe) : ?>
                <tr class="js-groupe-row">
                    <td>
                        <input type="hidden" name="groupes[<?= $index ?>][id_groupe]" value="<?= (int) $groupe->id_groupe ?>">
                        <input type="text" class="form-control form-control-sm"
                            name="groupes[<?= $index ?>][groupe_name]"
                            value="<?= $this->escape($groupe->groupe_name) ?>">
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm text-center"
                            name="groupes[<?= $index ?>][groupe_tri]"
                            value="<?= (int) $groupe->groupe_tri ?>">
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                           
                            <input type="text" class="form-control form-control-sm js-groupe-icon-input"
                                name="groupes[<?= $index ?>][icon]"
                                placeholder="fa-water"
                                value="<?= $this->escape((string) $groupe->icon) ?>">
                             <i class="fa-solid js-groupe-icon-preview <?= $this->escape((string) $groupe->icon) ?>"
                                style="width:1.25rem" aria-hidden="true"></i>
                        </div>
                    </td>
                    <td class="text-center">
                        <input type="hidden" name="groupes[<?= $index ?>][published]" value="0">
                        <input type="checkbox" class="form-check-input" value="1"
                            name="groupes[<?= $index ?>][published]"
                            <?= $groupe->published ? 'checked' : '' ?>>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <button type="button" class="btn btn-sm btn-outline-success" id="btnAjouterGroupe">
        <span class="fa-solid fa-plus"></span> <?= Text::_('COM_GDA_SAISONS_GROUPES_ADD') ?>
    </button>

    <!-- Modèle de ligne vide, cloné par saisons.js au clic sur "Ajouter un groupe" -->
    <template id="tpl-groupe-row">
        <tr class="js-groupe-row">
            <td>
                <input type="hidden" name="groupes[__INDEX__][id_groupe]" value="0">
                <input type="text" class="form-control form-control-sm" name="groupes[__INDEX__][groupe_name]" value="">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm text-center" name="groupes[__INDEX__][groupe_tri]" value="0">
            </td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid js-groupe-icon-preview" style="width:1.25rem" aria-hidden="true"></i>
                    <input type="text" class="form-control form-control-sm js-groupe-icon-input" name="groupes[__INDEX__][icon]" placeholder="fa-water" value="">
                </div>
            </td>
            <td class="text-center">
                <input type="hidden" name="groupes[__INDEX__][published]" value="0">
                <input type="checkbox" class="form-check-input" value="1" name="groupes[__INDEX__][published]">
            </td>
        </tr>
    </template>
</div>
