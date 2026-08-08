<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;

/**
 * @var array $displayData
 * - $displayData['campagne'] : campagne
 * - $displayData['task']  : Controler joomla task
 */

$item = $displayData['item'];
$task  = $displayData['task'];


$classes = [
    '1'   => 'table-active',
    '0'   => 'table-inactive',
];

$active_bool = $item->active ? '0' : '1';

$data_active = [
        'jform_campagne[id_campagne]' => $item->id_campagne,
        'jform_campagne[active]' => $item->active ? '0' : '1',
        'jform_campagne[id_type]' => $item->id_type,
        'task' => "campagnes.activer"
    ];

$data_courante = [
        'jform_campagne[id_campagne]' => $item->id_campagne,
        'jform_campagne[courante]' => $item->courante ? '0' : '1',
        'jform_campagne[id_type]' => $item->id_type,
        'task' => "campagnes.declarerCourante"
    ];

$id_type_saison = (int) ConfHelper::getValue('IdTypeSaison');

$data_remove = [
        'jform_campagne[titre]' => $item->titre,
        'jform_campagne[id_campagne]' => $item->id_campagne,
        'task' => "campagnes.effacer"
    ];

$data_rapport = [
        'jform_campagne[id_campagne]' => $item->id_campagne,
        'jform_campagne[titre]' => $item->titre,
        'jform_campagne[event_helloasso]' => $item->event_helloasso,
        'task' => "campagnes.rapport"
    ];

?>



 <tr class="<?= $classes[$item->active] ?>" id="campagne-<?= $item->id_campagne; ?>">
            <td>
               
                <?php // Afficher le buttom editer  ?>    
                <a class="btn btn-success btn-sm" type="button" name="edition"
                        data-toggle="tooltip" data-placement="top"
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
                        data-toggle="tooltip" data-placement="top"
                        title="<?= Text::_('COM_GDA_CAMPAGNE_REMOVE_TOOLTIP'); ?>" 
                        onclick='simpleCallAjax(<?=json_encode($data_remove)?>,campagneAdmRemoveCB)'>
                    <span class="fa-solid fa-trash"></span>        
                    </a>
                <?php endif; ?>
                </td>
            
            <td><span data-bs name="titre"><?= $item->titre; ?></span></td>    
            <td><span data-bs name="description"><?= $item->description; ?></span></td>  
            <td><span ><?= $item->type_name; ?></span></td> 
            <td><span data-bs name="date_debut"><?= $item->date_debut;?></span></td>
            <td><span data-bs name="date_fin"><?= $item->date_fin;?></span></td>
           
            <td><?php if  ($item->nbr_place === 0) : ?>
                    <?= Text::_('COM_GDA_CAMPAGNE_NO_LIMIT'); ?>
                <?php else : ?>
                    <?= $item->nbr_place; ?>
                <?php endif; ?>  
            <td> <span data-bs name="id_article"><?= $item->id_article; ?></span></td>
            <td>
                <?php if  ($item->active) : ?> 
                    <a class="btn btn-success btn-sm" type="button" name="activer" 
                        data-toggle="tooltip" 
                        data-placement="top"
                        onclick='simpleCallAjax(<?= json_encode($data_active) ?>,campagneAdmCB)'
                        title="<?= Text::_('COM_GDA_CAMPAGNE_CLOSE_TOOLTIP'); ?>" 
                    >
                            <span class="icon-icon-color-featured fa-door-open"></span>      
                    </a>
                <?php else : ?>
                    <a class="btn btn-dark btn-sm" type="button"
                        data-toggle="tooltip" data-placement="top"
                       onclick='simpleCallAjax(<?= json_encode($data_active) ?>,campagneAdmCB)'
                        title="<?= Text::_('COM_GDA_CAMPAGNE_OPEN_TOOLTIP'); ?>" >     
                            <span class="icon-unpublish"></span> 
                    </a>
                <?php endif; ?>
            <span class="hidden" data-bs name="id_article"><?= $item->id_article; ?></span>
            <span class="hidden" data-bs name="event_helloasso"><?= $item->event_helloasso; ?></span>
            <span class="hidden" data-bs name="id_type"><?= $item->id_type; ?></span>
            <span class="hidden" data-bs name="id_groupes"><?= $item->id_groupes;?></span>
            <span class="hidden" data-bs name="id_campagne"><?= $item->id_campagne; ?></span>
            <span class="hidden" data-bs name="active"><?= $item->active; ?></span>
            <span class="hidden" data-bs name="courante"><?= $item->courante; ?></span>
             <span class="hidden" data-bs name="nbr_place"><?= $item->nbr_place; ?></span>
            <span class="hidden" data-bs name="modal-title"><?= Text::_('COM_GDA_CAMPAGNE_EDIT'); ?></span>
            </td>
            <td>
                <?php if ($id_type_saison === (int) $item->id_type) : ?>
                    <?php if ($item->courante) : ?>
                        <a class="btn btn-warning btn-sm" type="button" name="courante"
                            data-toggle="tooltip" data-placement="top"
                            onclick='simpleCallAjax(<?= json_encode($data_courante) ?>,campagneAdmCB)'
                            title="<?= Text::_('COM_GDA_CAMPAGNE_COURANTE_RETIRER_TOOLTIP'); ?>"
                        >
                                <span class="fa-solid fa-star"></span>
                        </a>
                    <?php else : ?>
                        <a class="btn btn-outline-secondary btn-sm" type="button" name="courante"
                            data-toggle="tooltip" data-placement="top"
                            onclick='simpleCallAjax(<?= json_encode($data_courante) ?>,campagneAdmCB)'
                            title="<?= Text::_('COM_GDA_CAMPAGNE_COURANTE_DECLARER_TOOLTIP'); ?>"
                        >
                                <span class="fa-regular fa-star"></span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
            <td>
                    <?php
                    /* test ig evenet_helloasso n'est pas null alors afficher le logo helloasso avec un lien vers le formulaire helloasso */
                    if ($item->event_helloasso !== null AND $item->event_helloasso !== "null") {
                        $form= json_decode($item->event_helloasso, true);
                        $img = HTMLHelper::_('image', 'https://api.helloasso.com/v5/img/logo-ha.svg', Text::_('COM_GDA_CAMPAGNE_HELLOASSO'), ['width' => '20', 'height' => '20']);
                        echo HTMLHelper::_('link', $form['url'], $img, ['target' => '_blank']);
                    } 
                    ?>

            </td>
            <td>
                <a class="btn btn-primary btn-sm" type="button" name="rapport"
                        data-toggle="tooltip" data-placement="top"
                        data-bs-id_campagne="campagne-<?= $item->id_campagne?>"
                        onclick='simpleCallAjax(<?= json_encode($data_rapport) ?>,campagneRapportCB,false)'
                        title="<?= Text::_('COM_GDA_CAMPAGNE_RAPPORT_TOOLTIP'); ?>" >
                    <!-- <span class="fa-solid fa-pencil"></span>         -->
                    <span class="fa-solid fa-chart-simple"></span> Rapport
                </a>
            </td>
           
        </tr>