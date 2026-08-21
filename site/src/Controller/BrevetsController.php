<?php

namespace NCB\Component\Gda\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\UsersHelper;

/**
 * Contrôleur de la vue « Brevets » (Bureau). Toutes les tâches sont ajax : le référentiel et les
 * rattachements se modifient sans quitter la page.
 */
class BrevetsController extends BaseController
{
    /**
     * Les tâches ajax ne sont pas couvertes par le niveau d'accès du menu, contrairement à
     * l'affichage de la vue : chacune doit revérifier l'appartenance au Bureau.
     */
    private function guardBureauMember(): void
    {
        if (!UsersHelper::isBureauMember()) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Enveloppe commune des tâches ajax : jeton CSRF, garde Bureau, réponse JSON normalisée et
     * journalisation des erreurs. Évite de répéter six fois le même bloc try/catch.
     *
     * L'action reçoit la réponse en 3e argument pour y poser son message de confirmation :
     * simpleCallAjax() l'affiche via Joomla.renderMessages(), un message laissé à null y
     * produirait une alerte vide.
     *
     * @param callable(\NCB\Component\Gda\Site\Model\BrevetsModel, \Joomla\Input\Input, JsonResponse): array $action
     *        Reçoit le modèle, l'input et la réponse ; renvoie les données de la réponse.
     */
    private function runAjax(callable $action): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        $response = new JsonResponse();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            /** @var \NCB\Component\Gda\Site\Model\BrevetsModel $model */
            $model = $this->getModel('brevets', 'site');

            $resultat = $action($model, $app->input, $response);

            $response->success = true;
            $response->data = $resultat;
        } catch (\Throwable $e) {
            $response->success = false;
            $response->message = $e->getMessage();
            GdaLogger::error('Vue Brevets : ' . $e->getMessage());
        }

        echo $response;
        $app->close();
    }

    /**
     * Ajax : ajoute une correspondance au référentiel FFESSM. Renvoie la ligne re-rendue, qui
     * remplace la ligne de saisie côté client (même pattern que saisons.sauvegarderCourante).
     */
    public function saveMapping(): void
    {
        $this->runAjax(static function ($model, $input, $response): array {
            $mapping = $model->createMapping([
                'label_ffessm' => $input->getString('label_ffessm', ''),
                'activite'     => $input->getString('activite', ''),
                'role'         => $input->getString('role', ''),
                'code'         => $input->getString('code', ''),
                'poids'        => $input->getInt('poids', 0),
            ]);

            $response->message = Text::sprintf('COM_GDA_BREVETS_MAPPING_CREATED', $mapping->label_ffessm);

            return [
                'id'   => (int) $mapping->id,
                'html' => LayoutHelper::render('brevets.mapping_row', ['mapping' => $mapping]),
            ];
        });
    }

    /**
     * Ajax : édition inline d'une case du référentiel (code ou poids).
     */
    public function updateMappingChamp(): void
    {
        $this->runAjax(static function ($model, $input, $response): array {
            $model->updateMappingChamp(
                $input->getInt('id_mapping', 0),
                $input->getCmd('champ', ''),
                $input->getString('valeur', '')
            );

            $response->message = Text::_('COM_GDA_BREVETS_MAPPING_UPDATED');

            return [];
        });
    }

    /**
     * Ajax : nombre de brevets adhérents qui seront détachés par la suppression d'une
     * correspondance. Interrogé avant d'afficher la confirmation, pour que l'avertissement
     * annonce l'impact réel.
     */
    public function countBrevetsLies(): void
    {
        $this->runAjax(static function ($model, $input): array {
            return ['count' => $model->countBrevetsLies($input->getInt('id_mapping', 0))];
        });
    }

    /**
     * Ajax : supprime une correspondance du référentiel.
     */
    public function deleteMapping(): void
    {
        $this->runAjax(static function ($model, $input, $response): array {
            $label = $model->deleteMapping($input->getInt('id_mapping', 0));

            $response->message = Text::sprintf('COM_GDA_BREVETS_MAPPING_DELETED', $label);

            return [];
        });
    }

    /**
     * Ajax : corrige le libellé d'un brevet adhérent (onglet 2).
     */
    public function updateNomBrevet(): void
    {
        $this->runAjax(static function ($model, $input, $response): array {
            $brevet = $model->updateNomBrevet($input->getInt('id_brevet', 0), $input->getString('nom', ''));

            $response->message = Text::sprintf(
                'COM_GDA_BREVETS_NOM_UPDATED',
                $brevet->adherent,
                $brevet->ancien_nom,
                $brevet->nom
            );

            return [];
        });
    }

    /**
     * Ajax : rattache un brevet adhérent au référentiel. Le libellé officiel remplace la saisie
     * de l'adhérent : il est renvoyé pour rafraîchir la ligne affichée.
     */
    public function attacherMapping(): void
    {
        $this->runAjax(static function ($model, $input, $response): array {
            $mapping = $model->attacherMapping(
                $input->getInt('id_brevet', 0),
                $input->getInt('id_mapping', 0)
            );

            $response->message = Text::sprintf(
                'COM_GDA_BREVETS_MAPPING_ATTACHED',
                $mapping->adherent,
                $mapping->ancien_nom,
                $mapping->label_ffessm
            );

            return [
                'nom'          => $mapping->nom,
                'label_ffessm' => $mapping->label_ffessm,
                'activite'     => $mapping->activite,
                'role'         => $mapping->role,
                'code'         => $mapping->code,
            ];
        });
    }
}
