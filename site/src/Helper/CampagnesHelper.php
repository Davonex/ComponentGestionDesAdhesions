<?php

// namespace My\Module\Hello\Site\Helper;
namespace NCB\Component\Gda\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

class CampagnesHelper
{




    /*
     * 
     * Functions statique pour la partie souscription a une campagne 
     * 
     */
    public static function showCardCamapgne($uneCampagne, $user)
    {

        $extra = '';
        // Id de la campagne de type saison 
        $saison = 1;
        if ($uneCampagne === null) {
            return false;
        }
        $placeDispo = $uneCampagne->nbr_place - $uneCampagne->nbr_souscriptions;
        if ($uneCampagne->nbr_place !== 0 and  $placeDispo <= 0) {
            $extra = 'campagne-pleine';
        } else {
            if ($uneCampagne->type_class != "") {
                $extra = $uneCampagne->type_class;
            } else {
                $extra = 'campagne-default';
            }
        }


        $result = '<div id="id_' . $uneCampagne->id_campagne . '" class="mt-2 d-flex col-md-6 col-sm-12  ' . $extra . '" >';
        $result .= '<div class="card h-100 w-100">';
        $result .= '<div class="card-header">';
        $result .= '<h4><p class="pt-2 float-start" data-bs name="type_name"> ' . $uneCampagne->type_name . '</p></h4>';
        if ($uneCampagne->nbr_place !== 0 and  $placeDispo <= 0) {
            $result .= '<p class="pt-2 float-end">Campagne <span class="badge rounded-pill bg-danger">' . Text::_('COM_GDA_CAMPAGNE_PLEINE') . '</span></p>';
        } else {
            $result .= '<p class="pt-2 float-end">Jusqu\'au <span class="badge rounded-pill bg-warning">'
                . HTMLHelper::_('date', $uneCampagne->date_fin, 'd M y')
                . '</span></p>';
        }
        $result .= '</div>'; // class="card-header"
        $result .= '<div class="row g-0">';
        $result .= '<div class="col-md-4">'; //colonne de l'image
        $result .= '<img name="SrcImg" src="' .
            ConfHelper::getImageSrc("CampagneImage", $uneCampagne->type_image)
            . '" class="img-thumbnail rounded float-end" alt="photo">';
        $result .= '</div>'; // class="col-md-5"
        $result .= '<div class="col-md-8">'; // colonne du texte
        $result .= '<div class="card-body limited-text">';
        $result .= '<h4 data-bs name="titre" class="card-title">' . $uneCampagne->titre . '</h4>';
        $result .= '<p data-bs name="description"  class="card-text">' . $uneCampagne->description . '</p>';
        // $result .= $this->spanModal('id_profil',$profil->id_profil,"position-absolute invisible");  
        $result .= '<span class="position-absolute invisible" data-bs name="id_campagne">' . $uneCampagne->id_campagne . '</span>';
        $result .= '<span class="position-absolute invisible" data-bs name="licence">' . $user->username . '</span>';
        $result .= '<span class="position-absolute invisible" data-bs name="id_profil">' . $user->id . '</span>';
        // username
        $result .= '</div>'; // class="card-body"
        $result .= '</div>'; // class="col-md-7"
        $result .= '</div>'; // class="row g-0"
        $result .= '<div class="card-footer text-muted h-100">';
        if ($uneCampagne->id_type ===  $saison) { // Type saison
            if ($uneCampagne->deja_inscrit !== 1) {
                $result .= self::buttonSouscrire($uneCampagne);
            } else {
                $result .= '<span class="badge rounded-pill bg-success float-end mt-2">' . Text::_('COM_GDA_CAMPAGNE_DEJA_INSCRIT') . '</span>';
            }
        } elseif ($uneCampagne->deja_inscrit === 1) {
            // Deja inscrit à la campagne on affiche le message COM_GDA_CAMPAGNE_DEJA_INSCRIT
            $result .= '<span class="badge rounded-pill bg-success float-end mt-2">' . Text::_('COM_GDA_CAMPAGNE_DEJA_INSCRIT') . '</span>';
            // Si ce n'est pas une campagne de type saison on affiche le bouton de désinscription
            $result .= self::buttonDeSouscrire($uneCampagne);
        } elseif ($uneCampagne->nbr_place !==  0  and  $placeDispo > 0) {
            // Si il y a des places dispoponible   
            $result .= '<small class="text-muted float-start mt-2">' . $placeDispo . ' place(s) de dispo</small>';
            $result .= self::buttonSouscrire($uneCampagne);
        } else {
            $result .= '<small class="text-muted float-start mt-2">' . Text::_('COM_GDA_CAMPAGNE_PLEINE') . '</small>';
        }
        $result .= '</div>'; // class="card-footer"
        $result .= '</div>'; // class="card"
        $result .= '</div>'; //  class="col"



        return $result;
    }


