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

$displayName = trim((string) ($displayData->display_name ?? ''));
$username = trim((string) ($displayData->username ?? ''));
$tempPassword = (string) ($displayData->temp_password ?? '');
$loginUrl = Uri::root() . ltrim(Route::_('index.php?option=com_users&view=login', false), '/');
?>
<div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
  <h2 style="margin: 0 0 16px;"><?= $this->escape(Text::_('COM_GDA_EMAIL_PASSWORD_RESET_TITLE')) ?></h2>
  <p style="margin: 0 0 12px;"><?= $this->escape(Text::sprintf('COM_GDA_EMAIL_PASSWORD_RESET_INTRO', $displayName)) ?></p>

  <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
    <tr>
      <td style="padding: 8px; border: 1px solid #e5e7eb; font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_PASSWORD_RESET_USERNAME_LABEL')) ?></td>
      <td style="padding: 8px; border: 1px solid #e5e7eb;"><?= $this->escape($username) ?></td>
    </tr>
    <tr>
      <td style="padding: 8px; border: 1px solid #e5e7eb; font-weight: 600;"><?= $this->escape(Text::_('COM_GDA_EMAIL_PASSWORD_RESET_PASSWORD_LABEL')) ?></td>
      <td style="padding: 8px; border: 1px solid #e5e7eb; font-family: monospace; font-size: 16px;"><?= $this->escape($tempPassword) ?></td>
    </tr>
  </table>

  <p style="margin: 0 0 12px;"><?= $this->escape(Text::_('COM_GDA_EMAIL_PASSWORD_RESET_BODY')) ?></p>
  <p style="margin: 0 0 18px;">
    <a href="<?= $this->escape($loginUrl) ?>"><?= $this->escape(Text::_('COM_GDA_EMAIL_PASSWORD_RESET_LOGIN_LINK_LABEL')) ?></a>
  </p>
  <p style="margin: 20px 0 0; font-size: 12px; color: #6b7280;"><?= $this->escape(Text::_('COM_GDA_EMAIL_PASSWORD_RESET_FOOTER')) ?></p>
</div>
