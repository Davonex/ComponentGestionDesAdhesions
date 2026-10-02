<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/**
 * Onglet « Compétences » de la vue Suivi (Responsables de Groupe) : référentiel des compétences et
 * de leurs techniques par niveau. Filtre par niveau et ajout d'une ligne en haut ; chaque cellule
 * se modifie au double-clic avec sauvegarde automatique (suivi.js).
 *
 * @var array $displayData
 * - $displayData['referentiel'] : object{groupes, competences, id_groupe_defaut} (SuiviModel::getReferentielCompetences())
 */

$referentiel = $displayData['referentiel'];
$idGroupeDefaut = (int) $referentiel->id_groupe_defaut;
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <label class="form-label mb-0" for="suiviCompetencesFiltre"><?= Text::_('COM_GDA_SUIVI_NIVEAU') ?></label>
    <select class="form-select form-select-sm w-auto js-suivi-competences-filtre" id="suiviCompetencesFiltre">
        <option value=""><?= Text::_('COM_GDA_SUIVI_NIVEAU_TOUS') ?></option>
        <?php foreach ($referentiel->groupes as $groupe) : ?>
            <option value="<?= (int) $groupe->id_groupe ?>"<?= (int) $groupe->id_groupe === $idGroupeDefaut ? ' selected' : '' ?>>
                <?= $this->escape($groupe->groupe_name) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-sm btn-primary js-suivi-ajouter-competence" title="<?= $this->escape(Text::_('COM_GDA_SUIVI_COMPETENCE_AJOUTER_AIDE')) ?>">
        <i class="fa-solid fa-plus me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_SUIVI_COMPETENCE_AJOUTER') ?>
    </button>
    <span class="small text-muted ms-md-auto"><?= Text::_('COM_GDA_SUIVI_COMPETENCES_AIDE') ?></span>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0 gda-suivi-competences">
        <thead class="table-light">
            <tr>
                <th scope="col"><?= Text::_('COM_GDA_SUIVI_NIVEAU') ?></th>
                <th scope="col"><?= Text::_('COM_GDA_SUIVI_COMPETENCE') ?></th>
                <th scope="col"><?= Text::_('COM_GDA_SUIVI_TECHNIQUES') ?></th>
                <th scope="col" class="text-center"><?= Text::_('COM_GDA_SUIVI_ORDRE') ?></th>
                <th scope="col" class="text-center"><?= Text::_('COM_GDA_SUIVI_ACTIF') ?></th>
            </tr>
        </thead>
        <tbody class="js-suivi-competences-lignes">
            <?php foreach ($referentiel->competences as $competence) : ?>
                <?= LayoutHelper::render('suivi.competence_ligne', ['competence' => $competence, 'groupes' => $referentiel->groupes]) ?>
            <?php endforeach; ?>
            <tr class="js-suivi-competences-vide d-none">
                <td colspan="5" class="text-muted text-center"><?= Text::_('COM_GDA_SUIVI_COMPETENCES_AUCUNE') ?></td>
            </tr>
        </tbody>
    </table>
</div>