    private static function buttonSouscrire($uneCampagne)
    {

        $result = '<a href="#"              
            class="btn btn-success float-end"
            type="button" 
            id="openForm" 
            data-bs-id_campagne="id_' . $uneCampagne->id_campagne . '"
            data-bs-toggle="modal" 
            data-bs-target="#modalSignIn" 
            data-toggle="tooltip" 
            data-placement="top" 
            title="' . Text::_('COM_GDA_CAMPAGNE_SOUSCRIT_TOOLTIP') . '">';
        $result .= '' . Text::_('COM_GDA_CAMPAGNE_SOUSCRIT');
        $result .= '</a>';

        return $result;
    }

    private static function buttonDeSouscrire($uneCampagne)
    {

        $result = '<a href="#"              
            class="btn btn-warning float-start"
            type="button" 
            id="openForm" 
            data-bs-id_campagne="id_' . $uneCampagne->id_campagne . '"
            data-bs-toggle="modal" 
            data-bs-target="#modalSignOut" 
            data-toggle="tooltip" 
            data-placement="top" 
            title="' . Text::_('COM_GDA_CAMPAGNE_DESOUSCRIT_TOOLTIP') . '">';
        $result .= '' . Text::_('COM_GDA_CAMPAGNE_DESOUSCRIT');
        $result .= '</a>';

        return $result;
    }


    public static function showModalSouscription()
    {
        $result = '<div class="modal fade" id="modalSignIn" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">';
        $result .= '<div class="modal-dialog modal-lg">';
        $result .= '<div class="modal-content">';
        $result .= '<div class="modal-header">'; // class="modal-header"
        $result .= '<h4  data-bs class="modal-title" id="jform_campagne_type_name" id="modalLabel"></h4>';
        $result .= '<button type="button" id="closeSignIn" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
        $result .= '</div>'; // class="modal-header"
        $result .= '<div class="modal-body">';  // class="modal-body"
        $result .= CampagnesHelper::modalForm("souscrit");
        $result .= '</div>'; // class="modal-body"
        $result .= '<div class="modal-footer">';
        $result .= '	<button id="submitSignIn" type="button" class="btn btn-success float-end" >';
        $result .= '<i class="fa-solid fa-check me-2"></i>' . Text::_('COM_GDA_CAMPAGNE_SOUSCRIT');
        $result .= '</button>';
        // $result .= '<button type="submit" form="formSouscription" class="btn btn-success">'.Text::_('COM_GDA_CAMPAGNE_SOUSCRIT').'</button>';
        $result .= '</div>'; // class="modal-footer"
        $result .= '</div>'; // class="modal-content"
        $result .= '</div>'; // class="modal-dialog modal-lg"
        $result .= '</div>'; //  id="myModal"

        // $result .= CampagnesHelper::codeJs();


        return $result;
    }


    public static function showModalDeSouscription()
    {
        $result = '<div class="modal fade" id="modalSignOut" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">';
        $result .= '<div class="modal-dialog modal-lg">';
        $result .= '<div class="modal-content">';
        $result .= '<div class="modal-header">'; // class="modal-header"
        $result .= '<h4  data-bs class="modal-title" id="jform_campagne_type_name" name="jform_campagne[type_name]" id="modalLabel"></h4>';
        $result .= '<button type="button" id="closeSignOut" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
        $result .= '</div>'; // class="modal-header"
        $result .= '<div class="modal-body">';  // class="modal-body"
        $result .= CampagnesHelper::modalForm("desouscrit");
        $result .= '</div>'; // class="modal-body"
        $result .= '<div class="modal-footer">';
        $result .= '	<button id="submitSignOut" type="button" class="btn btn-warning float-start" >';
        $result .= '<i class="fa-solid fa-ban me-2"></i>' . Text::_('COM_GDA_CAMPAGNE_DESOUSCRIT');
        $result .= '</button>';
        $result .= '</div>'; // class="modal-footer"
        $result .= '</div>'; // class="modal-content"
        $result .= '</div>'; // class="modal-dialog modal-lg"
        $result .= '</div>'; //  id="myModal"

        // $result .= CampagnesHelper::codeJs();


        return $result;
    }


    /**
     *  Formulaire Modale
     *
     * 
     */

    public static function modalForm($task)
    {
        $result = '<form 
                    method="post" 
                    name="form_' . $task . '" 
                    id="form_' . $task . '" 
                    enctype="multipart/form-data">';
        $result .= '<h4 data-bs  id="jform_campagne_titre" class="card-title"></h4> ';
        $result .= '<p data-bs id="jform_campagne_description" name="jform_campagne[description]"  class="card-text"></p>';
        $result .= '<input type="hidden" name="task" value="campagnes.' . $task . '" />';
        $result .= '<input data-bs type="hidden" id="jform_campagne_id_campagne" name="jform_campagne[id_campagne]" value="" />';
        $result .= '<input data-bs type="hidden" id="jform_campagne_id_profil" name="jform_campagne[id_profil]" value="" />';
        // Ligne pour ajouter l'ID de menu, mais pas besoin pour l'ajax

        $result .= '<input data-bs type="hidden" id="jform_campagne_licence" name="jform_campagne[licence]" value="" />';
        $result .= HtmlHelper::_('form.token');

        $result .= '</form>';

        return $result;
    }
}
