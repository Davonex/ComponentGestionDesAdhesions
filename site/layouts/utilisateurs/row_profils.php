<?php

/**
 * Layout : ligne de l'onglet "Profils" de la vue Utilisateurs. Colonnes : Adhésion, Photo, Nom
 * Prénom, Licence (badge validité), CACI (badge validité), Brevets, Email, Suppression - même
 * présentation (badge = date coloré selon validité, tooltip = statut) que layouts/groupes/detail.php.
 *
 * @var array $displayData
 * - $displayData['utilisateur'] : object ligne utilisateur (voir UtilisateursModel::getUtilisateurs())
 * - $displayData['clubGroups']  : array des groupes club (['bureau' => ['id', 'label'], ...])
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/** @var object $utilisateur */
$utilisateur = $displayData['utilisateur'];
$clubGroups = $displayData['clubGroups'] ?? [];

$assignedGroupIds = array_column($utilisateur->groupes_club, 'id_groupe');
$groupeLabels = [];
foreach ($clubGroups as $clubGroup) {
    if ($clubGroup['id'] !== null && in_array((int) $clubGroup['id'], $assignedGroupIds, true)) {
        $groupeLabels[] = $clubGroup['label'];
    }
}

$displayName = trim((string) (($utilisateur->civilite ?? '') . ' ' . ($utilisateur->nom ?? $utilisateur->name) . ' ' . ($utilisateur->prenom ?? '')));
$pathPhoto = FileHelper::getImageSrc($utilisateur->photo ?? null, 'ProfilPhotoPath', 'DefaultProfilPhoto', false);

$simplifiedStatus = AdhesionStatusHelper::getSimplifiedStatus($utilisateur->adhesion_status);
$simplifiedStatusLabel = AdhesionStatusHelper::getSimplifiedStatusLabel($simplifiedStatus);

$licenceStatusEnum = AdhesionStatusHelper::getLicenceValidityStatus($utilisateur->date_licence ?? null);
$licenceStatusLabel = AdhesionStatusHelper::getStatusLabel($licenceStatusEnum);
$licenceStatusClass = AdhesionStatusHelper::getStatusBadgeClass($licenceStatusEnum);
$dateLicenceAffiche = ToolsHelper::from_sqldate($utilisateur->date_licence ?? null);

$caciStatusEnum = AdhesionStatusHelper::getCaciFileStatus($utilisateur->caci ?? null, $utilisateur->date_caci ?? null);
$caciStatusLabel = AdhesionStatusHelper::getStatusLabel($caciStatusEnum);
$caciStatusClass = AdhesionStatusHelper::getStatusBadgeClass($caciStatusEnum);
$pathCaci = !empty($utilisateur->caci) ? FileHelper::getImageSrc($utilisateur->caci, 'CaciPath', 'DefaultCaci', false) : '';
$dateCaciAffiche = ToolsHelper::from_sqldate($utilisateur->date_caci ?? null);

$brevetsShortlist = $utilisateur->brevets_shortlist ?? [];
?>
<tr data-id-user="<?= (int) $utilisateur->id ?>">
    <td class="text-center align-middle">
        <span
            class="badge gda-icon-lg bg-<?= $this->escape(AdhesionStatusHelper::getSimplifiedStatusBadgeClass($simplifiedStatus)) ?>"
            title="<?= $this->escape($simplifiedStatusLabel) ?>">
            <i class="<?= $this->escape(AdhesionStatusHelper::getSimplifiedStatusIcon($simplifiedStatus)) ?>" aria-hidden="true"></i>
            <span class="visually-hidden"><?= $this->escape($simplifiedStatusLabel) ?></span>
        </span>
    </td>
    <td class="text-center align-middle">
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
    <td class="text-center align-middle">
        <?= LayoutHelper::render('utilisateurs.cell_nom', [
            'utilisateur' => $utilisateur,
            'display_name' => $displayName,
            'adhesion_label' => null,
            'groupe_labels' => $groupeLabels,
        ]) ?>
    </td>
    <td class="text-center align-middle">
        <span class="badge bg-<?= $this->escape($licenceStatusClass) ?>" title="<?= $this->escape($licenceStatusLabel) ?>"><?= $dateLicenceAffiche !== '' ? $this->escape($dateLicenceAffiche) : '&mdash;' ?></span>
    </td>
    <td class="text-center align-middle">
        <?php if (!empty($pathCaci)) : ?>
            <a
                href="#"
                class="badge bg-<?= $this->escape($caciStatusClass) ?> js-caci-thumb"
                data-image-src="<?= $this->escape($pathCaci) ?>"
                data-image-alt="<?= $this->escape(Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI')) ?>"
                data-bs-toggle="modal"
                data-bs-target="#imagePreviewModal"
                title="<?= $this->escape($caciStatusLabel) ?>">
                <?= $dateCaciAffiche !== '' ? $this->escape($dateCaciAffiche) : '&mdash;' ?>
            </a>
        <?php else : ?>
            <span class="badge bg-<?= $this->escape($caciStatusClass) ?>" title="<?= $this->escape($caciStatusLabel) ?>"><?= $dateCaciAffiche !== '' ? $this->escape($dateCaciAffiche) : '&mdash;' ?></span>
        <?php endif; ?>
    </td>
    <td>
        <?php if (empty($brevetsShortlist)) : ?>
            <span class="text-muted"><?= Text::_('COM_GDA_GROUPES_TABLE_BREVETS_NONE') ?></span>
        <?php else : ?>
            <?php foreach ($brevetsShortlist as $brevet) : ?>
                <span class="badge me-1 bg-<?= $this->escape($brevet->role ?? 'pratiquant') ?>" title="<?= $this->escape($brevet->label_affichage ?? '') ?>"><?= $this->escape($brevet->code ?? '') ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
        <br>
        <a href="#" class="js-show-profil-brevets small" data-id-profil="<?= (int) $utilisateur->id ?>">
            <i class="fa-solid fa-award"></i> <?= Text::_('COM_GDA_GROUPES_TABLE_BREVETS_LINK') ?>
        </a>
    </td>
    <td class="text-center align-middle"><?= $this->escape($utilisateur->email) ?></td>
    <td class="text-center align-middle">
        <button
            type="button"
            class="btn btn-sm btn-outline-danger gda-icon-lg js-delete-utilisateur"
            data-item-id="<?= (int) $utilisateur->id ?>"
            data-item-civilite="<?= $this->escape($utilisateur->civilite ?? '') ?>"
            data-item-name="<?= $this->escape(trim((string) (($utilisateur->nom ?? '') . ' ' . ($utilisateur->prenom ?? '')))) ?>"
            data-item-username="<?= $this->escape($utilisateur->username) ?>"
            data-item-email="<?= $this->escape($utilisateur->email) ?>"
            data-item-photo="<?= $this->escape($pathPhoto) ?>"
            data-bs-toggle="tooltip"
            data-bs-placement="top"
            title="<?= $this->escape(Text::sprintf('COM_GDA_UTILISATEURS_DELETE_TOOLTIP', $displayName)) ?>">
            <i class="fa-solid fa-trash-can"></i>
        </button>
    </td>
</tr>
