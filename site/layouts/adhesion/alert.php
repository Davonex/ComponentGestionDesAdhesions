<?php

use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

/**
 * @var array $displayData
 * - $displayData['alerts'] : array de ['title' => string, 'message' => string, 'html' => bool]
 *
 * Rendu unique pour les popups de contrôle métier de la vue Adhésion (remplace GdaDialog.alert()) :
 * scan de licence introuvable/déjà connue, âge minimum non atteint, réduction Famille invalide.
 * Contenu complet d'une .modal-content, injecté par le JS dans #adhesionAlertModalContent.
 *
 * Par défaut, 'message' est échappé (texte brut) : c'est la seule protection contre l'injection
 * HTML pour ce layout. Un appelant peut opter pour du HTML (ex: une liste à puces) en passant
 * 'html' => true - à ne faire QUE pour un message statique écrit en dur/via une clé de langue,
 * jamais pour une valeur qui contient, même indirectement, de la saisie utilisateur.
 */

/** @var array<int, array{title: string, message: string, html?: bool}> $alerts */
$alerts = $displayData['alerts'] ?? [];
$title = count($alerts) === 1 ? $alerts[0]['title'] : Text::_('COM_GDA_ADHESION_ALERT_TITLE');
?>
<div class="modal-header bg-gda-header text-header">
  <h5 class="modal-title mb-0">
    <i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>
    <?= $this->escape($title) ?>
  </h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
</div>
<div class="modal-body">
  <?php foreach ($alerts as $index => $alert) : ?>
    <?php if ($index > 0) : ?><hr><?php endif; ?>
    <?php if (count($alerts) > 1) : ?>
      <h6 class="fw-semibold"><?= $this->escape($alert['title']) ?></h6>
    <?php endif; ?>
    <p class="mb-0"><?= !empty($alert['html']) ? $alert['message'] : $this->escape($alert['message']) ?></p>
  <?php endforeach; ?>
</div>
<div class="modal-footer border-0">
  <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?= Text::_('JOK') ?></button>
</div>
