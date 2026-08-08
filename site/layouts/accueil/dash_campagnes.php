<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;


use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;

/**
 * @var array $displayData
 * - $displayData['campagnes'] : campagnes
 * - $displayData['user']  : user
 */


$items = $displayData['campagne'];
$user = $displayData['user'];

$id_type_saison = ConfHelper::getValue('IdTypeSaison');

?>
<div class="col-12 col-lg-6">
  <div class="card bg-gda-white">

    <div class="card-header">📢 Campagnes ouvertes</div>

    <div class="card-body">

      <?php

      foreach ($items as $cle => $item) {
        if ($item->id_type !== (int) $id_type_saison) {
          $Inscrit = ($item->deja_inscrit) ? true : false;
          $placeDispo = $item->nbr_place - $item->nbr_souscriptions;
          $data_call = [
            'jform_souscription[id_campagne]' => $item->id_campagne,
            'jform_souscription[id_profil]' => $user->id,
            'jform_souscription[username]' => $user->username,
            'jform_souscription[name]' => $user->name,
          ];
      ?>
          <div id="souscription_<?= $item->id_campagne ?>" class="d-flex align-items-center justify-content-between gap-2 py-1">
            <div> <!-- flex item 1 -->
              <span class="fw-bold" data-bs name="titre"><?php echo $item->titre; ?></span>
              <span class="text-muted small ms-2">Places : <?php echo $item->nbr_place - $item->nbr_souscriptions; ?> /
                <?php echo $item->nbr_place; ?></span>
              <span class="text-muted small ms-2">Fin : <?php echo HTMLHelper::_('date', $item->date_fin, 'd M y'); ?></span>
            </div>
            <div class="text-end"> <!-- flex item 2 -->
              <?php if ($Inscrit) : ?>
                <a class="btn btn-warning btn-sm" type="button" name="souscrit"
                  onclick='simpleCallAjax(<?= json_encode(array_merge($data_call, ['task' => 'campagnes.desouscrit'])); ?>,campagneSouscritCB)'
                  title="<?= Text::_('COM_GDA_CAMPAGNE_DESOUSCRIT_TOOLTIP') ?>">
                  <!-- <span class="fa-solid fa-pencil"></span>         -->
                  <i class="fa-solid fa-user-xmark"></i> <?= Text::_('COM_GDA_CAMPAGNE_DESOUSCRIT') ?>
                </a>
              <?php else : ?>
                <a class="btn btn-success btn-sm" type="button" name="souscrit"
                  onclick='simpleCallAjax(<?= json_encode(array_merge($data_call, ['task' => 'campagnes.souscrit'])); ?>,campagneSouscritCB)'
                  title="<?= Text::_('COM_GDA_CAMPAGNE_SOUSCRIT_TOOLTIP') ?>">
                  <!-- <span class="fa-solid fa-pencil"></span>         -->
                  <i class="fa-solid fa-user-plus"></i> <?= Text::_('COM_GDA_CAMPAGNE_SOUSCRIT') ?>
                </a>
              <?php endif; ?>
            </div>
            <span class="hidden" data-bs name="description"><?php echo $item->description; ?></span>
            <span class="hidden" data-bs name="id_profile"><?php echo $user->id; ?></span>
          </div> <!-- card campagne -->
    </div> <!-- row -->
<?php
        }
      }


?>

  </div> <!-- card-body -->

  <div class="card-footer">
    <span class="hidden" data-bs name="id_campagne"><?= $item->id_campagne; ?></span>
  </div>



</div>