<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;


/**
 * @var array $displayData
 * - $displayData['items'] : campagne
 */

$items = $displayData['items'];

// je souhaite egalemnt ajouter la lib js simple_database a mon tabealu html pour pouvoir faire des recherches et des tris facilement sur le tableau, je vais donc ajouter les classes nécessaires et les data attributes pour initialiser la librairie simple_database
// je vais ajouter la classe "simple-database" à mon tableau et les data attributes suivants :
// data-simple-database-search : pour activer la recherche
// data-simple-database-sort : pour activer le tri
// data-simple-database-pagination : non nécessaire pour l'instant car je n'ai pas besoin de pagination, mais je peux l'ajouter plus tard si besoin
?>

<div class="card mb-3">
    <!-- <div class="card-header">
        <h5 class="mb-0"><?= Text::_('COM_GDA_SECRETARIAT_STEP_1') ?? 'Verification phase I' ?></h5>
    </div> -->
    <div class="card-body">
        <?php if (empty($items)) : ?>
            <p class="text-muted"><?= Text::_('COM_GDA_SECRETARIAT_STEP_1_EMPTY') ?? 'Aucun profil trouvé' ?></p>
        <?php else : ?>

            <table class="table table-bordered table-striped simple-database secretariat-table" data-simple-database-search="true" data-simple-database-sort="true">
                <thead>
                    <tr>
                        <th></th>
                        <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PHOTO') ?? 'Photo' ?></th>
                        <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_LICENCE') ?? 'Licence' ?></th>
                        <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_NAME') ?? 'Nom' ?></th>
                        <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_EMAIL') ?? 'Email' ?></th>
                        <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_ADRESS') ?? 'Adresse' ?></th>
                        <!-- <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_VDY') ?? 'VDY' ?></th> -->
                        <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI') ?? 'Caci' ?></th>
                        <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_DATE_CACI') ?? 'Date Caci' ?></th>
                        <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_DATE') ?? 'Subscription Date' ?></th>
                        <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_ACTION') ?? 'Action' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <?php
                        $pathPhoto = FileHelper::getImageSrc($item->photo, 'ProfilPhotoPath', 'DefaultProfilPhoto', false);
                        $pathCaci = FileHelper::getImageSrc($item->caci, 'CaciPath', '', false);
                        $civilite = trim((string) ($item->civilite ?? ''));
                        $fullName = trim((string) (($item->nom ?? '') . ' ' . ($item->prenom ?? '')));
                        $memberLabel = trim(($civilite !== '' ? $civilite : 'M.') . ' ' . $fullName);
                        $deleteTooltip = Text::sprintf('COM_GDA_SECRETARIAT_DELETE_TOOLTIP', $memberLabel);
                        $licenceValue = trim((string) ($item->username ?? ''));
                        $licencePrefix = strtoupper(substr($licenceValue, 0, 1));
                        $licenceTooltip = '';
                        $licenceClass = 'gda-licence-chip';

                        if ($licencePrefix === 'A') {
                            $licenceTooltip = Text::_('COM_GDA_SECRETARIAT_LICENCE_RENEWAL_HINT');
                        } elseif ($licencePrefix === 'N') {
                            $licenceTooltip = Text::_('COM_GDA_SECRETARIAT_LICENCE_CREATION_HINT');
                            $licenceClass .= ' gda-licence-chip--warning';
                        }
                        ?>
                        <?php $isCaciValidable = (bool) ($item->is_caci_validable ?? false); ?>
                        <!-- suppression -->
                        <tr>
                            <td class="text-center">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger js-delete-adherent"
                                    data-item-id="<?= (int) $item->id_profil ?>"
                                    data-item-campagne="<?= (int) $item->id_campagne ?>"
                                    data-item-civilite="<?= $this->escape($civilite) ?>"
                                    data-item-name="<?= $this->escape($fullName) ?>"
                                    data-item-licence="<?= $this->escape($licenceValue) ?>"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="<?= $this->escape($deleteTooltip) ?>"
                                    title="<?= $this->escape($deleteTooltip) ?>"
                                    aria-label="<?= $this->escape($deleteTooltip) ?>">
                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                </button>
                            </td>
                            <!-- photo -->
                            <td class="text-center">
                                <?php if (!empty($pathPhoto)) : ?>
                                    <a
                                        href="#"
                                        class="js-image-preview-thumb"
                                        data-image-src="<?= $this->escape($pathPhoto) ?>"
                                        data-image-alt="<?= $this->escape(($item->civilite ?? '') . ' ' . ($item->nom ?? '') . ' ' . ($item->prenom ?? '')) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#imagePreviewModal"
                                        aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PHOTO') ?? 'Photo') ?>">
                                        <img
                                            src="<?= $this->escape($pathPhoto) ?>"
                                            alt="<?= $this->escape(($item->civilite ?? '') . ' ' . ($item->nom ?? '') . ' ' . ($item->prenom ?? '')) ?>"
                                            width="64"
                                            height="64"
                                            loading="lazy"
                                            class="gda-preview-thumb gda-preview-thumb--64">
                                    </a>
                                <?php else : ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- NomPrenom -->
                            <td class="text-center">
                                <?php if ($licenceValue !== '') : ?>
                                    <span
                                        class="<?= $this->escape($licenceClass) ?>"
                                        <?php if ($licenceTooltip !== '') : ?>
                                            data-bs-toggle="tooltip"
                                            data-bs-title="<?= $this->escape($licenceTooltip) ?>"
                                            title="<?= $this->escape($licenceTooltip) ?>"
                                        <?php endif; ?>><?= $this->escape($licenceValue) ?></span>
                                <?php else : ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- civilité -->
                            <td>
                                <a href="#" class="js-show-profil-card" data-id-profil="<?= (int) $item->id_profil ?>"><?= $this->escape($item->civilite . ' ' . $item->nom . ' ' . $item->prenom) ?></a>
                            </td>
                            <!-- email -->
                            <td><?= $this->escape($item->email) ?></td>
                            <!-- adresse -->
                            <td><?= $this->escape($item->adresse . ' ' . $item->code_postal . ' ' . $item->ville) ?></td>
                            <!-- <td><?= $item->cotisation_code[1] === "1" ? '✅' : '❌' ?></td> -->
                             <!-- photo CACI -->
                            <td class="text-center">
                                <?php if (!empty($pathCaci)) : // affiche miature Caci
                                ?>
                                    <a
                                        href="#"
                                        class="js-caci-thumb"
                                        data-caci-src="<?= $this->escape($pathCaci) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#imagePreviewModal"
                                        aria-label="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI') ?? 'CACI') ?>">
                                        <img
                                            src="<?= $this->escape($pathCaci) ?>"
                                            alt="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_CACI') ?? 'CACI') ?>"
                                            width="32"
                                            height="32"
                                            loading="lazy"
                                            style="object-fit: cover; cursor: zoom-in;">
                                    </a>
                                <?php else : echo ('❌'); ?>
                                <?php endif; ?>
                            </td>
                            <!-- Date caci -->
                            <td
                                class="js-editable-date-caci text-center"
                                data-item-id="<?= $item->id_profil ?>"
                                data-item-campagne="<?= (int) $item->id_campagne ?>"
                                data-current-date="<?= $this->escape($item->date_caci) ?>"
                                data-bs-toggle="tooltip"
                                data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_DATE_CACI_EDIT_HINT')); ?>"
                                style="cursor: pointer;"
                                title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_DATE_CACI_EDIT_HINT')); ?>">
                                <span class="date-display"><?= $this->escape($item->date_caci) ?></span>
                                <input
                                    type="text"
                                    class="form-control form-control-sm date-input d-none"
                                    placeholder="DD/MM/YYYY"
                                    data-date-format="DD/MM/YYYY" />
                            </td>
                            <!-- Date souscription -->
                            <td class="text-center"><?= HTMLHelper::date($item->date_souscription, 'd/m/Y H:i') ?></td>
                            <!-- Action -->
                            <td class="text-center">
                                <span
                                    class="d-inline-block"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="<?= $this->escape($isCaciValidable ? Text::_('COM_GDA_SECRETARIAT_CACI_VALIDE') : Text::_('COM_GDA_SECRETARIAT_CACI_VALIDE_DISABLED_HINT')) ?>"
                                    title="<?= $this->escape($isCaciValidable ? Text::_('COM_GDA_SECRETARIAT_CACI_VALIDE') : Text::_('COM_GDA_SECRETARIAT_CACI_VALIDE_DISABLED_HINT')) ?>">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary js-validate-caci"
                                        data-item-id="<?= (int) $item->id_profil ?>"
                                        data-item-campagne="<?= (int) $item->id_campagne ?>"
                                        <?= $isCaciValidable ? '' : 'disabled aria-disabled="true"' ?>>
                                        <?= Text::_('COM_GDA_SECRETARIAT_CACI_VALIDE') ?>
                                    </button>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        <?php endif; ?>
    </div>


</div>