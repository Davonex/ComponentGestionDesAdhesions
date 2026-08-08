<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;

/**
 * @var array $displayData
 * - $displayData['payement']    : données de la commande HelloAsso
 * - $displayData['id_profil']   : identifiant du profil
 * - $displayData['id_campagne'] : identifiant de la campagne
 */
$payment    = (array) ($displayData['payement'] ?? []);
$idProfil   = (int) ($displayData['id_profil'] ?? 0);
$idCampagne = (int) ($displayData['id_campagne'] ?? 0);
$idOrder    = (int) ($displayData['id_order'] ?? '');
$cotisation = (int) ($displayData['cotisation'] ?? 0);
$licence = trim((string) ($displayData['username'] ?? ''));


/**
 * @var array $displayData
 * - $displayData['payement']    : données de la commande HelloAsso
 * - $displayData['id_profil']   : identifiant du profil
 * - $displayData['id_campagne'] : identifiant de la campagne
 */
$payment    = (array) ($displayData['payement'] ?? []);
$idProfil   = (int) ($displayData['id_profil'] ?? 0);
$idCampagne = (int) ($displayData['id_campagne'] ?? 0);
$idOrder    = (string) ($displayData['id_order'] ?? '');
$cotisation = (int) ($displayData['cotisation'] ?? 0);
$username   = trim((string) ($displayData['username'] ?? ''));

$OrderExisting = count($payment['payments'] ?? []) !== 0;


if ($OrderExisting) {

  // Récupération sécurisée des éléments du paiement
  $Items      = $payment['items'][0] ?? [];
  $NomPrenom  = trim(($Items['user']['lastName'] ?? '') . ' ' . ($Items['user']['firstName'] ?? ''));
  $dateStr    = $payment['date'] ?? '';
  $Date       = $dateStr !== '' ? (new DateTime($dateStr))->format('d/m/Y H:i') : '';
  $Amount     = (int) ($Items['amount'] ?? 0);
  $Commande   = (string) ($Items['name'] ?? '');

  $receiptUrl = '';
  foreach (($payment['payments'] ?? []) as $pay) {
    if ($receiptUrl === '' && !empty($pay['paymentReceiptUrl'])) {
      $receiptUrl = (string) $pay['paymentReceiptUrl'];
    }
  }
  $remaining = ($Amount - $cotisation * 100) / 100;
  $cotisationUnknown = $cotisation <= 0;
} else {

  $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
  $user = $userFactory->loadUserById($idProfil);
  $NomPrenom = trim($user->name);
}

?>

<div class="modal-header bg-gda-header text-header">
  <h5 class="modal-title mb-0">
    <i class="fa-solid fa-file-invoice-dollar me-2"></i>
    <?= $this->escape($NomPrenom !== '' ? $NomPrenom : Text::_('COM_GDA_SECRETARIAT_PAYEMENT')) ?>
  </h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
</div>

<div class="modal-body">
  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <!-- card content -->
      <?php if ($OrderExisting && $cotisationUnknown): ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          <div><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_COTISATION_INCONNUE')) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($OrderExisting): ?>
        <!-- Affichage des informations de paiement -->
        <div class="row g-3 align-items-center">
          <div class="col-auto">
            <span class="badge bg-secondary fs-6"><?= $this->escape($idOrder !== '' ? $idOrder : '') ?></span>
          </div>
          <div class="col">
            <h6 class="card-title mb-1"><?= $this->escape($Commande ?: Text::_('COM_GDA_SECRETARIAT_PAYEMENT')) ?></h6>
            <p class="mb-0 text-muted small">
              <i class="fa-solid fa-clock me-1"></i> <?= $this->escape($Date) ?>
              <?php if ($username !== ''): ?>
                &nbsp;•&nbsp; <i class="fa-solid fa-user me-1"></i> <?= $this->escape($username) ?>
              <?php endif; ?>
            </p>
          </div>
          <div class="col-auto text-end">
            <div class="fs-5 fw-semibold"><?= $this->escape(sprintf('%.2f €', $Amount / 100)) ?></div>
            <div class="text-muted small"><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT')) ?></div>
          </div>
        </div>
        <!-- Affichage du tableau  de paiement -->
        <div class="row g-3 align-items-center">
          <div class="table-responsive mb-3">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th class="text-center">#</th>
                  <th class="text-center"><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_STATE')) ?></th>
                  <th class="text-center"><i class="fa-solid fa-clock me-1"></i></th>
                  <th class="text-end"><i class="fa-solid fa-file-invoice-dollar me-2"></i></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (($payment['payments'] ?? []) as $index => $pay): ?>
                  <tr>
                    <td><?= $this->escape((string) ($index + 1)) ?></td>
                    <td class="text-center"><?= $this->escape(Text::_("COM_GDA_SECRETARIAT_PAYEMENT_STATE_" . strtoupper($pay['state'] ?? 'unknown'))) ?></td>
                    <td class="text-center"><?= $this->escape((new DateTime($pay['date'] ?? 'now'))->format('d/m/Y H:i')) ?></td>
                    <td class="text-end"><?= $this->escape(sprintf('%.2f €', ($pay['amount'] ?? 0) / 100)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <th colspan="3" class="text-end"><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_TOTAL')) ?></th>
                  <th class="text-end"><?= $this->escape(sprintf('%.2f €', $Amount / 100)) ?></th>
                </tr>
                <tr>
                  <th colspan="3" class="text-end"><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_RESTANT_DU')) ?></th>
                  <th class="text-end">
                    <?php if ($cotisationUnknown): ?>
                      <span class="text-warning" title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_COTISATION_INCONNUE')) ?>">
                        <i class="fa-solid fa-circle-question me-1"></i><?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_RESTANT_DU_INCONNU')) ?>
                      </span>
                    <?php else: ?>
                      <span class="<?= $remaining < 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $this->escape(sprintf('%.2f €', $remaining)) ?>
                      </span>
                    <?php endif; ?>
                  </th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
          <a href="<?= $this->escape($receiptUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
            <i class="fa-solid fa-file-invoice me-1"></i> <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_RECU')) ?>
          </a>
          <?php if (!empty($payment['fiscalReceiptUrl'] ?? '')): ?>
            <a href="<?= $this->escape($payment['fiscalReceiptUrl']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success">
              <i class="fa-solid fa-receipt me-1"></i> <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_FISCAL')) ?>
            </a>
          <?php endif; ?>
        </div>



      <?php else: ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          <div>
            <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_NO_RECU')) ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $this->escape(Text::_('JCLOSE')) ?></button>
</div>