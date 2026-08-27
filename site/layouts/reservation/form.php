<?php

/**
 * Layout : corps du popup de réservation / modification (injecté en ajax dans #reservationModal).
 *
 * @var array $displayData
 * - $displayData['campagne']          : la campagne (titre, description, reservation_multiple, ...)
 * - $displayData['reservation']       : réservation existante ou null (voir ReservationService::getReservation())
 * - $displayData['rolesDisponibles']  : rôles réellement configurés pour cette campagne (#__gda_campagne_roles)
 * - $displayData['placesDisponibles'] : places restantes (tous rôles confondus), ou null si illimité
 * - $displayData['placesDisponiblesParRole'] : role => places restantes|null (chaque rôle a sa
 *   propre capacité, voir ReservationService::getPlacesDisponiblesParRole())
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Service\ReservationService;

$campagne                 = $displayData['campagne'];
$reservation              = $displayData['reservation'] ?? null;
$rolesDisponibles         = $displayData['rolesDisponibles'] ?? [];
$placesDisponibles        = $displayData['placesDisponibles'] ?? null;
$placesDisponiblesParRole = $displayData['placesDisponiblesParRole'] ?? [];

// Mes places actives (une réservation annulée est traitée comme une nouvelle réservation :
// l'adhérent revient, ses anciennes places restées 'annulee' ne comptent pas).
$mesPlaces   = ($reservation !== null && !$reservation->annulee) ? $reservation->places : [];
$dejaReserve = !empty($mesPlaces);
$complet     = $placesDisponibles !== null && $placesDisponibles === 0;

$enAttente = false;
foreach ($mesPlaces as $place) {
    if ($place->statut === ReservationService::STATUT_ATTENTE) {
        $enAttente = true;
        break;
    }
}

$monCommentaire = $dejaReserve ? (string) $reservation->commentaire : '';

// Quantité déjà accordée/demandée par rôle, pour préremplir les lignes existantes (une réservation
// Loisir peut porter plusieurs rôles à la fois) — lu en JS par reservation.js à l'ouverture.
$quantiteParRole = [];
foreach ($mesPlaces as $place) {
    $quantiteParRole[$place->role] = ($quantiteParRole[$place->role] ?? 0) + 1;
}
?>

<div class="modal-header bg-gda-header text-header">
    <h5 class="modal-title mb-0">
        <i class="fa-solid fa-graduation-cap me-2" aria-hidden="true"></i>
        <?= htmlspecialchars((string) $campagne->titre, ENT_QUOTES, 'UTF-8') ?>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
        aria-label="<?= htmlspecialchars(Text::_('JCLOSE'), ENT_QUOTES, 'UTF-8') ?>"></button>
</div>

<div class="modal-body p-4">

    <?php if ($campagne->description) : ?>
        <p class="text-muted"><?= (string) $campagne->description ?></p>
    <?php endif; ?>

    <?php if ($complet && !$dejaReserve) : ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="fa-solid fa-hourglass-half me-2" aria-hidden="true"></i>
            <span><?= Text::_('COM_GDA_RESERVATION_ALERTE_COMPLET') ?></span>
        </div>
    <?php endif; ?>

    <?php if ($dejaReserve && $enAttente) : ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="fa-solid fa-hourglass-half me-2" aria-hidden="true"></i>
            <span><?= Text::_('COM_GDA_RESERVATION_ALERTE_EN_ATTENTE') ?></span>
        </div>
    <?php endif; ?>

    <form id="formReservation">
        <input type="hidden" name="id_campagne" value="<?= (int) $campagne->id_campagne ?>">

        <div class="mb-3">
            <label class="form-label"><?= Text::_('COM_GDA_RESERVATION_ROLE') ?></label>
            <div id="reservationRoleRows"></div>
            <?php if (!empty($campagne->reservation_multiple)) : ?>
                <button type="button" class="btn btn-sm btn-outline-success mt-1" id="reservationRoleAdd">
                    <span class="fa-solid fa-plus"></span> <?= Text::_('COM_GDA_RESERVATION_ROLE_ADD') ?>
                </button>
            <?php endif; ?>
        </div>

        <div class="mb-0">
            <label class="form-label" for="reservation_commentaire"><?= Text::_('COM_GDA_RESERVATION_COMMENTAIRE') ?></label>
            <textarea class="form-control" id="reservation_commentaire" name="commentaire" rows="3"
                placeholder="<?= htmlspecialchars(Text::_('COM_GDA_RESERVATION_COMMENTAIRE_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($monCommentaire, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </form>

    <!-- Lu par reservation.js à l'ouverture pour préremplir les lignes rôle+quantité ci-dessus. -->
    <div id="reservationExistantes" class="d-none"><?= htmlspecialchars(json_encode($quantiteParRole), ENT_QUOTES, 'UTF-8') ?></div>
</div>

<div class="modal-footer">
    <?php if ($dejaReserve) : ?>
        <button type="button" class="btn btn-outline-danger me-auto js-annuler-reservation"
            data-id-campagne="<?= (int) $campagne->id_campagne ?>">
            <i class="fa-solid fa-user-xmark me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_RESERVATION_DESISTER') ?>
        </button>
    <?php endif; ?>

    <button type="button" class="btn <?= $dejaReserve ? 'btn-info' : 'btn-primary' ?> js-valider-reservation"
        data-id-campagne="<?= (int) $campagne->id_campagne ?>">
        <i class="fa-solid fa-check me-1" aria-hidden="true"></i>
        <?= $dejaReserve ? Text::_('COM_GDA_RESERVATION_METTRE_A_JOUR') : Text::_('COM_GDA_RESERVATION_RESERVER') ?>
    </button>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= Text::_('COM_GDA_CANCEL') ?></button>
</div>

<?= LayoutHelper::render('reservation.role_row_template', [
    'rolesDisponibles'         => $rolesDisponibles,
    'placesDisponiblesParRole' => $placesDisponiblesParRole,
    'reservationMultiple'      => !empty($campagne->reservation_multiple),
]); ?>
