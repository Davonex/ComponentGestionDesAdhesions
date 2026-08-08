<?php

// components/com_gdadhesions/src/Controller/FormController.php
namespace NCB\Component\Gda\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Application\CMSApplication;
use NCB\Component\Gda\Site\Service\CotisationService;
use Joomla\CMS\Language\Text;

class FormController extends BaseController
{
    public function checkEmail(): void
    {

         /** @var CMSApplication $app */
            $app   = Factory::getApplication();
        $Response = new JsonResponse();

        try {
            $this->checkToken();     

           
            // $input = $app->input;
            $email = trim((string) $app->getInput()->get('elementValue', '', 'Raw'));
            $session = $app->getUserState('session');

            if (($session['email'] ?? null) === $email) {
                $Response->success = true;
            } else {
                $db = Factory::getContainer()->get('DatabaseDriver');

                $currentProfilId = (int) ($session['id'] ?? 0);

                if ($currentProfilId <= 0) {
                    $adhesionKey = trim((string) $app->getUserState('adhesion.key'));

                    if ($adhesionKey !== '') {
                        $profileQuery = $db->getQuery(true)
                            ->select($db->quoteName('id_profil'))
                            ->from($db->quoteName('#__gda_profils'))
                            ->where($db->quoteName('key') . ' = :adhesion_key')
                            ->bind(':adhesion_key', $adhesionKey);

                        $db->setQuery($profileQuery);
                        $currentProfilId = (int) $db->loadResult();
                    }
                }

                $query = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('email') . ' = :email')
                    ->bind(':email', $email);

                $db->setQuery($query, 0, 1);
                $emailOwnerId = (int) $db->loadResult();

                if ($emailOwnerId === 0 || $emailOwnerId === $currentProfilId) {
                    $Response->success = true;
                } else {
                    $Response->success = false;
                    $Response->message = "Cette Adresse mail existe déjà !";
                }
            }
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        echo  $Response;
        $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte
    }

    public function checkUserName(): void
    {

          $Response = new JsonResponse();

            /** @var CMSApplication $app */
            $app   = Factory::getApplication();

        try {
            $this->checkToken();

          

            $username =  $app->getInput()->get('elementValue', null, 'Raw');
            $session = $app->getUserState('session');

            // si session['username'] est null, il faut que le username n'existe pas 
            if (!is_null($session['username']) && $session['username'] == $username) {
                $Response->success = true;
            } else if (is_null($session['username']) && $username[0] == 'N') {
                // cas ou il n'y a pas de session ouvert  et username comment par N (nouveau)
                $Response->success = true;
            } else {
                // cas  different
                $db = Factory::getContainer()->get('DatabaseDriver');

                // $db = $app->getDatabase();
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('username') . ' = ' . $db->quote($username));
                $db->setQuery($query);
                $Response->success = (int) $db->loadResult() == 0;
                if (! $Response->success) {
                    $Response->message = "Cette Licence existe déjà !";
                }
            }
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
        echo  $Response;
        $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte
    }


    public function CheckCotisation(): void
    {
        /** @var CMSApplication $app */
        $app   = Factory::getApplication();
        try {
            $Response = new JsonResponse();
            $this->checkToken();

            $data = [
                'dateDeNaissance' => $app->getInput()->get('dateDeNaissance', null, 'Raw'),
                'codePostal' => $app->getInput()->get('codePostal', null, 'Raw'),
                'reduction' => $app->getInput()->get('reduction', null, 'Raw'),
            ];
            // Utiliser l'objet cotisation service pour calculer le tarif de la cotisation et les droits à l'image
            $service = new CotisationService(Factory::getContainer()->get('DatabaseDriver'), $data);

            $result['code'] =  $service->getCode();
            $result['montant'] =  CotisationService::getMontant($result['code'], Factory::getContainer()->get('DatabaseDriver'));
            // mettre la decription de la cotisation et concatener le montant en Euro avec 0 decimale
            $result['innerHtml'] = Text::_('COM_GDA_COTISATION_TARIF_' . $result['code']) . ' : <pan class="fw-bold">' . number_format($result['montant'], 0, ',', ' ') . ',00 €</span>';
            $Response->data = $result;
            $Response->success = true;
        } catch (\Exception $e) {
            $Response = new JsonResponse();
            $Response->success = false;
            $Response->message = 'Erreur: ' . $e->getMessage();
        }
        echo  $Response;
        $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte
    }
}
