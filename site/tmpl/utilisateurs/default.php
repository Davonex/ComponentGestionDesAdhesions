<?php

/** Template de la vue "Utilisateurs" (Bureau) : groupes club et activation des comptes. */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\Helpers\Bootstrap;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;

Bootstrap::modal();

/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();

$wa->useStyle('com_gdadhesions.gda');
$wa->useScript('com_gdadhesions.form_modal');

// Formulaire d'édition du profil (popup au clic sur le Nom Prénom, réservé au Bureau) :
// même assets que la vue Profil pour le drag&drop photo et le spinner de sauvegarde.
$wa->useScript('com_gdadhesions.file_upload');
$wa->useStyle('com_gdadhesions.file_upload');
$wa->useScript('com_gdadhesions.spinner');
$wa->useStyle('com_gdadhesions.spinner');

$wa->useScript('simple-datatables');
$wa->useStyle('simple-datatables');

$wa->useScript('com_gdadhesions.utilisateurs');

/** @var array $utilisateurs */
$utilisateurs = $this->utilisateurs;

$clubGroupIds = UsersHelper::getClubGroupIds();
$clubGroups = [
    'bureau' => ['id' => $clubGroupIds['bureau'] ?? null, 'label' => Text::_('COM_GDA_ROLE_BUREAU')],
    'responsable' => ['id' => $clubGroupIds['responsable'] ?? null, 'label' => Text::_('COM_GDA_ROLE_RESPONSABLE_GROUPE')],
    'moniteur' => ['id' => $clubGroupIds['moniteur'] ?? null, 'label' => Text::_('COM_GDA_ROLE_MONITEUR')],
];
?>

