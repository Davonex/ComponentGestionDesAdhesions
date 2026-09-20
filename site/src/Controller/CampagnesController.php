<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
// Gda
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Model\CampagnesModel;
use NCB\Component\Gda\Site\Service\ReservationService;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;


class CampagnesController extends AjaxController
{

    //     public function execute($task)
    // {
    //     \Joomla\CMS\Factory::getApplication()->enqueueMessage('Controleur demandé : campagnes');
    // \Joomla\CMS\Factory::getApplication()->enqueueMessage('Task demandé : ' . $task);
    // \Joomla\CMS\Factory::getApplication()->enqueueMessage('Tâches connues : ' . implode(', ', array_keys($this->taskMap)));
    // return parent::execute($task);
    // }

    /**
     * Vérifie que l'utilisateur connecté peut gérer les campagnes (Bureau ou Responsable de
     * Groupe, même condition que View/Campagnes/HtmlView.php::display()) : contrairement à
     * l'affichage de la vue, les tâches ajax de ce contrôleur n'étaient jusqu'ici protégées par
     * aucun contrôle d'accès (seul le jeton CSRF), permettant à n'importe quel adhérent connecté
     * de créer/modifier/supprimer une campagne ou de consulter le suivi des inscriptions.
     *
     * @return void
     * @throws \RuntimeException Si l'utilisateur connecté n'est ni Bureau ni Responsable de Groupe.
     */
    private function guardGestionnaireCampagnes(): void
    {
        if (!UsersHelper::isBureauMember() && !UsersHelper::isResponsableGroupe()) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Les campagnes de type Saison sont exclusivement gérées par la vue Saisons (ouverture,
     * fermeture, saison courante). Rejette toute tentative de créer/modifier/supprimer une
     * campagne de ce type via cette vue — y compris un appel ajax direct, puisque le champ
     * "Type de campagne" du formulaire n'expose déjà plus Saison comme option sélectionnable
     * (voir models/fields/typedecampagne.php).
     * @param string|int $idType L'identifiant du type de campagne à vérifier.
     * @throws \Exception Si le type de campagne est Saison.
     */
    private function guardNonSaison(string $idType): void
    {
        if ((string) $idType === (string) ConfHelper::getValue('IdTypeSaison')) {
            throw new \Exception(Text::_('COM_GDA_CAMPAGNE_TYPE_SAISON_EXCLU'), 501);
        }
    }

    /**
     *   *   Ajax pour activer ou desactiver une camapgne dans la View admin
     */
    public function activer($key = null, $urlVar = null)
    {

        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app   = Factory::getApplication();
        try {
            $this->checkToken();
            $this->guardGestionnaireCampagnes();

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');
            $form = $model->getForm(null, false);

            // Get data
            $dataForm = $app->input->getArray(array('jform_campagne' => 'ARRAY'));

            // name of array 'jform' must match 'control' => 'jform' line in the model code
            // $dataForm  = $this->input->post->get('jform_campagne', array(), 'array');

            if (!empty($dataForm['jform_campagne'])) {
                $app->setUserState('campagne.activer', $dataForm['jform_campagne']);
                $this->guardNonSaison($dataForm['jform_campagne']['id_type']);

                $model->Activer();
                // $model->saveUser();
                $Response->success = true;

                //$dataForm['jform_campagne']['titre']. ": est dé-inscrit à la campagne";
                //** Creer le code HTML a remplacer */

                $Campagne = $model->getCampagne($dataForm['jform_campagne']['id_campagne']);
                $Response->data =  base64_encode(
                    LayoutHelper::render('campagnes.row', [
                        'item' => $Campagne,
                        'task' => 'sauver'
                    ])
                );
                $Response->message = $Campagne->active ? Text::sprintf('COM_GDA_CAMPAGNE_OPENED', $Campagne->titre) : Text::sprintf('COM_GDA_CAMPAGNE_CLOSED', $Campagne->titre);
                //$Response->data['jform_Profil'] = $data['jform_Profil'];
            } else {
                $Response->success = false;
            }


            // Redirect back to the form in all cases
            // $this->setRedirect(Route::_('index.php?option=com_gdadhesions&view=campagnes&Itemid=' .$data['Itemid']) );
            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte
    }


    /**
     *  Ajax pour effacer une camapgne dans la View admin
     */
    public function effacer()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app   = Factory::getApplication();
        try {
            $this->checkToken();
            $this->guardGestionnaireCampagnes();
            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');
            $form = $model->getForm(null, false);

            // Get data
            $dataForm = $app->input->getArray(array('jform_campagne' => 'ARRAY'));

            // name of array 'jform' must match 'control' => 'jform' line in the model code
            // $dataForm  = $this->input->post->get('jform_campagne', array(), 'array');

            if (!empty($dataForm['jform_campagne'])) {
                $app->setUserState('campagne.effacer', $dataForm['jform_campagne']);
                $this->guardNonSaison($dataForm['jform_campagne']['id_type']);

                $model->Effacer();
                // $model->saveUser();
                $Response->success = true;

                //$dataForm['jform_campagne']['titre']. ": est dé-inscrit à la campagne";
                //** Creer le code HTML a remplacer */

                // $Campagne = $model->getCampagne($dataForm['jform_campagne']['id_campagne']);
                $Response->data =  base64_encode(
                    json_encode(['id_campagne' => $dataForm['jform_campagne']['id_campagne']])
                );
                $Response->message = Text::sprintf('COM_GDA_CAMPAGNE_REMOVED', $dataForm['jform_campagne']['titre']);
            } else {
                $Response->success = false;
            }


            // Redirect back to the form in all cases
            // $this->setRedirect(Route::_('index.php?option=com_gdadhesions&view=campagnes&Itemid=' .$data['Itemid']) );
            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte
    }

    // public function new() {
    //  // store this in the session so that
    //     $data = $this->input->getArray(array(
    //         'id' => 'INT',
    //         'Itemid' => 'INT'
    //     ));

    //     if (!empty($data['id']) OR !empty($data['Itemid']))
    //     {
    //         $this->app->setUserState('campagnes.new', $data);
    //         $document   = $this->app->getDocument();
    //         $viewType   = $document->getType();
    //         $viewName   = $this->input->get('view', $this->default_view);
    //         $model = $this->getModel('campagnes','site');
    //         $viewLayout = $this->input->get('layout', 'default', 'string');
    //         $view = $this->getView($viewName, $viewType);
    //         $view->setLayout($viewLayout);
    //         $view->setModel($model, true);
    //         $view->display();
    //     }    
    // }


    /**
     *   Ajax pour sauver  a une camapgne dans la View accueil
     */

    public function sauver($key = null, $urlVar = null)
    {

        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app   = Factory::getApplication();
        try {
            $this->checkToken();
            $this->guardGestionnaireCampagnes();

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');
            // $form = $model->getForm(null, false);

            // Get data
            $dataForm = $app->input->getArray(array('jform_campagne' => 'ARRAY'));

            // name of array 'jform' must match 'control' => 'jform' line in the model code
            // $dataForm  = $this->input->post->get('jform_campagne', array(), 'array');

            if (!empty($dataForm['jform_campagne'])) {
                $app->setUserState('campagne.sauver', $dataForm['jform_campagne']);
                $this->guardNonSaison($dataForm['jform_campagne']['id_type']);

                // $validData = $model->validate($form, $data['jform_Profil']);
                if ($dataForm['jform_campagne']['titre'] === "") {
                    throw new \Exception("Formulaire non conforme", 500);
                }


                $return_id_campagne = $model->Sauver();
                // $model->saveUser();
                $Response->success = true;
                $Response->message = Text::sprintf('COM_GDA_CAMPAGNE_SAVED', $dataForm['jform_campagne']['titre']);
                //$dataForm['jform_campagne']['titre']. ": est dé-inscrit à la campagne";
                //** Creer le code HTML a remplacer */

                $Campagne = $model->getCampagne($return_id_campagne);
                $Response->data =  base64_encode(
                    LayoutHelper::render('campagnes.row', [
                        'item' => $Campagne,
                        'task' => $this->doTask
                    ])
                );
                //$Response->data['jform_Profil'] = $data['jform_Profil'];
            } else {
                $Response->success = false;
            }


            // Redirect back to the form in all cases
            // $this->setRedirect(Route::_('index.php?option=com_gdadhesions&view=campagnes&Itemid=' .$data['Itemid']) );
            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte
    }

    /**
     *  Ajax pour generer le rapport d'une camapgne dans la View admin.
     *  Deux rapports distincts partagent cette task, distingués par 'jform_campagne[rapport_type]'
     *  (posté par layouts/campagnes/row.php, deux boutons séparés) : "reservation" (table
     *  #__gda_reservation*, cf. CampagnesModel::getRapport()) et "helloasso" (paiements en ligne).
     *  Le rapport "helloasso" se scinde lui-même selon la nature de la campagne : la structure des
     *  items renvoyés par l'API HelloAsso pour un formulaire "Shop" (Boutique, pas d'inscrit, un
     *  produit acheté) diffère de celle d'un formulaire "Event" (Formation/Loisir, un adhérent
     *  inscrit) - d'où getRapportHelloAssoBoutique() en plus de getRapportHelloAsso(). L'UI
     *  désactive déjà chaque bouton quand son rapport n'a pas de sens (Boutique pour "reservation",
     *  pas d'event HelloAsso pour "helloasso"), donc aucune garde supplémentaire ici : un appel
     *  invalide retombe sur l'exception déjà levée par les deux méthodes ou une liste vide pour getRapport().
     */
    public function rapport()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app   = Factory::getApplication();
        try {
            $this->checkToken();
            $this->guardGestionnaireCampagnes();

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');

            // Get data
            $data = $app->input->getArray(array('jform_campagne' => 'ARRAY'));
            if (!empty($data['jform_campagne'])) {
                $app->setUserState('campagne.rapport', $data['jform_campagne']);
                $hasHelloAsso = CampagnesModel::aUnLienHelloAsso($data['jform_campagne']['event_helloasso'] ?? null);
                $rapportType = $data['jform_campagne']['rapport_type'] ?? 'reservation';
                $isBoutique = ((int) ($data['jform_campagne']['id_type'] ?? 0)) === (int) ConfHelper::getValue('IdTypeBoutique');

                if ($rapportType === 'helloasso') {
                    $data_rapport = $isBoutique ? $model->getRapportHelloAssoBoutique() : $model->getRapportHelloAsso();
                    $Response->message = empty($data_rapport)
                        ? Text::_('COM_GDA_CAMPAGNE_RAPPORT_HELLOASSO_AUCUN_PAIEMENT')
                        : Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_HELLOASSO_MSG', count($data_rapport));
                } else {
                    $data_rapport = $model->getRapport();
                    // Les désistements de l'adhérent figurent dans le rapport mais ne comptent pas comme inscrits.
                    $nbInscrits = count(array_filter(
                        $data_rapport,
                        static fn ($ligne) => ($ligne['statut'] ?? '') !== ReservationService::STATUT_ANNULEE
                    ));
                    $Response->message = $nbInscrits === 0
                        ? Text::_('COM_GDA_CAMPAGNE_RAPPORT_AUCUN_INSCRIT')
                        : Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_MSG', $nbInscrits);
                }

                $Layout = LayoutHelper::render('campagnes.rapport', [
                    'items'        => $data_rapport,
                    'form'         => $data['jform_campagne'],
                    'rapportType'  => $rapportType,
                    'isBoutique'   => $isBoutique,
                    'hasHelloAsso' => $hasHelloAsso,
                ]);
                $Response->data =  base64_encode($Layout);
                $Response->success = true;
            } else {
                $Response->success = false;
            }


            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte
    }


    /**
     *  Ajax pour le suivi des inscriptions d'une campagne (onglet "Suivi des inscriptions").
     *  Affiche le layout de suivi (Formation et Loisir, les deux seules natures hors Saison à
     *  suivre des réservations), "à venir" pour toute future nature qui n'en aurait pas encore.
     */
    public function suivi()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        try {
            $this->checkToken();
            $this->guardGestionnaireCampagnes();

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');

            $idCampagne = (int) $app->input->getInt('id_campagne');

            if ($idCampagne > 0) {
                $campagne        = $model->getCampagne($idCampagne);
                $idTypeFormation = (int) ConfHelper::getValue('IdTypeFormation');
                $idTypeLoisir    = (int) ConfHelper::getValue('IdTypeLoisir');

                if (in_array((int) $campagne->id_type, [$idTypeFormation, $idTypeLoisir], true)) {
                    $inscrits = $model->getInscritsCampagne($idCampagne, $campagne->titre);
                    $Layout = LayoutHelper::render('campagnes.suivi_inscrits', ['inscrits' => $inscrits]);
                } else {
                    $Layout = LayoutHelper::render('campagnes.suivi_comingsoon', ['type_name' => $campagne->type_name]);
                }

                $Response->success = true;
                $Response->data = base64_encode($Layout);
            } else {
                $Response->success = false;
            }

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        $app->close();  // stoppe l'exécution pour que seule la réponse JSON parte
    }

    /**
     * Ajax de l'onglet "Récapitulatif" : matrice adhérents x campagnes Formation (dernier statut).
     *
     * @return void
     */
    public function recapitulatif()
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        try {
            $this->checkToken();
            $this->guardGestionnaireCampagnes();

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');

            $Response = new JsonResponse();
            $Response->success = true;
            $Response->data = base64_encode(LayoutHelper::render('campagnes.recapitulatif', $model->getRecapitulatifFormations()));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        $app->close();
    }

    /**
     * Ajax pour changer le statut d'une inscription depuis l'onglet "Suivi des inscriptions" :
     * décision du responsable de campagne (valider / refuser une inscription en attente, ou
     * revenir en arrière). Ne renvoie pas de HTML : le statut n'est qu'un des attributs de la
     * ligne (badge + libellé), le client met à jour ces deux éléments directement plutôt que de
     * recharger tout l'onglet.
     */
    public function changerStatutInscription()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        try {
            $this->checkToken();
            $this->guardGestionnaireCampagnes();

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');

            $idPlace = $app->input->getInt('id_place', 0);
            $statut  = $app->input->getString('statut', '');

            if ($idPlace <= 0) {
                throw new \Exception(Text::_('COM_GDA_CAMPAGNES_SUIVI_STATUT_INTROUVABLE'), 404);
            }

            $statutPrecedent = $model->changerStatutInscription($idPlace, $statut);

            $cleMessage = 'COM_GDA_CAMPAGNES_SUIVI_STATUT_UPDATED';

            // Mail « inscription validée » seulement à l'ENTRÉE en confirmée : un simple
            // ré-enregistrement de « confirmée » ne prévient pas une seconde fois l'adhérent. Un
            // échec d'envoi ne remet pas en cause la décision déjà enregistrée : il est journalisé
            // et signalé au responsable.
            if ($statut === ReservationService::STATUT_CONFIRMEE && $statutPrecedent !== ReservationService::STATUT_CONFIRMEE) {
                try {
                    $cleMessage = $model->notifierInscriptionAcceptee($idPlace)
                        ? 'COM_GDA_CAMPAGNES_SUIVI_STATUT_UPDATED_MAIL_OK'
                        : 'COM_GDA_CAMPAGNES_SUIVI_STATUT_UPDATED_MAIL_KO';
                } catch (\Throwable $e) {
                    GdaLogger::error('Notification inscription validée impossible (id_place=' . $idPlace . ') : ' . $e->getMessage());
                    $cleMessage = 'COM_GDA_CAMPAGNES_SUIVI_STATUT_UPDATED_MAIL_KO';
                }
            }

            $Response->success = true;
            $Response->message = Text::_($cleMessage);
            $Response->data = ['statut' => $statut];

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        $app->close();  // stoppe l'exécution pour que seule la réponse JSON parte
    }

    /**
     *
     */
    function getformDetailHelloAsso()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app   = Factory::getApplication();
        try {
            $this->checkToken();
            $this->guardGestionnaireCampagnes();

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');

            // Get data from imput slug and type of form helloasso
            // recupere formSlug si il existe dans l'input
            $formSlug = $app->input->get('formSlug') ?? null;
            $formType = $app->input->get('formType') ?? null;

            if ($formSlug && $formType) {
                $service = new \NCB\Component\Gda\Site\Service\HelloAssoService();
                $service->getAccessToken();
                $dataForm = $service->getFormsPublic($formType, $formSlug);



                $Response->success = true;
                $Response->data =  base64_encode(json_encode(
                    [
                        // 'startDate'   => ToolsHelper::isoToFrDate($dataForm['startDate']),
                        // 'endDate'      => ToolsHelper::isoToFrDate($dataForm['endDate']),
                        'title'        => $dataForm['title'],
                        'description'  => $dataForm['description']
                    ]
                ));
            } else {
                $Response->success = false;
            }

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte  
    }
}
