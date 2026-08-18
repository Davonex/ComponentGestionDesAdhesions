<?php

/**
 * Layout : contenu de la modale d'édition des brevets (vue Profil).
 *
 * Les lignes de saisie ne sont pas rendues ici : elles sont clonées côté client depuis
 * layouts/brevets/row_template.php par addBrevet() (brevets.js), à l'ouverture de la modale, à
 * partir des brevets portés par la carte (profil_brevets.js). Cela permet de réutiliser la même
 * modale pour le profil principal et les profils "on behalf" affichés sur la même page.
 *
 * @var array $displayData  (aucune donnée : la modale est peuplée à l'ouverture)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
?>

<form id="brevetsAdminForm" name="brevetsAdminForm">

  <!-- Import FFESSM : le scan du QR code de la carte licence remplace la saisie manuelle -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="form-text mb-0"><?php echo $this->escape(Text::_('COM_GDA_BREVETS_SCAN_DESC')); ?></span>
    <button id="openBrevetsQrScanner" class="btn btn-outline-secondary flex-shrink-0 ms-2" type="button">
      <i class="fa fa-qrcode"></i> <?php echo $this->escape(Text::_('COM_GDA_SCAN_QRCODE')); ?>
    </button>
  </div>

  <!-- Fenêtre plein écran du scanner (mêmes classes que la vue Adhésion : media/com_gdadhesions/css/html5-qrcode.css) -->
  <div id="brevetsQrModal" class="qr-modal">
    <button type="button" id="closeBrevetsQrScanner" class="btn btn-danger qr-close"><?php echo $this->escape(Text::_('JCLOSE')); ?></button>
    <div id="brevetsQrReader" style="width:100%;max-width:600px;margin:auto;"></div>
  </div>

  <hr>

  <!-- Lignes de brevets, injectées par brevets.js -->
  <div id="brevets-container"></div>

  <button type="button" class="btn btn-sm btn-primary mt-2" id="add-brevet-btn">
    <i class="fa-solid fa-circle-plus"></i> <?php echo $this->escape(Text::_('COM_GDA_BREVETS_ADD')); ?>
  </button>

  <input type="hidden" name="id_profil" id="brevetsIdProfil" value="0" />
  <input type="hidden" name="task" value="profil.saveBrevets" />
  <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
</form>

<?php echo LayoutHelper::render('brevets.row_template'); ?>
