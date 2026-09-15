<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Layout : détail simplifié d'une commande HelloAsso encore orpheline (popup #payementModal, même
 * coquille que layouts/secretariat/payement.php). Aucun adhérent connu à ce stade : contrairement à
 * payement.php, pas de comparaison avec une cotisation attendue - uniquement les informations brutes
 * de la commande, y compris tous ses items (utile pour un paiement groupé familial), pour aider la
 * secrétaire à identifier à qui il appartient avant de l'associer manuellement.
 *
 * @var array $displayData
 * - $displayData['detail'] : object, voir RapprochementPaiementService::getDetailCommandeOrpheline()
 *   (id_order, date, payeur_nom, payeur_email, montant_total, items: array<object>{nom, licence})
 */
$detail = $displayData['detail'];
?>

<div class="modal-header bg-gda-header text-header">
  <h5 class="modal-title mb-0">
    <i class="fa-solid fa-file-invoice-dollar me-2"></i>
    <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_ORPHELINS_DETAIL_COMMANDE_TITLE')) ?>
  </h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
</div>

<div class="modal-body">
  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <div class="row g-3 align-items-center">
        <div class="col-auto">
          <span class="badge bg-secondary fs-6"><?= $this->escape($detail->id_order) ?></span>
        </div>
        <div class="col">
          <h6 class="card-title mb-1"><?= $this->escape($detail->payeur_nom) ?></h6>
          <p class="mb-0 text-muted small">
            <i class="fa-solid fa-clock me-1"></i> <?= $this->escape($detail->date) ?>
            <?php if ($detail->payeur_email !== '') : ?>
              &nbsp;•&nbsp; <i class="fa-solid fa-envelope me-1"></i> <?= $this->escape($detail->payeur_email) ?>
            <?php endif; ?>
          </p>
        </div>
        <div class="col-auto text-end">
          <div class="fs-5 fw-semibold"><?= $this->escape(sprintf('%.2f €', $detail->montant_total)) ?></div>
          <div class="text-muted small"><?= Text::_('COM_GDA_SECRETARIAT_ORPHELINS_DETAIL_COMMANDE_MONTANT_TOTAL') ?></div>
        </div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_NAME') ?></th>
              <th><?= Text::_('COM_GDA_SECRETARIAT_TABLE_HEADER_LICENCE') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($detail->items)) : ?>
              <tr>
                <td colspan="2" class="text-muted"><?= Text::_('COM_GDA_SECRETARIAT_ORPHELINS_DETAIL_COMMANDE_EMPTY') ?></td>
              </tr>
            <?php else : ?>
              <?php foreach ($detail->items as $item) : ?>
                <tr>
                  <td><?= $this->escape((string) $item->nom) ?></td>
                  <td>
                    <?= $item->licence ? $this->escape((string) $item->licence) : Text::_('COM_GDA_SECRETARIAT_ORPHELINS_LICENCE_INCONNUE') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $this->escape(Text::_('JCLOSE')) ?></button>
</div>
