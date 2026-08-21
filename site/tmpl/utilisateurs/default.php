<?php

/** Template de la vue "Utilisateurs" (Bureau) : groupes club et activation des comptes. */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\Helpers\Bootstrap;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;

Bootstrap::tab();
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
    <!-- <h3 class="mb-3"><?= Text::_('COM_GDA_VIEW_UTILISATEURS_MENU_LABEL') ?></h3> -->

    <?php if (empty($utilisateurs)) : ?>
        <p class="text-muted"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_EMPTY') ?></p>
    <?php else : ?>
        <!-- Filtres communs aux 3 onglets (appliqués simultanément aux 3 tableaux, voir utilisateurs.js) -->
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

        <ul class="nav nav-tabs" id="utilisateursTabNav" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="utilisateurs-tab-profils" data-bs-toggle="tab"
                    data-bs-target="#utilisateurs-pane-profils" type="button" role="tab"
                    aria-controls="utilisateurs-pane-profils" aria-selected="true">
                    <?= Text::_('COM_GDA_UTILISATEURS_TAB_PROFILS') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="utilisateurs-tab-acces" data-bs-toggle="tab"
                    data-bs-target="#utilisateurs-pane-acces" type="button" role="tab"
                    aria-controls="utilisateurs-pane-acces" aria-selected="false">
                    <?= Text::_('COM_GDA_UTILISATEURS_TAB_ACCES') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="utilisateurs-tab-trombinoscope" data-bs-toggle="tab"
                    data-bs-target="#utilisateurs-pane-trombinoscope" type="button" role="tab"
                    aria-controls="utilisateurs-pane-trombinoscope" aria-selected="false">
                    <?= Text::_('COM_GDA_UTILISATEURS_TAB_TROMBINOSCOPE') ?>
                </button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 p-3" id="utilisateursTabContent">

            <!-- Onglet Profils -->
            <div class="tab-pane fade show active" id="utilisateurs-pane-profils" role="tabpanel"
                aria-labelledby="utilisateurs-tab-profils">
                <div class="table-responsive">
                    <table class="table table-striped align-middle gda-table-compact" id="tableUtilisateursProfils">
                        <thead>
                            <tr>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_ADHESION') ?></th>
                                <th><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_PHOTO') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_NAME') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_LICENCE') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_CACI') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_BREVETS') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_EMAIL') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_SUPP') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilisateurs as $utilisateur) : ?>
                                <?= LayoutHelper::render('utilisateurs.row_profils', [
                                    'utilisateur' => $utilisateur,
                                    'clubGroups' => $clubGroups,
                                ]) ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Onglet Niveau d'accès -->
            <div class="tab-pane fade" id="utilisateurs-pane-acces" role="tabpanel"
                aria-labelledby="utilisateurs-tab-acces">
                <div class="table-responsive">
                    <table class="table table-striped align-middle gda-table-compact" id="tableUtilisateursAcces">
                        <thead>
                            <tr>
                                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_PHOTO') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_NAME') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_GROUPES') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_STATUT') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_PASSWORD') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilisateurs as $utilisateur) : ?>
                                <?= LayoutHelper::render('utilisateurs.row_acces', [
                                    'utilisateur' => $utilisateur,
                                    'clubGroups' => $clubGroups,
                                ]) ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Onglet Trombinoscope -->
            <div class="tab-pane fade" id="utilisateurs-pane-trombinoscope" role="tabpanel"
                aria-labelledby="utilisateurs-tab-trombinoscope">
                <div class="table-responsive">
                    <table class="table table-striped align-middle gda-table-compact" id="tableUtilisateursTrombinoscope">
                        <thead>
                            <tr>
                                <th class="text-center"><?= Text::_('COM_GDA_GROUPES_TABLE_HEADER_PHOTO') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_NAME') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_FONCTION') ?></th>
                                <th class="text-center"><?= Text::_('COM_GDA_UTILISATEURS_TABLE_HEADER_ORDRE') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilisateurs as $utilisateur) : ?>
                                <?= LayoutHelper::render('utilisateurs.row_trombi', [
                                    'utilisateur' => $utilisateur,
                                    'clubGroups' => $clubGroups,
                                ]) ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal de prévisualisation de la photo / du CACI -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-body text-center p-2">
                    <img id="imagePreviewImage" src="" alt="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'édition du profil (formulaire complet, chargé en ajax au clic sur le Nom Prénom) /
         fiche brevets (chargée en ajax au clic sur "Liste des brevets") -->
    <div class="modal fade" id="profilCardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" id="profilCardModalContent">
                <!-- Le contenu de la modal est chargé dynamiquement via ajax -->
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression définitive d'un adhérent -->
    <div class="modal fade" id="deleteUtilisateurModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger border-3">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <?= Text::_('COM_GDA_UTILISATEURS_DELETE_MODAL_TITLE') ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold text-danger">
                        <i class="fa-solid fa-circle-exclamation me-1"></i>
                        <?= Text::_('COM_GDA_UTILISATEURS_DELETE_MODAL_WARNING') ?>
                    </p>
                    <div class="d-flex align-items-center gap-3 p-2 border rounded bg-light">
                        <img id="deleteUtilisateurPhoto" src="" alt="" width="64" height="64" class="rounded d-none">
                        <div>
                            <div class="fw-semibold" id="deleteUtilisateurName"></div>
                            <div class="text-muted small" id="deleteUtilisateurUsername"></div>
                            <div class="text-muted small" id="deleteUtilisateurEmail"></div>
                        </div>
                    </div>
                    <input type="hidden" id="deleteUtilisateurId" value="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('COM_GDA_CANCEL') ?></button>
                    <button type="button" class="btn btn-danger" id="deleteUtilisateurSubmit">
                        <i class="fa-solid fa-trash-can me-1"></i> <?= Text::_('COM_GDA_UTILISATEURS_DELETE_CONFIRM') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de réinitialisation du mot de passe -->
    <div class="modal fade" id="resetPasswordUtilisateurModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-warning border-3">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-key me-2"></i>
                        <?= Text::_('COM_GDA_UTILISATEURS_RESET_PASSWORD_MODAL_TITLE') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        <?= Text::_('COM_GDA_UTILISATEURS_RESET_PASSWORD_MODAL_WARNING') ?>
                    </p>
                    <div class="d-flex align-items-center gap-3 p-2 border rounded bg-light">
                        <img id="resetPasswordUtilisateurPhoto" src="" alt="" width="64" height="64" class="rounded d-none">
                        <div>
                            <div class="fw-semibold" id="resetPasswordUtilisateurName"></div>
                            <div class="text-muted small" id="resetPasswordUtilisateurUsername"></div>
                            <div class="text-muted small" id="resetPasswordUtilisateurEmail"></div>
                        </div>
                    </div>
                    <input type="hidden" id="resetPasswordUtilisateurId" value="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('COM_GDA_CANCEL') ?></button>
                    <button type="button" class="btn btn-warning" id="resetPasswordUtilisateurSubmit">
                        <i class="fa-solid fa-key me-1"></i> <?= Text::_('COM_GDA_UTILISATEURS_RESET_PASSWORD_CONFIRM') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
