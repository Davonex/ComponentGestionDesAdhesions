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
                <th><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_PHOTO') ?></th>
                <th><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_NAME') ?></th>
                <th><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI') ?></th>
                <th><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_DATE_CACI') ?></th>
                <th><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_STATUS') ?></th>
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
                    <td><?= $this->escape($fullName) ?></td>
                    <td class="text-center">
                        <?php if (!empty($pathCaci)) : ?>
                            <a
                                href="#"
                                class="js-caci-thumb"
                                data-image-src="<?= $this->escape($pathCaci) ?>"
                                data-image-alt="<?= $this->escape(Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI')) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#imagePreviewModal"
                                aria-label="<?= $this->escape(Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI')) ?>">
                                <img
                                    src="<?= $this->escape($pathCaci) ?>"
                                    alt="<?= $this->escape(Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI')) ?>"
                                    width="32"
                                    height="32"
                                    loading="lazy"
                                    style="object-fit: cover; cursor: zoom-in;">
                            </a>
                        <?php else : ?>
                            &#10060;
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= $this->escape($dateCaci) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $this->escape($statusClass) ?>"><?= $this->escape($statusLabel) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
