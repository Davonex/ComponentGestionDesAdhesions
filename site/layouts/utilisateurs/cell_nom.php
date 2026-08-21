<?php

/**
 * Layout : cellule "Nom Prénom [Licence]" partagée par les 3 onglets de la vue Utilisateurs
 * (Profils / Niveau d'accès / Trombinoscope). Porte, en plus du lien d'édition visible, des
 * marqueurs cachés utilisés par les filtres globaux (Adhésion / Groupe, voir utilisateurs.js
 * ::applyFilters()) quand l'onglet appelant n'a pas déjà une colonne visible dédiée pour cette
 * info - même motif que le marqueur "grp:" déjà utilisé sur la colonne Groupes.
 *
 * @var array $displayData
 * - $displayData['utilisateur']    : object ligne utilisateur (au moins id, username)
 * - $displayData['display_name']   : string nom affiché (civilité + nom + prénom)
 * - $displayData['adhesion_label'] : ?string libellé du statut d'adhésion simplifié à cacher ici
 *                                     (null = ne pas l'ajouter, déjà visible ailleurs sur l'onglet)
 * - $displayData['groupe_labels']  : ?string[] libellés des groupes club assignés à cacher ici
 *                                     (null = ne pas les ajouter, déjà visibles ailleurs)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$utilisateur = $displayData['utilisateur'];
$displayName = $displayData['display_name'] ?? '';
$adhesionLabel = $displayData['adhesion_label'] ?? null;
$groupeLabels = $displayData['groupe_labels'] ?? null;
?>
<a href="#"
    class="js-edit-profil-card"
    data-id-profil="<?= (int) $utilisateur->id ?>"
    title="<?= $this->escape(Text::_('COM_GDA_UTILISATEURS_EDIT_PROFIL_TOOLTIP')) ?>">
    <span class="js-utilisateur-name"><?= $this->escape($displayName) ?></span>
</a>
<?php if ($adhesionLabel !== null) : ?>
    <span class="visually-hidden">adh:<?= $this->escape($adhesionLabel) ?></span>
<?php endif; ?>
<?php if ($groupeLabels !== null) : ?>
    <span class="visually-hidden">
        <?php foreach ($groupeLabels as $groupeLabel) : ?>
            grp:<?= $this->escape($groupeLabel) ?>
        <?php endforeach; ?>
    </span>
<?php endif; ?>
