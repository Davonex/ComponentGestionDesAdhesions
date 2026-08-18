<?php

/**
 * Layout : carte "Profil" (photo, coordonnées) réutilisée par :
 * - la vue Profil (fiche éditable de l'adhérent connecté / d'un profil "on behalf")
 * - la popup "fiche adhérent" des vues Groupe et Secrétariat (lecture seule, via ProfilController::showCard())
 *
 * @var array $displayData
 * - $displayData['profil']     : objet profil (id_profil, civilite, nom, prenom, username, photo, adresse,
 *                                 code_postal, ville, telephone, email, a_prevenir, a_prevenir_tel, date_de_naissance)
 * - $displayData['principale'] : bool - style "profil principal" (text-bg-gda) vs "on behalf" (text-white bg-secondary)
 * - $displayData['editable']   : bool - affiche le bouton "Modifier" (ouvre #myModal), ou sinon une croix de
 *                                 fermeture (data-bs-dismiss="modal") et le lien "Liste des brevets" (ouvre la
 *                                 popup profil.card_brevet via ProfilController::showBrevets()), pour l'usage
 *                                 popup lecture seule
 * - $displayData['fields']     : array de clés parmi photo|coordonnees|telephone|email|urgence - blocs de contenu
 *                                 à afficher. Voir NCB\Component\Gda\Site\Model\ProfilModel::CARD_FIELDS_FULL/LIGHT
 *                                 pour la définition centrale de ces listes.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Model\ProfilModel;

$profil = $displayData['profil'] ?? null;

if ($profil === null) {
  return;
}

$principale = $displayData['principale'] ?? true;
$editable = $displayData['editable'] ?? true;
$fields = $displayData['fields'] ?? ProfilModel::CARD_FIELDS_FULL;

$extraClass = $principale ? ' text-bg-gda' : ' text-white bg-secondary';

$showPhoto = in_array('photo', $fields, true);
$showCoordonnees = in_array('coordonnees', $fields, true);
$showTelephone = in_array('telephone', $fields, true);
$showEmail = in_array('email', $fields, true);
$showUrgence = in_array('urgence', $fields, true);
$showCoordonneesTitle = $showCoordonnees || $showTelephone || $showEmail;
?>

<div id="id_<?php echo (int) $profil->id_profil; ?>" class="h-100 <?php echo $displayData['taille'] ?? ''; ?>">
  <div class="card<?php echo $extraClass; ?>">
    <div class="card-header">
      <p class="pt-2 float-start">
        <span data-bs name="civilite"><?php echo $this->escape($profil->civilite ?? ''); ?></span>
        <span data-bs name="nom"><?php echo $this->escape($profil->nom ?? ''); ?></span>
        <span data-bs name="prenom"><?php echo $this->escape($profil->prenom ?? ''); ?></span>
        (<span data-bs name="licence"><?php echo $this->escape($profil->username ?? ''); ?></span>)
      </p>
      <?php if ($editable) : ?>
        <a class="btn btn-success float-end"
          type="button"
          id="openForm"
          data-bs-id_profil="id_<?php echo (int) $profil->id_profil; ?>"
          data-bs-toggle="modal"
          data-bs-target="#myModal"
          data-toggle="tooltip"
          data-placement="top"
          title="<?php echo $this->escape(Text::_('COM_GDA_PROFIL_EDIT_TOOLTIP')); ?>">
          <i class="fa-solid fa-user-pen"></i> <?php echo $this->escape(Text::_('COM_GDA_PROFIL_EDIT')); ?>
        </a>
      <?php else : ?>
        <button type="button"
          class="btn-close float-end bg-white rounded-circle p-2"
          data-bs-dismiss="modal"
          aria-label="<?php echo $this->escape(Text::_('JCLOSE')); ?>">
        </button>
      <?php endif; ?>
    </div> <!-- class="card-header" -->
    <div class="row g-0">
      <?php if ($showPhoto) : ?>
        <div class="col-md-5">
          <img data-bs name="Srcphoto" src="<?php echo FileHelper::getImageSrc($profil->photo, "ProfilPhotoPath", "DefaultProfilPhoto"); ?>" class="img-thumbnail rounded mx-auto d-block" alt="photo">
        </div>
      <?php endif; ?>
      <div class="<?php echo $showPhoto ? 'col-md-7' : 'col-md-12'; ?>">
        <div class="card-body">
          <?php if ($showCoordonneesTitle || $showUrgence) : ?>
            <dl class="card-text">
              <?php if ($showCoordonneesTitle) : ?>
                <dt><span><?php echo $this->escape(Text::_('COM_GDA_PROFIL_CARD_COORDONNEES')); ?></span></dt>
                <?php if ($showCoordonnees) : ?>
                  <dd class="ms-2">
                    <i class="fa-solid fa-house"></i> :
                    <span data-bs name="adresse"><?php echo $this->escape($profil->adresse ?? ''); ?></span>,
                    <span data-bs name="code_postal"><?php echo $this->escape($profil->code_postal ?? ''); ?></span>
                    <span data-bs name="ville"><?php echo $this->escape($profil->ville ?? ''); ?></span>
                  </dd>
                <?php endif; ?>
                <?php if ($showTelephone) : ?>
                  <dd class="ms-2">
                    <i class="fa-solid fa-phone"></i> :
                    <span data-bs name="telephone"><?php echo $this->escape(ToolsHelper::ShowTel($profil->telephone)); ?></span>
                  </dd>
                <?php endif; ?>
                <?php if ($showEmail) : ?>
                  <dd class="ms-2">
                    <i class="fa-solid fa-at"></i> :
                    <span data-bs name="email"><?php echo $this->escape($profil->email ?? ''); ?></span>
                  </dd>
                <?php endif; ?>
              <?php endif; ?>
              <?php if ($showUrgence) : ?>
                <dt><span><?php echo $this->escape(Text::_('COM_GDA_PROFIL_CARD_URGENCE')); ?></span></dt>
                <dd class="ms-2">
                  <i class="fa-solid fa-person-drowning"></i> :
                  <span data-bs name="a_prevenir"><?php echo $this->escape($profil->a_prevenir ?? ''); ?></span>
                </dd>
                <dd class="ms-2">
                  <i class="fa-solid fa-phone"></i> :
                  <span data-bs name="a_prevenir_tel"><?php echo $this->escape(ToolsHelper::ShowTel($profil->a_prevenir_tel)); ?></span>
                </dd>
              <?php endif; ?>
            </dl>
          <?php endif; ?>



          <!-- Champs cachés utilisés par openModal() (form_modal.js) pour préremplir la modale d'édition -->
          <span class="position-absolute invisible" data-bs name="date_de_naissance"><?php echo $this->escape(ToolsHelper::from_sqldate($profil->date_de_naissance)); ?></span>
          <span class="position-absolute invisible" data-bs name="id_profil"><?php echo (int) $profil->id_profil; ?></span>
          <span class="position-absolute invisible" data-bs name="photo"><?php echo $this->escape($profil->photo ?? ''); ?></span>
        </div> <!-- class="card-body" -->
      </div>
    </div> <!-- class="row g-0" -->
    <div class="row g-0">
      <?php if (!$editable) : ?>
        <p class="card-text mb-0 text-center">
          <a href="#" class="js-show-profil-brevets fw-bold" style="color: var(--blanc);" data-id-profil="<?php echo (int) $profil->id_profil; ?>">
            <i class="fa-solid fa-award"></i> <?php echo $this->escape(Text::_('COM_GDA_PROFIL_CARD_BREVETS_LINK')); ?>
          </a>
        </p>
      <?php endif; ?>
    </div> <!-- class="row g-0" -->
  </div> <!-- class="card" -->
</div>