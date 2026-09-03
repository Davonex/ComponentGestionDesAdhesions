<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * @var array $displayData
 * - $displayData['groupe'] : object{id_groupe, groupe_name, icon, adherents}
 * - $displayData['showRole'] : bool, affiche la colonne Rôle (adherent->role) — utilisé par
 *   l'onglet "Suivi des inscriptions" de la vue Campagnes (CampagnesModel::getInscritsCampagne()),
 *   absent pour la vue Groupes qui ne connaît pas cette notion.
 * - $displayData['showReservationStatut'] : bool, affiche les colonnes Statut/Date de réservation
 *   (adherent->en_attente, ->rang_attente, ->date_reservation) — même onglet Suivi que showRole,
 *   mêmes libellés que la popup rapport (COM_GDA_CAMPAGNE_RAPPORT_*).
 */

$groupe = $displayData['groupe'];
$adherents = $groupe->adherents;
$showRole = $displayData['showRole'] ?? false;
$showReservationStatut = $displayData['showReservationStatut'] ?? false;
?>

<?php if (empty($adherents)) : ?>
    <p class="text-muted"><?= Text::_('COM_GDA_GROUPES_TABLE_EMPTY') ?></p>
<?php else : ?>
    <table class="table table-bordered table-striped simple-database gda-groupes-table" data-simple-database-search="true" data-simple-database-sort="true">
        <thead>
            <tr>
                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_PHOTO') ?></th>
                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_NAME') ?></th>
                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_BREVETS') ?></th>
                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_LICENCE') ?></th>
                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI') ?></th>
                <?php if ($showRole) : ?>
                    <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_ROLE') ?></th>
                <?php endif; ?>
                <?php if ($showReservationStatut) : ?>
                    <th class="text-center"><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_STATUT') ?></th>
                    <th class="text-center"><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_DATE') ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($adherents as $adherent) : ?>
                <?php
                $pathPhoto = FileHelper::getImageSrc($adherent->photo, 'ProfilPhotoPath', 'DefaultProfilPhoto', false);
                $pathCaci = FileHelper::getImageSrc($adherent->caci, 'CaciPath', '', false);
                $civilite = trim((string) ($adherent->civilite ?? ''));
                $fullName = trim(($civilite !== '' ? $civilite : 'M.') . ' ' . ($adherent->nom ?? '') . ' ' . ($adherent->prenom ?? ''));
                $dateCaci = ToolsHelper::from_sqldate($adherent->date_caci);
                $statusLabel = AdhesionStatusHelper::getStatusLabel($adherent->caci_status);
                $statusClass = AdhesionStatusHelper::getStatusBadgeClass($adherent->caci_status);
                $dateLicence = ToolsHelper::from_sqldate($adherent->date_licence);
                $licenceStatusLabel = AdhesionStatusHelper::getStatusLabel($adherent->licence_status);
                $licenceStatusClass = AdhesionStatusHelper::getStatusBadgeClass($adherent->licence_status);
                ?>
                <tr>
                    <td class="text-center">
                        <?php if (!empty($pathPhoto)) : ?>
                            <a
                                href="#"
                                class="js-image-preview-thumb"
                                data-image-src="<?= $this->escape($pathPhoto) ?>"
                                data-image-alt="<?= $this->escape($fullName) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#imagePreviewModal"
                                aria-label="<?= $this->escape(Text::_('COM_GDA_GROUPES_TABLE_HEADER_PHOTO')) ?>">
                                <img
                                    src="<?= $this->escape($pathPhoto) ?>"
                                    alt="<?= $this->escape($fullName) ?>"
                                    width="64"
                                    height="64"
                                    loading="lazy"
                                    class="gda-preview-thumb gda-preview-thumb--64">
                            </a>
                        <?php else : ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="#" class="js-show-profil-card" data-id-profil="<?= (int) $adherent->id_profil ?>"><?= $this->escape($fullName) ?></a>
                    </td>
                    <td>
                        <?php $shortlist = $adherent->brevets_shortlist ?? []; ?>
                        <?php if (empty($shortlist)) : ?>
                            <span class="text-muted"><?= Text::_('COM_GDA_GROUPES_TABLE_BREVETS_NONE') ?></span>
                        <?php else : ?>
                            <?php foreach ($shortlist as $brevet) : ?>
                                <span class="badge me-1 bg-<?= $this->escape($brevet->role ?? 'pratiquant') ?>" title="<?= $this->escape($brevet->label_affichage ?? '') ?>"><?= $this->escape($brevet->code ?? '') ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <br>
                        <a href="#" class="js-show-profil-brevets small" data-id-profil="<?= (int) $adherent->id_profil ?>">
                            <i class="fa-solid fa-award"></i> <?= Text::_('COM_GDA_GROUPES_TABLE_BREVETS_LINK') ?>
                        </a>
                    </td>
                    <td class="text-center">
                        
                        <span class="badge bg-<?= $this->escape($licenceStatusClass) ?>" title="<?= $this->escape($licenceStatusLabel) ?>"> <i class="fa-solid fa-id-card me-1" aria-hidden="true"></i><?= $dateLicence !== '' ? $this->escape($dateLicence) : '&mdash;' ?></span>
                    </td>
                    <td class="text-center">
                        
                        <?php if (!empty($pathCaci)) : ?>
                            <a
                                href="#"
                                class="badge bg-<?= $this->escape($statusClass) ?> js-caci-thumb"
                                data-image-src="<?= $this->escape($pathCaci) ?>"
                                data-image-alt="<?= $this->escape(Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI')) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#imagePreviewModal"
                                title="<?= $this->escape($statusLabel) ?>">
                                <i class="fa-solid fa-file-medical me-1" aria-hidden="true"></i>
                                <?= $dateCaci !== '' ? $this->escape($dateCaci) : '&mdash;' ?>
                            </a>
                        <?php else : ?>
                            <span class="badge bg-<?= $this->escape($statusClass) ?>" title="<?= $this->escape($statusLabel) ?>"><i class="fa-solid fa-file-medical me-1" aria-hidden="true"></i><?= $dateCaci !== '' ? $this->escape($dateCaci) : '&mdash;' ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($showRole) : ?>
                        <td class="text-center"><?= $adherent->role !== '' ? $this->escape($adherent->role) : '&mdash;' ?></td>
                    <?php endif; ?>
                    <?php if ($showReservationStatut) : ?>
                        <td class="text-center">
                            <?php if ($adherent->en_attente) : ?>
                                <span class="badge bg-warning text-dark">
                                    <?= Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_LISTE_ATTENTE', $adherent->rang_attente) ?>
                                </span>
                            <?php else : ?>
                                <span class="badge bg-success"><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_CONFIRMEE') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?= $adherent->date_reservation ? HTMLHelper::_('date', $adherent->date_reservation, 'd M Y H:i') : '&mdash;' ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
