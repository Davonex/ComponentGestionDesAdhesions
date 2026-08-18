<?php

/**
 * Layout : encart "Campagnes de formation" du dashboard adhérent.
 *
 * Remplace, pour la nature Formation, l'ancien layout générique accueil/dash_campagnes.php
 * (qui lisait #__gda_souscriptions, désormais réservée à la saison). Les trois autres natures
 * — Sortie, Soirée, Boutique — auront chacune leur propre layout.
 *
 * @var array $displayData
 * - $displayData['formations'] : AccueilModel::getFormations(), triées par date de fin croissante
 * - $displayData['user']       : utilisateur connecté
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$formations = $displayData['formations'] ?? [];
$user       = $displayData['user'];

// Encart entièrement masqué s'il n'y a aucune formation ouverte : un bloc vide n'apporte rien
// au dashboard.
if (empty($formations)) {
    return;
}
?>

<div class="col-12 col-lg-6">
    <div class="card bg-gda-white h-100">

        <div class="card-header">
            <i class="fa-solid fa-graduation-cap me-2" aria-hidden="true"></i><?= Text::_('COM_GDA_RESERVATION_FORMATIONS_TITRE') ?>
        </div>

        <div class="card-body" id="gda-formations-liste">
            <?php foreach ($formations as $formation) : ?>
                <?= LayoutHelper::render('accueil.dash_formation_ligne', [
                    'formation' => $formation,
                    'user'      => $user,
                ]) ?>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Popup de réservation / modification (contenu injecté par reservation.js) -->
<div class="modal fade" id="reservationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" id="reservationModalContent">
            <!-- rempli en ajax -->
        </div>
    </div>
</div>

<!-- Popup d'affichage de l'article lié à une campagne -->
<div class="modal fade" id="articleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content" id="articleModalContent">
            <!-- rempli en ajax -->
        </div>
    </div>
</div>
