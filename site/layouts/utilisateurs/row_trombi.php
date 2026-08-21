<?php

/**
 * Layout : ligne de l'onglet "Trombinoscope" de la vue Utilisateurs. Photo, identité, fonction et
 * ordre d'affichage dans le trombinoscope public du Bureau (layouts/trombinoscope/bureau.php).
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
$adhesionLabel = AdhesionStatusHelper::getSimplifiedStatusLabel(AdhesionStatusHelper::getSimplifiedStatus($utilisateur->adhesion_status));
?>
<tr data-id-user="<?= (int) $utilisateur->id ?>">
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
            'adhesion_label' => $adhesionLabel,
            'groupe_labels' => $groupeLabels,
        ]) ?>
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
    <td class="js-editable-ordre text-center" data-id-user="<?= (int) $utilisateur->id ?>">
        <span class="ordre-display"><?= $utilisateur->ordre_bureau !== null ? (int) $utilisateur->ordre_bureau : '' ?></span>
        <input
            type="number"
            class="form-control form-control-sm ordre-input d-none text-center"
            min="0"
            max="999"
            value="<?= $utilisateur->ordre_bureau !== null ? (int) $utilisateur->ordre_bureau : '' ?>"
            data-current-ordre="<?= $utilisateur->ordre_bureau !== null ? (int) $utilisateur->ordre_bureau : '' ?>">
    </td>
</tr>
