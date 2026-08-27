<?php

/**
 * Layout : une ligne de campagne (Formation ou Loisir) dans l'encart "Mes réservations" du
 * dashboard adhérent.
 *
 * Extrait dans son propre layout pour être ré-rendu seul en ajax après une réservation ou une
 * annulation (voir ReservationController::renderLigne()).
 *
 * @var array $displayData
 * - $displayData['formation'] : objet campagne enrichi par AccueilModel::getCampagnesReservables()
 *   (places_occupees, capacite_totale, mes_places, places_par_role, paiement_helloasso)
 * - $displayData['user']      : utilisateur connecté
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use NCB\Component\Gda\Site\Service\ReservationService;

$formation = $displayData['formation'];

$placesTotal    = (int) $formation->capacite_totale;
$placesOccupees = (int) $formation->places_occupees;
$illimite       = $placesTotal === 0;
$placesDispo    = $illimite ? null : max(0, $placesTotal - $placesOccupees);
$complet        = !$illimite && $placesDispo === 0;

// Mes places pour cette campagne : une réservation Loisir peut mélanger plusieurs rôles à
// statuts différents (certains confirmés, d'autres en attente) — voir
// AccueilModel::getCampagnesReservables(). Le cas courant (Formation, toujours 1 rôle, ou Loisir
// à statut homogène) garde son badge unique historique ; l'affichage se détaille par rôle
// uniquement si la réservation en porte effectivement plusieurs à la fois.
$mesPlaces = $formation->mes_places ?? [];
$reserve   = !empty($mesPlaces);
$rolesMultiples = count($mesPlaces) > 1;

// Le rôle est désormais toujours demandé (Formation et Loisir) : le popup est donc toujours
// nécessaire pour au moins choisir un rôle (plus de réservation directe en un clic).
$besoinPopup = true;
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom gda-formation-ligne"
    id="formation-<?= (int) $formation->id_campagne ?>">

    <div class="flex-grow-1">
        <a href="#"
            class="fw-bold text-decoration-none<?= (int) $formation->id_article > 0 ? ' js-show-article' : ' pe-none' ?>"
            <?= (int) $formation->id_article > 0 ? 'data-id-article="' . (int) $formation->id_article . '"' : '' ?>>
            <?= htmlspecialchars((string) $formation->titre, ENT_QUOTES, 'UTF-8') ?>
        </a>

        <?php if ($reserve && !$rolesMultiples) : ?>
            <?php $place = $mesPlaces[0]; ?>
            <?php if ($place->statut === ReservationService::STATUT_ATTENTE) : ?>
                <span class="badge bg-warning text-dark ms-2">
                    <?php if ($place->rang) : ?>
                        <?= Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_LISTE_ATTENTE', $place->rang) ?>
                    <?php else : ?>
                        <?= Text::_('COM_GDA_RESERVATION_STATUT_ATTENTE') ?>
                    <?php endif; ?>
                </span>
            <?php else : ?>
                <span class="badge bg-success ms-2"><?= Text::_('COM_GDA_RESERVATION_STATUT_CONFIRMEE') ?></span>
            <?php endif; ?>
        <?php elseif ($rolesMultiples) : ?>
            <?php foreach ($mesPlaces as $place) : ?>
                <?php if ($place->statut === ReservationService::STATUT_ATTENTE) : ?>
                    <span class="badge bg-warning text-dark ms-2">
                        <?= htmlspecialchars($place->role, ENT_QUOTES, 'UTF-8') ?> :
                        <?php if ($place->rang) : ?>
                            <?= Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_LISTE_ATTENTE', $place->rang) ?>
                        <?php else : ?>
                            <?= Text::_('COM_GDA_RESERVATION_STATUT_ATTENTE') ?>
                        <?php endif; ?>
                    </span>
                <?php else : ?>
                    <span class="badge bg-success ms-2"><?= htmlspecialchars($place->role, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($formation->paiement_helloasso === 'paye') : ?>
            <span class="badge bg-success ms-2" title="<?= $this->escape(Text::_('COM_GDA_RESERVATION_PAIEMENT_DETECTE')) ?>">
                <i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>&euro;
            </span>
        <?php elseif ($formation->paiement_helloasso === 'non_paye') : ?>
            <span class="badge bg-danger ms-2" title="<?= $this->escape(Text::_('COM_GDA_RESERVATION_PAIEMENT_INTROUVABLE')) ?>">
                <i class="fa-solid fa-circle-xmark me-1" aria-hidden="true"></i>&euro;
            </span>
        <?php endif; ?>

        <?php // Description saisie par le Bureau dans le formulaire de campagne (source de confiance) :
        // rendue telle quelle pour permettre la mise en forme (gras, liens, ...). ?>
        <div class="text-muted small"><?= (string) $formation->description ?></div>

        <?php $placesParRole = $formation->places_par_role ?? []; ?>
        <?php if (count($placesParRole) > 1) : ?>
            <?php foreach ($placesParRole as $ligneRole) : ?>
                <div class="text-muted small">
                    <i class="fa-solid fa-users me-1" aria-hidden="true"></i>
                    <?php if ($ligneRole->disponible === null) : ?>
                        <?= Text::sprintf('COM_GDA_RESERVATION_PLACES_ROLE_NO_LIMIT', $this->escape($ligneRole->role)) ?>
                    <?php else : ?>
                        <?= Text::sprintf('COM_GDA_RESERVATION_PLACES_ROLE', $this->escape($ligneRole->role), $ligneRole->disponible, $ligneRole->total) ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="text-muted small">
                <i class="fa-solid fa-users me-1" aria-hidden="true"></i>
                <?php if ($illimite) : ?>
                    <?= Text::_('COM_GDA_CAMPAGNE_NO_LIMIT') ?>
                <?php else : ?>
                    <?= Text::sprintf('COM_GDA_RESERVATION_PLACES', $placesDispo, $placesTotal) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="text-end">
        <div class="text-muted small mb-1">
            <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>
            <?= Text::sprintf('COM_GDA_RESERVATION_JUSQUAU', HTMLHelper::_('date', $formation->date_fin, 'd M Y')) ?>
        </div>
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
