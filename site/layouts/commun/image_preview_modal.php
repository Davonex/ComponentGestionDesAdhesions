<?php

/**
 * Layout : modal de prévisualisation d'image (photo, CACI), partagée par les vues Groupes,
 * Secrétariat, Campagnes (onglet Suivi) et Utilisateurs.
 *
 * Remplie par le JS de chaque vue au clic sur un déclencheur .js-image-preview-thumb / .js-caci-thumb
 * (data-image-src, data-image-alt) : les id #imagePreviewModal et #imagePreviewImage sont référencés
 * par groupes.js (aussi chargé par la vue Campagnes), secretariat.js et utilisateurs.js : ne pas
 * les renommer.
 * Style de la croix : .btn-close.gda-modal-close-overlay (gda.css).
 *
 * @var array $displayData Aucune donnée attendue.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <?php // Croix en surimpression, hors modal-body pour ne pas défiler avec une image haute. ?>
            <button type="button" class="btn-close gda-modal-close-overlay" data-bs-dismiss="modal" aria-label="<?= $this->escape(Text::_('JCLOSE')) ?>"></button>
            <div class="modal-body text-center p-2">
                <img id="imagePreviewImage" src="" alt="" class="img-fluid">
            </div>
        </div>
    </div>
</div>
