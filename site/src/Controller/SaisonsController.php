<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Service\GroupesService;

class SaisonsController extends BaseController
{
    /**
     * Vérifie que l'utilisateur connecté est membre du Bureau, sinon lève une exception.
     * Nécessaire car les tâches ajax ne sont pas protégées par le niveau d'accès du menu,
     * contrairement à l'affichage de la vue.
     */
    private function guardBureauMember(): void
    {
        if (!UsersHelper::isBureauMember()) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Ajax : sauvegarde groupée des champs de la saison courante ET des groupes du club
     * (colonne gauche + colonne droite du 1er onglet), en une seule transaction — les deux
     * colonnes partagent un unique formulaire et un unique bouton "Sauvegarder".
     */
    public function sauvegarderCourante(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        $Response = new JsonResponse();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $dataForm = $app->input->getArray(['jform_saison' => 'ARRAY', 'groupes' => 'ARRAY']);
            $data     = $dataForm['jform_saison'] ?? [];
            $groupes  = $dataForm['groupes'] ?? [];

            if (empty($data) || empty($data['id_campagne'])) {
                throw new \Exception(Text::_('COM_GDA_SAISONS_FORM_INVALID'), 500);
            }

            $idCampagne = (int) $data['id_campagne'];

            /** @var \NCB\Component\Gda\Site\Model\SaisonsModel $model */
            $model = $this->getModel('saisons', 'site');

            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $groupesService = new GroupesService($db);
            $db->transactionStart();

            try {
                $model->sauvegarderCourante($idCampagne, $data);
                $groupesService->saveGroupes($groupes);
                $db->transactionCommit();
            } catch (\Throwable $e) {
                $db->transactionRollback();
                throw $e;
            }

            $Response->success = true;
            $Response->message = Text::sprintf('COM_GDA_SAISONS_SAVED', $data['titre']);
            $Response->data = base64_encode(
                LayoutHelper::render('saisons.groupes', [
                    'groupes'   => $groupesService->getAllGroupes(),
                    'activites' => $groupesService->getActivitesDisponibles(),
                ])
            );
        } catch (\Throwable $e) {
            $Response->success = false;
            $Response->message = $e->getMessage();
            GdaLogger::error('Erreur lors de la sauvegarde de la saison courante : ' . $e->getMessage());
        }

        echo $Response;
        $app->close();
    }

    /**
     * Ajax : crée une nouvelle saison à partir du formulaire minimal (modal "Ajouter").
     */
    public function ajouter(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        $Response = new JsonResponse();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $dataForm = $app->input->getArray(['jform_saison_ajout' => 'ARRAY']);
            $data = $dataForm['jform_saison_ajout'] ?? [];

            if (empty($data['titre'])) {
                throw new \Exception(Text::_('COM_GDA_SAISONS_TITRE_REQUIRED'), 500);
            }

            /** @var \NCB\Component\Gda\Site\Model\SaisonsModel $model */
            $model = $this->getModel('saisons', 'site');
            $idCampagne = $model->creerSaison($data);
            $saison = $model->getSaison($idCampagne);

            $Response->success = true;
            $Response->message = Text::sprintf('COM_GDA_SAISONS_ADD_SAVED', $saison->titre);
            $Response->data = base64_encode(
                LayoutHelper::render('saisons.ligne', ['item' => $saison])
            );
        } catch (\Throwable $e) {
            $Response->success = false;
            $Response->message = $e->getMessage();
            GdaLogger::error('Erreur lors de la création d\'une saison : ' . $e->getMessage());
        }

