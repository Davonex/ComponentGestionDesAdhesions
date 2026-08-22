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
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;

/** @var object $displayData */

$civilite = trim((string) ($displayData->civilite ?? ''));
$nom = trim((string) ($displayData->nom ?? ''));
$prenom = trim((string) ($displayData->prenom ?? ''));
$username = trim((string) ($displayData->username ?? ''));
$profilKey = trim((string) ($displayData->profil_key ?? ''));
$mode = trim((string) ($displayData->mode ?? 'update'));
$helloasso = trim((string) ($displayData->helloasso ?? '0'));
$cotisationCode = trim((string) ($displayData->cotisation_code ?? ''));
$fullName = trim($civilite . ' ' . $prenom . ' ' . $nom);
$profileUrl = Uri::root() . ltrim(Route::_('index.php?option=com_gdadhesions&view=adhesion&key=' . $profilKey, false), '/');
$bodyKey = $mode === 'create' ? 'COM_GDA_EMAIL_PROFILE_CREATE_BODY' : 'COM_GDA_EMAIL_PROFILE_UPDATE_BODY';
$urlHelloAsso = ConfHelper::getSaisonService()->getSaisonOuverte()->url ?? '#';

echo Text::sprintf('COM_GDA_EMAIL_PROFILE_LIFECYCLE_INTRO', $fullName) . "\n\n";
echo Text::_($bodyKey) . "\n\n";
// si le user est blocker alors on envoir le lien d'edition de profile
if (!UsersHelper::userExists($username) || UsersHelper::isBlocked($username)) {
    echo Text::_('COM_GDA_EMAIL_PROFILE_LIFECYCLE_LINK_LABEL') . ': ' . $profileUrl . "\n\n";
}
echo Text::sprintf('COM_GDA_EMAIL_PROFILE_LIFECYCLE_USERNAME_LINE', $username) . "\n";

echo Text::_('COM_GDA_EMAIL_PROFILE_LIFECYCLE_LINK_LABEL') . "\n\n";

// Section HelloAsso (memes textes que la popup de confirmation, adhesion.popup), sans le HTML
// <strong>/<span> du gabarit d'origine.
if ($helloasso === '0') {
    echo Text::_('COM_GDA_ADHESION_POPUP_LAST_STEP') . "\n";
    echo Text::_('COM_GDA_ADHESION_POPUP_HELLOASSO_INTRO') . "\n\n";
    echo '1. ' . strip_tags(Text::sprintf('COM_GDA_ADHESION_POPUP_STEP1', Text::_('COM_GDA_COTISATION_TARIF_' . $cotisationCode))) . "\n";
    echo '2. ' . strip_tags(Text::sprintf('COM_GDA_ADHESION_POPUP_STEP2', $username)) . "\n";
    echo '3. ' . Text::_('COM_GDA_ADHESION_POPUP_STEP3') . "\n";
    echo '4. ' . Text::_('COM_GDA_ADHESION_POPUP_STEP4') . "\n\n";
    echo Text::_('COM_GDA_ADHESION_POPUP_HELLOASSO_CTA') . ': ' . $urlHelloAsso . "\n\n";
}

echo Text::_('COM_GDA_EMAIL_PROFILE_LIFECYCLE_FOOTER') . "\n";