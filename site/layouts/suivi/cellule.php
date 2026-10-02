<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Case du tableau de suivi (un élève, une séance) : bouton qui ouvre le formulaire d'évaluation.
 * Trois états : aucune évaluation, évaluation partielle, toutes les compétences évaluées.
 *
 * @var array $displayData
 * - id_profil, id_groupe (int), date_seance (Y-m-d), nb_evaluees, nb_competences (int)
 * - nom_complet (string, facultatif) : complète le libellé accessible du bouton.
 */

$nbEvaluees    = (int) $displayData['nb_evaluees'];
$nbCompetences = (int) $displayData['nb_competences'];

if ($nbEvaluees === 0) {
    $classe = 'btn-outline-secondary';
    $icone  = 'fa-plus';
    $libelle = Text::_('COM_GDA_SUIVI_CELLULE_A_EVALUER');
} elseif ($nbEvaluees < $nbCompetences) {
    $classe = 'btn-warning';
    $icone  = 'fa-hourglass-half';
    $libelle = Text::sprintf('COM_GDA_SUIVI_CELLULE_PARTIELLE', $nbEvaluees, $nbCompetences);
} else {
    $classe = 'btn-success';
    $icone  = 'fa-check';
    $libelle = Text::sprintf('COM_GDA_SUIVI_CELLULE_COMPLETE', $nbEvaluees, $nbCompetences);
}

$nomComplet = (string) ($displayData['nom_complet'] ?? '');
?>
<button
    type="button"
    class="btn btn-sm <?= $classe ?> gda-suivi-cellule js-suivi-evaluer"
    data-id-profil="<?= (int) $displayData['id_profil'] ?>"
    data-id-groupe="<?= (int) $displayData['id_groupe'] ?>"
    data-date-seance="<?= $this->escape($displayData['date_seance']) ?>"
    title="<?= $this->escape($libelle) ?>"
    aria-label="<?= $this->escape(trim($nomComplet . ' ' . $libelle)) ?>">
    <i class="fa-solid <?= $icone ?>" aria-hidden="true"></i>
    <?php if ($nbEvaluees > 0) : ?>
        <span class="ms-1"><?= $nbEvaluees ?>/<?= $nbCompetences ?></span>
    <?php endif; ?>
</button>
