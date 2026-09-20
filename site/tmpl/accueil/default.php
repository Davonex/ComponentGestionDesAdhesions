<?php

/** Template de la page d'accueil de l'espace Adherents */


\defined('_JEXEC');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;

use Joomla\CMS\HTML\Helpers\Bootstrap;

Bootstrap::framework();

// JHtml::_('bootstrap.framework');  //use Joomla\CMS\HTML\Helpers\Bootstrap


/** @var Joomla\CMS\Application\SiteApplication $app */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();

$wa->useStyle('com_gdadhesions.gda');
$wa->useScript('com_gdadhesions.form_modal');
$wa->useScript('com_gdadhesions.spinner');

$wa->useScript('com_gdadhesions.row_list');
$wa->useScript('com_gdadhesions.campagne');
$wa->useScript('com_gdadhesions.dialog');
$wa->useScript('com_gdadhesions.reservation');
$wa->useScript('com_gdadhesions.boutique');
// tom-select
$wa->useStyle('com_gdadhesions.tom-select');
$wa->useScript('com_gdadhesions.tom-select');

Text::script('COM_GDA_RESERVATION_DESISTER_CONFIRM_TITRE');
Text::script('COM_GDA_RESERVATION_DESISTER_CONFIRM_MESSAGE');
Text::script('COM_GDA_CANCEL');
Text::script('COM_GDA_CONFIRM');


?>
<!-- bloc de bienvenue collapsable -->
<div class="card">

  <div class="card-header d-flex align-items-center">

    <button class="btn btn-sm p-0 me-2 toggle-card"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#welcomeCard"
      aria-expanded="true">
      <i class="fa-solid fa-chevron-right"></i>
    </button>

    <h5 class="mb-0">
      <?php echo $this->user->name . " [" . $this->user->username . "]"; ?>
    </h5>

    <span class="badge gda-role-badge bg-secondary ms-auto">
      <i class="<?php echo $this->escape($this->userRole['icon']); ?>" aria-hidden="true"></i><?php echo $this->escape(Text::_($this->userRole['label'])); ?>
    </span>

  </div>

  <div class="collapse show" id="welcomeCard">
    <div class="card-body">
      <!-- <p class="mb-0">
        <?php echo Text::_('COM_GDA_ACCUEIL_WELCOME_MESSAGE'); ?>
      </p> -->

      <?php echo LayoutHelper::render('accueil.welcome_status', [
        'profil' => $this->profil,
        'itemid' => $this->itemid ?? 0,
      ]); ?>
    </div>
  </div>

</div>


<div class="row g-2 dashboard">
  <!-- container dashboard py-4 -->


  <?php
  // Deux colonnes explicitement empilées (plutôt qu'un simple enchaînement de "col-lg-6" laissé
  // au wrap automatique de la grille Bootstrap) : chaque encart est repliable (.toggle-card), et
  // un wrap flex "à plat" ne fait pas remonter un encart de la colonne de droite quand celui du
  // dessus, à gauche, se replie (la grille Bootstrap n'est pas un masonry). Les deux cartes de
  // gauche (Suivi Adhésion + Boutique) doivent donc rester dans le même flux vertical, indépendant
  // de la hauteur de la colonne de droite.
  ?>
  <div class="col-12 col-lg-6 d-flex flex-column gap-2">
    <!-- ALERTES - Suivi Adhésion et CACI -->
    <?php echo LayoutHelper::render('accueil.dash_status_adhesion', [
      'souscription' => $this->souscription,
      'statusEnum'   => $this->statusEnum,
      'user'         => $this->user,
      'itemid'       => $this->itemid ?? 0
    ]);
    ?>

    <!-- Boutique -->
    <?php echo LayoutHelper::render(
      'accueil.dash_boutique',
      ['campagnes' => $this->campagnesBoutique]
    );
    ?>
  </div>

  <div class="col-12 col-lg-6 d-flex flex-column gap-2">
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

    <!-- Campagnes réservables : un encart par nature (Formation, Loisir) -->
    <?php echo LayoutHelper::render('accueil.dash_campagnes_reservables', [
      'campagnes' => $this->campagnesFormation,
      'user'      => $this->user,
      'cardId'    => 'formationsCard',
      'titleKey'  => 'COM_GDA_RESERVATION_FORMATIONS_TITRE',
      'icon'      => 'fa-solid fa-graduation-cap',
    ]);
    ?>

    <?php echo LayoutHelper::render('accueil.dash_campagnes_reservables', [
      'campagnes' => $this->campagnesLoisir,
      'user'      => $this->user,
      'cardId'    => 'loisirsCard',
      'titleKey'  => 'COM_GDA_RESERVATION_LOISIRS_TITRE',
      'icon'      => 'fa-solid fa-umbrella-beach',
    ]);
    ?>

    <?php echo LayoutHelper::render('accueil.dash_reservation_modals'); ?>
  </div>

  <!-- Campagnes (layout générique, en attente des layouts Sortie / Soirée) -->



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
