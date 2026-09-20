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
// statuts différents (certains validés, d'autres encore en attente de validation par le
// responsable de campagne) — voir AccueilModel::getCampagnesReservables(). Le cas courant
// (Formation, toujours 1 rôle, ou Loisir à statut homogène) garde son badge unique ; l'affichage
// se détaille par rôle uniquement si la réservation en porte effectivement plusieurs à la fois.
$mesPlaces = $formation->mes_places ?? [];
$reserve   = !empty($mesPlaces);
$rolesMultiples = count($mesPlaces) > 1;

// Une place refusée par le responsable, ou annulée par l'adhérent après validation, verrouille la
// réservation : l'adhérent ne peut plus la modifier ni s'en désinscrire (voir
// ReservationService::estVerrouillee()), seul le responsable peut faire évoluer son statut.
$verrouille = count(array_filter($mesPlaces, static fn ($place) => in_array($place->statut, [ReservationService::STATUT_REFUSEE, ReservationService::STATUT_ANNULEE], true))) > 0;

// Places regroupées par (rôle, statut) : deux places identiques s'affichent en un seul badge
// "Pratiquant ×2" plutôt qu'en deux badges indiscernables.
$groupesPlaces = [];
foreach ($mesPlaces as $place) {
    $cle = $place->role . "|" . $place->statut;
    $groupesPlaces[$cle] ??= ["role" => $place->role, "statut" => $place->statut, "quantite" => 0];
    $groupesPlaces[$cle]["quantite"]++;
}

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
            <?= $this->escape((string) $formation->titre) ?>
        </a>

        <?php
        // Rendu du badge de statut d'une place : quatre statuts possibles ici (attente/refusee/
        // confirmee — STATUT_ANNULEE = désistement après validation, voir AccueilModel), portés
        // par une seule fonction pour éviter de dupliquer ce mapping entre le cas simple (un rôle)
        // et le cas multi-rôles (Loisir) ci-dessous.
        $badgeStatutPlace = static function (string $statut): array {
            if ($statut === ReservationService::STATUT_ATTENTE) {
                return ['bg-warning text-dark', 'fa-hourglass-half', Text::_('COM_GDA_RESERVATION_STATUT_ATTENTE')];
            }

            if ($statut === ReservationService::STATUT_REFUSEE) {
                return ['bg-danger', 'fa-circle-xmark', Text::_('COM_GDA_RESERVATION_STATUT_REFUSEE')];
            }

            if ($statut === ReservationService::STATUT_ANNULEE) {
                return ['bg-secondary', 'fa-ban', Text::_('COM_GDA_RESERVATION_STATUT_ANNULEE')];
            }

            return ['bg-success', 'fa-circle-check', Text::_('COM_GDA_RESERVATION_STATUT_CONFIRMEE')];
        };
        ?>

        <?php if (!$reserve) : ?>
            <span class="badge bg-secondary ms-2" title="<?= $this->escape(Text::_('COM_GDA_RESERVATION_STATUT_NON_INSCRIT')) ?>">
                <i class="fa-solid fa-circle-minus me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_RESERVATION_STATUT_NON_INSCRIT') ?>
            </span>
        <?php elseif (!$rolesMultiples) : ?>
            <?php [$badgeClass, $badgeIcon, $badgeLabel] = $badgeStatutPlace($mesPlaces[0]->statut); ?>
            <span class="badge <?= $badgeClass ?> ms-2" title="<?= $this->escape($badgeLabel) ?>">
                <i class="fa-solid <?= $badgeIcon ?> me-1" aria-hidden="true"></i><?= $badgeLabel ?>
            </span>
        <?php else : ?>
            <?php foreach ($groupesPlaces as $groupe) : ?>
                <?php [$badgeClass, $badgeIcon, $badgeLabel] = $badgeStatutPlace($groupe["statut"]); ?>
                <span class="badge <?= $badgeClass ?> ms-2" title="<?= $this->escape($badgeLabel) ?>">
                    <i class="fa-solid <?= $badgeIcon ?> me-1" aria-hidden="true"></i><?= $this->escape($groupe["role"]) ?><?= $groupe["quantite"] > 1 ? " ×" . $groupe["quantite"] : "" ?>
                </span>
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

        <?php // Nombre de places configuré à 0 = pas de limite : on n'affiche alors aucune info de
        // place (ni "illimité", ni compteur), plutôt qu'un texte qui n'apporte rien à l'adhérent. ?>
        <?php $placesParRole = $formation->places_par_role ?? []; ?>
        <?php if (count($placesParRole) > 1) : ?>
            <?php foreach ($placesParRole as $ligneRole) : ?>
                <?php if ($ligneRole->disponible !== null) : ?>
                    <div class="text-muted small">
                        <i class="fa-solid fa-users me-1" aria-hidden="true"></i>
                        <?= Text::sprintf('COM_GDA_RESERVATION_PLACES_ROLE', $this->escape($ligneRole->role), $ligneRole->disponible, $ligneRole->total) ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php elseif (!$illimite) : ?>
            <div class="text-muted small">
                <i class="fa-solid fa-users me-1" aria-hidden="true"></i>
                <?= Text::sprintf('COM_GDA_RESERVATION_PLACES', $placesDispo, $placesTotal) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="text-end">
        <div class="text-muted small mb-1">
            <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>
            <?= Text::sprintf('COM_GDA_RESERVATION_JUSQUAU', HTMLHelper::_('date', $formation->date_fin, 'd M Y')) ?>
        </div>
        <?php if ($verrouille) : ?>
            <span class="d-inline-block" title="<?= $this->escape(Text::_('COM_GDA_RESERVATION_VERROUILLEE')) ?>">
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                    <i class="fa-solid fa-lock me-1" aria-hidden="true"></i><?= Text::_('COM_GDA_RESERVATION_MODIFIER') ?>
                </button>
            </span>
        <?php else : ?>
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
        <?php endif; ?>
    </div>
</div>
