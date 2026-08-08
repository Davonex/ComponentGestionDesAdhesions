<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * @var array $displayData
 * - $displayData['payement']    : données de la commande HelloAsso
 * - $displayData['id_profil']   : identifiant du profil
 * - $displayData['id_campagne'] : identifiant de la campagne
 */
$payment    = (array) ($displayData['payement'] ?? []);
$idProfil   = (int) ($displayData['id_profil'] ?? 0);
$idCampagne = (int) ($displayData['id_campagne'] ?? 0);

// Recupere les données intéressantes du paiement
$Item      = $payment['items'][0] ?? [];
$NomPrenom = trim(($Item['user']['lastName'] ?? '') . ' ' . ($Item['user']['firstName'] ?? ''));

// Reçus HelloAsso : URL d'attestation de paiement et reçu fiscal éventuel.
// Ouverts dans un nouvel onglet (HelloAsso exige une session connectée, l'aperçu
// intégré n'est donc pas possible).
$receiptUrl = '';
$fiscalUrl  = '';

foreach (($payment['payments'] ?? []) as $pay) {
  if ($receiptUrl === '' && !empty($pay['paymentReceiptUrl'])) {
    $receiptUrl = (string) $pay['paymentReceiptUrl'];
  }

  if ($fiscalUrl === '' && !empty($pay['fiscalReceiptUrl'])) {
    $fiscalUrl = (string) $pay['fiscalReceiptUrl'];
  }
}

?>
<!-- .modal-header -->
<div class="modal-header">

  <h3 class="modal-title" modal-title><?= $this->escape($NomPrenom !== '' ? $NomPrenom : Text::_('COM_GDA_SECRETARIAT_PAYEMENT')) ?></h3>
  <button type="button" id="closeModalForm" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body p-0">
  <?php if ($receiptUrl !== '') : ?>
    <iframe
      src="<?= $this->escape($receiptUrl) ?>"
      title="<?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_RECU')) ?>"
      style="width:100%; height:75vh; border:0;"
      loading="lazy"></iframe>
  <?php else : ?>
    <div class="alert alert-warning m-3" role="alert">
      <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_NO_RECU')) ?>
    </div>
  <?php endif; ?>
</div><!-- .modal-body -->


<div class="modal-footer">
  <?php if ($receiptUrl !== '') : ?>
    <a href="<?= $this->escape($receiptUrl) ?>" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
      <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_OUVRIR_RECU')) ?>
    </a>
  <?php endif; ?>
  <?php if ($fiscalUrl !== '') : ?>
    <a href="<?= $this->escape($fiscalUrl) ?>" class="btn btn-outline-primary" target="_blank" rel="noopener noreferrer">
      <?= $this->escape(Text::_('COM_GDA_SECRETARIAT_PAYEMENT_OUVRIR_RECU_FISCAL')) ?>
    </a>
  <?php endif; ?>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $this->escape(Text::_('JCLOSE')) ?></button>
</div><!-- .modal-footer -->