        echo $Response;
        $app->close();
    }

    /**
     * Ajax : ouvre ou ferme une saison aux inscriptions (une seule saison ouverte à la fois).
     */
    public function toggleActive(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        $Response = new JsonResponse();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $idCampagne = $app->input->getInt('id_campagne', 0);
            $active = (bool) $app->input->getInt('active', 0);

            if ($idCampagne <= 0) {
                throw new \Exception(Text::_('COM_GDA_SAISONS_FORM_INVALID'), 500);
            }

            /** @var \NCB\Component\Gda\Site\Model\SaisonsModel $model */
            $model = $this->getModel('saisons', 'site');
            $model->toggleActive($idCampagne, $active);
            $saison = $model->getSaison($idCampagne);

            $Response->success = true;
            $Response->message = $active
                ? Text::sprintf('COM_GDA_CAMPAGNE_OPENED', $saison->titre)
                : Text::sprintf('COM_GDA_CAMPAGNE_CLOSED', $saison->titre);
            $Response->data = base64_encode(
                LayoutHelper::render('saisons.ligne', ['item' => $saison])
            );
        } catch (\Throwable $e) {
            $Response->success = false;
            $Response->message = $e->getMessage();
            GdaLogger::error('Erreur lors du changement d\'état d\'ouverture d\'une saison : ' . $e->getMessage());
        }

        echo $Response;
        $app->close();
    }

    /**
     * Ajax : déclare ou retire une saison comme saison courante (une seule à la fois).
     */
    public function toggleCourante(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        $Response = new JsonResponse();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $idCampagne = $app->input->getInt('id_campagne', 0);
            $courante = (bool) $app->input->getInt('courante', 0);

            if ($idCampagne <= 0) {
                throw new \Exception(Text::_('COM_GDA_SAISONS_FORM_INVALID'), 500);
            }

            /** @var \NCB\Component\Gda\Site\Model\SaisonsModel $model */
            $model = $this->getModel('saisons', 'site');
            $model->toggleCourante($idCampagne, $courante);
            $saison = $model->getSaison($idCampagne);

            $Response->success = true;
            $Response->message = $courante
                ? Text::sprintf('COM_GDA_CAMPAGNE_COURANTE_DECLAREE', $saison->titre)
                : Text::sprintf('COM_GDA_CAMPAGNE_COURANTE_RETIREE', $saison->titre);

            // Contrairement à toggleActive (qui ne concerne jamais qu'une seule ligne),
            // déclarer une nouvelle saison courante ferme automatiquement l'ancienne : deux
            // lignes peuvent changer d'état. On renvoie donc la liste complète re-rendue plutôt
            // qu'une seule <tr>, pour que le tableau reste synchronisé avec la base.
            $rowsHtml = '';
            foreach ($model->getListeSaisons() as $ligneSaison) {
                $rowsHtml .= LayoutHelper::render('saisons.ligne', ['item' => $ligneSaison]);
            }
            $Response->data = base64_encode($rowsHtml);
        } catch (\Throwable $e) {
            $Response->success = false;
            $Response->message = $e->getMessage();
            GdaLogger::error('Erreur lors du changement de saison courante : ' . $e->getMessage());
        }

        echo $Response;
        $app->close();
    }

    /**
     * Ajax : édition inline d'une case du référentiel tarifaire (onglet « Tarification »).
     *
     * Renvoie la ligne entière re-rendue plutôt que la valeur enregistrée : le tableau affiche
     * des données dérivées (montants formatés, aperçu du suffixe « [Hors Agglo] », badge
     * actif/inactif) dont le calcul appartient au serveur — le JS n'a jamais à formater un
     * montant ni à dupliquer la règle du suffixe.
     *
     * @return void
     */
    public function updateTarif(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();
        $Response = new JsonResponse();

        try {
            $this->checkToken();
            $this->guardBureauMember();

            $idTarif = $app->input->getInt('id_tarif', 0);
            $champ   = $app->input->getCmd('champ', '');
            // getRaw et non getString : le filtre 'string' supprime silencieusement tout ce qui
            // ressemble à une balise, si bien qu'un libellé légitime comme « Jeunes <18 ans »
            // serait tronqué à « Jeunes » sans que le Bureau en soit averti. La protection XSS
            // est assurée à l'affichage, où tous les points de sortie du libellé échappent
            // (6 layouts via $this->escape(), htmlspecialchars() dans FormController pour le
            // récapitulatif injecté en innerHTML, textContent côté secretariat.js).
            $valeur  = (string) $app->input->getRaw('valeur', '');

            /** @var \NCB\Component\Gda\Site\Model\SaisonsModel $model */
            $model = $this->getModel('saisons', 'site');
            $tarif = $model->updateTarif($idTarif, $champ, $valeur);

            $Response->success = true;
            $Response->message = Text::_('COM_GDA_SAISONS_TARIF_UPDATED');

            // Désactiver un tarif ne retire l'option correspondante du formulaire d'adhésion que
            // si plus aucun tarif actif ne la porte (A et D partagent « Normal », B et E
            // « Réduction Famille »). On le dit explicitement plutôt que de laisser le Bureau
            // constater que le formulaire n'a pas changé.
            if ($champ === 'actif' && (int) $tarif->actif === 0 && $tarif->reduction !== null) {
                $autresActifs = CotisationService::getLignesActivesPourReduction(
                    (int) $tarif->reduction,
                    (int) $tarif->id_tarif
                );

                if ($autresActifs !== []) {
                    $Response->message .= ' ' . Text::sprintf(
                        'COM_GDA_SAISONS_TARIF_WARN_OPTION_PARTAGEE',
                        CotisationService::getLibelleOption((int) $tarif->reduction),
                        implode(', ', $autresActifs)
                    );
                }
            }

            // Modifier un libellé d'option touche toutes les lignes qui partagent cette option
            // (A/D, B/E) : on renvoie alors le référentiel entier re-rendu, le JS remplaçant
            // chaque <tr> par son id. Même motif que toggleCourante(), qui fait déjà changer
            // deux lignes d'un coup.
            $lignes = $champ === 'option_libelle'
                ? $model->getTarifs()
                : [$tarif];

            $html = '';

            foreach ($lignes as $ligne) {
                $html .= LayoutHelper::render('saisons.tarification_ligne', ['tarif' => $ligne]);
            }

            $Response->data = base64_encode($html);

            GdaLogger::info(sprintf(
                'Tarif mis à jour (id_tarif=%d, code=%s, champ=%s).',
                (int) $tarif->id_tarif,
                $tarif->code,
                $champ
            ));
        } catch (\Throwable $e) {
            $Response->success = false;
            $Response->message = $e->getMessage();
            GdaLogger::error('Erreur lors de la mise à jour d\'un tarif : ' . $e->getMessage());
        }

        echo $Response;
        $app->close();
    }
}
