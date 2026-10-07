<?php

/**
 * Layout : contenu d'un onglet de la vue Groupes (export PDF, vue Détail et vue Vignette), injecté
 * dans son volet par groupes.js. Rendu par GroupesController::onglet().
 *
 * @var array $displayData
 * - $displayData['groupe']              : object{id_groupe, groupe_name, icon, adherents} (GroupesModel::getOngletGroupe())
 * - $displayData['peutModifierGroupes'] : bool, colonne Groupes modifiable (Responsables de Groupe)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Model\GroupesModel;

$groupe = $displayData['groupe'];
$paneId = 'groupe-pane-' . (int) $groupe->id_groupe;
?>
<div class="d-none d-md-flex justify-content-end mb-2">
    <button type="button" class="btn btn-sm btn-outline-secondary js-groupe-export-pdf" data-target="#<?= $paneId ?> table">
        <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_GROUPES_EXPORT_PDF') ?>
    </button>
</div>

<div class="gda-groupes-view gda-groupes-view--detail" data-view-mode="detail">
    <?= LayoutHelper::render('groupes.detail', [
        'groupe'              => $groupe,
        // Colonne Groupes sur les onglets virtuels « Tous les groupes » (0) et « Sans groupe » (-1).
        'showGroupes'         => $groupe->id_groupe <= GroupesModel::ID_GROUPE_TOUS,
        'peutModifierGroupes' => $displayData['peutModifierGroupes'] ?? false,
    ]) ?>
</div>
<div class="gda-groupes-view gda-groupes-view--vignette" data-view-mode="vignette">
    <?= LayoutHelper::render('groupes.vignette', ['groupe' => $groupe]) ?>
</div>
