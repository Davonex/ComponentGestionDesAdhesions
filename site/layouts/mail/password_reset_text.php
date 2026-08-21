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

echo Text::sprintf('COM_GDA_EMAIL_PASSWORD_RESET_INTRO', $displayName) . "\n\n";
echo Text::sprintf('COM_GDA_EMAIL_PASSWORD_RESET_USERNAME_LINE', $username) . "\n";
echo Text::sprintf('COM_GDA_EMAIL_PASSWORD_RESET_PASSWORD_LINE', $tempPassword) . "\n\n";
echo Text::_('COM_GDA_EMAIL_PASSWORD_RESET_BODY') . "\n\n";
echo Text::sprintf('COM_GDA_EMAIL_PASSWORD_RESET_LOGIN_LINE', $loginUrl) . "\n\n";
echo Text::_('COM_GDA_EMAIL_PASSWORD_RESET_FOOTER') . "\n";