<div class="gda-utilisateurs card shadow-lg p-4">
    <h3 class="mb-3"><?= Text::_('COM_GDA_VIEW_UTILISATEURS_MENU_LABEL') ?></h3>

    <?php if (empty($utilisateurs)) : ?>
        <p class="text-muted"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_EMPTY') ?></p>
    <?php else : ?>
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <label for="filterAdhesionStatus" class="form-label mb-0"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_ADHESION') ?></label>
                <select id="filterAdhesionStatus" class="form-select form-select-sm w-auto">
                    <option value=""><?= Text::_('COM_GDA_UTILISATEURS_FILTER_ADHESION_ALL') ?></option>
                    <option value="<?= $this->escape(AdhesionStatusHelper::getSimplifiedStatusLabel(AdhesionStatusHelper::SIMPLIFIED_STATUS_NOT_SUBSCRIBED)) ?>">
                        <?= Text::_('COM_GDA_UTILISATEURS_ADHESION_STATUS_NOT_SUBSCRIBED') ?>
                    </option>
                    <option value="<?= $this->escape(AdhesionStatusHelper::getSimplifiedStatusLabel(AdhesionStatusHelper::SIMPLIFIED_STATUS_IN_PROGRESS)) ?>">
                        <?= Text::_('COM_GDA_UTILISATEURS_ADHESION_STATUS_IN_PROGRESS') ?>
                    </option>
                    <option value="<?= $this->escape(AdhesionStatusHelper::getSimplifiedStatusLabel(AdhesionStatusHelper::SIMPLIFIED_STATUS_COMPLETED)) ?>">
                        <?= Text::_('COM_GDA_UTILISATEURS_ADHESION_STATUS_COMPLETED') ?>
                    </option>
                </select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label for="filterGroupe" class="form-label mb-0"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_GROUPES') ?></label>
                <select id="filterGroupe" class="form-select form-select-sm w-auto">
                    <option value=""><?= Text::_('COM_GDA_UTILISATEURS_FILTER_GROUPE_ALL') ?></option>
                    <?php foreach ($clubGroups as $clubGroup) : ?>
                        <?php if ($clubGroup['id'] !== null) : ?>
                            <option value="grp:<?= $this->escape($clubGroup['label']) ?>"><?= $this->escape($clubGroup['label']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle gda-table-compact" id="tableUtilisateurs">
                <thead>
                    <tr>
                        <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_ADHESION') ?></th>
                        <th><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_PHOTO') ?></th>
                        <th><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_NAME') ?></th>
                        <th><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_USERNAME') ?></th>
                        <th><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_EMAIL') ?></th>
                        <th><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_GROUPES') ?></th>
                        <th><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_FONCTION') ?></th>
                        <th><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_STATUT') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($utilisateurs as $utilisateur) : ?>
                        <?php
                        $assignedGroupIds = array_column($utilisateur->groupes_club, 'id_groupe');
                        $displayName = trim((string) (($utilisateur->civilite ?? '') . ' ' . ($utilisateur->nom ?? $utilisateur->name) . ' ' . ($utilisateur->prenom ?? '')));
                        $pathPhoto = FileHelper::getImageSrc($utilisateur->photo ?? null, 'ProfilPhotoPath', 'DefaultProfilPhoto', false);
                        $simplifiedStatus = AdhesionStatusHelper::getSimplifiedStatus($utilisateur->adhesion_status);
                        ?>
                        <tr data-id-user="<?= (int) $utilisateur->id ?>">
                            <td class="text-center">
                                <span
                                    class="badge bg-<?= $this->escape(AdhesionStatusHelper::getSimplifiedStatusBadgeClass($simplifiedStatus)) ?>"
                                    title="<?= $this->escape(AdhesionStatusHelper::getSimplifiedStatusLabel($simplifiedStatus)) ?>">
                                    <i class="<?= $this->escape(AdhesionStatusHelper::getSimplifiedStatusIcon($simplifiedStatus)) ?>" aria-hidden="true"></i>
                                    <span class="visually-hidden"><?= $this->escape(AdhesionStatusHelper::getSimplifiedStatusLabel($simplifiedStatus)) ?></span>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($pathPhoto)) : ?>
                                    <a
                                        href="#"
                                        class="js-image-preview-thumb js-utilisateur-photo-link"
                                        data-image-src="<?= $this->escape($pathPhoto) ?>"
                                        data-image-alt="<?= $this->escape($displayName) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#imagePreviewModal"
                                        aria-label="<?= $this->escape(Text::_('COM_GDA_GROUPES_TABLE_HEADER_PHOTO')) ?>">
                                        <img
                                            src="<?= $this->escape($pathPhoto) ?>"
                                            alt="<?= $this->escape($displayName) ?>"
                                            width="48"
                                            height="48"
                                            loading="lazy"
                                            class="gda-preview-thumb js-utilisateur-photo-img">
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="#"
                                    class="js-edit-profil-card"
                                    data-id-profil="<?= (int) $utilisateur->id ?>"
                                    title="<?= $this->escape(Text::_('COM_GDA_UTILISATEURS_EDIT_PROFIL_TOOLTIP')) ?>">
                                    <span class="js-utilisateur-name"><?= $this->escape($displayName) ?></span>
                                </a>
                            </td>
                            <td><?= $this->escape($utilisateur->username) ?></td>
                            <td><?= $this->escape($utilisateur->email) ?></td>
                            <td>
                                <?php foreach ($clubGroups as $clubGroup) : ?>
                                    <?php if ($clubGroup['id'] !== null) : ?>
                                        <div class="form-check form-check-inline">
                                            <input
                                                class="form-check-input js-utilisateur-group"
                                                type="checkbox"
                                                id="group-<?= (int) $utilisateur->id ?>-<?= (int) $clubGroup['id'] ?>"
                                                data-id-user="<?= (int) $utilisateur->id ?>"
                                                data-id-groupe="<?= (int) $clubGroup['id'] ?>"
                                                <?= in_array((int) $clubGroup['id'], $assignedGroupIds, true) ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label" for="group-<?= (int) $utilisateur->id ?>-<?= (int) $clubGroup['id'] ?>">
                                                <?= $this->escape($clubGroup['label']) ?>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <span class="visually-hidden">
                                    <?php foreach ($clubGroups as $clubGroup) : ?>
                                        <?php if ($clubGroup['id'] !== null && in_array((int) $clubGroup['id'], $assignedGroupIds, true)) : ?>
                                            grp:<?= $this->escape($clubGroup['label']) ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </span>
                            </td>
                            <td class="js-editable-fonction" data-id-user="<?= (int) $utilisateur->id ?>">
                                <span class="fonction-display"><?= $this->escape($utilisateur->fonction ?? '') ?></span>
                                <input
                                    type="text"
                                    class="form-control form-control-sm fonction-input d-none"
                                    maxlength="100"
                                    value="<?= $this->escape($utilisateur->fonction ?? '') ?>"
                                    data-current-fonction="<?= $this->escape($utilisateur->fonction ?? '') ?>">
                            </td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input
                                        class="form-check-input js-utilisateur-block"
                                        type="checkbox"
                                        role="switch"
                                        id="block-<?= (int) $utilisateur->id ?>"
                                        data-id-user="<?= (int) $utilisateur->id ?>"
                                        <?= (int) $utilisateur->block === 0 ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="block-<?= (int) $utilisateur->id ?>">
                                        <?= (int) $utilisateur->block === 0
                                            ? Text::_('COM_GDA_UTILISATEURS_STATUT_ACTIF')
                                            : Text::_('COM_GDA_UTILISATEURS_STATUT_BLOQUE') ?>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Modal de prévisualisation de la photo -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-body text-center p-2">
                    <img id="imagePreviewImage" src="" alt="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'édition du profil (formulaire complet, chargé en ajax au clic sur le Nom Prénom) -->
    <div class="modal fade" id="profilCardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" id="profilCardModalContent">
                <!-- Le contenu de la modal est chargé dynamiquement via ajax -->
            </div>
        </div>
    </div>
</div>
