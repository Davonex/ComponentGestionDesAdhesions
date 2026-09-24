<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Service\ReservationService;

/**
 * @var array $displayData
 * - $displayData['groupe'] : object{id_groupe, groupe_name, icon, adherents}
 * - $displayData['showRole'] : bool, affiche la colonne Rôle (adherent->role) — utilisé par
 *   l'onglet "Suivi des inscriptions" de la vue Campagnes (CampagnesModel::getInscritsCampagne()),
 *   absent pour la vue Groupes qui ne connaît pas cette notion.
 * - $displayData['showReservationStatut'] : bool, affiche les colonnes Statut/Date de réservation
 *   (adherent->id_place, ->statut, ->date_reservation) — même onglet Suivi que
 *   showRole. La cellule Statut est éditable au double-clic (voir
 *   CampagnesController::changerStatutInscription()) : le responsable de campagne y valide ou
 *   refuse une inscription, ou revient en arrière entre attente/confirmee/refusee.
 */

$groupe = $displayData['groupe'];
$adherents = $groupe->adherents;
$showRole = $displayData['showRole'] ?? false;
$showReservationStatut = $displayData['showReservationStatut'] ?? false;

// Echelle de largeurs partagee avec la vue Secretariat (col-secretariat-*, cf. gda.css) : la classe
// "secretariat-table" n'active table-layout: fixed + ces largeurs que dans le contexte de l'onglet
// "Suivi des inscriptions" (voir la portee des selecteurs CSS, scopee a #campagnes-pane-suivi) -
// sans effet ici pour la vue Groupes, qui ne passe jamais showReservationStatut.
$tableClass = 'table table-bordered table-striped gda-groupes-table' . ($showReservationStatut ? ' secretariat-table gda-suivi-compact' : '');
// Onglet Suivi : Licence et CACI empilés dans une seule colonne pour gagner de la place.
$fusionLicenceCaci = $showReservationStatut;
?>

<?php if (empty($adherents)) : ?>
    <p class="text-muted"><?= Text::_('COM_GDA_GROUPES_TABLE_EMPTY') ?></p>
