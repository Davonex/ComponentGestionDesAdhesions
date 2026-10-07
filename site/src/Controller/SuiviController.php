<?php

namespace NCB\Component\Gda\Site\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Response\JsonResponse;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Model\SuiviModel;

/**
 * Tasks ajax de la vue Suivi : tableau de suivi d'un groupe, formulaire d'évaluation d'un élève
 * pour une séance, sauvegarde de cette évaluation et bilan de l'évaluation d'un élève.
 *
 * Toutes les tasks sont réservées aux Moniteurs, Responsables de Groupe et membres du Bureau (même
 * garde que View/Suivi/HtmlView.php::display()) ; le droit de modifier une évaluation existante
 * (auteur ou Responsable de Groupe) est contrôlé par SuiviService.
 */
class SuiviController extends AjaxController
{
    /**
     * Ajax : tableau élèves x séances d'un groupe pour la saison courante.
     *
     * Paramètres : id_groupe, seances (dates Y-m-d séparées par des virgules, ajoutées à l'écran
     * et pas encore enregistrées).
     *
     * @return void
     */
    public function tableau(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardEncadrant();

            $idGroupe        = $this->input->getInt('id_groupe', 0);
            $seancesAjoutees = array_filter(explode(',', $this->input->getString('seances', '')));

            $tableau = $this->getSuiviModel()->getTableauSuivi($idGroupe, $this->getIdSaisonCourante(), $seancesAjoutees);

            $Response = new JsonResponse();
            $Response->success = true;
            $Response->data = base64_encode(LayoutHelper::render('suivi.tableau', ['tableau' => $tableau]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Ajax : formulaire d'évaluation d'un élève pour une séance (contenu de la popup).
     *
     * Paramètres : id_groupe, id_profil, date_seance (Y-m-d).
     *
     * @return void
     */
    public function formulaire(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardEncadrant();

            $formulaire = $this->getSuiviModel()->getFormulaireEvaluation(
                $this->input->getInt('id_profil', 0),
                $this->input->getInt('id_groupe', 0),
                $this->getIdSaisonCourante(),
                $this->input->getString('date_seance', ''),
                (int) $app->getIdentity()->id,
                UsersHelper::isResponsableGroupe()
            );

            $Response = new JsonResponse();
            $Response->success = true;
            $Response->data = base64_encode(LayoutHelper::render('suivi.formulaire', ['formulaire' => $formulaire]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Ajax : bilan de l'évaluation d'un élève (compétences x séances, contenu de la popup).
     *
     * Paramètres : id_groupe, id_profil.
     *
     * @return void
     */
    public function bilan(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardEncadrant();

            $bilan = $this->getSuiviModel()->getBilanEleve(
                $this->input->getInt('id_profil', 0),
                $this->input->getInt('id_groupe', 0),
                $this->getIdSaisonCourante()
            );

            $Response = new JsonResponse();
            $Response->success = true;
            $Response->data = base64_encode(LayoutHelper::render('suivi.bilan', ['bilan' => $bilan]));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Ajax : enregistre l'évaluation d'un élève pour une séance et renvoie la case du tableau
     * mise à jour.
     *
     * Paramètres : id_groupe, id_profil, date_seance (Y-m-d),
     * competences[id_competence][appreciation|observation].
     *
     * @return void
     */
    public function sauver(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardEncadrant();

            $model      = $this->getSuiviModel();
            $idProfil   = $this->input->getInt('id_profil', 0);
            $idGroupe   = $this->input->getInt('id_groupe', 0);
            $dateSeance = $this->input->getString('date_seance', '');
            $idCampagne = $this->getIdSaisonCourante();

            // Observations lues brutes : la longueur est contrôlée par le service et le texte est
            // échappé à l'affichage (un filtre de saisie supprimerait tout ce qui ressemble à une balise).
            $saisies = $this->input->post->get('competences', [], 'array');

            $nbEnregistrees = $model->sauverEvaluation(
                $idProfil,
                $idGroupe,
                $idCampagne,
                $dateSeance,
                \is_array($saisies) ? $saisies : [],
                (int) $app->getIdentity()->id,
                UsersHelper::isResponsableGroupe()
            );

            $Response = new JsonResponse();
            $Response->success = true;
            $Response->message = $nbEnregistrees > 0
                ? Text::plural('COM_GDA_SUIVI_EVALUATION_ENREGISTREE', $nbEnregistrees)
                : Text::_('COM_GDA_SUIVI_EVALUATION_INCHANGEE');
            $Response->data = base64_encode(LayoutHelper::render(
                'suivi.cellule',
                $model->getCellule($idProfil, $idGroupe, $idCampagne, $dateSeance)
            ));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Ajax : onglet « Compétences » (référentiel des compétences et techniques par niveau).
     *
     * @return void
     */
    public function competences(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardResponsableGroupe();

            $Response = new JsonResponse();
            $Response->success = true;
            $Response->data = base64_encode(LayoutHelper::render(
                'suivi.competences',
                ['referentiel' => $this->getSuiviModel()->getReferentielCompetences()]
            ));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Ajax : ajoute une compétence au niveau donné et renvoie sa ligne.
     *
     * Paramètre : id_groupe.
     *
     * @return void
     */
    public function ajouterCompetence(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardResponsableGroupe();

            $ligne = $this->getSuiviModel()->ajouterCompetence($this->input->getInt('id_groupe', 0));

            $Response = new JsonResponse();
            $Response->success = true;
            $Response->message = Text::_('COM_GDA_SUIVI_COMPETENCE_AJOUTEE');
            $Response->data = base64_encode(LayoutHelper::render('suivi.competence_ligne', $ligne));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Ajax : modifie un champ d'une compétence (édition en ligne) et renvoie sa ligne re-rendue.
     *
     * Paramètres : id_competence, champ, valeur (lue brute : le libellé et les techniques sont
     * échappés à l'affichage, un filtre de saisie supprimerait tout ce qui ressemble à une balise).
     *
     * @return void
     */
    public function modifierCompetence(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app = Factory::getApplication();

        try {
            $this->checkToken();
            $this->guardResponsableGroupe();

            $ligne = $this->getSuiviModel()->modifierCompetence(
                $this->input->getInt('id_competence', 0),
                $this->input->getCmd('champ', ''),
                (string) $this->input->post->getRaw('valeur', '')
            );

            $Response = new JsonResponse();
            $Response->success = true;
            $Response->message = Text::_('COM_GDA_SUIVI_COMPETENCE_MODIFIEE');
            $Response->data = base64_encode(LayoutHelper::render('suivi.competence_ligne', $ligne));

            echo $Response;
        } catch (\Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Modèle Suivi du site.
     *
     * @return SuiviModel Le modèle.
     */
    private function getSuiviModel(): SuiviModel
    {
        /** @var SuiviModel $model */
        $model = $this->getModel('suivi', 'site');

        return $model;
    }
}
