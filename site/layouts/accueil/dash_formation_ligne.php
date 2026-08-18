<?php

/**
 * Layout : une ligne de formation dans l'encart "Campagnes de formation" du dashboard.
 *
 * Extrait dans son propre layout pour être ré-rendu seul en ajax après une réservation ou une
 * annulation (voir ReservationController::renderLigne()).
 *
 * @var array $displayData
 * - $displayData['formation'] : objet campagne enrichi par AccueilModel::getFormations()
 *   (places_occupees, ma_reservation, mon_statut, mes_places, mon_commentaire)
 * - $displayData['user']      : utilisateur connecté
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Service\ReservationService;

$formation = $displayData['formation'];

$placesTotal    = (int) $formation->nbr_place;
$placesOccupees = (int) $formation->places_occupees;
$illimite       = $placesTotal === 0;
$placesDispo    = $illimite ? null : max(0, $placesTotal - $placesOccupees);
$complet        = !$illimite && $placesDispo === 0;

// Une réservation annulée ne compte pas comme "déjà réservé" : l'adhérent peut re-réserver.
$monStatut = $formation->mon_statut ?? null;
$reserve   = $monStatut !== null && $monStatut !== ReservationService::STATUT_ANNULEE;
$enAttente = $monStatut === ReservationService::STATUT_ATTENTE;

// Le popup n'est nécessaire que si la campagne demande une information supplémentaire, ou pour
// prévenir d'une mise en liste d'attente. Sinon un simple clic suffit à réserver.
$aHelloAsso  = !empty($formation->event_helloasso) && $formation->event_helloasso !== 'null';
$besoinPopup = $reserve || $complet || $aHelloAsso
    || !empty($formation->reservation_multiple) || !empty($formation->role_actif);
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom gda-formation-ligne"
    id="formation-<?= (int) $formation->id_campagne ?>">

    <div class="flex-grow-1">
        <a href="#"
            class="fw-bold text-decoration-none<?= (int) $formation->id_article > 0 ? ' js-show-article' : ' pe-none' ?>"
            <?= (int) $formation->id_article > 0 ? 'data-id-article="' . (int) $formation->id_article . '"' : '' ?>>
            <?= htmlspecialchars((string) $formation->titre, ENT_QUOTES, 'UTF-8') ?>
        </a>

        <?php if ($enAttente) : ?>
            <span class="badge bg-warning text-dark ms-2"><?= Text::_('COM_GDA_RESERVATION_STATUT_ATTENTE') ?></span>
        <?php elseif ($reserve) : ?>
            <span class="badge bg-success ms-2"><?= Text::_('COM_GDA_RESERVATION_STATUT_CONFIRMEE') ?></span>
        <?php endif; ?>

        <?php // Description saisie par le Bureau dans le formulaire de campagne (source de confiance) :
        // rendue telle quelle pour permettre la mise en forme (gras, liens, ...). ?>
        <div class="text-muted small"><?= (string) $formation->description ?></div>

        <div class="text-muted small">
            <i class="fa-solid fa-users me-1" aria-hidden="true"></i>
            <?php if ($illimite) : ?>
                <?= Text::_('COM_GDA_CAMPAGNE_NO_LIMIT') ?>
            <?php else : ?>
                <?= Text::sprintf('COM_GDA_RESERVATION_PLACES', $placesDispo, $placesTotal) ?>
            <?php endif; ?>
            <span class="ms-3">
                <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>
                <?= Text::sprintf('COM_GDA_RESERVATION_JUSQUAU', HTMLHelper::_('date', $formation->date_fin, 'd M Y')) ?>
            </span>
        </div>
    </div>

    <div class="text-end">
        <button type="button"
            class="btn btn-sm <?= $reserve ? 'btn-outline-primary' : 'btn-success' ?> js-reserver"
            data-id-campagne="<?= (int) $formation->id_campagne ?>"
            data-besoin-popup="<?= $besoinPopup ? '1' : '0' ?>"
            data-complet="<?= $complet ? '1' : '0' ?>">
            <?php if ($reserve) : ?>
                <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_RESERVATION_MODIFIER') ?>
            <?php else : ?>
                <i class="fa-solid fa-user-plus me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_RESERVATION_RESERVER') ?>
            <?php endif; ?>
        </button>
    </div>
</div>
