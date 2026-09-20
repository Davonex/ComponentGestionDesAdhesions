<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Model\CampagnesModel;

/**
 * @var array $displayData
 * - $displayData['item'] : campagne (avec places_occupees, type_name, ...)
 * - $displayData['task']  : Controler joomla task
 */

$item = $displayData['item'];
$task  = $displayData['task'];


$classes = [
    '1'   => 'table-active',
    '0'   => 'table-inactive',
];

$data_active = [
        'jform_campagne[id_campagne]' => $item->id_campagne,
        'jform_campagne[active]' => $item->active ? '0' : '1',
        'jform_campagne[id_type]' => $item->id_type,
        'task' => "campagnes.activer"
    ];

$data_remove = [
        'jform_campagne[titre]' => $item->titre,
        'jform_campagne[id_campagne]' => $item->id_campagne,
        'jform_campagne[id_type]' => $item->id_type,
        'task' => "campagnes.effacer"
    ];

/*
** Deux rapports distincts pour une même campagne : "reservation" (table #__gda_reservation*,
** propre à ce composant) et "helloasso" (paiements en ligne, via l'API HelloAsso). Chaque bouton
** n'est affiché que s'il a un sens : pas de rapport "reservation" pour une Boutique (aucun mécanisme
** de réservation, voir CampagnesModel::getHelloAssoFormTypeParNature()), pas de rapport "helloasso"
** sans event HelloAsso lié.
**/
$isBoutique = $item->type_name === 'Boutique';

$data_rapport_reservation = [
        'jform_campagne[id_campagne]' => $item->id_campagne,
        'jform_campagne[titre]' => $item->titre,
        'jform_campagne[event_helloasso]' => $item->event_helloasso,
        'jform_campagne[rapport_type]' => 'reservation',
        'task' => "campagnes.rapport"
    ];

$data_rapport_helloasso = [
        'jform_campagne[id_campagne]' => $item->id_campagne,
        'jform_campagne[titre]' => $item->titre,
        'jform_campagne[event_helloasso]' => $item->event_helloasso,
        'jform_campagne[rapport_type]' => 'helloasso',
        // Distingue le rapport "Event" (adhérents inscrits) du rapport "Shop" (produits achetés,
        // structure d'item HelloAsso différente) - voir CampagnesController::rapport().
        'jform_campagne[id_type]' => $item->id_type,
        'task' => "campagnes.rapport"
    ];

$capaciteTotale = (int) ($item->capacite_totale ?? $item->nbr_place);
$placesTotal = ($capaciteTotale === 0) ? Text::_('COM_GDA_CAMPAGNE_NO_LIMIT') : $capaciteTotale;
$rolePlaces   = $item->role_places ?? [];
$roleOccupees = $item->role_occupees ?? [];

// Reformaté au format attendu par le champ calendar (showtime), la base stockant du Y-m-d H:i:s.
$dateEvenement = (!empty($item->date_evenement) && $item->date_evenement !== '0000-00-00 00:00:00')
    ? date('d/m/Y H:i', strtotime($item->date_evenement))
    : '';

$hasHelloAsso = CampagnesModel::aUnLienHelloAsso($item->event_helloasso);

