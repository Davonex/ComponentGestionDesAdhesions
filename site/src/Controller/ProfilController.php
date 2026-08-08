<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;


class ProfilController extends BaseController
{

    //     public function execute($task)
    // {
    //     JLog::add('Tâche demandée : ' . $task, JLog::INFO, 'com_gdadhesions');
    //     parent::execute($task); // Appelle la méthode de la classe parente
    // }

    //     public function execute($task)
    // {
    //      \Joomla\CMS\Factory::getApplication()->enqueueMessage('Controleur demandé : profil');
    // \Joomla\CMS\Factory::getApplication()->enqueueMessage('Task demandé : ' . $task);
    // \Joomla\CMS\Factory::getApplication()->enqueueMessage('Tâches connues : ' . implode(', ', array_keys($this->taskMap)));

    // return parent::execute($task);
    // }

    public function save()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            // store this in the session so that
            // Initialisation

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\ProfilModel $model */
            $model = $this->getModel('Profil', 'site');
            // $form = $model->getForm(null, false);
            $input = $app->input;

            //$data = $input->getArray(); // Toutes les données POST ou JSON

            $data = $input->getArray(array(
                'jform_Profil' => 'ARRAY'
            ));
            if (isset($input->files->get("jform_Profil")['upload'])) {
                $file = $input->files->get("jform_Profil")['upload'];
            } else {
                $file = null;
            }

            $app->setUserState('profil.save', $data['jform_Profil']);
            $app->setUserState('profil.file', $file);



            // check if date and ID are correct.
            $model->isCheckProfil();

            if (!\is_null($file)) {
                // Gestion de l'upload image 
                $model->UploadImage();
                $Response->success = true;
                //$Response->data['file'] = $file;
            }

            if (!empty($data['jform_Profil'])) {
                // $validData = $model->validate($form, $data['jform_Profil']);
                $model->saveProfil();

                $model->saveUser();
                $Response->success = true;
                //$Response->data['jform_Profil'] = $data['jform_Profil'];
            } else {
                $Response->success = false;
            }

            echo $Response;
            // Factory::getApplication()->close(); // Arrête l'exécution
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }

    /**
     * Sauvegarde la mise à jour du CACI (fichier + date de validité) depuis la modale dédiée.
     */
    public function saveCaci()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\ProfilModel $model */
            $model = $this->getModel('Profil', 'site');
            $input = $app->input;

            $data = $input->getArray(array(
                'jform_Caci' => 'ARRAY'
            ));

            if (empty($data['jform_Caci']['id_profil'])) {
                throw new \Exception('id_profil vide');
            }

            if (isset($input->files->get("jform_Caci")['upload.caci'])) {
                $file = $input->files->get("jform_Caci")['upload.caci'];
            } else {
                $file = null;
            }

            $app->setUserState('profil.caci.save', $data['jform_Caci']);
            $app->setUserState('profil.caci.file', $file);

            if (!\is_null($file) && $file['tmp_name'] !== '') {
                $model->UploadCaci();
            }

            $model->saveCaci();

            // Reconstruit un objet profil léger (sans nouvelle requête) pour re-rendre la carte CACI à jour
            $data = $app->getUserState('profil.caci.save');
            $updatedProfil = (object) array(
                'id_profil' => (int) $data['id_profil'],
                'caci' => $data['caci'] ?? null,
                'date_caci' => !empty($data['date_caci']) ? ToolsHelper::to_sqldate((string) $data['date_caci']) : null,
            );

            $Response->success = true;
            $Response->data = base64_encode(LayoutHelper::render('profil.card_caci', ['profil' => $updatedProfil]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }
}
