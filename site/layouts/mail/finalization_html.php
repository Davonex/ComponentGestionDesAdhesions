<?php

/**
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

$civilite = trim((string) ($displayData->civilite ?? ''));
$nom = trim((string) ($displayData->nom ?? ''));
$prenom = trim((string) ($displayData->prenom ?? ''));
$campagneTitre = trim((string) ($displayData->campagne_titre ?? ''));
$username = trim((string) ($displayData->username ?? ''));
$fullName = trim($civilite . ' ' . $nom . ' ' . $prenom);
$profilUrl = Uri::root() . ltrim(Route::_('index.php?option=com_gdadhesions&view=profil', false), '/');
?>
<div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
  <h2 style="margin: 0 0 16px;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_TITLE')) ?></h2>
  <p style="margin: 0 0 12px;"><?= $this->escape(Text::sprintf('COM_GDA_EMAIL_FINALIZE_INTRO', $fullName)) ?></p>
  <p style="margin: 0 0 12px;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_BODY')) ?></p>

  <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
    <tr>
      <td style="padding: 8px; border: 1px solid #e5e7eb; font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_CAMPAIGN_LABEL')) ?></td>
      <td style="padding: 8px; border: 1px solid #e5e7eb;"><?= $this->escape($campagneTitre) ?></td>
    </tr>
    <tr>
      <td style="padding: 8px; border: 1px solid #e5e7eb; font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_USERNAME_LABEL')) ?></td>
      <td style="padding: 8px; border: 1px solid #e5e7eb;"><?= $this->escape($username) ?></td>
    </tr>
  </table>

  <p style="margin: 0 0 18px;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_PROFILE_LINK_LABEL')) ?></p>
  <p style="margin: 20px 0 0; font-size: 12px; color: #6b7280;"><?= $this->escape(Text::_('COM_GDA_EMAIL_FINALIZE_FOOTER')) ?></p>
</div>
