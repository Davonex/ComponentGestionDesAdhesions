<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Layout : détail du paiement HelloAsso d'un adhérent (popup #payementModal), pur affichage.
 * Toute l'extraction/le calcul (correspondance item HelloAsso ↔ adhérent, montants, réduction,
 * restant dû) est fait par SecretariatModel::getPayement()/buildPaymentReport() — ce layout ne
 * fait que formater/échapper les propriétés déjà résolues.
 *
 * @var array $displayData
 * - $displayData['report'] : object rapport de paiement, voir SecretariatModel::buildPaymentReport()
 *   (order_found, id_order, date, libelle_choisi, statut, beneficiaire_nom, beneficiaire_licence,
 *   payeur_nom, payeur_email, montant_catalogue, reduction_code, reduction_montant, total_paye,
 *   cotisation_code, cotisation_label, cotisation_montant, cotisation_connue, difference,
 *   receipt_url, fiscal_receipt_url)
 */
$report = $displayData['report'];
?>

<div class="modal-header bg-gda-header text-header">
  <h5 class="modal-title mb-0">
    <i class="fa-solid fa-file-invoice-dollar me-2"></i>
    <?= $this->escape($report->beneficiaire_nom !== '' ? $report->beneficiaire_nom : Text::_('COM_GDA_SECRETARIAT_PAYEMENT')) ?>
  </h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
</div>

<div class="modal-body">
  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <?php if (!$report->order_found) : ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          <div><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_NO_RECU')) ?></div>
        </div>
      <?php else : ?>
        <?php if (!$report->cotisation_connue) : ?>
          <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <div><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_COTISATION_INCONNUE')) ?></div>
          </div>
        <?php endif; ?>

        <!-- Affichage des informations de paiement -->
        <div class="row g-3 align-items-center">
          <div class="col-auto">
            <span class="badge bg-secondary fs-6"><?= $this->escape($report->id_order) ?></span>
          </div>
          <div class="col">
            <h6 class="card-title mb-1"><?= $this->escape($report->libelle_choisi ?: Text::_('COM_GDA_SECRETARIAT_PAYEMENT')) ?></h6>
            <p class="mb-0 text-muted small">
              <i class="fa-solid fa-clock me-1"></i> <?= $this->escape($report->date) ?>
              <?php if ($report->beneficiaire_licence !== '') : ?>
                &nbsp;•&nbsp; <i class="fa-solid fa-user me-1"></i> <?= $this->escape($report->beneficiaire_licence) ?>
              <?php endif; ?>
            </p>
            <?php if ($report->payeur_nom !== '' && $report->payeur_nom !== $report->beneficiaire_nom) : ?>
              <p class="mb-0 text-muted small">
                <i class="fa-solid fa-credit-card me-1"></i> <?= $this->escape(Text::sprintf('COM_GDA_SECRETARIAT_PAYEMENT_PAYEUR', $report->payeur_nom)) ?>
              </p>
            <?php endif; ?>
          </div>
          <div class="col-auto text-end">
            <div class="fs-5 fw-semibold"><?= $this->escape(sprintf('%.2f €', $report->total_paye)) ?></div>
            <div class="text-muted small"><?= $this->escape($report->statut) ?></div>
          </div>
        </div>

        <!-- Récapitulatif : Montant / Réduction / Total payé / Cotisation attendue / Restant dû ou Trop versé -->
        <div class="table-responsive mb-3 mt-3">
          <table class="table table-sm align-middle mb-0">
            <tbody>
              <tr>
                <th class="fw-normal"><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_MONTANT')) ?></th>
                <td class="text-end"><?= $this->escape(sprintf('%.2f €', $report->montant_catalogue)) ?></td>
              </tr>
              <?php if ($report->reduction_montant > 0) : ?>
                <tr class="text-success">
                  <th class="fw-normal">
                    <i class="fa-solid fa-tag me-1"></i>
                    <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_DISCOUNT')) ?>
                    <?php if (!empty($report->reduction_code)) : ?>
                      <span class="text-muted">(<?= $this->escape($report->reduction_code) ?>)</span>
                    <?php endif; ?>
                  </th>
                  <td class="text-end">-<?= $this->escape(sprintf('%.2f €', $report->reduction_montant)) ?></td>
                </tr>
              <?php endif; ?>
              <tr class="table-light">
                <th><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_TOTAL')) ?></th>
                <th class="text-end"><?= $this->escape(sprintf('%.2f €', $report->total_paye)) ?></th>
              </tr>
              <tr>
                <th class="fw-normal">
                  <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_COTISATION_ATTENDUE')) ?>
                  <?php if ($report->cotisation_label !== '') : ?>
                    <span class="text-muted">(<?= $this->escape($report->cotisation_label) ?>)</span>
                  <?php endif; ?>
                </th>
                <td class="text-end">
                  <?php if ($report->cotisation_connue) : ?>
                    <?= $this->escape(sprintf('%.2f €', $report->cotisation_montant)) ?>
                  <?php else : ?>
                    <span class="text-warning" title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_COTISATION_INCONNUE')) ?>">
                      <i class="fa-solid fa-circle-question"></i>
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr class="table-light">
                <th>
                  <?= $this->escape($report->difference < 0
                    ? Text::_('COM_GDA_SECRETARIAT_PAYEMENT_TROP_VERSE')
                    : Text::_('COM_GDA_SECRETARIAT_PAYEMENT_RESTANT_DU')) ?>
                </th>
                <th class="text-end">
                  <?php if (!$report->cotisation_connue) : ?>
                    <span class="text-warning"><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_RESTANT_DU_INCONNU')) ?></span>
                  <?php elseif ($report->difference > 0) : ?>
                    <span class="text-danger"><?= $this->escape(sprintf('%.2f €', $report->difference)) ?></span>
                  <?php elseif ($report->difference < 0) : ?>
                    <span class="text-warning"><?= $this->escape(sprintf('%.2f €', abs($report->difference))) ?></span>
                  <?php else : ?>
                    <span class="text-success"><?= $this->escape(sprintf('%.2f €', $report->difference)) ?></span>
                  <?php endif; ?>
                </th>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex gap-2 justify-content-end">
          <?php if ($report->receipt_url !== '') : ?>
            <a href="<?= $this->escape($report->receipt_url) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
              <i class="fa-solid fa-file-invoice me-1"></i> <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_RECU')) ?>
            </a>
          <?php endif; ?>
          <?php if ($report->fiscal_receipt_url !== '') : ?>
            <a href="<?= $this->escape($report->fiscal_receipt_url) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success">
              <i class="fa-solid fa-receipt me-1"></i> <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_FISCAL')) ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $this->escape(Text::_('JCLOSE')) ?></button>
</div>