?>



 <tr class="<?= $classes[$item->active] ?>"
    id="campagne-<?= $item->id_campagne; ?>"
    data-active="<?= (int) $item->active; ?>"
    data-id-type="<?= (int) $item->id_type; ?>">
            <td class="align-middle  text-center">

                <?php // Afficher le buttom editer  ?>
                <a class="btn btn-success btn-sm" type="button" name="edition"
                        data-bs-id_article="<?= $item->id_article; ?>"
                        data-bs-toggle="modal"
                        data-bs-id_campagne="campagne-<?= $item->id_campagne?>""
                        data-bs-target="#modalForm"
                        title="<?= Text::_('COM_GDA_CAMPAGNE_EDIT_TOOLTIP'); ?>" >
                    <span class="fa-solid fa-pencil"></span>
                </a>
                 <?php if  (!$item->active) : ?>
                    <?php // Afficher le buttom effacer  ?>
                    <a class="btn btn-warning btn-sm" type="button" name="suppression"
                        title="<?= Text::_('COM_GDA_CAMPAGNE_REMOVE_TOOLTIP'); ?>"
                        onclick='campagneAdmConfirmRemove(<?= json_encode($data_remove, JSON_HEX_APOS | JSON_HEX_AMP) ?>)'>
                    <span class="fa-solid fa-trash"></span>
                    </a>
                <?php endif; ?>
                </td>

            <td class="align-middle text-start">
                <span data-bs name="titre"><?= $this->escape((string) $item->titre); ?></span>
                <br><small class="text-muted"><?= $this->escape((string) $item->type_name); ?><?= !empty($item->sous_type) ? ' · ' . $this->escape(Text::_('COM_GDA_CAMPAGNE_SOUS_TYPE_' . strtoupper((string) $item->sous_type))) : ''; ?></small>
            </td>
            <td class="align-middle text-center"><?= $dateEvenement !== '' ? HTMLHelper::_('date', $item->date_evenement, 'd M Y H:i') : '—'; ?></td>
            <td class="align-middle text-center"><span data-bs name="date_debut"><?= $item->date_debut;?></span></td>
            <td class="align-middle text-center"><span data-bs name="date_fin"><?= $item->date_fin;?></span></td>

            <?php // Boutique : pas de rôle, pas de mécanisme de réservation (voir $isBoutique plus haut). ?>
            <?php // Sinon, une ligne par rôle configuré : confirmées (coche verte) + en cours de ?>
            <?php // validation (sablier orange), ex "Encadrant : ✔ 1, ⏳ 2 / 5" ; capacité 0 = illimité, ?>
            <?php // pas de dénominateur. role_places vide en dehors de Boutique (campagne legacy sans ?>
            <?php // rôle) : repli sur le total, comme avant l'ajout du détail par rôle. ?>
            <td class="text-start align-middle"> 
                <?php if ($isBoutique) : ?>
                    <?= Text::_('COM_GDA_CAMPAGNE_PLACES_NA') ?>
                <?php elseif (!empty($rolePlaces)) : ?>
                    <?php foreach ($rolePlaces as $role => $capacite) : ?>
                        <?php $compte = $roleOccupees[$role] ?? ['confirmee' => 0, 'attente' => 0]; ?>
                        <div class="mb-1">
                            <?= htmlspecialchars($role) ?> :
                            <span class="badge bg-success" title="<?= Text::_('COM_GDA_RESERVATION_STATUT_CONFIRMEE') ?>">
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i> <?= (int) $compte['confirmee'] ?>
                            </span>
                            <span class="badge bg-warning text-dark" title="<?= Text::_('COM_GDA_RESERVATION_STATUT_ATTENTE') ?>">
                                <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> <?= (int) $compte['attente'] ?>
                            </span>
                            <?php if ($capacite > 0) : ?>
                                <span class="badge border text-dark">
                                    <i class="fa-solid fa-users" aria-hidden="true"></i> <?= $capacite ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?= (int) $item->places_occupees; ?> / <?= $placesTotal; ?>
                <?php endif; ?>
            </td>

            <td class="align-middle text-center">
                <?php if ((int) $item->id_article > 0) : ?>
                    <a href="#" class="js-show-article" data-id-article="<?= (int) $item->id_article; ?>"
                        title="<?= Text::_('COM_GDA_CAMPAGNE_LIST_ARTICLE'); ?>">
                        <span class="fa-solid fa-newspaper"></span>
                    </a>
                <?php endif; ?>
            </td>
                    <!-- FERMER/ OURVERTE -->
            <td class="align-middle text-center">
                <?php if  ($item->active) : ?>
                    <a class="btn btn-success btn-sm" type="button" name="activer"
                        onclick='simpleCallAjax(<?= json_encode($data_active, JSON_HEX_APOS | JSON_HEX_AMP) ?>,campagneAdmCB)'
                        title="<?= Text::_('COM_GDA_CAMPAGNE_CLOSE_TOOLTIP'); ?>"
                    >
                            <span class="icon-icon-color-featured fa-door-open"></span>
                    </a>
                <?php else : ?>
                    <a class="btn btn-dark btn-sm" type="button"
                       onclick='simpleCallAjax(<?= json_encode($data_active, JSON_HEX_APOS | JSON_HEX_AMP) ?>,campagneAdmCB)'
                        title="<?= Text::_('COM_GDA_CAMPAGNE_OPEN_TOOLTIP'); ?>" >
                            <span class="icon-unpublish"></span>
                    </a>
                <?php endif; ?>
            </td>
                        <!-- RAPPORT -->
            <td class="align-middle">
                <div class="d-flex justify-content-evenly">
                    <?php // Chaque rapport n'est proposé que s'il a un sens pour cette campagne (pas de bouton grisé). ?>
                    <?php if (!$isBoutique) : ?>
                        <a class="btn btn-primary btn-sm" type="button" name="rapport-reservation"
                                data-bs-id_campagne="campagne-<?= $item->id_campagne?>"
                                onclick='simpleCallAjax(<?= json_encode($data_rapport_reservation, JSON_HEX_APOS | JSON_HEX_AMP) ?>,campagneRapportCB,false)'
                                title="<?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_TOOLTIP'); ?>" >
                            <span class="fa-solid fa-chart-simple"></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($hasHelloAsso) : ?>
                        <a class="btn btn-primary btn-sm" type="button" name="rapport-helloasso"
                                data-bs-id_campagne="campagne-<?= $item->id_campagne?>"
                                onclick='simpleCallAjax(<?= json_encode($data_rapport_helloasso, JSON_HEX_APOS | JSON_HEX_AMP) ?>,campagneRapportCB,false)'
                                title="<?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_HELLOASSO_TOOLTIP'); ?>" >
                            <?= HTMLHelper::_('image', FileHelper::getHelloAssoLogoSrc(), Text::_('COM_GDA_CAMPAGNE_HELLOASSO'), ['width' => '16', 'height' => '16']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </td>

            <?php // Champs cachés utilisés par LstModal (form_modal.js) pour préremplir la modal d'édition. ?>
            <?php // Un <tr> ne peut contenir que des <td>/<th> : ces spans doivent rester dans un <td>, ?>
            <?php // sinon le navigateur les sort du tableau (foster parenting) et openModal() ne les trouve plus. ?>
            <td class="d-none">
                <span class="hidden" data-bs name="description"><?= $item->description; ?></span>
                <span class="hidden" data-bs name="id_article"><?= $item->id_article; ?></span>
                <span class="hidden" data-bs name="event_helloasso"><?= $item->event_helloasso; ?></span>
                <span class="hidden" data-bs name="id_type"><?= $item->id_type; ?></span>
                <span class="hidden" data-bs name="sous_type"><?= $this->escape((string) ($item->sous_type ?? '')); ?></span>
                <span class="hidden" data-bs name="id_groupes"><?= $item->id_groupes;?></span>
                <span class="hidden" data-bs name="id_responsable"><?= (int) ($item->id_responsable ?? 0) ?: ''; ?></span>
                <span class="hidden" data-bs name="id_campagne"><?= $item->id_campagne; ?></span>
                <span class="hidden" data-bs name="active"><?= $item->active; ?></span>
                <span class="hidden" data-bs name="date_evenement"><?= $dateEvenement; ?></span>
                <span class="hidden" data-bs name="reservation_multiple"><?= (int) $item->reservation_multiple; ?></span>
                <span class="hidden" data-bs name="role_places"><?= htmlspecialchars(json_encode($item->role_places ?? []), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="hidden" data-bs name="modal-title"><?= Text::_('COM_GDA_CAMPAGNE_EDIT'); ?></span>
            </td>
        </tr>
