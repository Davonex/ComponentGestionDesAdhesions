<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;

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

$data_rapport = [
        'jform_campagne[id_campagne]' => $item->id_campagne,
        'jform_campagne[titre]' => $item->titre,
        'jform_campagne[event_helloasso]' => $item->event_helloasso,
        'task' => "campagnes.rapport"
    ];

$capaciteTotale = (int) ($item->capacite_totale ?? $item->nbr_place);
$placesTotal = ($capaciteTotale === 0) ? Text::_('COM_GDA_CAMPAGNE_NO_LIMIT') : $capaciteTotale;

// Reformaté au format attendu par le champ calendar (showtime), la base stockant du Y-m-d H:i:s.
$dateEvenement = (!empty($item->date_evenement) && $item->date_evenement !== '0000-00-00 00:00:00')
    ? date('d/m/Y H:i', strtotime($item->date_evenement))
    : '';

$hasHelloAsso = $item->event_helloasso !== null && $item->event_helloasso !== "null";
$rapportTooltip = $hasHelloAsso
    ? Text::_('COM_GDA_CAMPAGNE_RAPPORT_HELLOASSO_TOOLTIP')
    : Text::_('COM_GDA_CAMPAGNE_RAPPORT_TOOLTIP');

?>



 <tr class="<?= $classes[$item->active] ?>"
    id="campagne-<?= $item->id_campagne; ?>"
    data-active="<?= (int) $item->active; ?>"
    data-id-type="<?= (int) $item->id_type; ?>">
            <td>

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
                        onclick='simpleCallAjax(<?=json_encode($data_remove)?>,campagneAdmRemoveCB)'>
                    <span class="fa-solid fa-trash"></span>
                    </a>
                <?php endif; ?>
                </td>

            <td>
                <span data-bs name="titre"><?= $item->titre; ?></span>
                <br><small class="text-muted"><?= $item->type_name; ?></small>
            </td>
            <td><?= $dateEvenement !== '' ? HTMLHelper::_('date', $item->date_evenement, 'd M Y H:i') : '—'; ?></td>
            <td><span data-bs name="date_debut"><?= $item->date_debut;?></span></td>
            <td><span data-bs name="date_fin"><?= $item->date_fin;?></span></td>

            <td><?= (int) $item->places_occupees; ?> / <?= $placesTotal; ?></td>

            <td>
                <?php if ((int) $item->id_article > 0) : ?>
                    <a href="#" class="js-show-article" data-id-article="<?= (int) $item->id_article; ?>"
                        title="<?= Text::_('COM_GDA_CAMPAGNE_LIST_ARTICLE'); ?>">
                        <span class="fa-solid fa-newspaper"></span>
                    </a>
                <?php endif; ?>
            </td>

            <td>
                <?php if  ($item->active) : ?>
                    <a class="btn btn-success btn-sm" type="button" name="activer"
                        onclick='simpleCallAjax(<?= json_encode($data_active) ?>,campagneAdmCB)'
                        title="<?= Text::_('COM_GDA_CAMPAGNE_CLOSE_TOOLTIP'); ?>"
                    >
                            <span class="icon-icon-color-featured fa-door-open"></span>
                    </a>
                <?php else : ?>
                    <a class="btn btn-dark btn-sm" type="button"
                       onclick='simpleCallAjax(<?= json_encode($data_active) ?>,campagneAdmCB)'
                        title="<?= Text::_('COM_GDA_CAMPAGNE_OPEN_TOOLTIP'); ?>" >
                            <span class="icon-unpublish"></span>
                    </a>
                <?php endif; ?>
            </td>

            <td>
                <a class="btn btn-primary btn-sm" type="button" name="rapport"
                        data-bs-id_campagne="campagne-<?= $item->id_campagne?>"
                        onclick='simpleCallAjax(<?= json_encode($data_rapport) ?>,campagneRapportCB,false)'
                        title="<?= $rapportTooltip; ?>" >
                    <?php if ($hasHelloAsso) : ?>
                        <?= HTMLHelper::_('image', FileHelper::getHelloAssoLogoSrc(), Text::_('COM_GDA_CAMPAGNE_HELLOASSO'), ['width' => '16', 'height' => '16']); ?>
                    <?php else : ?>
                        <span class="fa-solid fa-chart-simple"></span>
                    <?php endif; ?>
                </a>
            </td>

            <?php // Champs cachés utilisés par LstModal (form_modal.js) pour préremplir la modal d'édition. ?>
            <?php // Un <tr> ne peut contenir que des <td>/<th> : ces spans doivent rester dans un <td>, ?>
            <?php // sinon le navigateur les sort du tableau (foster parenting) et openModal() ne les trouve plus. ?>
            <td class="d-none">
                <span class="hidden" data-bs name="description"><?= $item->description; ?></span>
                <span class="hidden" data-bs name="id_article"><?= $item->id_article; ?></span>
                <span class="hidden" data-bs name="event_helloasso"><?= $item->event_helloasso; ?></span>
                <span class="hidden" data-bs name="id_type"><?= $item->id_type; ?></span>
                <span class="hidden" data-bs name="id_groupes"><?= $item->id_groupes;?></span>
                <span class="hidden" data-bs name="id_campagne"><?= $item->id_campagne; ?></span>
                <span class="hidden" data-bs name="active"><?= $item->active; ?></span>
                <span class="hidden" data-bs name="date_evenement"><?= $dateEvenement; ?></span>
                <span class="hidden" data-bs name="reservation_multiple"><?= (int) $item->reservation_multiple; ?></span>
                <span class="hidden" data-bs name="role_places"><?= htmlspecialchars(json_encode($item->role_places ?? []), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="hidden" data-bs name="modal-title"><?= Text::_('COM_GDA_CAMPAGNE_EDIT'); ?></span>
            </td>
        </tr>
