<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Service\CotisationService;

/**
 * Onglet « Tarification » de la vue Saisons (réservé au Bureau).
 *
 * @var array $displayData
 * - $displayData['tarifs'] : object[] - référentiel complet (CotisationService::getLignesAdmin()),
 *   lignes inactives comprises, triées par `ordre`.
 */

$tarifs = $displayData['tarifs'] ?? [];

// Les deux natures sont présentées dans un même tableau, mais dans deux <tbody> distincts : les
// licences FFESSM n'ont ni option de formulaire ni tarif hors agglomération, les mélanger aux
// cotisations club rendrait ces colonnes vides incompréhensibles.
$cotisations = [];
$licences = [];

foreach ($tarifs as $tarif) {
    if ($tarif->nature === CotisationService::NATURE_LICENCE) {
        $licences[] = $tarif;
        continue;
    }

    $cotisations[] = $tarif;
}

?>

<div id="saisons-tarification-panel">

    <p class="text-muted small"><?= Text::_('COM_GDA_SAISONS_TARIF_INTRO') ?></p>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="table-tarifs">
            <thead>
                <tr>
                    <!-- <th class="text-center" style="width:8%"><?= Text::_('COM_GDA_SAISONS_TARIF_TH_CODE') ?></th> -->
                    <th><?= Text::_('COM_GDA_SAISONS_TARIF_TH_LIBELLE') ?></th>
                    <th class="text-center" style="width:16%"><?= Text::_('COM_GDA_SAISONS_TARIF_TH_OPTION') ?></th>
                    <th class="text-center" style="width:11%"><?= Text::_('COM_GDA_SAISONS_TARIF_TH_VY') ?></th>
                    <th class="text-center" style="width:11%"><?= Text::_('COM_GDA_SAISONS_TARIF_TH_HVY') ?></th>
                    <th class="text-center" style="width:15%"><?= Text::_('COM_GDA_SAISONS_TARIF_TH_COMMENTAIRE') ?></th>
                    <th class="text-center" style="width:10%"><?= Text::_('COM_GDA_SAISONS_TARIF_TH_ACTIF') ?></th>
                </tr>
            </thead>
            <tbody id="tbody-tarifs-cotisation">
                <?php foreach ($cotisations as $tarif) : ?>
                    <?= LayoutHelper::render('saisons.tarification_ligne', ['tarif' => $tarif]) ?>
                <?php endforeach; ?>
            </tbody>
            <tbody id="tbody-tarifs-licence">
                <tr class="table-light">
                    <th colspan="7" class="fw-semibold"><?= Text::_('COM_GDA_SAISONS_TARIF_SECTION_LICENCES') ?></th>
                </tr>
                <?php foreach ($licences as $tarif) : ?>
                    <?= LayoutHelper::render('saisons.tarification_ligne', ['tarif' => $tarif]) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
