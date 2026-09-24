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
use NCB\Component\Gda\Site\Helper\GdaLogger;
// use NCB\Component\Gda\Site\Model\AdhesionModel;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Service\SouscriptionService;




class AdhesionController extends AjaxController
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
                $Response->data = base64_encode(LayoutHelper::render('adhesion.alert', ['alerts' => [
                    ['title' => Text::_('COM_GDA_ADHESION_SCAN_TITLE'), 'message' => $Response->message],
                ]]));

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
                $Response->data = base64_encode(LayoutHelper::render('adhesion.alert', ['alerts' => [
                    ['title' => Text::_('COM_GDA_ADHESION_SCAN_TITLE'), 'message' => $Response->message],
                ]]));
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

            // Âge minimum du club : contrôle bloquant, à revérifier ici même si le bouton Valider
            // est déjà désactivé côté client (media/com_gdadhesions/js/adhesions.js) - une requête
            // ajax directe ne passerait pas par ce garde-fou JS.
            $cotisationCheck = new CotisationService(
                Factory::getContainer()->get(DatabaseInterface::class),
                ['dateDeNaissance' => $data['date_de_naissance'] ?? '', 'reduction' => $data['reduction'] ?? 0]
            );

            if (!$cotisationCheck->isAgeMinimumRespecte()) {
                throw new \Exception(Text::_('COM_GDA_ADHESION_AGE_MINIMUM_MESSAGE'));
            }

            $app->setUserState('adhesion.save', $data);

            $app->setUserState('adhesion.brevets', $arr_brevets);


            // Branche empruntée, conservée pour le journal de diagnostic (voir getContexteDiagnostic()).
            $branche = 'inconnue';

            try {
                // Il serai mieux de faire une verification de la la Lience users.username  et de la users.id
                if (empty($session) || !$session['username']) { // Pas de connexion
                    // cas d'une nouvelle adhesion sont entre dans la tablea users
                    if ($data['id'] === "0") { // ID à 0, c'est une nouvelle adhésion
                        $branche = 'nouvelle_adhesion';

                        if (!$model->isCheckCreation()) { // verif si le profil peut être créé
                            throw new \DomainException(Text::_('COM_GDA_ADHESION_SAVE_CREATION_REFUSEE'));
                        }

                        // creer user et profil
                        if (!$model->createUser()) {
                            // Compte déjà existant : mêmes explications que les contrôles en direct du
                            // formulaire (FormController::checkUserName()/checkEmail()), qui ont pu être
                            // contournés (autocomplétion sans passage par le champ, double envoi...).
                            $doublonLicence = !empty($data['username']) && UsersHelper::userExists((string) $data['username']);
                            $cleMessage = $doublonLicence ? 'COM_GDA_ADHESION_LICENCE_EXISTS_MESSAGE' : 'COM_GDA_ADHESION_EMAIL_EXISTS_MESSAGE';

                            throw new \DomainException(Text::_($cleMessage) . ' ' . Text::_('COM_GDA_ADHESION_SAVE_COMPTE_EXISTANT_AIDE'));
                        }

                        // creer le nouveau profil ; en cas d'échec, supprimer le compte tout juste
                        // créé pour que l'adhérent puisse réessayer (sinon e-mail « déjà utilisé »).
                        try {
                            $model->createProfil();
                        } catch (\Exception $e) {
                            $model->annulerCreationUser();
                            throw $e;
                        }
                        $model->sendWelcomeMail();
                    } else { // un profile existe re-edition grace au token
                        $branche = 'reedition_token';
                        $model->UpdateProfil();
                        $model->UpdateUser();
                        // essayer de recuperer le message que le mail n'est pas envoyer !
                        // et le transmettre au formulaire
                        $model->sendUpdateMail();
                    }
                } else {
                    $branche = 'membre_connecte';
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

                // Garde-fou : sans profil résolu (id 0), les insertions suivantes violeraient les clés
                // étrangères vers #__gda_profils et l'erreur SQL brute serait renvoyée à l'adhérent.
                if ((int) ($app->getUserState('adhesion.save')['id'] ?? 0) === 0) {
                    throw new \DomainException(Text::_('COM_GDA_ADHESION_SAVE_PROFIL_INTROUVABLE'));
                }

                /* sauveagarde les brevets  (Anule & remplace) */
                $model->saveInBrevets();
                /* sauvegarde de l'adhésion dans les groupes  selectionnés*/
                $model->saveInGroupes();
                /* mettre dans la table de souscription la campagne d'adhésion active pour le profil */
                $model->saveSouscription();
            } catch (\DomainException $e) {
                // Refus métier : message destiné à l'adhérent, conservé tel quel.
                GdaLogger::warning('AdhesionController::save() refusée : ' . $e->getMessage() . ' | ' . $this->getContexteDiagnostic($branche));
                throw $e;
            } catch (\Exception $e) {
                if ($e->getCode() === 403) {
                    // Refus d'autorisation : le message est déjà destiné à l'adhérent.
                    GdaLogger::warning('AdhesionController::save() non autorisée : ' . $e->getMessage() . ' | ' . $this->getContexteDiagnostic($branche));
                    throw $e;
                }

                // Erreur technique (SQL, fichier...) : détail complet dans le journal, message
                // générique pour l'adhérent afin de ne jamais exposer de requête ni de schéma.
                GdaLogger::error(sprintf(
                    'AdhesionController::save() erreur technique : %s (%s:%d) | %s | trace : %s',
                    $e->getMessage(),
                    basename($e->getFile()),
                    $e->getLine(),
                    $this->getContexteDiagnostic($branche),
                    str_replace("\n", ' <- ', $e->getTraceAsString())
                ));
                throw new \Exception(Text::_('COM_GDA_ADHESION_SAVE_ERREUR_TECHNIQUE'), 500, $e);
            }



            /* Met à jour la session pour que les changement soient visible immédiatement */
            $data = $app->getUserState('adhesion.save');

            // Rappel CACI dans la popup de confirmation : même règle que le secrétariat
            // (SouscriptionService::isCaciValidable() - fichier présent ET date valide au moins
            // NB_MOIS_VALIDITE_CACI_MIN mois à compter du 1er jour du mois de début de saison
            // fédérale). date_caci est stockée au format SQL (Y-m-d) par fixdata(), reconvertie en
            // d/m/Y (format attendu par le service) via ToolsHelper::from_sqldate().
            $souscriptionService = new SouscriptionService(Factory::getContainer()->get(DatabaseInterface::class));
            $dateCaciFr = ToolsHelper::from_sqldate($data['date_caci'] ?? null);
            $isCaciValidable = $souscriptionService->isCaciValidable($dateCaciFr, $data['caci'] ?? null);

            /* setter le popucontent pour la popup de confirmation d'adhésion */
            $data['popupcontent'] = LayoutHelper::render('adhesion.popup', [
                'item' => $app->getUserState('adhesion.save'),
                'caci_validable' => $isCaciValidable,
                'caci_date_fr' => $dateCaciFr,
            ]) ?? "";

            $Response->data =  base64_encode(json_encode($data));
            $Response->success = true;
            $Response->message .= Text::sprintf('COM_GDA_ADHESION_SAVE_SUCCESS', '',  $data['prenom'], $data['nom']);
            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }

    /**
     * Rassemble les éléments utiles pour diagnostiquer un échec d'enregistrement de l'adhésion
     * depuis le journal : branche empruntée, identifiants postés et résolus, session, clé de
     * réédition (préfixe seulement), volumétrie des brevets/groupes, client.
     *
     * @param   string  $branche  Branche de save() empruntée (nouvelle_adhesion, reedition_token, membre_connecte).
     * @return  string  Contexte sur une ligne, au format « clé=valeur ».
     */
    private function getContexteDiagnostic(string $branche): string
    {
        $app = Factory::getApplication();
        $adhesion = (array) $app->getUserState('adhesion.save');
        $session = (array) $app->getUserState('session');
        $brevets = $app->getUserState('adhesion.brevets');
        $cle = (string) ($adhesion['key'] ?? $app->getUserState('adhesion.key') ?? '');
        $serveur = $app->getInput()->server;

        $elements = [
            'branche' => $branche,
            'id_formulaire' => $adhesion['id'] ?? 'n/a',
            'username' => $adhesion['username'] ?? 'n/a',
            'email' => $adhesion['email'] ?? 'n/a',
            'nom' => trim(($adhesion['prenom'] ?? '') . ' ' . ($adhesion['nom'] ?? '')),
            'reduction' => $adhesion['reduction'] ?? 'n/a',
            'nb_groupes' => is_array($adhesion['id_groupes'] ?? null) ? count($adhesion['id_groupes']) : 0,
            'nb_brevets' => is_array($brevets) ? count($brevets) : 0,
            'session_id' => $session['id'] ?? 'aucune',
            'session_username' => $session['username'] ?? 'aucune',
            'cle_prefixe' => $cle !== '' ? substr($cle, 0, 4) . '...' : 'aucune',
            'user_joomla' => $app->getIdentity()->id,
            'ip' => $serveur->getString('REMOTE_ADDR', ''),
            'user_agent' => $serveur->getString('HTTP_USER_AGENT', ''),
        ];

        $lignes = [];
        foreach ($elements as $nom => $valeur) {
            $lignes[] = $nom . '=' . $valeur;
        }

        return implode(' ; ', $lignes);
    }
}
