<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use NCB\Component\Gda\Site\Helper\AdhesionHelper;
use NCB\Component\Gda\Site\Helper\FileHelper;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\ProfilModel;


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

            $this->assertCanEditProfil((int) ($data['jform_Profil']['id_profil'] ?? 0), $model);

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

                // Données à jour (photo incluse : UploadImage() a mis à jour l'état de session avec
                // le nom de fichier final) pour permettre à l'appelant de rafraîchir l'affichage
                // (ex: ligne du tableau de la vue Utilisateurs) sans recharger la page.
                $savedData = $app->getUserState('profil.save');
                $photo = $savedData['photo'] ?? '';
                $Response->data = [
                    'id_profil' => (int) ($savedData['id_profil'] ?? 0),
                    'civilite'  => $savedData['civilite'] ?? '',
                    'nom'       => $savedData['nom'] ?? '',
                    'prenom'    => $savedData['prenom'] ?? '',
                    'photo'     => $photo,
                    // URL déjà résolue (dossier ProfilPhotoPath/défaut) : évite de reconstruire ce chemin
                    // côté JS à partir du src d'une image existante, fragile si la ligne affichait
                    // jusque là la photo par défaut (dossier différent) ou n'avait pas encore d'<img>.
                    'photoSrc'  => FileHelper::getImageSrc($photo, 'ProfilPhotoPath', 'DefaultProfilPhoto', false),
                ];
                $Response->message = Text::sprintf('COM_GDA_PROFIL_SAVED', trim(($savedData['nom'] ?? '') . ' ' . ($savedData['prenom'] ?? '')));
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

            $this->assertCanEditProfil((int) $data['jform_Caci']['id_profil'], $model);

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

    /**
     * Ajax : extraction des brevets FFESSM depuis l'URL du QR code de la carte licence, pour la
     * modale d'édition des brevets de la vue Profil.
     *
     * Tâche distincte de adhesion.extract : cette dernière refuse toute licence déjà connue de la
     * base (garde pertinente pour une *nouvelle* adhésion), ce qui rejetterait systématiquement un
     * adhérent existant. Ici la règle est inverse : la licence scannée doit être exactement celle
     * du profil ciblé, sans quoi on importerait les brevets de quelqu'un d'autre.
     */
    public function extractBrevets()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\ProfilModel $model */
            $model = $this->getModel('Profil', 'site');

            $idProfil = $app->input->getInt('id_profil', 0);
            $url = $app->input->getString('url', '');

            if ($idProfil <= 0) {
                throw new \Exception('id_profil invalide');
            }

            if ($url === '') {
                throw new \Exception(Text::_('COM_GDADHESIONS_ERROR_NO_URL'));
            }

            $this->assertCanEditProfil($idProfil, $model);

            $profil = $model->getProfilById($idProfil);

            if ($profil === null) {
                throw new \Exception(Text::_('COM_GDA_PROFIL_NOT_FOUND'), 404);
            }

            $data = AdhesionHelper::scrap($url);

            if (!$data || empty($data['informations']['licence'])) {
                throw new \Exception(Text::_('COM_GDA_BREVETS_SCAN_NOT_FOUND'));
            }

            $licenceScannee = $data['informations']['licence'];
            $porteur = AdhesionHelper::formatPorteurLicence($data['informations']);

            if ($licenceScannee !== (string) $profil->username) {
                throw new \Exception(Text::sprintf('COM_GDA_BREVETS_LICENCE_MISMATCH', $porteur));
            }

            // Le token FFESSM est encodé dans l'URL du QR code sous la forme id=<licence>_<token>.
            // On le complète immédiatement s'il manque : c'est un identifiant technique, pas une
            // donnée saisie par l'adhérent, et le service n'écrase jamais un token déjà présent.
            if (preg_match('/id=([0-9]+)_([A-Za-z0-9]+)/', $url, $m)) {
                $model->updateFfessmToken($idProfil, $m[2]);
            }

            $Response->success = true;
            // Le porteur accompagne les brevets : le JS le réinjecte dans la demande de
            // confirmation avant de remplacer les brevets déjà saisis.
            $Response->data = [
                'brevets' => $data['brevets'] ?? [],
                'porteur' => $porteur,
            ];
            $Response->message = Text::sprintf('COM_GDA_BREVETS_SCAN_SUCCESS', count($data['brevets'] ?? []));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }

    /**
     * Ajax : enregistrement des brevets saisis dans la modale d'édition (annule et remplace).
     * Renvoie la carte profil.card_brevet ré-rendue, comme saveCaci() pour la carte CACI.
     */
    public function saveBrevets()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app = Factory::getApplication();
            /** @var \NCB\Component\Gda\Site\Model\ProfilModel $model */
            $model = $this->getModel('Profil', 'site');

            $idProfil = $app->input->getInt('id_profil', 0);
            $brevets = $app->getInput()->get('brevets', [], 'ARRAY');

            if ($idProfil <= 0) {
                throw new \Exception('id_profil invalide');
            }

            $this->assertCanEditProfil($idProfil, $model);

            $profil = $model->getProfilById($idProfil);

            if ($profil === null) {
                throw new \Exception(Text::_('COM_GDA_PROFIL_NOT_FOUND'), 404);
            }

            $nombre = $model->saveBrevets($idProfil, $brevets);

            $Response->success = true;
            $Response->message = Text::sprintf('COM_GDA_BREVETS_SAVED', $nombre);
            $Response->data = base64_encode(LayoutHelper::render('profil.card_brevet', [
                'profil' => $profil,
                'editable' => true,
                'taille' => $app->input->getString('taille', ''),
                'brevets' => $model->getBrevets($idProfil),
            ]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }

    /**
     * Vérifie que l'utilisateur connecté peut modifier le profil ciblé : le sien, un profil "on
     * behalf" dont il a la charge, ou n'importe quel profil s'il est membre du Bureau. Lève une
     * exception sinon. À appeler avant toute écriture dans save()/saveCaci() : ces tâches ajax
     * ne sont, comme showCard(), pas protégées par le niveau d'accès du menu.
     *
     * @param int         $targetIdProfil Identifiant du profil visé par la sauvegarde.
     * @param ProfilModel $model          Réutilisé pour vérifier la relation "on behalf" sans requête dupliquée.
     */
    private function assertCanEditProfil(int $targetIdProfil, ProfilModel $model): void
    {
        $currentUser = Factory::getApplication()->getIdentity();

        if ($targetIdProfil <= 0 || $targetIdProfil === (int) $currentUser->id) {
            return;
        }

        $targetProfil = $model->getProfilById($targetIdProfil);
        $isOwnOnBehalf = $targetProfil !== null && $targetProfil->on_behalf === $currentUser->username;

        if ($isOwnOnBehalf || UsersHelper::isBureauMember()) {
            return;
        }

        throw new \Exception(Text::_('COM_GDA_ERROR_UNAUTHORIZED'), 403);
    }

    /**
     * Ajax : fiche adhérent en lecture seule, affichée en popup depuis les vues Groupe et Secretariat
     * (clic sur le Nom Prénom d'un adhérent). Réservé aux Moniteurs, Responsables de Groupe et membres
     * du Bureau — contrairement aux vues, les tâches ajax ne sont pas protégées par le niveau d'accès
     * du menu, la vérification doit donc être faite ici.
     */
    public function showCard()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            if (!UsersHelper::canViewMemberDetails()) {
                throw new \Exception(Text::_('COM_GDA_ERROR_UNAUTHORIZED'), 403);
            }

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app = Factory::getApplication();
            $idProfil = $app->input->getInt('id_profil', 0);

            if ($idProfil <= 0) {
                throw new \Exception('id_profil invalide');
            }

            /** @var \NCB\Component\Gda\Site\Model\ProfilModel $model */
            $model = $this->getModel('Profil', 'site');
            $profil = $model->getProfilById($idProfil);

            if ($profil === null) {
                throw new \Exception(Text::_('COM_GDA_PROFIL_NOT_FOUND'), 404);
            }

            $fields = UsersHelper::isBureauMember() ? ProfilModel::CARD_FIELDS_FULL : ProfilModel::CARD_FIELDS_LIGHT;

            $Response->success = true;
            $Response->data = base64_encode(LayoutHelper::render('profil.card_profil', [
                'profil' => $profil,
                'editable' => false,
                'fields' => $fields,
            ]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }

    /**
     * Ajax : liste des brevets en lecture seule, affichée en popup depuis la fiche adhérent
     * (lien "Liste des brevets" de profil.card_profil). Même restriction d'accès que showCard().
     */
    public function showBrevets()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            if (!UsersHelper::canViewMemberDetails()) {
                throw new \Exception(Text::_('COM_GDA_ERROR_UNAUTHORIZED'), 403);
            }

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app = Factory::getApplication();
            $idProfil = $app->input->getInt('id_profil', 0);

            if ($idProfil <= 0) {
                throw new \Exception('id_profil invalide');
            }

            /** @var \NCB\Component\Gda\Site\Model\ProfilModel $model */
            $model = $this->getModel('Profil', 'site');
            $profil = $model->getProfilById($idProfil);

            if ($profil === null) {
                throw new \Exception(Text::_('COM_GDA_PROFIL_NOT_FOUND'), 404);
            }

            $Response->success = true;
            $Response->data = base64_encode(LayoutHelper::render('profil.card_brevet', [
                'profil' => $profil,
                'closable' => true,
                'brevets' => $model->getBrevets($idProfil),
            ]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }

    /**
     * Ajax : formulaire d'édition complet du profil (champs + photo) d'un adhérent, affiché en
     * popup depuis la vue Utilisateurs (clic sur le Nom Prénom). Réservé au Bureau — contrairement
     * à showCard() (lecture seule, ouverte aux Moniteurs/Responsables de Groupe), cette action
     * permet la modification et doit donc rester strictement limitée au Bureau.
     */
    public function showEditForm()
    {
        $Response = new JsonResponse();
        try {
            $this->checkToken();

            if (!UsersHelper::isBureauMember()) {
                throw new \Exception(Text::_('COM_GDA_ERROR_UNAUTHORIZED'), 403);
            }

            /** @var \Joomla\CMS\Application\SiteApplication $app */
            $app = Factory::getApplication();
            $idProfil = $app->input->getInt('id_profil', 0);

            if ($idProfil <= 0) {
                throw new \Exception('id_profil invalide');
            }

            /** @var \NCB\Component\Gda\Site\Model\ProfilModel $model */
            $model = $this->getModel('Profil', 'site');
            $targetProfil = $model->getProfilById($idProfil);

            if ($targetProfil === null) {
                throw new \Exception(Text::_('COM_GDA_PROFIL_NOT_FOUND'), 404);
            }

            // Recharge le profil par username pour préremplir le formulaire (loadFormData() lit $this->_item).
            $model->getItem($targetProfil->username);
            $form = $model->getForm();

            $title = trim((string) (($targetProfil->civilite ?? '') . ' ' . ($targetProfil->nom ?? '') . ' ' . ($targetProfil->prenom ?? '')))
                . ' [' . $targetProfil->username . ']';
            $activeMenuItem = $app->getMenu()->getActive();

            $Response->success = true;
            $Response->data = base64_encode(LayoutHelper::render('profil.edit_form_popup', [
                'form' => $form,
                'photoFlag' => !empty($targetProfil->photo),
                // Pas de ternaire sur la photo : getImageSrc() retombe déjà sur DefaultProfilPhoto
                // quand la colonne est vide ou que le fichier n'existe plus (sinon src="" dans la modale).
                'photoSrc' => FileHelper::getImageSrc($targetProfil->photo, 'ProfilPhotoPath', 'DefaultProfilPhoto', false),
                'itemid' => $activeMenuItem->id ?? 0,
                'title' => $title,
            ]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }
    }
}
