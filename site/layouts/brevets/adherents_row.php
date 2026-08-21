<?php

/**
 * Layout : une ligne de l'onglet « Brevets des adhérents » de la vue Brevets.
 *
 * La ligne ne porte AUCUN <select> de rattachement : le référentiel compte ~80 entrées et la vue
 * affiche plus d'un millier de brevets, ce qui produirait des dizaines de milliers d'<option>.
 * Un éditeur unique, rendu une seule fois par adherents_table.php, est déplacé dans la cellule
 * au double-clic (voir brevets_mgt.js).
 *
 * @var array $displayData
 * - $displayData['brevet'] : object {id, nom, id_mapping, id_profil, civilite, nom_profil,
 *                            prenom, photo, label_ffessm, activite, role, code}
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\FileHelper;

/** @var object $brevet */
$brevet = $displayData['brevet'];

$displayName = trim((string) (($brevet->nom_profil ?? '') . ' ' . ($brevet->prenom ?? '')));

// Cache-busting désactivé (dernier argument à false) : la vignette est la photo de profil déjà
// servie ailleurs, la laisser en cache navigateur évite de retélécharger un millier d'images.
$pathPhoto = FileHelper::getImageSrc($brevet->photo ?? null, 'ProfilPhotoPath', 'DefaultProfilPhoto', false);

$estRattache = $brevet->id_mapping !== null;

// Marqueurs cachés consommés par les filtres [Tous | Rattachés | Non rattachés] et Activité :
// simple-datatables filtre sur le texte des cellules, il lui faut une valeur stable à chercher.
$marqueurs = $estRattache
    ? 'map:oui act:' . $brevet->activite
    : 'map:non';
?>
<tr class="js-brevet-row" data-id-brevet="<?= (int) $brevet->id ?>">
    <td>
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($pathPhoto)) : ?>
                <img src="<?= $this->escape($pathPhoto) ?>"
                    alt=""
                    width="32" height="32"
                    loading="lazy"
                    class="gda-preview-thumb gda-preview-thumb--32">
            <?php endif; ?>
            <span><?= $this->escape($displayName) ?></span>
            <span class="visually-hidden js-brevet-marqueurs"><?= $this->escape($marqueurs) ?></span>
        </div>
    </td>
    <td class="js-editable-nom-brevet" title="<?= $this->escape(Text::_('COM_GDA_BREVETS_EDIT_HINT')) ?>">
        <span class="nom-brevet-display"><?= $this->escape($brevet->nom ?? '') ?></span>
        <input type="text" class="form-control form-control-sm nom-brevet-input d-none"
            maxlength="100"
            value="<?= $this->escape($brevet->nom ?? '') ?>"
            data-current-nom="<?= $this->escape($brevet->nom ?? '') ?>">
    </td>
    <td class="js-editable-mapping"
        data-id-mapping="<?= $estRattache ? (int) $brevet->id_mapping : '' ?>"
        title="<?= $this->escape(Text::_('COM_GDA_BREVETS_MAPPING_EDIT_HINT')) ?>">
        <span class="mapping-display">
            <?php if ($estRattache) : ?>
                <?= $this->escape($brevet->label_ffessm) ?>
                <span class="text-muted small">(<?= $this->escape($brevet->activite) ?>)</span>
            <?php else : ?>
                <span class="badge bg-warning text-dark"><?= Text::_('COM_GDA_BREVETS_NON_RATTACHE') ?></span>
            <?php endif; ?>
        </span>
    </td>
</tr>
