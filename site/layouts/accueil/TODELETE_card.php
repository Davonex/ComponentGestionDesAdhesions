<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;

use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;

/**
 * @var array $displayData
 * - $displayData['campagne'] : campagne
 * - $displayData['task']  : Controler joomla task
 */

$item = $displayData['campagne'];
$user = $displayData['user'];

$placeDispo = $item->nbr_place - $item->nbr_souscriptions;
$id_type_saison = ConfHelper::GetKey('IdTypeSaison');

// Est ce que cette campagne est une saison 
$Saison = ($item->id_type === $id_type_saison) ? true : false;
// Est ce que je $USER est dja sincrit
$Inscrit = ($item->deja_inscrit) ? true : false;
// Est ce que la campagne est pleine
$Full = ($item->nbr_place !==  0  and  $placeDispo > 0) ? true : false;





?>

<div id="souscription_<?= $item->id_campagne ?>" class="mt-2 d-flex col-md-6 col-sm-12  <?= $extra ?>">
    <div class="card h-100 w-100">
        <div class="card-header">
            <h4>
                <p class="pt-2 float-start" data-bs name="type_name"> <?= $item->type_name ?></p>
            </h4>
            <?php if ($Full) : ?>
                <p class="pt-2 float-end"><span class="badge rounded-pill bg-danger"><?= Text::_('COM_GDA_CAMPAGNE_PLEINE') ?></span></p>
            <?php else : ?>
                <p class="pt-2 float-end">
                    Jusqu'au <span class="badge rounded-pill bg-warning"><?= HTMLHelper::_('date', $item->date_fin, 'd M y') ?></span>
                </p>
            <?php endif; ?>
        </div> <!-- END card-header -->
        <div class="row g-0">
            <div class="col-md-4">
                <img name="SrcImg"
                    src="<?= FileHelper::getImageSrc($item->type_image, "ImagesPath", "CampagneImageDefault") ?>"
                    class="img-thumbnail rounded float-end" alt="photo">
            </div> <!-- class="col-md-5" -->
            <div class="col-md-8">
                <div class="card-body limited-text">
                    <h4 data-bs name="titre" class="card-title"><?= $item->titre ?></h4>
                    <p data-bs name="description" class="card-text"><?= $item->description ?></p>
                    <span class="position-absolute invisible" data-bs name="id_campagne"><?= $item->id_campagne ?></span>
                    <span class="position-absolute invisible" data-bs name="licence"><?= $user->username ?></span>
                    <span class="position-absolute invisible" data-bs name="id_profil"><?= $user->id ?></span>
                    <?php if ($Saison) : ?>
                        <!-- afficher les groupe disponible -->
                        <span class="position-absolute invisible" data-bs name="id_groupes"><?= $item->id_groupes ?></span>
                    <?php endif; ?>
                </div> <!-- class="card-body" -->
            </div> <!-- class="col-md-7" -->
        </div> <!-- class="row g-0" -->
        <div class="card-footer text-muted h-100">
            <?php if ($Saison && !$Inscrit) : ?>
                <a href="#"
                    class="btn btn-success float-end"
                    type="button"
                    id="openForm"
                    data-bs-id_souscription="souscription_<?= $item->id_campagne ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#modalSign"
                    data-toggle="tooltip"
                    data-placement="top"
                    title="<?= Text::_('COM_GDA_CAMPAGNE_SOUSCRIT_TOOLTIP') ?>">
                    <?= Text::_('COM_GDA_CAMPAGNE_SOUSCRIT') ?>
                </a>
            <?php elseif ($Saison && $Inscrit) : ?>
                <span class="badge rounded-pill bg-success float-end mt-2"><?= Text::_('COM_GDA_CAMPAGNE_DEJA_INSCRIT') ?></span>

            <?php elseif ($Inscrit) : ?>
                <!-- Deja inscrit à la campagne on affiche le message COM_GDA_CAMPAGNE_DEJA_INSCRIT -->
                <span class="badge rounded-pill bg-success float-end mt-2"><?= Text::_('COM_GDA_CAMPAGNE_DEJA_INSCRIT') ?></span>
                <!-- // Si ce n'est pas une campagne de type saison on affiche le bouton de désinscription -->
                <a href="#"
                    class="btn btn-warning float-start"
                    type="button"
                    id="openForm"
                    data-bs-id_souscription="souscription_<?= $item->id_campagne ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#modalSign"
                    data-toggle="tooltip"
                    data-placement="top"
                    title="<?= Text::_('COM_GDA_CAMPAGNE_DESOUSCRIT_TOOLTIP') ?>">
                    <?= Text::_('COM_GDA_CAMPAGNE_DESOUSCRIT') ?>
                </a>
            <?php elseif (!$Full) : ?>
                <!-- // Si il y a des places dispoponible    -->
                <small class="text-muted float-start mt-2"><?= $placeDispo ?> place(s) de dispo</small>
                <a href="#"
                    class="btn btn-success float-end"
                    type="button"
                    id="openForm"
                    data-bs-id_souscription="souscription_<?= $item->id_campagne ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#modalSign"
                    data-toggle="tooltip"
                    data-placement="top"
                    title="<?= Text::_('COM_GDA_CAMPAGNE_SOUSCRIT_TOOLTIP') ?>">
                    <?= Text::_('COM_GDA_CAMPAGNE_SOUSCRIT') ?>
                </a>
            <?php else : ?>
                <small class="text-muted float-start mt-2"><?= Text::_('COM_GDA_CAMPAGNE_PLEINE') ?></small>
            <?php endif; ?>
        </div> <!-- // class="card-footer" -->
    </div> <!-- // class="card" -->
</div> <!-- //  class="col" -->