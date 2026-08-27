<?php

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\FileHelper;

defined('_JEXEC') or die;

/**
 * @var array $displayData
 * - $displayData['campagne']     : campagne réservée (doit exposer titre)
 * - $displayData['urlHelloAsso'] : URL de paiement HelloAsso (décodée depuis campagne.event_helloasso)
 *
 * Popup affiché juste après une réservation avec au moins une place confirmée, sur une campagne
 * liée à un événement HelloAsso, tant que le paiement n'a pas été rapproché
 * (#__gda_reservation.id_order vide) — voir ReservationController::reserver(). Même motif visuel
 * que layouts/adhesion/popup.php (étapes numérotées), adapté à une réservation de campagne
 * plutôt qu'à une adhésion.
 */

$campagne     = $displayData['campagne'];
$urlHelloAsso = $displayData['urlHelloAsso'];
?>

<div class="modal-header bg-gda-header text-header">
  <h5 class="modal-title mb-0">
    <i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>
    <?= Text::sprintf('COM_GDA_RESERVATION_HELLOASSO_TITLE', $this->escape($campagne->titre)) ?>
  </h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
</div>

<div class="modal-body text-center px-4 py-3">

  <div class="alert alert-info border-0 bg-light rounded-3 p-3 mb-3 text-start">
    <p class="mb-2 text-center"><strong><?= Text::_('COM_GDA_RESERVATION_HELLOASSO_LAST_STEP') ?></strong></p>
    <p class="text-muted small mb-3 text-center"><?= Text::_('COM_GDA_RESERVATION_HELLOASSO_INTRO') ?></p>

    <ol class="list-unstyled mb-0">
      <li class="d-flex align-items-start mb-2">
        <span class="badge rounded-pill bg-primary me-2">1</span>
        <span class="small"><?= Text::sprintf('COM_GDA_RESERVATION_HELLOASSO_STEP1', $this->escape($campagne->titre)) ?></span>
      </li>
      <li class="d-flex align-items-start mb-2">
        <span class="badge rounded-pill bg-primary me-2">2</span>
        <span class="small"><?= Text::_('COM_GDA_RESERVATION_HELLOASSO_STEP2') ?></span>
      </li>
      <li class="d-flex align-items-start">
        <span class="badge rounded-pill bg-primary me-2">3</span>
        <span class="small"><?= Text::_('COM_GDA_RESERVATION_HELLOASSO_STEP3') ?></span>
      </li>
    </ol>
  </div>

  <a href="<?= $this->escape($urlHelloAsso) ?>" class="HaAuthorizeButton" target="_blank" rel="noopener">
    <img src="<?= $this->escape(FileHelper::getHelloAssoLogoSrc()) ?>" alt="HelloAsso" class="HaAuthorizeButtonLogo" />
    <span class="HaAuthorizeButtonTitle"><?= Text::_('COM_GDA_RESERVATION_HELLOASSO_CTA') ?></span>
  </a>

  <p class="text-muted small mt-3 mb-0">Vous serez redirigé vers la plateforme sécurisée HelloAsso.</p>

</div>

<div class="modal-footer">
  <button type="button" class="btn btn-success" data-bs-dismiss="modal"><?= Text::_('JOK') ?></button>
</div>
