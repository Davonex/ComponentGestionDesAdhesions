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
use NCB\Component\Gda\Site\Helper\ToolsHelper;


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
     * Les campagnes de type Saison sont exclusivement gérées par la vue Saisons (ouverture,
     * fermeture, saison courante). Rejette toute tentative de créer/modifier/supprimer une
     * campagne de ce type via cette vue — y compris un appel ajax direct, puisque le champ
     * "Type de campagne" du formulaire n'expose déjà plus Saison comme option sélectionnable
     * (voir models/fields/typedecampagne.php).
     */
    private function guardNonSaison($idType): void
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
                $hasHelloAsso = $data['jform_campagne']['event_helloasso'] !== "null";

                // Le rapport HelloAsso (paiements en ligne) sera traité dans un second temps :
                // on affiche un "à venir" pour ces campagnes plutôt que d'appeler getRapportHelloAsso().
                $data_rapport = $hasHelloAsso ? [] : $model->getRapport();
                $Response->message = $hasHelloAsso
                    ? Text::_('COM_GDA_CAMPAGNE_RAPPORT_HELLOASSO_COMINGSOON')
                    : Text::sprintf('COM_GDA_CAMPAGNE_RAPPORT_MSG', count($data_rapport));

                $Layout = LayoutHelper::render('campagnes.rapport', [
                    'items'        => $data_rapport,
                    'form'         => $data['jform_campagne'],
                    'hasHelloAsso' => $hasHelloAsso,
                ]);
                $Response->data =  base64_encode($Layout);
                $Response->success = true;

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
     *  Ajax pour le suivi des inscriptions d'une campagne (onglet "Suivi des inscriptions").
     *  Affiche le layout de suivi propre à la nature de la campagne (Formation pour l'instant,
     *  "à venir" pour les autres natures).
     */
    public function suivi()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $model */
            $model = $this->getModel('campagnes', 'site');

            $idCampagne = (int) $app->input->getInt('id_campagne');

            if ($idCampagne > 0) {
                $campagne = $model->getCampagne($idCampagne);
                $idTypeFormation = (int) ConfHelper::getValue('IdTypeFormation');

                if ((int) $campagne->id_type === $idTypeFormation) {
                    $inscrits = $model->getInscritsCampagne($idCampagne, $campagne->titre);
                    $Layout = LayoutHelper::render('campagnes.suivi_formation', ['inscrits' => $inscrits]);
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