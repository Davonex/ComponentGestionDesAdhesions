<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;

/**
 * Tableau de suivi d'un groupe : une ligne par élève, une colonne par séance.
 *
 * @var array $displayData
 * - $displayData['tableau'] : object{id_groupe, nb_competences, eleves, seances, synthese} (SuiviModel::getTableauSuivi())
 */

$tableau = $displayData['tableau'];
?>

<?php if (empty($tableau->eleves)) : ?>
    <p class="text-muted mb-0"><?= Text::_('COM_GDA_SUIVI_AUCUN_ELEVE') ?></p>
<?php else : ?>
    <?php if (empty($tableau->seances)) : ?>
        <p class="text-muted"><?= Text::_('COM_GDA_SUIVI_AUCUNE_SEANCE') ?></p>
    <?php endif; ?>

    <div class="table-responsive gda-suivi-scroll">
        <table class="table table-sm table-bordered align-middle mb-0 gda-suivi-table">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="gda-suivi-col-eleve"><?= Text::_('COM_GDA_SUIVI_ELEVE') ?></th>
                    <?php foreach ($tableau->seances as $dateSeance) : ?>
                        <?php $date = Factory::getDate($dateSeance); ?>
                        <th scope="col" class="text-center text-nowrap" data-date-seance="<?= $this->escape($dateSeance) ?>" title="<?= $this->escape($date->format('l d/m/Y', false, true)) ?>">
                            <?= $this->escape($date->format('D d/m', false, true)) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableau->eleves as $eleve) : ?>
                    <?php
                    $idProfil = (int) $eleve->id_profil;
                    $nomComplet = trim($eleve->prenom . ' ' . $eleve->nom);
                    $pathPhoto = FileHelper::getImageSrc($eleve->photo, 'ProfilPhotoPath', 'DefaultProfilPhoto', false);
                    ?>
                    <tr>
                        <th scope="row" class="gda-suivi-col-eleve">
                            <a href="#" class="d-flex align-items-center gap-2 text-reset js-suivi-bilan" data-id-profil="<?= $idProfil ?>" data-id-groupe="<?= (int) $tableau->id_groupe ?>" title="<?= $this->escape(Text::_('COM_GDA_SUIVI_BILAN_VOIR')) ?>">
                                <?php if (!empty($pathPhoto)) : ?>
                                    <img src="<?= $this->escape($pathPhoto) ?>" alt="" loading="lazy" class="gda-suivi-photo">
                                <?php endif; ?>
                                <span class="gda-suivi-nom"><?= $this->escape($nomComplet) ?></span>
                            </a>
                        </th>
                        <?php foreach ($tableau->seances as $dateSeance) : ?>
                            <td class="text-center">
                                <?= LayoutHelper::render('suivi.cellule', [
                                    'id_profil'      => $idProfil,
                                    'id_groupe'      => $tableau->id_groupe,
                                    'date_seance'    => $dateSeance,
                                    'nb_evaluees'    => $tableau->synthese[$idProfil][$dateSeance] ?? 0,
                                    'nb_competences' => $tableau->nb_competences,
                                    'nom_complet'    => $nomComplet,
                                ]) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
