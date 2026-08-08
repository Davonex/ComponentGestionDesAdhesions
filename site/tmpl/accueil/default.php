<?php

/** Template de la page d'accueil de l'espace Adherents */


\defined('_JEXEC');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\CampagnesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;

use Joomla\CMS\HTML\Helpers\Bootstrap;
use NCB\Component\Gda\Site\Helper\ConfHelper;

Bootstrap::framework();

// JHtml::_('bootstrap.framework');  //use Joomla\CMS\HTML\Helpers\Bootstrap


/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();
  // $wa->useScript('core');
  // $wa->useScript('keepalive');
  // $wa->useScript('field.modal-fields');
  // $wa->useScript('joomla.dialog');


$wa->useStyle('com_gdadhesions.gda');
$wa->useScript('com_gdadhesions.form_modal');
$wa->useScript('com_gdadhesions.spinner');

$wa->useScript('com_gdadhesions.campagne');
// tom-select
$wa->useStyle('com_gdadhesions.tom-select');
$wa->useScript('com_gdadhesions.tom-select');


?>
<!-- bloc de bienvenue collapsable -->
<div class="card">

    <div class="card-header d-flex align-items-center">

        <button class="btn btn-sm p-0 me-2 toggle-card"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#welcomeCard"
        aria-expanded="false">
    <i class="fa-solid fa-chevron-right"></i>
</button>    

        <h5 class="mb-0">
          <?php echo $this->user->name . " [" . $this->user->username . "]"; ?>
        </h5>

    </div>

    <div class="collapse" id="welcomeCard">
        <div class="card-body">
            <p class="mb-0">
                Bienvenue dans votre espace adhérent
            </p>
        </div>
    </div>

</div>


<div class="row g-2 dashboard">
<!-- container dashboard py-4 -->


  <!-- ALERTES - Suivi Adhésion et CACI -->
  <?php
  // Récupérer les données de souscription
  
  $model = $this->getModel();
  
  // Déterminer la saison courante (suivi CACI/licence, indépendant de l'ouverture des inscriptions)
  $saisonCourante = ConfHelper::getSaisonService()->getSaisonCourante();

  $souscription = null;
  $statusEnum = \NCB\Component\Gda\Site\Helper\AdhesionStatusHelper::STATUS_NOT_SUBSCRIBED;

  if ($saisonCourante !== null && $this->user !== null && $this->user->id > 0) {
    $souscription = $model->getAdhesionStatus($this->user->id, $saisonCourante->id_campagne);
    $statusEnum = \NCB\Component\Gda\Site\Helper\AdhesionStatusHelper::getStatusEnum($souscription);
  }

  // Afficher le layout avec les données
  echo LayoutHelper::render('accueil.dash_status_adhesion', [
    'souscription' => $souscription,
    'statusEnum'   => $statusEnum,
    'user'         => $this->user,
    'itemid'       => $this->itemid ?? 0
  ]);
  ?>

  <!-- Messages -->

  <!-- <div class="card col-12 col-md-4 col-lg-6">

    <div class="card-header">💬 Messages du club</div>

    <p class="ncb_texte">
      La seance de vendredi prochain est annulée. La piscine est fermée pour cause de travaux.
    </p>

    <p class="ncb_texte">
      Rappel que sur boussys st antoine les vestiares doivent rester propres.
    </p>

  </div> -->

  <!-- Campagnes -->

  <?php echo LayoutHelper::render(
            'accueil.dash_campagnes',   ['campagne' => $this->campagnes, 'user' => $this->user]
        );
    ?>

  <!-- PLANNING -->

  <!-- <div class="card  col-12 col-md-8">

    <h5>📅 Planning (15 prochains jours)</h5>

    <table class="table">

      <thead>
        <tr>
          <th>Date</th>
          <th>Activité</th>
          <th>Groupe</th>
        </tr>
      </thead>

      <tbody>

        <tr>
          <td>15 mai</td>
          <td>Entrainement</td>
          <td>Groupe A</td>
        </tr>

        <tr>
          <td>18 mai</td>
          <td>Match</td>
          <td>Equipe 1</td>
        </tr>

        <tr>
          <td>22 mai</td>
          <td>Stage technique</td>
          <td>Tous</td>
        </tr>

      </tbody>

    </table>

  </div> -->
</div> <!-- class="dashboard" -->










<?php    
//     foreach ($this->campagnes as $cle => $campagne) {
//     if ($cle%2 == 0) // 1er carte de la ligne
//     {
//       echo '<div class="row">';
//       echo LayoutHelper::render(
//             'accueil.card',   ['campagne' => $campagne, 'user' => $this->user]
//         );
//     } else { // 2eme carte de la ligne
//       echo LayoutHelper::render(
//             'accueil.card',   ['campagne' => $campagne, 'user' => $this->user]
//         );
//       echo '</div>'; // class="row g-4"
//     }
//   }
echo LayoutHelper::render(
            'accueil.souscription_modale',   
            [
              'form' => $this->form,
              'task' => 'souscrit',
              ]
        );

 // Code de la mb_encoding_aliases


  ?>





<!-- </div>  class="row" -->




<script type="module">
// document.addEventListener('DOMContentLoaded', function() {
//   LstModal("modalSign", "souscription")
  // queryAjax ("submitSignIn","form_souscrit",campagneCB,"closeSignIn");

  // LstModal("modalSignOut","campagne")
  // queryAjax ("submitSignOut","form_desouscrit",campagneCB,"closeSignOut");
// });
</script>