<?php

/**
 * Layout : ligne de l'onglet "Niveau d'accès" de la vue Utilisateurs. Photo, identité, groupes
 * club, statut actif/bloqué, et réinitialisation du mot de passe.
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
            'groupe_labels' => null,
        ]) ?>
    </td>
    <td class="text-center align-middle">
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
    <td class="align-middle">
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
    <td class="text-center align-middle">
        <button
            type="button"
            class="btn btn-sm btn-outline-warning gda-icon-lg js-reset-password-utilisateur"
            data-item-id="<?= (int) $utilisateur->id ?>"
            data-item-civilite="<?= $this->escape($utilisateur->civilite ?? '') ?>"
            data-item-name="<?= $this->escape(trim((string) (($utilisateur->nom ?? '') . ' ' . ($utilisateur->prenom ?? '')))) ?>"
            data-item-username="<?= $this->escape($utilisateur->username) ?>"
            data-item-email="<?= $this->escape($utilisateur->email) ?>"
            data-item-photo="<?= $this->escape($pathPhoto) ?>"
            data-bs-toggle="tooltip"
            data-bs-placement="top"
            title="<?= $this->escape(Text::sprintf('COM_GDA_UTILISATEURS_RESET_PASSWORD_TOOLTIP', $displayName)) ?>">
            <i class="fa-solid fa-key"></i>
        </button>
    </td>
</tr>
