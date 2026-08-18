<?php

/**
 * Layout : carte "Brevets" (liste en lecture seule, tableau avec ascenseur pour ne pas allonger
 * la carte quand l'adhérent a beaucoup de brevets). Réutilisée par :
 * - la vue Profil, affichée en carte pleine à côté de profil.card_profil / profil.card_caci
 * - la popup "Liste des brevets" ouverte depuis profil.card_profil (lecture seule, via
 *   ProfilController::showBrevets()), quand card_profil est elle-même affichée en modal
 *
 * @var array $displayData
 * - $displayData['profil']   : objet profil (id_profil)
 * - $displayData['brevets']  : array d'objets brevet (nom, obtention, lieu), voir ProfilModel::getBrevets()
 * - $displayData['closable'] : bool - affiche une croix de fermeture (data-bs-dismiss="modal")
 *                               dans le header, pour l'usage popup (défaut false)
 * - $displayData['editable'] : bool - affiche le bouton "Modifier" qui ouvre #brevetsModal
 *                               (vue Profil uniquement, défaut false)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

$profil = $displayData['profil'] ?? null;

if ($profil === null) {
    return;
}

$brevets = $displayData['brevets'] ?? [];
$closable = $displayData['closable'] ?? false;
$editable = $displayData['editable'] ?? false;

// Brevets sérialisés pour peupler la modale d'édition sans aller-retour serveur supplémentaire :
// la carte est re-rendue après chaque sauvegarde, la donnée reste donc toujours à jour.
// Les dates sont exposées au format ISO attendu par <input type="date">.
$brevetsData = $editable ? array_map(
    static fn($brevet) => [
        'nom' => $brevet->nom ?? '',
        'obtention' => $brevet->obtention ?? '',
        'lieu' => $brevet->lieu ?? '',
    ],
    $brevets
) : [];
?>

<div id="brevets_<?php echo (int) $profil->id_profil; ?>" class="h-100 <?php echo $displayData['taille'] ?? ''; ?>">
  <div class="card text-bg-gda"
    <?php if ($editable) : ?>data-brevets="<?php echo $this->escape(json_encode($brevetsData)); ?>"<?php endif; ?>>
    <div class="card-header">
      <p class="pt-2 float-start"><?php echo $this->escape(Text::_('COM_GDA_PROFIL_CARD_BREVETS')); ?></p>
      <?php if ($editable) : ?>
        <button type="button"
          class="btn btn-success float-end js-edit-brevets"
          data-bs-id_profil="<?php echo (int) $profil->id_profil; ?>"
          data-bs-toggle="modal"
          data-bs-target="#brevetsModal"
          data-toggle="tooltip"
          data-placement="top"
          title="<?php echo $this->escape(Text::_('COM_GDA_BREVETS_EDIT_TOOLTIP')); ?>">
          <i class="fa-solid fa-pen-to-square"></i> <?php echo $this->escape(Text::_('COM_GDA_PROFIL_EDIT')); ?>
        </button>
      <?php elseif ($closable) : ?>
        <button type="button"
          class="btn-close float-end bg-white rounded-circle p-2"
          data-bs-dismiss="modal"
          aria-label="<?php echo $this->escape(Text::_('JCLOSE')); ?>">
        </button>
      <?php endif; ?>
    </div> <!-- class="card-header" -->
    <div class="card-body">
      <?php if (empty($brevets)) : ?>
        <p class="text-recap mb-0"><?php echo Text::_('COM_GDA_ADHESION_RECAP_AUCUN_BREVET'); ?></p>
      <?php else : ?>
        <table class="table table-sm gda-table-brevets mb-0">
          <thead>
            <tr>
              <th scope="col"><?php echo $this->escape(Text::_('COM_GDA_BREVET_NOM')); ?></th>
              <!-- <th scope="col"><?php echo $this->escape(Text::_('COM_GDA_BREVET_OBTENTION')); ?></th>
              <th scope="col"><?php echo $this->escape(Text::_('COM_GDA_BREVET_LIEU')); ?></th> -->
            </tr>
          </thead>
          <tbody>
            <?php foreach ($brevets as $brevet) : ?>
              <tr>
                <td><?php echo $this->escape($brevet->nom ?? ''); ?></td>
                <!-- <td class="text-center"><?php echo $this->escape(!empty($brevet->obtention) ? ToolsHelper::from_sqldate($brevet->obtention) : '-'); ?></td>
                <td class="text-center"><?php echo $this->escape($brevet->lieu ?? ''); ?></td> -->
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div> <!-- class="card-body" -->
  </div> <!-- class="card" -->
</div>