<?php else : ?>
    <div class="table-responsive">
    <table class="<?= $tableClass ?>">
        <thead>
            <tr>
                <th class="text-center col-secretariat-xs"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_PHOTO') ?></th>
                <th class="text-center col-secretariat-lg"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_NAME') ?></th>
                <th class="text-center col-secretariat-md"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_BREVETS') ?></th>
                <?php if ($fusionLicenceCaci) : ?>
                    <th class="text-center col-secretariat-sm"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_LICENCE') ?> / <?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI') ?></th>
                <?php else : ?>
                    <th class="text-center col-secretariat-sm"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_LICENCE') ?></th>
                    <th class="text-center col-secretariat-xs"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI') ?></th>
                <?php endif; ?>
                <?php if ($showRole) : ?>
                    <th class="text-center col-secretariat-sm"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_ROLE') ?></th>
                <?php endif; ?>
                <?php if ($showReservationStatut) : ?>
                    <th class="text-center col-secretariat-sm"><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_STATUT') ?></th>
                    <th class="text-center col-secretariat-sm"><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_DATE') ?></th>
                    <th class="col-secretariat-xl"><?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_COMMENTAIRE') ?></th>
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
                $licenceStatusClass = AdhesionStatusHelper::getLicenceBadgeClass($adherent->licence_status);
                ?>
                <tr<?= $showReservationStatut ? ' data-role="' . $this->escape((string) $adherent->role) . '" data-statut="' . $this->escape((string) $adherent->statut) . '"' : '' ?>>
                    <td class="text-center col-secretariat-xs">
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
                    <td class="col-secretariat-lg">
                        <a href="#" class="js-show-profil-card" data-id-profil="<?= (int) $adherent->id_profil ?>"><?= $this->escape($fullName) ?></a>
                    </td>
                    <td class="col-secretariat-md">
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
                    <?php ob_start(); ?>
                    <span class="badge <?= $this->escape($licenceStatusClass) ?>" title="<?= $this->escape($licenceStatusLabel) ?>"> <i class="fa-solid fa-id-card me-1" aria-hidden="true"></i><?= $dateLicence !== '' ? $this->escape($dateLicence) : '&mdash;' ?></span>
                    <?php $licenceHtml = ob_get_clean(); ob_start(); ?>
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
                    <?php $caciHtml = ob_get_clean(); ?>
                    <?php if ($fusionLicenceCaci) : ?>
                        <td class="text-center col-secretariat-sm">
                            <div class="mb-1"><?= $licenceHtml ?></div>
                            <div><?= $caciHtml ?></div>
                        </td>
                    <?php else : ?>
                        <td class="text-center col-secretariat-sm"><?= $licenceHtml ?></td>
                        <td class="text-center col-secretariat-xs"><?= $caciHtml ?></td>
                    <?php endif; ?>
                    <?php if ($showRole) : ?>
                        <td class="text-center col-secretariat-sm"><?= $adherent->role !== '' ? $this->escape($adherent->role) : '&mdash;' ?></td>
                    <?php endif; ?>
                    <?php if ($showReservationStatut) : ?>
                        <?php
                        $statutBadges = [
                            ReservationService::STATUT_ATTENTE   => ['bg-warning text-dark', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_ATTENTE'],
                            ReservationService::STATUT_REFUSEE   => ['bg-danger', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_REFUSEE'],
                            ReservationService::STATUT_CONFIRMEE => ['bg-success', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_CONFIRMEE'],
                            ReservationService::STATUT_ANNULEE   => ['bg-secondary', 'COM_GDA_CAMPAGNES_SUIVI_STATUT_ANNULEE'],
                        ];
                        [$statutBadgeClass, $statutLabelKey] = $statutBadges[$adherent->statut] ?? $statutBadges[ReservationService::STATUT_ATTENTE];
                        // Une place annulée par l'adhérent reste modifiable par le responsable (c'est ce qui
                        // déverrouille l'adhérent) ; « Annulée » n'est en revanche pas un choix de la liste.
                        $statutEditable = true;
                        ?>
                        <td
                            class="text-center col-secretariat-sm<?= $statutEditable ? ' js-editable-statut-inscription' : '' ?>"
                            <?php if ($statutEditable) : ?>
                            data-id-place="<?= (int) $adherent->id_place ?>"
                            data-current-statut="<?= $this->escape($adherent->statut) ?>"
                            title="<?= $this->escape(Text::_('COM_GDA_CAMPAGNES_SUIVI_STATUT_EDIT_HINT')) ?>"
                            style="cursor: pointer;"
                            <?php endif; ?>>
                            <span class="statut-inscription-display badge <?= $statutBadgeClass ?>">
                                <?= Text::_($statutLabelKey) ?>
                            </span>
                            <?php if ($statutEditable) : ?>
                            <select class="statut-inscription-input form-select form-select-sm d-none" aria-label="<?= $this->escape(Text::_('COM_GDA_CAMPAGNE_RAPPORT_COL_STATUT')) ?>">
                                <option value="<?= ReservationService::STATUT_ATTENTE ?>" <?= $adherent->statut === ReservationService::STATUT_ATTENTE ? 'selected' : '' ?>><?= Text::_('COM_GDA_CAMPAGNES_SUIVI_STATUT_ATTENTE') ?></option>
                                <option value="<?= ReservationService::STATUT_REFUSEE ?>" <?= $adherent->statut === ReservationService::STATUT_REFUSEE ? 'selected' : '' ?>><?= Text::_('COM_GDA_CAMPAGNES_SUIVI_STATUT_REFUSEE') ?></option>
                                <option value="<?= ReservationService::STATUT_CONFIRMEE ?>" <?= $adherent->statut === ReservationService::STATUT_CONFIRMEE ? 'selected' : '' ?>><?= Text::_('COM_GDA_CAMPAGNES_SUIVI_STATUT_CONFIRMEE') ?></option>
                            </select>
                            <?php endif; ?>
                        </td>
                        <td class="text-center col-secretariat-sm">
                            <?= $adherent->date_reservation ? HTMLHelper::_('date', $adherent->date_reservation, 'd M Y H:i') : '&mdash;' ?>
                        </td>
                        <td class="col-secretariat-xl small">
                            <?= trim((string) ($adherent->commentaire ?? '')) !== '' ? nl2br($this->escape($adherent->commentaire)) : '<span class="text-muted">&mdash;</span>' ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
