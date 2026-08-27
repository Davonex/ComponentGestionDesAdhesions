<?php

/**
 * Layout : <template> de la ligne rôle+quantité du popup de réservation, clonée par le module JS
 * RowList (row_list.js) via reservation.js. Formation et Loisir passent toutes deux par ce même
 * gabarit : la possibilité d'ajouter une 2e ligne ou de choisir une quantité > 1 ne dépend que de
 * campagne.reservation_multiple (décidé ici, côté serveur, une fois pour toute la popup).
 *
 * @var array $displayData
 * - $displayData['rolesDisponibles']         : rôles réellement configurés pour cette campagne
 *   (#__gda_campagne_roles), pas le gabarit par défaut de la nature
 * - $displayData['placesDisponiblesParRole']  : role => places restantes|null
 * - $displayData['reservationMultiple']       : bool, campagne->reservation_multiple
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$rolesDisponibles         = $displayData['rolesDisponibles'] ?? [];
$placesDisponiblesParRole = $displayData['placesDisponiblesParRole'] ?? [];
$reservationMultiple      = !empty($displayData['reservationMultiple']);
?>

<template id="reservation-role-template">
  <div class="row g-2 reservation-role-item mb-2 align-items-end">
    <div class="col">
      <select data-field="role" class="form-select form-select-sm">
        <?php foreach ($rolesDisponibles as $role) :
            $dispoRole = $placesDisponiblesParRole[$role] ?? null;

            if ($dispoRole === null) {
                $suffixe = '';
            } elseif ($dispoRole > 0) {
                $suffixe = ' (' . Text::sprintf('COM_GDA_RESERVATION_ROLE_PLACES_RESTANTES', $dispoRole) . ')';
            } else {
                $suffixe = ' (' . Text::_('COM_GDA_RESERVATION_ROLE_COMPLET') . ')';
            }
        ?>
          <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($role . $suffixe, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($reservationMultiple) : ?>
      <div class="col-3">
        <input type="number" data-field="quantite" class="form-control form-control-sm" min="1" step="1" value="1">
      </div>
      <div class="col-2 text-end">
        <button type="button" class="btn btn-outline-danger btn-sm js-row-list-remove"
          aria-label="<?= $this->escape(Text::_('COM_GDA_RESERVATION_ROLE_REMOVE')); ?>">
          <i class="fa-solid fa-trash-can"></i>
        </button>
      </div>
    <?php else : ?>
      <!-- Formation, ou campagne Loisir configurée à 1 place : toujours exactement 1 place, pas
           de saisie de quantité ni de suppression de ligne (seul "Me désinscrire" retire la place). -->
      <input type="hidden" data-field="quantite" value="1">
    <?php endif; ?>
  </div>
</template>
