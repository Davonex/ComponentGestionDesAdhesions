<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Service\ReservationService;

/**
 * Réservations des adhérents aux campagnes hors saison (Formation et Loisir).
 *
 * Toutes les tâches sont ajax et suivent le pattern des autres contrôleurs du composant :
 * checkToken(), JsonResponse, HTML renvoyé encodé en base64.
 *
 * Sécurité : l'identifiant de l'adhérent n'est JAMAIS lu depuis la requête, toujours pris sur
 * l'utilisateur connecté — sans quoi n'importe qui pourrait réserver ou annuler au nom d'un autre.
 */
class ReservationController extends BaseController
{
    /**
     * Utilisateur connecté, ou exception si la session n'en a pas : les tâches ajax ne sont pas
     * couvertes par le niveau d'accès du menu, contrairement aux vues.
     */
    private function getAdherent(): \Joomla\CMS\User\User
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user === null || (int) $user->id <= 0) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return $user;
    }

    private function getReservationService(): ReservationService
    {
        return new ReservationService(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * Recharge la formation demandée avec son état de réservation à jour, et rend la ligne du
     * dashboard. Utilisé après chaque écriture pour que le front remplace la ligne concernée.
     */
    private function renderLigne(int $idCampagne, $user): string
    {
        /** @var \NCB\Component\Gda\Site\Model\AccueilModel $model */
        $model = $this->getModel('Accueil', 'Site');

        foreach ($model->getCampagnesReservables($user) as $formation) {
            if ((int) $formation->id_campagne === $idCampagne) {
                return LayoutHelper::render('accueil.dash_campagne_reservable_ligne', [
                    'formation' => $formation,
                    'user'      => $user,
                ]);
            }
        }

        throw new \Exception(Text::_('COM_GDA_RESERVATION_CAMPAGNE_INTROUVABLE'), 404);
    }

    /**
     * Réserve une ou plusieurs places (potentiellement réparties sur plusieurs rôles à la fois
     * pour une campagne Loisir), ou met à jour une réservation existante. Le service décide seul
     * du statut de chaque place (confirmée / liste d'attente) selon les places restantes de
     * chaque rôle demandé. Si au moins une place est confirmée, que le paiement n'a pas encore
     * été rapproché (#__gda_reservation.id_order vide) et que la campagne est liée à un
     * événement HelloAsso, la réponse porte en plus un popup de paiement (voir
     * reservation.helloasso_popup) : ce popup réapparaît à chaque réservation/modification tant
     * que le paiement n'a pas été rapproché.
     *
     * @return void Réponse ajax échoée directement (JsonResponse).
     */
    public function reserver()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();

            $user       = $this->getAdherent();
            $input      = $app->getInput();
            $idCampagne = $input->getInt('id_campagne', 0);

            if ($idCampagne <= 0) {
                throw new \Exception(Text::_('COM_GDA_RESERVATION_CAMPAGNE_INTROUVABLE'), 404);
            }

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $campagnesModel */
            $campagnesModel = $this->getModel('Campagnes', 'Site');
            $campagne       = $campagnesModel->getCampagne($idCampagne);

            $demandes = [];
            foreach ($input->get('role_places', [], 'array') as $ligne) {
                $demandes[] = [
                    'role'     => trim((string) ($ligne['role'] ?? '')),
                    'quantite' => (int) ($ligne['quantite'] ?? 0),
                ];
            }

            $idTypeFormation = (int) ConfHelper::getValue('IdTypeFormation');

            if ((int) $campagne->id_type === $idTypeFormation) {
                // Formation : un seul rôle possible, toujours 1 place (garde-fou serveur, même
                // motif que le verrou reservation_multiple dans CampagnesModel::Sauver()).
                $demandes = array_slice($demandes, 0, 1);

                if ($demandes) {
                    $demandes[0]['quantite'] = 1;
                }
            } elseif (!$campagne->reservation_multiple && array_sum(array_column($demandes, 'quantite')) > 1) {
                // Loisir sans reservation_multiple : total <= 1 place, même garde-fou.
                throw new \Exception(Text::_('COM_GDA_RESERVATION_MULTIPLE_INTERDITE'), 400);
            }

            $capacitesParRole = $campagnesModel->getRolesCapacite([$idCampagne])[$idCampagne] ?? [];

            $reservation = $this->getReservationService()->reserver(
                $idCampagne,
                (int) $user->id,
                $demandes,
                $capacitesParRole,
                $input->getString('commentaire', null)
            );

            $enAttente          = false;
            $aUnePlaceConfirmee = false;

            foreach ($reservation->places as $place) {
                if ($place->statut === ReservationService::STATUT_ATTENTE) {
                    $enAttente = true;
                } elseif ($place->statut === ReservationService::STATUT_CONFIRMEE) {
                    $aUnePlaceConfirmee = true;
                }
            }

            $helloAssoPopup = null;

            if ($aUnePlaceConfirmee && empty($reservation->id_order) && !empty($campagne->event_helloasso)) {
                $eventHelloAsso = json_decode((string) $campagne->event_helloasso, true);
                $urlHelloAsso   = $eventHelloAsso['url'] ?? null;

                if (!empty($urlHelloAsso)) {
                    $helloAssoPopup = LayoutHelper::render('reservation.helloasso_popup', [
                        'campagne'     => $campagne,
                        'urlHelloAsso' => $urlHelloAsso,
                    ]);
                }
            }

            $Response->success = true;
            $Response->message = $enAttente
                ? Text::sprintf('COM_GDA_RESERVATION_EN_ATTENTE', $campagne->titre)
                : Text::sprintf('COM_GDA_RESERVATION_CONFIRMEE', $campagne->titre);
            // JsonResponse (Joomla\CMS\Response\JsonResponse) ne déclare que success/message/
            // messages/data : y ajouter une propriété dynamique (ex: $Response->helloasso_popup)
            // est deprecated depuis PHP 8.2 et casse la réponse (le warning HTML s'intercale
            // avant le JSON). Le popup HelloAsso est donc niché dans data, seule propriété
            // extensible sans risque.
            $Response->data = [
                'ligne'           => base64_encode($this->renderLigne($idCampagne, $user)),
                'helloasso_popup' => $helloAssoPopup !== null ? base64_encode($helloAssoPopup) : null,
            ];

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Annule la réservation de l'adhérent connecté (passage en statut 'annulee', pas de
     * suppression : l'historique et le rang initial sont conservés).
     */
    public function annuler()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();

            $user       = $this->getAdherent();
            $idCampagne = $app->getInput()->getInt('id_campagne', 0);

            if ($idCampagne <= 0) {
                throw new \Exception(Text::_('COM_GDA_RESERVATION_CAMPAGNE_INTROUVABLE'), 404);
            }

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $campagnesModel */
            $campagnesModel = $this->getModel('Campagnes', 'Site');
            $campagne       = $campagnesModel->getCampagne($idCampagne);

            $this->getReservationService()->annuler($idCampagne, (int) $user->id);

            $Response->success = true;
            $Response->message = Text::sprintf('COM_GDA_RESERVATION_ANNULEE', $campagne->titre);
            $Response->data    = base64_encode($this->renderLigne($idCampagne, $user));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Contenu du popup de réservation / modification : rappel de la campagne, choix des rôles
     * si la campagne en demande, commentaire, et boutons adaptés (réserver ou mettre à jour).
     */
    public function getFormulaire()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();

            $user       = $this->getAdherent();
            $idCampagne = $app->getInput()->getInt('id_campagne', 0);

            if ($idCampagne <= 0) {
                throw new \Exception(Text::_('COM_GDA_RESERVATION_CAMPAGNE_INTROUVABLE'), 404);
            }

            /** @var \NCB\Component\Gda\Site\Model\CampagnesModel $campagnesModel */
            $campagnesModel = $this->getModel('Campagnes', 'Site');
            $campagne       = $campagnesModel->getCampagne($idCampagne);

            $service     = $this->getReservationService();
            $reservation = $service->getReservation($idCampagne, (int) $user->id);

            // Rôles proposés : ceux réellement configurés pour CETTE campagne (#__gda_campagne_roles),
            // pas le gabarit par défaut de la nature (getRolesDeCampagne()) — un rôle ajouté/renommé
            // par le Bureau doit apparaître ici même s'il ne fait pas partie des rôles par défaut.
            $capacitesParRole = $campagnesModel->getRolesCapacite([$idCampagne])[$idCampagne] ?? [];
            $rolesDispo       = array_keys($capacitesParRole);

            // Places restantes par rôle, pour les afficher dans le sélecteur : le total de la
            // campagne ne suffit pas à savoir si LE rôle choisi a encore de la place.
            $placesDisponiblesParRole = [];
            foreach ($rolesDispo as $roleDispo) {
                $placesDisponiblesParRole[$roleDispo] = $service->getPlacesDisponiblesParRole(
                    $idCampagne,
                    $roleDispo,
                    (int) ($capacitesParRole[$roleDispo] ?? 0)
                );
            }

            $Response->success = true;
            $Response->data    = base64_encode(LayoutHelper::render('reservation.form', [
                'campagne'                 => $campagne,
                'reservation'               => $reservation,
                'rolesDisponibles'          => $rolesDispo,
                'placesDisponibles'         => $service->getPlacesDisponiblesTotal($idCampagne, $service->getCapaciteTotale($campagne)),
                'placesDisponiblesParRole'  => $placesDisponiblesParRole,
            ]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Contenu du popup "article lié à la campagne".
     *
     * L'article appartient à com_content : on passe par son propre modèle (plutôt qu'une requête
     * SQL directe) pour bénéficier de ses contrôles d'état/accès, puis on déclenche onContentPrepare
     * afin que les plugins de contenu s'appliquent comme sur une page d'article normale.
     */
    public function showArticle()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();

            $this->getAdherent();
            $idArticle = $app->getInput()->getInt('id_article', 0);

            if ($idArticle <= 0) {
                throw new \Exception(Text::_('COM_GDA_RESERVATION_ARTICLE_INTROUVABLE'), 404);
            }

            $articleModel = $app->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Article', 'Site', ['ignore_request' => true]);

            // ignore_request empêche ArticleModel::populateState() de s'exécuter (pour ne pas
            // lire son propre "id" depuis la requête HTTP brute, qui ne le porte pas ici), mais
            // ArticleModel::getItem() clone ensuite l'état "params" sans vérifier qu'il existe :
            // sans ce setState, il vaut null et le clone plante ("__clone method called on
            // non-object"). populateState() le peuple normalement avec $app->getParams().
            $articleModel->setState('params', $app->getParams());
            $articleModel->setState('article.id', $idArticle);
            $articleModel->setState('filter.published', 1);

            $article = $articleModel->getItem();

            if (!$article) {
                throw new \Exception(Text::_('COM_GDA_RESERVATION_ARTICLE_INTROUVABLE'), 404);
            }

            $article->text = ($article->introtext ?? '') . ($article->fulltext ?? '');

            $params = new Registry();
            PluginHelper::importPlugin('content');
            $app->triggerEvent('onContentPrepare', ['com_content.article', &$article, &$params, 0]);

            $Response->success = true;
            $Response->data    = base64_encode(LayoutHelper::render('reservation.article', [
                'article' => $article,
            ]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }
}
