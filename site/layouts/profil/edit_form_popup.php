<?php

/**
 * Layout : enveloppe (header/body/footer) du formulaire d'édition du profil pour le popup ajax
 * de la vue Utilisateurs (ProfilController::showEditForm(), réservé au Bureau). Injecté dans
 * #profilCardModalContent à la place de la fiche en lecture seule (profil.card_profil).
 *
 * @var array $displayData
 * - $displayData['form']      : Joomla\CMS\Form\Form - formulaire jform_Profil déjà prérempli
 * - $displayData['photoFlag'] : bool - une photo existe déjà pour ce profil
 * - $displayData['photoSrc']  : string - URL de la photo (ou de la photo par défaut) déjà résolue
 * - $displayData['itemid']    : int - Itemid utilisé pour construire l'URL d'action du formulaire
 * - $displayData['title']     : string - civilité/nom/prénom affiché dans l'en-tête de la modal
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$title = $displayData['title'] ?? '';
?>
<div class="modal-header bg-gda-header text-header">
  <h5 class="modal-title mb-0">
    <i class="fa-solid fa-user-pen me-2"></i>
    <?php echo $this->escape($title); ?>
  </h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?php echo $this->escape(Text::_('JCLOSE')); ?>"></button>
</div>
<div class="modal-body" id="profilEditFormBody">
  <?php echo LayoutHelper::render('profil.edit_form', [
    'form' => $displayData['form'],
    'photoFlag' => $displayData['photoFlag'] ?? false,
    'photoSrc' => $displayData['photoSrc'] ?? '',
    'itemid' => $displayData['itemid'] ?? 0,
  ]); ?>
</div>
<div class="modal-footer">
  <button type="button" class="btn btn-secondary float-end js-save-profil-popup">
    <i class="fa-solid fa-floppy-disk"></i> <?php echo Text::_('COM_GDA_SAVE'); ?>
  </button>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('COM_GDA_CANCEL'); ?></button>
</div>
