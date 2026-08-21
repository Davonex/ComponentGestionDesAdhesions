<?php

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * @var array $displayData
 * - $displayData['groupe'] : object{id_groupe, groupe_name, icon, adherents}
 */

$groupe = $displayData['groupe'];
$adherents = $groupe->adherents;
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
                                <span class="badge me-1 bg-<?= $this->escape($brevet->role ?? 'pratiquant') ?>" title="<?= $this->escape($brevet->label_ffessm ?? '') ?>"><?= $this->escape($brevet->code ?? '') ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <br>
                        <a href="#" class="js-show-profil-brevets small" data-id-profil="<?= (int) $adherent->id_profil ?>">
                            <i class="fa-solid fa-award"></i> <?= Text::_('COM_GDA_GROUPES_TABLE_BREVETS_LINK') ?>
                        </a>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?= $this->escape($licenceStatusClass) ?>" title="<?= $this->escape($licenceStatusLabel) ?>"><?= $dateLicence !== '' ? $this->escape($dateLicence) : '&mdash;' ?></span>
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
                                <?= $dateCaci !== '' ? $this->escape($dateCaci) : '&mdash;' ?>
                            </a>
                        <?php else : ?>
                            <span class="badge bg-<?= $this->escape($statusClass) ?>" title="<?= $this->escape($statusLabel) ?>"><?= $dateCaci !== '' ? $this->escape($dateCaci) : '&mdash;' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
