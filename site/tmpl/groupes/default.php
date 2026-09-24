<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\HTML\Helpers\Bootstrap;

Bootstrap::tab();
Bootstrap::modal();

/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();

$wa->useStyle('com_gdadhesions.gda');

// datatables
$wa->useScript('simple-datatables');
$wa->useStyle('simple-datatables');

// Main Groupes JS
$wa->useScript('com_gdadhesions.groupes');

// Handler générique de popups ajax (fiche adhérent, ...)
$wa->useScript('com_gdadhesions.form_modal');

/** @var array $groupes */
$groupes = $this->groupes;
?>

<div class="gda-groupes card shadow-lg p-2 p-md-4">

    <?php if (!$this->saison) : ?>
        <p class="text-muted"><?= Text::_('COM_GDA_GROUPES_NO_SAISON') ?></p>
    <?php elseif (empty($groupes)) : ?>
        <p class="text-muted"><?= Text::_('COM_GDA_GROUPES_TABLE_EMPTY') ?></p>
    <?php else : ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <?php // Le mode Détail (tableau) n'est pas lisible sur téléphone : bascule masquée sous md, vignettes forcées par groupes.js. ?>
            <div class="btn-group d-none d-md-inline-flex" role="group" aria-label="<?= $this->escape(Text::_('COM_GDA_GROUPES_DISPLAY_MODE')) ?>">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnGroupesDisplayDetail" data-display-mode="detail">
                    <i class="fa-solid fa-table-list me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_GROUPES_DISPLAY_DETAIL') ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary active" id="btnGroupesDisplayVignette" data-display-mode="vignette">
                    <i class="fa-solid fa-grip me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_GROUPES_DISPLAY_VIGNETTE') ?>
                </button>
            </div>

            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="switchGroupesHideEmpty" checked>
                <label class="form-check-label" for="switchGroupesHideEmpty"><?= Text::_('COM_GDA_GROUPES_HIDE_EMPTY') ?></label>
            </div>
        </div>

        <?php // Sur téléphone, les onglets empilés occupent tout l'écran : une liste déroulante les remplace et pilote les mêmes onglets (groupes.js). ?>
        <select class="form-select d-md-none mb-2" id="groupesSelect" aria-label="<?= $this->escape(Text::_('COM_GDA_GROUPES_SELECT_LABEL')) ?>">
            <?php foreach ($groupes as $index => $groupe) : ?>
                <?php $count = count($groupe->adherents); ?>
                <option value="groupe-tab-<?= $groupe->id_groupe ?>" data-count="<?= $count ?>"<?= $index === 0 ? ' selected' : '' ?>>
                    <?= $this->escape($groupe->id_groupe === 0 ? Text::_('COM_GDA_GROUPES_ALL_TAB') : $groupe->groupe_name) ?> (<?= $count ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <ul class="nav nav-tabs d-none d-md-flex" id="groupesTabNav" role="tablist">
            <?php foreach ($groupes as $index => $groupe) : ?>
                <?php
                $tabId = 'groupe-tab-' . $groupe->id_groupe;
                $paneId = 'groupe-pane-' . $groupe->id_groupe;
                $count = count($groupe->adherents);
                ?>
                <li class="nav-item gda-groupes-tab-item" role="presentation" data-count="<?= $count ?>">
                    <button
                        class="nav-link<?= $index === 0 ? ' active' : '' ?>"
                        id="<?= $tabId ?>"
                        data-bs-toggle="tab"
                        data-bs-target="#<?= $paneId ?>"
                        type="button"
                        role="tab"
                        aria-controls="<?= $paneId ?>"
                        aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                        <?php if (!empty($groupe->icon)) : ?>
                            <i class="fa-solid <?= $this->escape($groupe->icon) ?> me-1" aria-hidden="true"></i>
                        <?php endif; ?>
                        <?= $this->escape($groupe->id_groupe === 0 ? Text::_('COM_GDA_GROUPES_ALL_TAB') : $groupe->groupe_name) ?>
                        <span class="badge bg-secondary ms-1"><?= $count ?></span>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="tab-content gda-groupes-tab-content border border-top-0 p-0 p-md-3" id="groupesTabContent">
            <?php foreach ($groupes as $index => $groupe) : ?>
                <?php $paneId = 'groupe-pane-' . $groupe->id_groupe; ?>
                <div
                    class="tab-pane fade<?= $index === 0 ? ' show active' : '' ?>"
                    id="<?= $paneId ?>"
                    role="tabpanel"
                    aria-labelledby="groupe-tab-<?= $groupe->id_groupe ?>">

                    <div class="d-none d-md-flex justify-content-end mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary js-groupe-export-pdf" data-target="#<?= $paneId ?> table">
                            <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_GROUPES_EXPORT_PDF') ?>
                        </button>
                    </div>

                    <div class="gda-groupes-view gda-groupes-view--detail d-none" data-view-mode="detail">
                        <?= LayoutHelper::render('groupes.detail', ['groupe' => $groupe]) ?>
                    </div>
                    <div class="gda-groupes-view gda-groupes-view--vignette" data-view-mode="vignette">
                        <?= LayoutHelper::render('groupes.vignette', ['groupe' => $groupe]) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <!-- Modals -->        
    <?= LayoutHelper::render('commun.image_preview_modal') ?>

    <div class="modal fade" id="profilCardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" id="profilCardModalContent">
                <!-- Le contenu de la modal est chargé dynamiquement via ajax -->
            </div>
        </div>
    </div>

</div>
