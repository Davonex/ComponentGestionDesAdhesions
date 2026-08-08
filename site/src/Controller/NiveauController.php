<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;


class NiveauController extends BaseController
{
    public function extract()
    {

        try {
            $this->checkToken();
            $Response = new JsonResponse();
            // Initialisation
            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\NiveauModel $model */
            $model = $this->getModel('Niveau', 'site');
            $input = $app->input;
            $data = $input->getArray(array(
                'extract' => 'ARRAY'
            ));

            if (!empty($data['extract'])) {
                $app->setUserState('niveau.extract', $data['extract']);
                $tab = $model->extractNiveau();
                if (isset($data['extract']['save']) and  $data['extract']['save'] === '1') {
                    $model->SaveAfterExtract($data['extract']['id_profil'], $tab);
                }

                if (\is_null($tab)) {
                    $Response->success = false;
                    $Response->message = "Html empty";
                }
                // $Response->data = json_encode($tab,JSON_UNESCAPED_UNICODE);
                $Response->data = $tab;
            } else {
                $Response->success = false;
            }
            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }
}
