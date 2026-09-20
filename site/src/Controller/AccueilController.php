<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\User\User;

/**
 * Tâches ajax du dashboard Accueil, hors réservation (voir ReservationController pour
 * réserver/annuler/getFormulaire/showArticle).
 *
 * Suit le même pattern que les autres contrôleurs ajax du composant : checkToken(), JsonResponse,
 * HTML renvoyé encodé en base64.
 */
class AccueilController extends AjaxController
{
    /**
     * Utilisateur connecté, ou exception si la session n'en a pas : les tâches ajax ne sont pas
     * couvertes par le niveau d'accès du menu, contrairement aux vues. Même motif que
     * ReservationController::getAdherent().
     *
     * @return User L'utilisateur Joomla connecté.
     * @throws \Exception Si aucun utilisateur n'est connecté.
     */
    private function getAdherent(): User
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user === null || (int) $user->id <= 0) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return $user;
    }

    /**
     * Recharge l'encart "Boutique" en forçant l'appel à l'API HelloAsso (ignore le cache fichier
     * de 30 min, voir HelloAssoService::getFormsPublicCached()/getFormsStatsCached()), pour que
     * l'adhérent puisse voir un stock ou un prix à jour sans attendre l'expiration du cache.
     *
     * @return void Réponse ajax échoée directement (JsonResponse).
     */
    public function refreshBoutique()
    {
        $Response = new JsonResponse();
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->getAdherent();

            /** @var \NCB\Component\Gda\Site\Model\AccueilModel $model */
            $model = $this->getModel('Accueil', 'Site');

            $Response->success = true;
            $Response->message = Text::_('COM_GDA_ACCUEIL_BOUTIQUE_RAFRAICHI');
            $Response->data    = base64_encode(LayoutHelper::render('accueil.dash_boutique', [
                'campagnes' => $model->getCampagnesBoutique(true),
            ]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }
}
