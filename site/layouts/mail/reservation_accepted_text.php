<?php

/**
 * Version texte du mail « inscription validée » - voir reservation_accepted_html.php.
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
$description   = trim(html_entity_decode(strip_tags((string) ($displayData->campagne_description ?? '')), ENT_QUOTES, 'UTF-8'));

$dateEvenement = !empty($displayData->date_evenement) && $displayData->date_evenement !== '0000-00-00 00:00:00'
    ? HTMLHelper::_('date', $displayData->date_evenement, 'd/m/Y H:i')
    : '';

echo Text::sprintf('COM_GDA_EMAIL_FINALIZE_INTRO', $fullName) . "\n\n";
echo Text::sprintf('COM_GDA_EMAIL_RESERVATION_ACCEPTED_BODY', $campagneTitre) . "\n\n";
echo Text::_('COM_GDA_EMAIL_FINALIZE_CAMPAIGN_LABEL') . ' : ' . $campagneTitre . "\n";

if ($role !== '') {
    echo Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_ROLE_LABEL') . ' : ' . $role . "\n";
}

if ($dateEvenement !== '') {
    echo Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_DATE_LABEL') . ' : ' . $dateEvenement . "\n";
}

if ($description !== '') {
    echo Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_DESCRIPTION_LABEL') . ' : ' . $description . "\n";
}

if ($idArticle > 0) {
    echo "\n" . Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_ARTICLE_LABEL') . ' '
        . Uri::root() . ltrim(Route::_('index.php?option=com_content&view=article&id=' . $idArticle, false), '/') . "\n";
}

if ($paymentUrl !== '') {
    echo "\n" . Text::_('COM_GDA_EMAIL_RESERVATION_ACCEPTED_PAYMENT_BODY') . "\n" . $paymentUrl . "\n";
}

echo "\n" . Text::_('COM_GDA_EMAIL_FINALIZE_FOOTER') . "\n";
