<?php

// components/com_gdadhesions/src/Controller/FormController.php
namespace NCB\Component\Gda\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Application\CMSApplication;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Service\SouscriptionService;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

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
                    // Message court (texte brut) pour le champ JSON générique 'message' ; le détail
                    // affiché dans la popup (adhesion.alert) est une clé de langue dédiée, avec
                    // 'html' => true car son contenu (liste à puces) est statique - jamais de
                    // saisie utilisateur dans ce message, voir la garde dans adhesion/alert.php.
                    $Response->message = Text::_('COM_GDA_ADHESION_EMAIL_EXISTS_MESSAGE');
                    $Response->data = base64_encode(LayoutHelper::render('adhesion.alert', ['alerts' => [
                        [
                            'title' => Text::_('COM_GDA_ADHESION_EMAIL_EXISTS_TITLE'),
                            'message' => Text::_('COM_GDA_ADHESION_EMAIL_EXISTS_MESSAGE_DETAILED'),
                            'html' => true,
                        ],
                    ]]));
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
                    // Même motif que checkEmail() : message court (texte brut) pour le champ JSON
                    // générique 'message', détail (liste à puces) en clé de langue dédiée avec
                    // 'html' => true, réservé à ce contenu statique - voir la garde dans
                    // adhesion/alert.php contre toute saisie utilisateur en HTML non échappé.
                    $Response->message = Text::_('COM_GDA_ADHESION_LICENCE_EXISTS_MESSAGE');
                    $Response->data = base64_encode(LayoutHelper::render('adhesion.alert', ['alerts' => [
                        [
                            'title' => Text::_('COM_GDA_ADHESION_LICENCE_EXISTS_TITLE'),
                            'message' => Text::_('COM_GDA_ADHESION_LICENCE_EXISTS_MESSAGE_DETAILED'),
                            'html' => true,
                        ],
                    ]]));
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
            // Contrôles métier (popups côté client) : réduction Famille réservée aux adultes
            // (avertissement, ne bloque pas), âge minimum du club (bloquant). Le message est
            // rendu ici, côté serveur, via le layout adhesion.alert pour rester modifiable au
            // même endroit que le reste du gabarit de la vue.
            $alerts = [];
            if (!$service->isAgeMinimumRespecte()) {
                $alerts[] = ['title' => Text::_('COM_GDA_ADHESION_AGE_MINIMUM_TITLE'), 'message' => Text::_('COM_GDA_ADHESION_AGE_MINIMUM_MESSAGE')];
            }
            if (!$service->isReductionFamilleValide()) {
                $alerts[] = ['title' => Text::_('COM_GDA_ADHESION_REDUCTION_FAMILLE_TITLE'), 'message' => Text::_('COM_GDA_ADHESION_REDUCTION_FAMILLE_MESSAGE')];
            }

            $result['age_minimum_non_respecte'] = !$service->isAgeMinimumRespecte();
            $result['alert_html'] = $alerts !== [] ? base64_encode(LayoutHelper::render('adhesion.alert', ['alerts' => $alerts])) : null;
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

    /**
     * Valide côté serveur, au fil de la saisie du formulaire d'adhésion (onglet "Informations
     * Plongeur"), la date de fin de validité du CACI, en s'appuyant sur la même règle métier que le
     * secrétariat (SouscriptionService::isDateCaciValidable() - validité minimale de 9 mois à
     * compter du 1er jour du mois de début de saison fédérale). Ne vérifie volontairement pas la
     * présence du fichier CACI, pas nécessairement encore chargé à ce stade de la saisie,
     * contrairement à SouscriptionService::isCaciValidable() utilisée par le secrétariat.
     *
     * @return void
     * @throws \Exception Si le jeton CSRF est invalide (checkToken()).
     */
    public function checkCaci(): void
    {
        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        $Response = new JsonResponse();

        try {
            $this->checkToken();

            $dateCaci = trim((string) $app->getInput()->get('dateCaci', '', 'Raw'));
            $service = new SouscriptionService(Factory::getContainer()->get('DatabaseDriver'));
            $valid = $dateCaci !== '' && $service->isDateCaciValidable($dateCaci);
            $state = $valid ? 'valid' : ($dateCaci === '' ? 'missing' : 'invalid');

            // Libellé court (affiché à côté du champ) et message long (affiché en tooltip au survol)
            // pour chacun des 3 états possibles - voir language/fr-FR/com_gdadhesions.ini.
            $labels = [
                'missing' => Text::_('COM_GDA_ADHESION_CACI_DATE_MISSING_SHORT'),
                'invalid' => Text::_('COM_GDA_ADHESION_CACI_DATE_INVALID_SHORT'),
                'valid' => Text::_('COM_GDA_ADHESION_CACI_DATE_VALID_SHORT'),
            ];
            $messages = [
                'missing' => Text::_('COM_GDA_ADHESION_CACI_DATE_MISSING_MESSAGE'),
                'invalid' => Text::_('COM_GDA_ADHESION_CACI_DATE_INVALID_MESSAGE'),
                'valid' => Text::_('COM_GDA_ADHESION_CACI_DATE_VALID_MESSAGE'),
            ];

            $Response->success = true;
            $Response->data = [
                'valid' => $valid,
                'label' => $labels[$state],
                'message' => $messages[$state],
            ];
        } catch (\Exception $e) {
            $Response = new JsonResponse();
            $Response->success = false;
            $Response->message = 'Erreur: ' . $e->getMessage();
        }
        echo  $Response;
        $app->close();  // stoppe l’exécution pour que seule la réponse JSON parte
    }
}
