<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Service\SuiviService;

/**
 * Une ligne du référentiel des compétences. Rendue au chargement de l'onglet ET renvoyée seule
 * après chaque ajout ou modification (SuiviController::ajouterCompetence()/modifierCompetence()).
 *
 * Chaque cellule .js-editable-competence porte le champ modifié (data-champ) ; double-clic =
 * édition, sortie du champ = sauvegarde (suivi.js). Le switch Actif enregistre au changement.
 *
 * @var array $displayData
 * - $displayData['competence'] : object (SuiviService::getReferentielCompetences())
 * - $displayData['groupes']    : array<int, object> niveaux proposés {id_groupe, groupe_name}
 */

$competence = $displayData['competence'];
$groupes = $displayData['groupes'];
$idCompetence = (int) $competence->id_competence;
$estActive = (int) $competence->actif === 1;
$aide = Text::_('COM_GDA_SUIVI_COMPETENCE_EDITER_AIDE');
?>
<tr class="js-suivi-competence-ligne<?= $estActive ? '' : ' table-secondary text-muted' ?>"
    data-id-competence="<?= $idCompetence ?>"
    data-id-groupe="<?= (int) $competence->id_groupe ?>">

    <td class="js-editable-competence" data-champ="id_groupe" title="<?= $this->escape($aide) ?>">
        <span class="js-affichage"><?= $this->escape($competence->groupe_name) ?></span>
        <select class="form-select form-select-sm d-none js-edition" aria-label="<?= $this->escape(Text::_('COM_GDA_SUIVI_NIVEAU')) ?>"
                data-valeur-initiale="<?= (int) $competence->id_groupe ?>">
            <?php foreach ($groupes as $groupe) : ?>
                <option value="<?= (int) $groupe->id_groupe ?>"<?= (int) $groupe->id_groupe === (int) $competence->id_groupe ? ' selected' : '' ?>>
                    <?= $this->escape($groupe->groupe_name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </td>

    <td class="js-editable-competence" data-champ="competence" title="<?= $this->escape($aide) ?>">
        <span class="js-affichage"><?= $this->escape($competence->competence) ?></span>
        <input type="text" class="form-control form-control-sm d-none js-edition"
               maxlength="<?= SuiviService::COMPETENCE_LONGUEUR_MAX ?>"
               aria-label="<?= $this->escape(Text::_('COM_GDA_SUIVI_COMPETENCE')) ?>"
               value="<?= $this->escape($competence->competence) ?>"
               data-valeur-initiale="<?= $this->escape($competence->competence) ?>">
    </td>

    <td class="js-editable-competence" data-champ="techniques" title="<?= $this->escape(Text::_('COM_GDA_SUIVI_TECHNIQUES_EDITER_AIDE')) ?>">
        <div class="js-affichage">
            <?php if (empty($competence->liste_techniques)) : ?>
                <span class="text-muted">&ndash;</span>
            <?php else : ?>
                <ul class="small mb-0 ps-3">
                    <?php foreach ($competence->liste_techniques as $technique) : ?>
                        <li><?= $this->escape($technique) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <textarea class="form-control form-control-sm d-none js-edition" rows="4"
                  maxlength="<?= SuiviService::TECHNIQUES_LONGUEUR_MAX ?>"
                  aria-label="<?= $this->escape(Text::_('COM_GDA_SUIVI_TECHNIQUES')) ?>"
                  data-valeur-initiale="<?= $this->escape((string) $competence->techniques) ?>"><?= $this->escape((string) $competence->techniques) ?></textarea>
    </td>

    <td class="text-center js-editable-competence" data-champ="ordre" title="<?= $this->escape($aide) ?>">
        <span class="js-affichage"><?= (int) $competence->ordre ?></span>
        <input type="number" class="form-control form-control-sm d-none js-edition gda-suivi-ordre" min="0" max="9999"
               aria-label="<?= $this->escape(Text::_('COM_GDA_SUIVI_ORDRE')) ?>"
               value="<?= (int) $competence->ordre ?>"
               data-valeur-initiale="<?= (int) $competence->ordre ?>">
    </td>

    <td class="text-center">
        <div class="form-check form-switch d-inline-block mb-0">
            <input class="form-check-input js-suivi-competence-actif" type="checkbox" role="switch"
                   aria-label="<?= $this->escape(Text::_('COM_GDA_SUIVI_ACTIF')) ?>"
                   <?= $estActive ? 'checked' : '' ?>>
        </div>
    </td>
</tr>
