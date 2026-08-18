<?php

/**
 * Layout : corps du popup de réservation / modification (injecté en ajax dans #reservationModal).
 *
 * @var array $displayData
 * - $displayData['campagne']          : la campagne (titre, description, nbr_place, role_actif, ...)
 * - $displayData['reservation']       : réservation existante ou null
 * - $displayData['rolesDisponibles']  : rôles proposés par la nature (vide si role_actif = 0)
 * - $displayData['placesDisponibles'] : places restantes, ou null si campagne illimitée
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Service\ReservationService;

$campagne          = $displayData['campagne'];
$reservation       = $displayData['reservation'] ?? null;
$rolesDisponibles  = $displayData['rolesDisponibles'] ?? [];
$placesDisponibles = $displayData['placesDisponibles'] ?? null;

// Une réservation annulée est traitée comme une nouvelle réservation (l'adhérent revient).
$dejaReserve = $reservation !== null && $reservation->statut !== ReservationService::STATUT_ANNULEE;
$complet     = $placesDisponibles !== null && $placesDisponibles === 0;

$mesPlaces      = $dejaReserve ? (int) $reservation->nbr_places : 1;
$monCommentaire = $dejaReserve ? (string) $reservation->commentaire : '';
$mesRoles       = $dejaReserve ? ($reservation->roles ?? []) : [];
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

    <?php if ($dejaReserve && $reservation->statut === ReservationService::STATUT_ATTENTE) : ?>
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="fa-solid fa-hourglass-half me-2" aria-hidden="true"></i>
            <span><?= Text::_('COM_GDA_RESERVATION_ALERTE_EN_ATTENTE') ?></span>
        </div>
    <?php endif; ?>

    <form id="formReservation">
        <input type="hidden" name="id_campagne" value="<?= (int) $campagne->id_campagne ?>">

        <?php if (!empty($campagne->reservation_multiple)) : ?>
            <div class="mb-3">
                <label class="form-label" for="reservation_nbr_places"><?= Text::_('COM_GDA_RESERVATION_NBR_PLACES') ?></label>
                <input type="number" class="form-control" id="reservation_nbr_places" name="nbr_places"
                    min="1" step="1" value="<?= $mesPlaces ?>" style="max-width: 8rem;">
            </div>
        <?php endif; ?>

        <?php if (!empty($campagne->role_actif) && !empty($rolesDisponibles)) : ?>
            <div class="mb-3">
                <label class="form-label" for="reservation_role_0"><?= Text::_('COM_GDA_RESERVATION_ROLE') ?></label>
                <?php
                // Une formation est toujours à 1 place (reservation_multiple forcé à Non) : un seul
                // sélecteur suffit. Les natures à places multiples en afficheront un par place.
                $roleChoisi = $mesRoles[0] ?? '';
                ?>
                <select class="form-select" id="reservation_role_0" name="roles[]" style="max-width: 16rem;">
                    <?php foreach ($rolesDisponibles as $role) : ?>
                        <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $roleChoisi === $role ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="mb-0">
            <label class="form-label" for="reservation_commentaire"><?= Text::_('COM_GDA_RESERVATION_COMMENTAIRE') ?></label>
            <textarea class="form-control" id="reservation_commentaire" name="commentaire" rows="3"
                placeholder="<?= htmlspecialchars(Text::_('COM_GDA_RESERVATION_COMMENTAIRE_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($monCommentaire, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </form>
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
