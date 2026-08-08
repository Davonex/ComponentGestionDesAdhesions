<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Database\DatabaseInterface;
// Gda
use NCB\Component\Gda\Site\Helper\CampagnesHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Service\SouscriptionService;


class CampagnesController extends BaseController
{

    //     public function execute($task)
    // {
    //     \Joomla\CMS\Factory::getApplication()->enqueueMessage('Controleur demandé : campagnes');
    // \Joomla\CMS\Factory::getApplication()->enqueueMessage('Task demandé : ' . $task);
    // \Joomla\CMS\Factory::getApplication()->enqueueMessage('Tâches connues : ' . implode(', ', array_keys($this->taskMap)));
    // return parent::execute($task);
    // }


    /**
     *   *   Ajax pour activer ou desactiver une camapgne dans la View admin
     */
    public function activer($key = null, $urlVar = null)
    {

        $Response = new JsonResponse();
        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app   = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');
            $form = $model->getForm(null, false);

            // Get data
            $dataForm = $app->input->getArray(array('jform_campagne' => 'ARRAY'));

            // name of array 'jform' must match 'control' => 'jform' line in the model code
            // $dataForm  = $this->input->post->get('jform_campagne', array(), 'array');

            if (!empty($dataForm['jform_campagne'])) {
                $app->setUserState('campagne.activer', $dataForm['jform_campagne']);
                /**
                 * A FAIRE !IMPORTANT
                 */
                // $validData = $model->validate($form, $data['jform_Profil']);
                // Verifier si une AUTRE campagne de type saison est deja ouverte, uniquement lors d'une ouverture
                // (fermer la campagne actuellement ouverte ne doit jamais être bloqué)

                $ouvreUneSaison = (int) $dataForm['jform_campagne']['active'] === 1
                    && (string) $dataForm['jform_campagne']['id_type'] === (string) ConfHelper::getValue('IdTypeSaison');

                if ($ouvreUneSaison) {
                    $saisonOuverte = $model->SaisonOuverte();
                    if ($saisonOuverte !== null && (int) $saisonOuverte->id_campagne !== (int) $dataForm['jform_campagne']['id_campagne']) {
                        throw new \Exception(Text::_('COM_GDA_CAMPAGNE_SAISON_ALLREADY_OPEN'), 501);
                    }
                }


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
     *   Ajax pour déclarer/retirer une campagne comme saison courante dans la View admin
     */
    public function declarerCourante($key = null, $urlVar = null)
    {

        $Response = new JsonResponse();
        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app   = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');

            // Get data
            $dataForm = $app->input->getArray(array('jform_campagne' => 'ARRAY'));

            if (!empty($dataForm['jform_campagne'])) {
                $app->setUserState('campagne.courante', $dataForm['jform_campagne']);

                if ((string) $dataForm['jform_campagne']['id_type'] !== (string) ConfHelper::getValue('IdTypeSaison')) {
                    throw new \Exception(Text::_('COM_GDA_CAMPAGNE_COURANTE_TYPE_INVALIDE'), 501);
                }

                $model->DeclarerCourante();
                $Response->success = true;

                $Campagne = $model->getCampagne($dataForm['jform_campagne']['id_campagne']);
                $Response->data =  base64_encode(
                    LayoutHelper::render('campagnes.row', [
                        'item' => $Campagne,
                        'task' => 'sauver'
                    ])
                );
                $Response->message = $Campagne->courante
                    ? Text::sprintf('COM_GDA_CAMPAGNE_COURANTE_DECLAREE', $Campagne->titre)
                    : Text::sprintf('COM_GDA_CAMPAGNE_COURANTE_RETIREE', $Campagne->titre);
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
     *  Ajax pour effacer une camapgne dans la View admin
     */
    public function effacer()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app   = Factory::getApplication();
        try {
            $this->checkToken();
            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');
            $form = $model->getForm(null, false);

            // Get data
            $dataForm = $app->input->getArray(array('jform_campagne' => 'ARRAY'));

            // name of array 'jform' must match 'control' => 'jform' line in the model code
            // $dataForm  = $this->input->post->get('jform_campagne', array(), 'array');

            if (!empty($dataForm['jform_campagne'])) {
                $app->setUserState('campagne.effacer', $dataForm['jform_campagne']);
                /**
                 * A FAIRE !IMPORTANT
                 */
                // $validData = $model->validate($form, $data['jform_Profil']);
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
        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app   = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');
            // $form = $model->getForm(null, false);

            // Get data
            $dataForm = $app->input->getArray(array('jform_campagne' => 'ARRAY'));

            // name of array 'jform' must match 'control' => 'jform' line in the model code
            // $dataForm  = $this->input->post->get('jform_campagne', array(), 'array');

            if (!empty($dataForm['jform_campagne'])) {
                $app->setUserState('campagne.sauver', $dataForm['jform_campagne']);

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
     *   Ajax pour souscrire a une camapgne dans la View accueil
     */

    public function souscrit()
    {

        /**
         *  Peut ajouter un control sur les droits ?
         */
        $Response = new JsonResponse();

        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app   = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');
            // $form = $model->getForm(null, false);
            // $input = $app->input;

            $user =  $this->app->getIdentity();
            // $levels = $user->getAuthorisedViewLevels();

            // Get data
            // $data = $this->input->getArray(array(
            //     'Itemid' => 'INT'
            // ));


            $dataForm = $app->getInput()->get('jform_souscription', array(), 'ARRAY');



            if (!empty($dataForm)) {
                // Appel au service de souscription (découplé de la session)
                $souscriptionService = new SouscriptionService(Factory::getContainer()->get(DatabaseInterface::class));
                $souscriptionService->souscrire($dataForm);
                $Response->success = true;
                
                $Response->message = Text::sprintf('COM_GDA_CAMPAGNE_SIGNIN', $dataForm['username']);
                //** Creer le code HTML a remplacer */
                /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $modelAccueil */
                $modelAccueil = $this->getModel('accueil', 'site');
                $Campagnes = $modelAccueil->getCampagnes($user);
                $Layout = LayoutHelper::render('accueil.dashboard_campagnes',   ['campagne' => $Campagnes, 'user' => $user]);
                $Response->data =  base64_encode($Layout);
                //$Response->data['jform_Profil'] = $data['jform_Profil'];
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
     *   Ajax pour desouscrire a une camapgne dans la View accueil
     */

    public function desouscrit()
    {

        /**
         *  Peut ajouter un control sur les droits ?
         */
        $Response = new JsonResponse();

        try {
            $this->checkToken();



            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app   = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');
            // $form = $model->getForm(null, false);
            // $input = $app->input;

            $user =  $this->app->getIdentity();
            // $levels = $user->getAuthorisedViewLevels();

           $dataForm = $app->getInput()->get('jform_souscription', array(), 'ARRAY');



            if (!empty($dataForm)) {
                // Appel au service de désouscription (découplé de la session)
                $souscriptionService = new SouscriptionService(Factory::getContainer()->get(DatabaseInterface::class));
                $souscriptionService->desouscrire($dataForm, $user->username);
                $Response->success = true;
                $Response->message = Text::sprintf('COM_GDA_CAMPAGNE_SIGNOUT', $dataForm['username']);
                
                //** Creer le code HTML a remplacer */
                /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $modelAccueil */
                $modelAccueil = $this->getModel('accueil', 'site');
                $Campagnes = $modelAccueil->getCampagnes($user);
                $Layout = LayoutHelper::render('accueil.dashboard_campagnes',   ['campagne' => $Campagnes, 'user' => $user]);
                $Response->data =  base64_encode($Layout);
                //$Response->data['jform_Profil'] = $data['jform_Profil'];
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
     *  Ajax pour generer le rapport d'une camapgne dans la View admin
     */
    public function rapport()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app   = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');

            // Get data
            $data = $app->input->getArray(array('jform_campagne' => 'ARRAY'));
            if (!empty($data['jform_campagne'])) {
                $app->setUserState('campagne.rapport', $data['jform_campagne']);
                if ($data['jform_campagne']['event_helloasso'] === "null") {
                    $data_rapport = $model->getRapport();
                    $Response->message = Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_MSG', count($data_rapport));
                } 
                else {
                    $data_rapport = $model->getRapportHelloAsso();
                    $Response->message = Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_MSG', count($data_rapport));
                }

                $Layout = LayoutHelper::render ('campagnes.rapport',['items' => $data_rapport ,'form' => $data['jform_campagne']] );
                $Response->data =  base64_encode($Layout);
                $Response->success = true;
                // $Response->data =  base64_encode(json_encode($data_rapport));    
                

            } else { 
                $Response->success = false;
            }


            echo $Response;
        } catch (\RuntimeException $e) {
            $response = new JsonResponse();
            $response->success = false;
            $response->message = 'HelloAsso refuse l’accès a cette ressource.';
            $response->data = null;
            echo $response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
      $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte  
    }


    /**
     * 
     */
    function getformDetailHelloAsso()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app   = Factory::getApplication();
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
                    'description'  => $dataForm['description']]));
                

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