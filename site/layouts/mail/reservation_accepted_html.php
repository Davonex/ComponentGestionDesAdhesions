<?php

/**
 * Mail « inscription validée » (Formation / Loisir), envoyé quand le responsable de campagne accepte
 * une place - voir NotificationMailService::sendReservationAcceptedEmail().
 *
 * @package     com_gdadhesions
 * @subpackage  layouts
 * @copyright   Copyright (C) 2024 GD Adhesions. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var object $displayData */

$fullName      = trim(trim((string) ($displayData->civilite ?? '')) . ' ' . trim((string) ($displayData->nom ?? '')) . ' ' . trim((string) ($displayData->prenom ?? '')));
$campagneTitre = trim((string) ($displayData->campagne_titre ?? ''));
$role          = trim((string) ($displayData->role ?? ''));
$paymentUrl    = trim((string) ($displayData->payment_url ?? ''));
$idArticle     = (int) ($displayData->id_article ?? 0);

// Description saisie par le Bureau (source de confiance, HTML autorisé), comme sur le dashboard.
$description = trim((string) ($displayData->campagne_description ?? ''));

$dateEvenement = !empty($displayData->date_evenement) && $displayData->date_evenement !== '0000-00-00 00:00:00'
    ? HTMLHelper::_('date', $displayData->date_evenement, 'd/m/Y H:i')
    : '';

$articleUrl = $idArticle > 0
    ? Uri::root() . ltrim(Route::_('index.php?option=com_content&view=article&id=' . $idArticle, false), '/')
    : '';

$cell = 'padding: 8px; border: 1px solid #e5e7eb;';
?>
<div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
  <h2 style="margin: 0 0 16px;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_TITLE')) ?></h2>
  <p style="margin: 0 0 12px;"><?= $this->escape(Text::sprintf('COM_GDA_EMAIL_FINALIZE_INTRO', $fullName)) ?></p>
  <p style="margin: 0 0 12px;"><?= Text::sprintf('COM_GDA_EMAIL_RESERVATION_ACCEPTED_BODY', '<strong>' . $this->escape($campagneTitre) . '</strong>') ?></p>

  <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
    <tr>
      <td style="<?= $cell ?> font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_CAMPAIGN_LABEL')) ?></td>
      <td style="<?= $cell ?>"><?= $this->escape($campagneTitre) ?></td>
    </tr>
    <?php if ($role !== '') : ?>
    <tr>
      <td style="<?= $cell ?> font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_ROLE_LABEL')) ?></td>
      <td style="<?= $cell ?>"><?= $this->escape($role) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($dateEvenement !== '') : ?>
    <tr>
      <td style="<?= $cell ?> font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_DATE_LABEL')) ?></td>
      <td style="<?= $cell ?>"><?= $this->escape($dateEvenement) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($description !== '') : ?>
    <tr>
      <td style="<?= $cell ?> font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_DESCRIPTION_LABEL')) ?></td>
      <td style="<?= $cell ?>"><?= $description ?></td>
    </tr>
    <?php endif; ?>
  </table>

  <?php if ($articleUrl !== '') : ?>
    <p style="margin: 0 0 16px;">
      <?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_ARTICLE_LABEL')) ?>
      <a href="<?= $this->escape($articleUrl) ?>"><?= $this->escape($articleUrl) ?></a>
    </p>
  <?php endif; ?>

  <?php if ($paymentUrl !== '') : ?>
    <p style="margin: 0 0 12px;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_PAYMENT_BODY')) ?></p>
    <p style="margin: 0 0 18px;">
      <a href="<?= $this->escape($paymentUrl) ?>"
         style="display: inline-block; padding: 10px 18px; background: #198754; color: #ffffff; text-decoration: none; border-radius: 4px;">
        <?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_PAYMENT_CTA')) ?>
      </a>
    </p>
  <?php endif; ?>

  <p style="margin: 20px 0 0; font-size: 12px; color: #6b7280;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_FOOTER')) ?></p>
</div>
