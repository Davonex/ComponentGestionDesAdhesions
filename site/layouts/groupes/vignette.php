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
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
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
            <div class="col">
                <div class="card h-100 text-center">
                    <div class="card-header py-2 text-truncate" title="<?= $this->escape($fullName) ?>">
                        <a href="#" class="js-show-profil-card text-reset" data-id-profil="<?= (int) $adherent->id_profil ?>"><?= $this->escape($fullName) ?></a>
                    </div>

                    <?php if (!empty($pathPhoto)) : ?>
                        <img
                            src="<?= $this->escape($pathPhoto) ?>"
                            alt="<?= $this->escape($fullName) ?>"
                            loading="lazy"
                            class="card-img-top gda-vignette-photo">
                    <?php else : ?>
                        <div class="gda-vignette-photo gda-vignette-photo--empty d-flex align-items-center justify-content-center text-muted">
                            <i class="fa-solid fa-user fa-2x" aria-hidden="true"></i>
                        </div>
                    <?php endif; ?>

                    <div class="card-body d-flex justify-content-between align-items-center py-2">
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
                        <span class="badge bg-<?= $this->escape($licenceStatusClass) ?>" title="<?= $this->escape($licenceStatusLabel) ?>"><?= $dateLicence !== '' ? $this->escape($dateLicence) : '&mdash;' ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
