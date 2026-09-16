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
 * Chaque `<tr>` porte un attribut `data-resolved` ("1"/"0", reflet de `$ligne->resolved`) : le
 * filtre Tous/Non associés/Associés (#orphelinsFilter, media/com_gdadhesions/js/secretariat.js)
 * s'appuie dessus pour retirer les lignes non voulues du DOM avant l'initialisation de
 * simple-datatables, purement côté client (pas de nouvel appel ajax par changement de filtre).
 *
 * La colonne Payeur affiche "Payé par X pour Y (licence)" : X est le payeur réel de la commande
 * (order.payer), Y (licence) le bénéficiaire déclaré dans HelloAsso pour cette place (item.user +
 * sa licence saisie) — à comparer avec la colonne Adhérent associé pour vérifier qu'une association
 * (automatique ou manuelle) est correcte.
 *
 * Message récapitulatif en tête (nombre de lignes encore orphelines), même motif que l'onglet
 * « Brevets des adhérents » (layouts/brevets/adherents_table.php, HtmlView::$nbNonRattaches).
 *
 * @var array $displayData
 * - $displayData['lignes']          : array<object> {id_order, date, payeur_nom, beneficiaire_nom,
 *                                      beneficiaire_licence, resolved(bool), adherent_id_profil(?int),
 *                                      adherent_nom(?string), adherent_licence(?string)}
 * - $displayData['candidats']       : array<object> {id_profil, label} - candidats libres de la campagne
 * - $displayData['id_campagne']     : int
 * - $displayData['nb_non_associes'] : int nombre de lignes encore orphelines
 */

$lignes = $displayData['lignes'] ?? [];
$candidats = $displayData['candidats'] ?? [];
$idCampagne = (int) ($displayData['id_campagne'] ?? 0);
$nbNonAssocies = (int) ($displayData['nb_non_associes'] ?? 0);
?>

<div class="card mb-3">
  <div class="card-body">
    <?php if (empty($lignes)) : ?>
      <p class="text-muted"><?= Text::_('COM_GDA_SECRETARIAT_STEP5_EMPTY') ?></p>
    <?php else : ?>
      <?php if ($nbNonAssocies > 0) : ?>
        <div class="alert alert-warning d-flex align-items-center gap-2" role="status">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <span><?= Text::plural('COM_GDA_SECRETARIAT_ORPHELINS_COUNT_NON_ASSOCIES', $nbNonAssocies) ?></span>
        </div>
      <?php else : ?>
        <div class="alert alert-success d-flex align-items-center gap-2" role="status">
          <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
          <span><?= Text::_('COM_GDA_SECRETARIAT_ORPHELINS_COUNT_TOUS_ASSOCIES') ?></span>
        </div>
      <?php endif; ?>

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
            <tr data-resolved="<?= !empty($ligne->resolved) ? '1' : '0' ?>">
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
                <?= $this->escape(Text::sprintf(
                  'COM_GDA_SECRETARIAT_PAYEMENT_PAYEUR',
                  (string) $ligne->payeur_nom,
                  (string) $ligne->beneficiaire_nom,
                  $ligne->beneficiaire_licence ? $ligne->beneficiaire_licence : Text::_('COM_GDA_SECRETARIAT_ORPHELINS_LICENCE_INCONNUE')
                )) ?>
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
