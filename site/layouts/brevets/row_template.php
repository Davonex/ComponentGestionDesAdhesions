<?php

/**
 * Layout : <template> de la ligne de saisie d'un brevet, cloné par addBrevet() (brevets.js).
 *
 * Partagé par la vue Adhésion (formulaire d'inscription) et la modale d'édition des brevets de la
 * vue Profil, pour que le markup de ligne ne soit défini qu'à un seul endroit : les deux vues
 * s'appuient sur les mêmes noms de champs brevets[i][nom|obtention|lieu], attendus côté serveur
 * par BrevetService::replaceBrevets().
 *
 * Rangé sous layouts/brevets/ (et non layouts/profil/ ou layouts/adhesion/) car il ne relève
 * d'aucune des deux vues en particulier : il porte le domaine "brevet".
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<template id="brevet-template">
  <div class="row g-2 brevet-item mb-2 align-items-end">
    <div class="col-md-5">
      <input type="text" name="brevets[][nom]" class="form-control" required
        placeholder="<?php echo $this->escape(Text::_('COM_GDA_BREVET_NOM')); ?>">
    </div>
    <div class="col-md-3">
      <input type="date" name="brevets[][obtention]" class="form-control"
        placeholder="<?php echo $this->escape(Text::_('COM_GDA_BREVET_OBTENTION')); ?>">
    </div>
    <div class="col-md-3">
      <input type="text" name="brevets[][lieu]" class="form-control"
        placeholder="<?php echo $this->escape(Text::_('COM_GDA_BREVET_LIEU')); ?>">
    </div>
    <div class="col-md-1 text-end">
      <button type="button" class="btn btn-outline-danger remove-brevet-btn"
        aria-label="<?php echo $this->escape(Text::_('COM_GDA_BREVETS_REMOVE')); ?>">
        <i class="fa-solid fa-trash-can"></i>
      </button>
    </div>
  </div>
</template>
