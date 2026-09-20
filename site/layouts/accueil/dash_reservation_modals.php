<?php

/**
 * Layout : popups partagés par les encarts de réservation du dashboard (Formation, Loisir).
 * À rendre une seule fois par page ; leur contenu est injecté en ajax par reservation.js.
 */

defined('_JEXEC') or die;
?>

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
