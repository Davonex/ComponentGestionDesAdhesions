<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Service\SuiviService;

/**
 * Contenu de la popup d'évaluation d'un élève pour une séance (#suiviEvaluationModal).
 * Une ligne par compétence : appréciation (liste) et observation. Sur téléphone, les trois
 * colonnes s'empilent. Une compétence évaluée par un autre moniteur est en lecture seule, sauf
 * pour un Responsable de Groupe.
 *
 * @var array $displayData
 * - $displayData['formulaire'] : object (SuiviModel::getFormulaireEvaluation())
 */

$formulaire = $displayData['formulaire'];
$eleve = $formulaire->eleve;
$nomComplet = trim($eleve->prenom . ' ' . $eleve->nom);
$dateSeance = Factory::getDate($formulaire->date_seance)->format('l d/m/Y', false, true);
$longueurMax = SuiviService::OBSERVATION_LONGUEUR_MAX;
?>
<form class="gda-suivi-form js-suivi-formulaire" novalidate>
    <input type="hidden" name="id_profil" value="<?= (int) $eleve->id_profil ?>">
    <input type="hidden" name="id_groupe" value="<?= (int) $formulaire->id_groupe ?>">
    <input type="hidden" name="date_seance" value="<?= $this->escape($formulaire->date_seance) ?>">

    <div class="modal-header">
        <div>
            <h5 class="modal-title mb-0"><?= $this->escape($nomComplet) ?></h5>
            <div class="small"><?= $this->escape(Text::sprintf('COM_GDA_SUIVI_SEANCE_DU', $dateSeance)) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
    </div>

    <div class="modal-body">
        <div class="row g-2 d-none d-md-flex fw-semibold border-bottom pb-2">
            <div class="col-md-4"><?= Text::_('COM_GDA_SUIVI_COMPETENCE') ?></div>
            <div class="col-md-3"><?= Text::_('COM_GDA_SUIVI_APPRECIATION') ?></div>
            <div class="col-md-5"><?= Text::_('COM_GDA_SUIVI_OBSERVATION') ?></div>
        </div>

        <?php foreach ($formulaire->competences as $competence) : ?>
            <?php
            $idCompetence = (int) $competence->id_competence;
            $evaluation = $competence->evaluation;
            $verrouille = !$competence->modifiable;
            $prefixe = 'competences[' . $idCompetence . ']';
            $idChamp = 'suivi-competence-' . $idCompetence;
            ?>
            <div class="row g-2 py-2 border-bottom gda-suivi-competence">
                <div class="col-12 col-md-4">
                    <label class="fw-semibold" for="<?= $idChamp ?>"><?= $this->escape($competence->competence) ?></label>
                    <?php if (!empty($competence->techniques)) : ?>
                        <?php $idTechniques = 'suivi-techniques-' . $idCompetence; ?>
                        <div>
                            <a class="small text-decoration-none gda-suivi-techniques-toggle" data-bs-toggle="collapse" href="#<?= $idTechniques ?>" role="button" aria-expanded="false" aria-controls="<?= $idTechniques ?>">
                                <i class="fa-solid fa-chevron-down me-1" aria-hidden="true"></i><?= Text::sprintf('COM_GDA_SUIVI_TECHNIQUES_VOIR', count($competence->techniques)) ?>
                            </a>
                        </div>
                        <div class="collapse" id="<?= $idTechniques ?>">
                            <ul class="small text-muted mb-1 ps-3">
                                <?php foreach ($competence->techniques as $technique) : ?>
                                    <li><?= $this->escape($technique) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if ($evaluation !== null) : ?>
                        <div class="small text-muted">
                            <?php if ($verrouille) : ?><i class="fa-solid fa-lock me-1" aria-hidden="true"></i><?php endif; ?>
                            <?= $this->escape(Text::sprintf(
                                'COM_GDA_SUIVI_EVALUE_PAR',
                                $evaluation->moniteur ?: Text::_('COM_GDA_SUIVI_MONITEUR_INCONNU'),
                                Factory::getDate($evaluation->date_evaluation)->format('d/m/Y', false, true)
                            )) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-3">
                    <select
                        class="form-select form-select-sm"
                        id="<?= $idChamp ?>"
                        name="<?= $prefixe ?>[appreciation]"
                        aria-label="<?= $this->escape(Text::_('COM_GDA_SUIVI_APPRECIATION')) ?>"
                        <?= $verrouille ? 'disabled' : '' ?>>
                        <?php // Une appréciation enregistrée ne s'efface pas : l'option vide n'est proposée qu'avant la première évaluation. ?>
                        <?php if ($evaluation === null) : ?>
                            <option value=""><?= Text::_('COM_GDA_SUIVI_APPRECIATION_AUCUNE') ?></option>
                        <?php endif; ?>
                        <?php foreach ($formulaire->appreciations as $valeur => $libelle) : ?>
                            <option value="<?= $this->escape($valeur) ?>"<?= $evaluation !== null && $evaluation->appreciation === $valeur ? ' selected' : '' ?>>
                                <?= $this->escape($libelle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <textarea
                        class="form-control form-control-sm"
                        name="<?= $prefixe ?>[observation]"
                        rows="2"
                        maxlength="<?= $longueurMax ?>"
                        placeholder="<?= $this->escape(Text::sprintf('COM_GDA_SUIVI_OBSERVATION_PLACEHOLDER', $longueurMax)) ?>"
                        aria-label="<?= $this->escape(Text::_('COM_GDA_SUIVI_OBSERVATION')) ?>"
                        <?= $verrouille ? 'disabled' : '' ?>><?= $this->escape((string) ($evaluation->observation ?? '')) ?></textarea>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="modal-footer">
        <button type="submit" class="btn btn-primary js-suivi-sauver">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_SUIVI_ENREGISTRER') ?>
        </button>
    </div>
</form>
