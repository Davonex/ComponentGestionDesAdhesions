<?php

/**
 * Mail au responsable d'une campagne quand un adhérent s'inscrit, se désinscrit, modifie ses places
 * ou écrit un commentaire - voir NotificationMailService::sendReservationActivityEmail().
 *
 * @package     com_gdadhesions
 * @subpackage  layouts
 * @copyright   Copyright (C) 2024 GD Adhesions. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var object $displayData */

$evenement     = strtoupper((string) ($displayData->evenement ?? 'INSCRIPTION'));
$responsable   = trim((string) ($displayData->responsable_nom ?? ''));
$adherent      = trim(trim((string) ($displayData->civilite ?? '')) . ' ' . trim((string) ($displayData->prenom ?? '')) . ' ' . trim((string) ($displayData->nom ?? '')));
$username      = trim((string) ($displayData->username ?? ''));
$email         = trim((string) ($displayData->adherent_email ?? ''));
$telephone     = trim((string) ($displayData->telephone ?? ''));
$campagneTitre = trim((string) ($displayData->campagne_titre ?? ''));
$commentaire   = trim((string) ($displayData->commentaire ?? ''));
$lignes        = (array) ($displayData->lignes ?? []);
$suiviUrl      = Uri::root() . ltrim(Route::_('index.php?option=com_gdadhesions&view=campagnes', false), '/');

$cles = [
    'attente'   => 'COM_GDA_CAMPAGNES_SUIVI_STATUT_ATTENTE',
    'confirmee' => 'COM_GDA_CAMPAGNES_SUIVI_STATUT_CONFIRMEE',
    'refusee'   => 'COM_GDA_CAMPAGNES_SUIVI_STATUT_REFUSEE',
];

// « En cours de validation ×2, Validée » ; $vide = libellé quand le rôle n'a aucune place active.
$resume = static function (array $comptes, string $vide) use ($cles): string {
    if ($comptes === []) {
        return $vide;
    }

    $morceaux = [];
    foreach ($comptes as $statut => $nombre) {
        $morceaux[] = Text::_($cles[$statut] ?? 'COM_GDA_CAMPAGNES_SUIVI_STATUT_ATTENTE') . ((int) $nombre > 1 ? ' ×' . (int) $nombre : '');
    }

    return implode(', ', $morceaux);
};

$cell = 'padding: 8px; border: 1px solid #e5e7eb;';
?>
<div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
  <h2 style="margin: 0 0 16px;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_TITLE_' . $evenement)) ?></h2>
  <p style="margin: 0 0 12px;"><?= $this->escape(Text::sprintf('COM_GDA_EMAIL_FINALIZE_INTRO', $responsable)) ?></p>
  <p style="margin: 0 0 12px;"><?= Text::sprintf('COM_GDA_EMAIL_RESERVATION_ACTIVITY_BODY_' . $evenement, '<strong>' . $this->escape($adherent) . '</strong>', '<strong>' . $this->escape($campagneTitre) . '</strong>') ?></p>

  <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
    <tr>
      <td style="<?= $cell ?> font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_ADHERENT_LABEL')) ?></td>
      <td style="<?= $cell ?>"><?= $this->escape($adherent) ?> (<?= $this->escape($username) ?>)</td>
    </tr>
    <?php if ($email !== '') : ?>
    <tr>
      <td style="<?= $cell ?> font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_EMAIL_LABEL')) ?></td>
      <td style="<?= $cell ?>"><a href="mailto:<?= $this->escape($email) ?>"><?= $this->escape($email) ?></a></td>
    </tr>
    <?php endif; ?>
    <?php if ($telephone !== '') : ?>
    <tr>
      <td style="<?= $cell ?> font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_PHONE_LABEL')) ?></td>
      <td style="<?= $cell ?>"><?= $this->escape($telephone) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
      <td style="<?= $cell ?> font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_CAMPAIGN_LABEL')) ?></td>
      <td style="<?= $cell ?>"><?= $this->escape($campagneTitre) ?></td>
    </tr>
    <?php foreach ($lignes as $ligne) : ?>
    <tr>
      <td style="<?= $cell ?> font-weight: 600;"><?= $this->escape((string) $ligne['role']) ?></td>
      <td style="<?= $cell ?>">
        <?= $this->escape($resume((array) $ligne['avant'], Text::_('COM_GDA_RESERVATION_STATUT_NON_INSCRIT'))) ?>
        &rarr;
        <strong><?= $this->escape($resume((array) $ligne['apres'], Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_DESINSCRIT'))) ?></strong>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if ($commentaire !== '') : ?>
    <tr>
      <td style="<?= $cell ?> font-weight: 600;">
        <?= $this->escape(Text::_('COM_GDA_RESERVATION_COMMENTAIRE')) ?>
        <?php if (!empty($displayData->commentaire_modifie)) : ?>
          <br><small style="font-weight: 400; color: #198754;"><?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_COMMENT_NEW')) ?></small>
        <?php endif; ?>
      </td>
      <td style="<?= $cell ?>"><?= nl2br($this->escape($commentaire)) ?></td>
    </tr>
    <?php endif; ?>
  </table>

  <p style="margin: 0 0 18px;">
    <a href="<?= $this->escape($suiviUrl) ?>"
       style="display: inline-block; padding: 10px 18px; background: #198754; color: #ffffff; text-decoration: none; border-radius: 4px;">
      <?= $this->escape(Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_CTA')) ?>
    </a>
  </p>

  <p style="margin: 20px 0 0; font-size: 12px; color: #6b7280;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_FOOTER')) ?></p>
</div>
