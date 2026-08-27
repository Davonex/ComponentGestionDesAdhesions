<?php

/**
 * Layout : <template> de la ligne de saisie d'un rôle+capacité, clonée par le module JS
 * RowList (row_list.js) via campagne.js. Formation et Loisir demandent toutes deux
 * systématiquement un rôle par place (voir ReservationService) : ce gabarit permet au Bureau
 * d'ajouter/renommer/supprimer librement les rôles d'une campagne, plutôt que de choisir parmi
 * une liste fixe (voir CampagnesModel::saveRolePlaces()).
 *
 * Les attributs data-field servent de point d'ancrage pour RowList, qui renomme chaque champ en
 * role_places[i][role] / role_places[i][nbr_place] à l'ajout (index = position dans la liste).
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<template id="campagne-role-template">
  <div class="row g-2 campagne-role-item mb-2 align-items-end">
    <div class="col-6">
      <input type="text" data-field="role" class="form-control form-control-sm" required
        placeholder="<?= $this->escape(Text::_('COM_GDA_CAMPAGNE_ROLE_PLACES_NOM')); ?>">
    </div>
    <div class="col-4">
      <input type="number" data-field="nbr_place" class="form-control form-control-sm" min="0" step="1" value="0"
        placeholder="<?= $this->escape(Text::_('COM_GDA_CAMPAGNE_ROLE_PLACES_CAPACITE')); ?>">
    </div>
    <div class="col-2 text-end">
      <button type="button" class="btn btn-outline-danger btn-sm js-row-list-remove"
        aria-label="<?= $this->escape(Text::_('COM_GDA_CAMPAGNE_ROLE_REMOVE')); ?>">
        <i class="fa-solid fa-trash-can"></i>
      </button>
    </div>
  </div>
</template>
