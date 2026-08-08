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

echo Text::sprintf('COM_GDA_EMAIL_FINALIZE_INTRO', $fullName) . "\n\n";
echo Text::_('COM_GDA_EMAIL_FINALIZE_BODY') . "\n\n";
echo Text::sprintf('COM_GDA_EMAIL_FINALIZE_CAMPAIGN_LINE', $campagneTitre) . "\n";
echo Text::sprintf('COM_GDA_EMAIL_FINALIZE_USERNAME_LINE', $username) . "\n";
echo Text::sprintf('COM_GDA_EMAIL_FINALIZE_PROFILE_LINK_LABEL', $profilUrl) . "\n\n";
echo Text::_('COM_GDA_EMAIL_FINALIZE_FOOTER') . "\n";
