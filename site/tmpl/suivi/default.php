<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\Helpers\Bootstrap;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

Bootstrap::tab();
Bootstrap::modal();
// Techniques repliables du formulaire d'évaluation.
Bootstrap::collapse();
// Charge le module Tooltip : les infobulles du bilan (chargé en ajax) sont créées par suivi.js.
Bootstrap::tooltip();

/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();

$wa->useStyle('com_gdadhesions.gda');

// simpleCallAjax
$wa->useScript('com_gdadhesions.form_modal');
$wa->useScript('com_gdadhesions.suivi');

Text::script('COM_GDA_SUIVI_ERR_DATE_SEANCE');

/** @var array $groupes */
$groupes = $this->groupes;
$aujourdhui = ToolsHelper::now('Y-m-d');
?>

<div class="gda-suivi card shadow-lg p-2 p-md-4">

    <?php // Sans saison ni groupe évalué, seul un Responsable de Groupe a encore un onglet utile : « Compétences ». ?>
    <?php if (!$this->saison && !$this->peutGererCompetences) : ?>
        <p class="text-muted"><?= Text::_('COM_GDA_GROUPES_NO_SAISON') ?></p>
    <?php elseif (empty($groupes) && !$this->peutGererCompetences) : ?>
        <p class="text-muted"><?= Text::_('COM_GDA_SUIVI_AUCUN_GROUPE') ?></p>
    <?php else : ?>
        <?php if (empty($groupes)) : ?>
            <p class="text-muted"><?= Text::_($this->saison ? 'COM_GDA_SUIVI_AUCUN_GROUPE' : 'COM_GDA_GROUPES_NO_SAISON') ?></p>
        <?php endif; ?>

        <ul class="nav nav-tabs" id="suiviTabNav" role="tablist">
            <?php foreach ($groupes as $index => $groupe) : ?>
                <?php $idGroupe = (int) $groupe->id_groupe; ?>
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link<?= $index === 0 ? ' active' : '' ?>"
                        id="suivi-tab-<?= $idGroupe ?>"
                        data-bs-toggle="tab"
                        data-bs-target="#suivi-pane-<?= $idGroupe ?>"
                        type="button"
                        role="tab"
                        aria-controls="suivi-pane-<?= $idGroupe ?>"
                        aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                        <?php if (!empty($groupe->icon)) : ?>
                            <i class="fa-solid <?= $this->escape($groupe->icon) ?> me-1" aria-hidden="true"></i>
                        <?php endif; ?>
                        <?= $this->escape($groupe->groupe_name) ?>
                    </button>
                </li>
            <?php endforeach; ?>
            <?php if ($this->peutGererCompetences) : ?>
                <li class="nav-item ms-md-auto" role="presentation">
                    <button
                        class="nav-link<?= empty($groupes) ? ' active' : '' ?>"
                        id="suivi-tab-competences"
                        data-bs-toggle="tab"
                        data-bs-target="#suivi-pane-competences"
                        type="button"
                        role="tab"
                        aria-controls="suivi-pane-competences"
                        aria-selected="<?= empty($groupes) ? 'true' : 'false' ?>">
                        <i class="fa-solid fa-list-check me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_SUIVI_ONGLET_COMPETENCES') ?>
                    </button>
                </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content border border-top-0 p-2 p-md-3" id="suiviTabContent">
            <?php foreach ($groupes as $index => $groupe) : ?>
                <?php $idGroupe = (int) $groupe->id_groupe; ?>
                <div
                    class="tab-pane fade<?= $index === 0 ? ' show active' : '' ?> js-suivi-pane"
                    id="suivi-pane-<?= $idGroupe ?>"
                    role="tabpanel"
                    aria-labelledby="suivi-tab-<?= $idGroupe ?>"
                    data-id-groupe="<?= $idGroupe ?>">

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-primary js-suivi-ajouter-seance">
                            <i class="fa-solid fa-calendar-plus me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_SUIVI_AJOUTER_SEANCE') ?>
                        </button>
                        <div class="input-group input-group-sm w-auto d-none js-suivi-nouvelle-seance">
                            <input
                                type="date"
                                class="form-control js-suivi-date-seance"
                                max="<?= $this->escape($aujourdhui) ?>"
                                value="<?= $this->escape($aujourdhui) ?>"
                                aria-label="<?= $this->escape(Text::_('COM_GDA_SUIVI_DATE_SEANCE')) ?>">
                            <button type="button" class="btn btn-success js-suivi-valider-seance">
                                <?= Text::_('COM_GDA_SUIVI_AJOUTER') ?>
                            </button>
                        </div>
                    </div>

                    <div class="js-suivi-tableau">
                        <div class="text-center py-4">
                            <div class="spinner-border text-success" role="status">
                                <span class="visually-hidden"><?= Text::_('COM_GDA_SUIVI_CHARGEMENT') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($this->peutGererCompetences) : ?>
                <div
                    class="tab-pane fade<?= empty($groupes) ? ' show active' : '' ?> js-suivi-competences-pane"
                    id="suivi-pane-competences"
                    role="tabpanel"
                    aria-labelledby="suivi-tab-competences">
                    <div class="text-center py-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden"><?= Text::_('COM_GDA_SUIVI_CHARGEMENT') ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <!-- Modals -->
    <div class="modal fade" id="suiviEvaluationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
            <div class="modal-content" id="suiviEvaluationModalContent">
                <!-- Formulaire d'évaluation chargé en ajax (layout suivi.formulaire) -->
            </div>
        </div>
    </div>

    <div class="modal fade" id="suiviBilanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
            <div class="modal-content" id="suiviBilanModalContent">
                <!-- Bilan de l'élève chargé en ajax (layout suivi.bilan) -->
            </div>
        </div>
    </div>

</div>
