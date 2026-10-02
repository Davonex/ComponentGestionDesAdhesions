<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Service\SuiviService;

/**
 * Contenu de la popup « bilan » d'un élève (#suiviBilanModal) : une ligne par
 * compétence, une colonne par séance où l'élève a été évalué, une icône d'appréciation au
 * croisement. Le survol (ou le toucher sur téléphone) de l'icône affiche une infobulle avec le
 * moniteur en gras et l'observation (infobulles initialisées par suivi.js).
 *
 * @var array $displayData
 * - $displayData['bilan'] : object (SuiviModel::getBilanEleve())
 */

$bilan = $displayData['bilan'];
$eleve = $bilan->eleve;
$nomComplet = trim($eleve->prenom . ' ' . $eleve->nom);

// Icône et couleur de chaque appréciation, dans l'ordre de progression.
$icones = [
    SuiviService::APPRECIATION_EN_COURS => 'fa-hourglass-half text-warning',
    SuiviService::APPRECIATION_ACQUIS   => 'fa-circle-check text-success',
    SuiviService::APPRECIATION_MAITRISE => 'fa-star text-primary',
];
?>
<div class="modal-header">
    <div>
        <h5 class="modal-title mb-0"><?= $this->escape($nomComplet) ?></h5>
        <div class="small"><?= Text::_('COM_GDA_SUIVI_BILAN_TITRE') ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
</div>

<div class="modal-body">
    <?php if (empty($bilan->seances)) : ?>
        <p class="text-muted mb-0"><?= Text::_('COM_GDA_SUIVI_BILAN_AUCUNE_EVALUATION') ?></p>
    <?php else : ?>
        <div class="table-responsive gda-suivi-scroll">
            <table class="table table-sm table-bordered align-middle mb-0 gda-suivi-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="gda-suivi-col-eleve"><?= Text::_('COM_GDA_SUIVI_COMPETENCE') ?></th>
                        <?php foreach ($bilan->seances as $dateSeance) : ?>
                            <?php $date = Factory::getDate($dateSeance); ?>
                            <th scope="col" class="text-center text-nowrap" title="<?= $this->escape($date->format('l d/m/Y', false, true)) ?>">
                                <?= $this->escape($date->format('D d/m', false, true)) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bilan->competences as $competence) : ?>
                        <?php $parSeance = $bilan->evaluations[(int) $competence->id_competence] ?? []; ?>
                        <tr>
                            <th scope="row" class="gda-suivi-col-eleve fw-normal">
                                <?php if (empty($competence->techniques)) : ?>
                                    <?= $this->escape($competence->competence) ?>
                                <?php else : ?>
                                    <?php
                                    // Techniques de la compétence en infobulle (même double échappement que les appréciations).
                                    $infobulle = '<ul class="text-start mb-0 ps-3">';

                                    foreach ($competence->techniques as $technique) {
                                        $infobulle .= '<li>' . $this->escape($technique) . '</li>';
                                    }

                                    $infobulle .= '</ul>';
                                    ?>
                                    <button
                                        type="button"
                                        class="btn btn-link p-0 text-reset text-start text-decoration-none fw-normal js-suivi-infobulle"
                                        data-bs-toggle="tooltip"
                                        data-bs-html="true"
                                        data-bs-title="<?= $this->escape($infobulle) ?>"
                                        aria-label="<?= $this->escape($competence->competence . ' : ' . implode(', ', $competence->techniques)) ?>">
                                        <?= $this->escape($competence->competence) ?>
                                        <i class="fa-solid fa-circle-info text-muted ms-1" aria-hidden="true"></i>
                                    </button>
                                <?php endif; ?>
                            </th>
                            <?php foreach ($bilan->seances as $dateSeance) : ?>
                                <td class="text-center">
                                    <?php $evaluation = $parSeance[$dateSeance] ?? null; ?>
                                    <?php if ($evaluation === null) : ?>
                                        <span class="text-muted" aria-hidden="true">&ndash;</span>
                                    <?php else : ?>
                                        <?php
                                        $libelle = $bilan->appreciations[$evaluation->appreciation] ?? $evaluation->appreciation;
                                        $moniteur = $evaluation->moniteur ?: Text::_('COM_GDA_SUIVI_MONITEUR_INCONNU');
                                        $observation = trim((string) $evaluation->observation);

                                        // Contenu HTML de l'infobulle : chaque valeur est échappée ici, puis
                                        // l'ensemble une seconde fois pour l'attribut (data-bs-html).
                                        $infobulle = '<strong>' . $this->escape($moniteur) . '</strong>'
                                            . ($observation !== '' ? '<br>' . $this->escape($observation) : '');
                                        ?>
                                        <button
                                            type="button"
                                            class="btn btn-link p-0 gda-suivi-appreciation js-suivi-infobulle"
                                            data-bs-toggle="tooltip"
                                            data-bs-html="true"
                                            data-bs-title="<?= $this->escape($infobulle) ?>"
                                            aria-label="<?= $this->escape($libelle . ' - ' . $moniteur . ($observation !== '' ? ' : ' . $observation : '')) ?>">
                                            <i class="fa-solid <?= $icones[$evaluation->appreciation] ?? 'fa-circle-question text-muted' ?>" aria-hidden="true"></i>
                                            <?php if ($observation !== '') : ?>
                                                <i class="fa-solid fa-comment-dots gda-suivi-indicateur-observation" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <ul class="list-inline small text-muted mt-2 mb-0">
            <?php foreach ($bilan->appreciations as $valeur => $libelle) : ?>
                <li class="list-inline-item"><i class="fa-solid <?= $icones[$valeur] ?> me-1" aria-hidden="true"></i><?= $this->escape($libelle) ?></li>
            <?php endforeach; ?>
            <li class="list-inline-item"><i class="fa-solid fa-comment-dots me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_SUIVI_BILAN_AVEC_OBSERVATION') ?></li>
        </ul>
    <?php endif; ?>
</div>
