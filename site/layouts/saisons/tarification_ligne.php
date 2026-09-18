<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Service\CotisationService;

/**
 * Une ligne du référentiel tarifaire. Rendue au chargement de l'onglet ET renvoyée seule par
 * SaisonsController::updateTarif() après chaque édition inline : tout ce que le tableau affiche
 * (montants formatés, aperçu du suffixe, badge d'activité) est calculé ici, côté serveur.
 *
 * @var array $displayData
 * - $displayData['tarif'] : object - ligne de #__gda_cotisation.
 */

$tarif = $displayData['tarif'];

$estLicence = $tarif->nature === CotisationService::NATURE_LICENCE;
$estActif   = (int) $tarif->actif === 1;

// Même règle que CotisationService::getLabel() : le suffixe n'apparaît que s'il apporte une
// information. L'afficher ici donne au Bureau un retour immédiat — saisir un tarif hors
// agglomération différent le fait apparaître.
$suffixeAffiche = '';

if (!$estLicence
    && number_format((float) $tarif->tarif_vy, 2, '.', '') !== number_format((float) $tarif->tarif_hvy, 2, '.', '')) {
    $suffixeAffiche = Text::_('COM_GDA_COTISATION_SUFFIXE_HORS_AGGLO');
}

$optionLabel = $tarif->reduction !== null
    ? CotisationService::getLibelleOption((int) $tarif->reduction)
    : '';

$hintEdition = Text::_('COM_GDA_SAISONS_TARIF_EDIT_HINT');
$hintOption  = Text::_('COM_GDA_SAISONS_TARIF_OPTION_EDIT_HINT');
$hintToggle  = Text::_('COM_GDA_SAISONS_TARIF_TOGGLE_HINT');

// Les champs de saisie portent la virgule décimale (ce que le Bureau tape), le service normalise
// en notation SQL à l'enregistrement.
$tarifVySaisie  = number_format((float) $tarif->tarif_vy, 2, ',', '');
$tarifHvySaisie = number_format((float) $tarif->tarif_hvy, 2, ',', '');
$commentaire    = (string) ($tarif->commentaire ?? '');

