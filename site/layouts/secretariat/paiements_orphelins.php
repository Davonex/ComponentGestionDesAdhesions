<?php

use Joomla\CMS\Language\Text;

/**
 * Layout : tableau des paiements HelloAsso d'une campagne, résolus ou orphelins. Volontairement
 * agnostique de #__gda_souscriptions : les lignes/candidats sont déjà normalisés par l'appelant
 * (RapprochementPaiementService::getPaiementsOrphelins()), pour permettre une réutilisation future
 * avec d'autres campagnes (Formation/Loisir via #__gda_reservation) sans modifier ce layout.
 *
 * Les colonnes Commande/Date/Payeur proviennent de HelloAsso (teintées en vert clair,
 * `bg-success-subtle`), la colonne locale Adhérent associé est teintée avec le token de charte
 * graphique `--logo-droit-500` (défini dans le template `cassiopeia_ncb`), pour les distinguer
 * visuellement l'une de l'autre.
 *
 * @var array $displayData
 * - $displayData['lignes']      : array<object> {id_order, date, payeur_nom, payeur_licence,
 *                                  resolved(bool), adherent_id_profil(?int), adherent_nom(?string),
 *                                  adherent_licence(?string)}
 * - $displayData['candidats']   : array<object> {id_profil, label} - candidats libres de la campagne
 * - $displayData['id_campagne'] : int
 */

$lignes = $displayData['lignes'] ?? [];
$candidats = $displayData['candidats'] ?? [];
$idCampagne = (int) ($displayData['id_campagne'] ?? 0);
?>

<div class="card mb-3">
  <div class="card-body">
    <?php if (empty($lignes)) : ?>
      <p class="text-muted"><?= Text::_('COM_GDA_SECRETARIAT_STEP5_EMPTY') ?></p>
    <?php else : ?>
      <table class="table table-bordered table-striped secretariat-table">
        <thead>
          <tr>
            <th class="bg-success-subtle" style="width: 110px;"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_COMMANDE') ?></th>
            <th class="bg-success-subtle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_DATE') ?></th>
            <th class="bg-success-subtle"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_PAYEUR') ?></th>
            <th style="background-color: var(--logo-droit-500);"><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_ADHERENT_ASSOCIE') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lignes as $ligne) : ?>
            <tr>
              <td class="bg-success-subtle text-center">
                <?php if (!empty($ligne->resolved)) : ?>
                  <a
                    href="#"
                    class="js-show-payement badge bg-secondary text-decoration-none"
                    data-item-id="<?= (int) $ligne->adherent_id_profil ?>"
                    data-item-campagne="<?= $idCampagne ?>"
                    data-item-order="<?= $this->escape((string) $ligne->id_order) ?>"
                    data-bs-toggle="tooltip"
                    data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_HINT')) ?>">
                    <?= $this->escape((string) $ligne->id_order) ?>
                  </a>
                <?php else : ?>
                  <a
                    href="#"
                    class="js-show-commande-detail badge bg-secondary text-decoration-none"
                    data-item-order="<?= $this->escape((string) $ligne->id_order) ?>"
                    data-item-campagne="<?= $idCampagne ?>"
                    data-bs-toggle="tooltip"
                    data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_ORPHELINS_DETAIL_COMMANDE_HINT')) ?>">
                    <?= $this->escape((string) $ligne->id_order) ?>
                  </a>
                <?php endif; ?>
              </td>
              <td class="bg-success-subtle text-center"><?= $this->escape((string) $ligne->date) ?></td>
              <td class="bg-success-subtle">
                <?= $this->escape((string) $ligne->payeur_nom) ?>
                (<?= $ligne->payeur_licence ? $this->escape($ligne->payeur_licence) : Text::_('COM_GDA_SECRETARIAT_ORPHELINS_LICENCE_INCONNUE') ?>)
              </td>
              <td
                class="js-orphelin-cell"
                style="background-color: var(--logo-droit-500);"
                data-item-order="<?= $this->escape((string) $ligne->id_order) ?>"
                data-item-campagne="<?= $idCampagne ?>">
                <?php if (!empty($ligne->resolved)) : ?>
                  <div
                    class="js-editable-adherent"
                    data-item-order="<?= $this->escape((string) $ligne->id_order) ?>"
                    data-item-campagne="<?= $idCampagne ?>"
                    data-current-profil="<?= (int) $ligne->adherent_id_profil ?>"
                    data-bs-toggle="tooltip"
                    data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_ORPHELINS_CHANGE_HINT')) ?>"
                    style="cursor: pointer;"
                    title="<?= Text::_('COM_GDA_SECRETARIAT_ORPHELINS_CHANGE_HINT') ?>">
                    <span class="adherent-display text-muted">
                      <?= $this->escape((string) $ligne->adherent_nom) ?>
                      (<?= $ligne->adherent_licence ? $this->escape($ligne->adherent_licence) : Text::_('COM_GDA_SECRETARIAT_ORPHELINS_LICENCE_INCONNUE') ?>)
                    </span>
                    <select class="adherent-input form-select form-select-sm d-none">
                      <option value=""><?= Text::_('COM_GDA_SECRETARIAT_ORPHELINS_CANDIDATE_DEFAULT') ?></option>
                      <option value="<?= (int) $ligne->adherent_id_profil ?>" selected>
                        <?= $this->escape((string) $ligne->adherent_nom) ?> (<?= $this->escape((string) $ligne->adherent_licence) ?>)
                      </option>
                      <?php foreach ($candidats as $candidat) : ?>
                        <option value="<?= (int) $candidat->id_profil ?>"><?= $this->escape((string) $candidat->label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php else : ?>
                  <div class="input-group input-group-sm">
                    <select class="form-select js-orphelin-candidat">
                      <option value=""><?= Text::_('COM_GDA_SECRETARIAT_ORPHELINS_CANDIDATE_DEFAULT') ?></option>
                      <?php foreach ($candidats as $candidat) : ?>
                        <option value="<?= (int) $candidat->id_profil ?>"><?= $this->escape((string) $candidat->label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button
                      type="button"
                      class="btn btn-primary js-orphelin-associer"
                      disabled
                      data-bs-toggle="tooltip"
                      data-bs-title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_ORPHELINS_ASSOCIER_HINT')) ?>"
                      title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_ORPHELINS_ASSOCIER_HINT')) ?>">
                      <?= Text::_('COM_GDA_SECRETARIAT_ORPHELINS_ASSOCIER') ?>
                    </button>
                  </div>
                  <?php if (empty($candidats)) : ?>
                    <div class="form-text text-warning"><?= Text::_('COM_GDA_SECRETARIAT_ORPHELINS_NO_CANDIDATS') ?></div>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
