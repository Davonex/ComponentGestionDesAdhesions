<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Factory;
// use Joomla\Database\DatabaseInterface;
// use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
// use Joomla\CMS\Application\SiteApplication;
use NCB\Component\Gda\Site\Helper\AdhesionHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
// use NCB\Component\Gda\Site\Model\AdhesionModel;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;




class AdhesionController extends BaseController
{

    /**
     * Permet d'extraire les informations d'une licence à partir du lien FFSEM de la carte licence
     */
    public function extract()
    {

        /** @var SiteApplication $app */
        $app = Factory::getApplication();

        $Response = new JsonResponse();
        try {
            $this->checkToken();

            $url = $app->input->getString('url');
            if (! $url) {
                throw new \Exception(Text::_('COM_GDADHESIONS_ERROR_NO_URL'));
            }

            $data = AdhesionHelper::scrap($url);

            if (preg_match('/id=([0-9]+)_([A-Za-z0-9]+)/', $url, $m)) {
                $token = $m[2];  // D5EC46
            }

            if (! $data || empty($data["informations"]["licence"])) {
                // aucune licence n'a été trouvée
                $Response->success = false;
                $Response->message = Text::_('COM_GDA_ADHESION_SCAN_NOT_FOUND');

                echo $Response;
                $app->close();
            }

            $licence = $data["informations"]["licence"];
            $porteur = AdhesionHelper::formatPorteurLicence($data["informations"]);

            /* Licence en cours d'édition : membre connecté, ou dossier repris via la clé de
            ** réédition (getProfil() résout les deux, et renvoie un username vide pour une
            ** première adhésion). Scanner sa propre carte doit rester autorisé — c'est le cas
            ** nominal du renouvellement — donc seule une licence connue ET différente est
            ** refusée. */
            /** @var AdhesionModel $model */
            $model = $this->getModel('Adhesion', 'site');
            $licenceCourante = (string) ($model->getProfil()->username ?? '');

            if ($licence !== $licenceCourante && UsersHelper::userExists($licence)) {
                // la licence scannée appartient à un compte existant qui n'est pas celui édité
                $Response->success = false;
                $Response->message = Text::sprintf('COM_GDA_ADHESION_SCAN_EXISTS', $porteur);
            } else {
                // la licence trouvée est valide et peut être utilisée pour l'adhésion
                $Response->success = true;
                $data["informations"]["token"] = $token ?? "";
                $data["porteur"] = $porteur;
                $Response->data = $data;
                $Response->message = Text::sprintf('COM_GDA_ADHESION_SCAN_FOUND', $porteur);
            }

            echo $Response;

            $app->close();
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }

    public function save()
    {

        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $session = $app->getUserState('session');

        $Response = new JsonResponse();
        try {
            $this->checkToken();
            /** @var AdhesionModel $model */
            $model = $this->getModel('Adhesion', 'site');


            // $data = $app->input->getArray(array('jform' => 'ARRAY'));;
            // Deprecated in Joomla 6 use $app->getInput()->get('jform', array(), 'ARRAY');

            $data = $app->getInput()->get('jform', array(), 'ARRAY');
            // $arr_brevets = $app->input->getArray(array('brevets' => 'ARRAY'));
            $arr_brevets = $app->getInput()->get('brevets', array(), 'ARRAY');

            // $saison = $app->getUserState('saison');

            $file_photo = $app->input->files->get("jform")['upload.photo'] ?? null;
            // si il y a une photo on la charge dans le répertoire des photos de profil
            if (isset($file_photo) and $file_photo['tmp_name'] != '') {
                $targetPhoto =  FileHelper::UploadFile((string) $data['photo'], (string) ConfHelper::getValue('ProfilPhotoPath'), $file_photo,800,1024);
                $data['photo'] = $targetPhoto;
            }
            // si il y a un caci on le charge dans le répertoire des caci
            $file_caci = $app->input->files->get("jform")['upload.caci'] ?? null;
            if (isset($file_caci) and $file_caci['tmp_name'] != '') {
                $targetCaci =  FileHelper::UploadFile((string) $data['caci'], (string) ConfHelper::getValue('CaciPath'), $file_caci);
                $data['caci'] = $targetCaci;
            }

            /* clean la donnée et transforme les valeur pour avoir le format correcte pour la base de donnée
            ** Ajoute aussi la date de la dernière mise à jour pour le profil
             */
            $data = $model->fixdata($data);
            $app->setUserState('adhesion.save', $data);

            $app->setUserState('adhesion.brevets', $arr_brevets);


            // Il serai mieux de faire une verification de la la Lience users.username  et de la users.id  
            if (empty($session) || !$session['username']) { // Pas de connexion
                // cas d'une nouvelle adhesion sont entre dans la tablea users
                if ($data['id'] === "0") { // ID à 0, c'est une nouvelle adhésion
                    if ($model->isCheckCreation()) { //verif si le profil peut être créé
                        // creer user et profil
                        if ($model->createUser()) {
                            // creer le nouveau profil
                            $model->createProfil();
                            $model->sendWelcomeMail();
                        };
                    }
                } else { // un profile existe re-edition grace au token
                    $model->UpdateProfil();
                    $model->UpdateUser();
                    // essayer de recuperer le message que le mail n'est pas envoyer !
                    // et le transmettre au formulaire
                    $model->sendUpdateMail();
                }
            } else {
                // A l'installation du component, le profil peut ne pas être créé.
                // verifier si le profil existe avant de faire la mise à jour.
                if (!$model->isProfilExiste()) {
                    $model->createProfil();
                } else {
                    $model->UpdateProfil();
                }
                $model->UpdateUser();
                $model->sendUpdateMail();
            }


            /* sauveagarde les brevets  (Anule & remplace) */
            $model->saveInBrevets();
            /* sauvegarde de l'adhésion dans les groupes  selectionnés*/
            $model->saveInGroupes();
            /* mettre dans la table de souscription la campagne d'adhésion active pour le profil */
            $model->saveSouscription();



            /* Met à jour la session pour que les changement soient visible immédiatement */
            $data = $app->getUserState('adhesion.save');
            /* setter le popucontent pour la popup de confirmation d'adhésion */
            $data['popupcontent'] = LayoutHelper::render('adhesion.popup', ['item' => $app->getUserState('adhesion.save')]) ?? "";

            $Response->data =  base64_encode(json_encode($data));
            $Response->success = true;
            $Response->message .= Text::sprintf('COM_GDA_ADHESION_SAVE_SUCCESS', '',  $data['prenom'], $data['nom']);
            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }
}