?>
<tr class="js-tarif-row<?= $estActif ? '' : ' table-inactive' ?>" id="tarif-<?= (int) $tarif->id_tarif ?>"
    data-id-tarif="<?= (int) $tarif->id_tarif ?>"
    data-nature="<?= $this->escape($tarif->nature) ?>">

    <!-- <td><code><?= $this->escape($tarif->code) ?></code></td> -->

    <td class="js-editable-tarif-libelle" style="cursor: pointer;" title="<?= $this->escape($hintEdition) ?>">
        <span class="tarif-libelle-display"><?= $this->escape($tarif->libelle) ?></span><?php if ($suffixeAffiche !== '') : ?><span class="text-muted small js-tarif-suffixe"><?= $this->escape($suffixeAffiche) ?></span><?php endif; ?>
        <input type="text" class="form-control form-control-sm tarif-libelle-input d-none"
               maxlength="255"
               aria-label="<?= $this->escape(Text::_('COM_GDA_SAISONS_TARIF_TH_LIBELLE')) ?>"
               value="<?= $this->escape($tarif->libelle) ?>"
               data-current-tarif-libelle="<?= $this->escape($tarif->libelle) ?>">
    </td>

    <?php if ($tarif->reduction === null) : ?>
        <?php
        // Une licence n'apparaît jamais dans la liste du formulaire (pas d'option) : la colonne
        // sert plutôt à distinguer visuellement les 3 lignes par catégorie d'âge (cible).
        $cibleLabels = [
            'ADULTE' => Text::_('COM_GDA_SAISONS_TARIF_CIBLE_ADULTE'),
            'JEUNE'  => Text::_('COM_GDA_SAISONS_TARIF_CIBLE_JEUNE'),
            'ENFANT' => Text::_('COM_GDA_SAISONS_TARIF_CIBLE_ENFANT'),
        ];
        $cibleAffichee = $cibleLabels[$tarif->cible] ?? '—';
        ?>
        <td class="text-muted small"><?= $this->escape($cibleAffichee) ?></td>
    <?php else : ?>
        <?php // Le libellé est partagé par toutes les lignes de même réduction (A/D, B/E) : le
              // serveur propage la modification et renvoie toutes les lignes concernées. ?>
        <td class="js-editable-tarif-option small" style="cursor: pointer;" title="<?= $this->escape($hintOption) ?>">
            <span class="tarif-option-display"><?= $this->escape($optionLabel) ?></span>
            <input type="text" class="form-control form-control-sm tarif-option-input d-none"
                   maxlength="100"
                   aria-label="<?= $this->escape(Text::_('COM_GDA_SAISONS_TARIF_TH_OPTION')) ?>"
                   value="<?= $this->escape($optionLabel) ?>"
                   data-current-tarif-option="<?= $this->escape($optionLabel) ?>">
        </td>
    <?php endif; ?>

    <td class="js-editable-tarif-vy text-center" style="cursor: pointer;" title="<?= $this->escape($hintEdition) ?>">
        <span class="tarif-vy-display"><?= $this->escape(CotisationService::formatMontant($tarif->tarif_vy)) ?></span>
        <input type="text" inputmode="decimal" class="form-control form-control-sm text-end tarif-vy-input d-none"
               aria-label="<?= $this->escape(Text::_('COM_GDA_SAISONS_TARIF_TH_VY')) ?>"
               value="<?= $this->escape($tarifVySaisie) ?>"
               data-current-tarif-vy="<?= $this->escape($tarifVySaisie) ?>">
    </td>

    <?php if ($estLicence) : ?>
        <?php // Une licence FFESSM ne dépend pas du lieu de résidence : une seule colonne éditable. ?>
        <td class="text-end text-muted" title="<?= $this->escape(Text::_('COM_GDA_SAISONS_TARIF_LICENCE_ZONE_UNIQUE')) ?>">—</td>
    <?php else : ?>
        <td class="js-editable-tarif-hvy text-center" style="cursor: pointer;" title="<?= $this->escape($hintEdition) ?>">
            <span class="tarif-hvy-display"><?= $this->escape(CotisationService::formatMontant($tarif->tarif_hvy)) ?></span>
            <input type="text" inputmode="decimal" class="form-control form-control-sm text-end tarif-hvy-input d-none"
                   aria-label="<?= $this->escape(Text::_('COM_GDA_SAISONS_TARIF_TH_HVY')) ?>"
                   value="<?= $this->escape($tarifHvySaisie) ?>"
                   data-current-tarif-hvy="<?= $this->escape($tarifHvySaisie) ?>">
        </td>
    <?php endif; ?>

    <td class="js-editable-tarif-commentaire" style="cursor: pointer;" title="<?= $this->escape($hintEdition) ?>">
        <span class="tarif-commentaire-display<?= $commentaire === '' ? ' text-muted' : '' ?>"><?= $commentaire === '' ? '—' : $this->escape($commentaire) ?></span>
        <input type="text" class="form-control form-control-sm tarif-commentaire-input d-none"
               maxlength="255"
               aria-label="<?= $this->escape(Text::_('COM_GDA_SAISONS_TARIF_TH_COMMENTAIRE')) ?>"
               value="<?= $this->escape($commentaire) ?>"
               data-current-tarif-commentaire="<?= $this->escape($commentaire) ?>">
    </td>

    <td class="text-center">
        <span class="badge <?= $estActif ? 'bg-success' : 'bg-secondary' ?> js-toggle-tarif-actif"
              role="button" tabindex="0" style="cursor:pointer"
              data-actif="<?= $estActif ? '1' : '0' ?>"
              title="<?= $this->escape($hintToggle) ?>">
            <?= Text::_($estActif ? 'COM_GDA_SAISONS_TARIF_ACTIF' : 'COM_GDA_SAISONS_TARIF_INACTIF') ?>
        </span>
    </td>
</tr>
