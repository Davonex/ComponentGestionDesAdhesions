<?php

/**
 * Version texte du mail au responsable - voir reservation_activity_html.php.
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

$resume = static function (array $comptes, string $vide) use ($cles): string {
    if ($comptes === []) {
        return $vide;
    }

    $morceaux = [];
    foreach ($comptes as $statut => $nombre) {
        $morceaux[] = Text::_($cles[$statut] ?? 'COM_GDA_CAMPAGNES_SUIVI_STATUT_ATTENTE') . ((int) $nombre > 1 ? ' x' . (int) $nombre : '');
    }

    return implode(', ', $morceaux);
};

echo Text::sprintf('COM_GDA_EMAIL_FINALIZE_INTRO', $responsable) . "\n\n";
echo Text::sprintf('COM_GDA_EMAIL_RESERVATION_ACTIVITY_BODY_' . $evenement, $adherent, $campagneTitre) . "\n\n";
echo Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_ADHERENT_LABEL') . ' : ' . $adherent . ' (' . $username . ')' . "\n";

if ($email !== '') {
    echo Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_EMAIL_LABEL') . ' : ' . $email . "\n";
}

if ($telephone !== '') {
    echo Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_PHONE_LABEL') . ' : ' . $telephone . "\n";
}

echo Text::_('COM_GDA_EMAIL_FINALIZE_CAMPAIGN_LABEL') . ' : ' . $campagneTitre . "\n";

foreach ($lignes as $ligne) {
    echo (string) $ligne['role'] . ' : '
        . $resume((array) $ligne['avant'], Text::_('COM_GDA_RESERVATION_STATUT_NON_INSCRIT'))
        . ' -> '
        . $resume((array) $ligne['apres'], Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_DESINSCRIT')) . "\n";
}

if ($commentaire !== '') {
    echo Text::_('COM_GDA_RESERVATION_COMMENTAIRE')
        . (!empty($displayData->commentaire_modifie) ? ' (' . Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_COMMENT_NEW') . ')' : '')
        . ' : ' . $commentaire . "\n";
}

echo "\n" . Text::_('COM_GDA_EMAIL_RESERVATION_ACTIVITY_CTA') . ' : ' . $suiviUrl . "\n\n";
echo Text::_('COM_GDA_EMAIL_FINALIZE_FOOTER') . "\n";
