<?php

/**
 * @package     com_gdadhesions
 * @subpackage  components
 * @copyright   Copyright (C) 2024 GD Adhesions. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Service\GroupesService;
use NCB\Component\Gda\Site\Service\SuiviService;

/**
 * Suivi de formation : tableau élèves x séances d'un groupe et formulaire d'évaluation des
 * compétences. Façade sur SuiviService, qui porte les règles métier.
 */
class SuiviModel extends ListModel
{
    /**
     * Model context string.
     *
     * @var    string
     */
    protected $context = 'com_gdadhesions.suivi';

    private ?SuiviService $suiviService = null;

    /**
     * Groupes évalués (un onglet par groupe).
     *
     * @return array<int, object> Liste {id_groupe, groupe_name, icon}.
     */
    public function getGroupesEvalues(): array
    {
        return $this->getSuiviService()->getGroupesEvalues();
    }

    /**
     * Données du tableau de suivi d'un groupe : élèves, séances (dates déjà évaluées plus celles
     * ajoutées à l'écran et pas encore enregistrées) et nombre de compétences évaluées par case.
     *
     * @param int      $idGroupe        Groupe de formation.
     * @param int      $idCampagne      Saison.
     * @param string[] $seancesAjoutees Dates (Y-m-d) ajoutées par le bouton « Ajouter une séance ».
     * @return object {id_groupe, nb_competences, eleves, seances, synthese}.
     * @throws \RuntimeException 404 si le groupe n'a aucune compétence à évaluer.
     */
    public function getTableauSuivi(int $idGroupe, int $idCampagne, array $seancesAjoutees = []): object
    {
        $service     = $this->getSuiviService();
        $competences = $this->getCompetencesOuEchec($idGroupe);
        $synthese    = $service->getSyntheseSeances($idGroupe, $idCampagne);

        $seances = [];

        foreach ($synthese as $parSeance) {
            foreach (array_keys($parSeance) as $dateSeance) {
                $seances[$dateSeance] = true;
            }
        }

        foreach ($seancesAjoutees as $dateSeance) {
            if ($this->isDateSeanceValide((string) $dateSeance)) {
                $seances[(string) $dateSeance] = true;
            }
        }

        $seances = array_keys($seances);
        sort($seances);

        $tableau = new \stdClass();
        $tableau->id_groupe      = $idGroupe;
        $tableau->nb_competences = \count($competences);
        $tableau->eleves         = $service->getEleves($idGroupe, $idCampagne);
        $tableau->seances        = $seances;
        $tableau->synthese       = $synthese;

        return $tableau;
    }

    /**
     * Données du formulaire d'évaluation d'un élève pour une séance.
     *
     * @param int    $idProfil       Élève.
     * @param int    $idGroupe       Groupe de formation.
     * @param int    $idCampagne     Saison.
     * @param string $dateSeance     Date de la séance (Y-m-d).
     * @param int    $idUtilisateur  Compte Joomla connecté.
     * @param bool   $estResponsable Vrai si l'utilisateur est Responsable de Groupe.
     * @return object {eleve, id_groupe, date_seance, appreciations, competences[] {id_competence, competence, evaluation, modifiable}}.
     * @throws \InvalidArgumentException 400 si la date de séance est invalide.
     * @throws \RuntimeException 404 si l'élève n'est pas dans le groupe ou si le groupe n'a aucune compétence.
     */
    public function getFormulaireEvaluation(
        int $idProfil,
        int $idGroupe,
        int $idCampagne,
        string $dateSeance,
        int $idUtilisateur,
        bool $estResponsable
    ): object {
        $this->assertDateSeanceValide($dateSeance);

        $service     = $this->getSuiviService();
        $competences = $this->getCompetencesOuEchec($idGroupe);
        $eleve       = $this->getEleveOuEchec($idProfil, $idGroupe, $idCampagne);
        $evaluations = $service->getEvaluationSeance($idProfil, $idGroupe, $idCampagne, $dateSeance);

        foreach ($competences as $idCompetence => $competence) {
            $competence->evaluation = $evaluations[$idCompetence] ?? null;
            $competence->modifiable = SuiviService::peutModifier($competence->evaluation, $idUtilisateur, $estResponsable);
        }

        $formulaire = new \stdClass();
        $formulaire->eleve         = $eleve;
        $formulaire->id_groupe     = $idGroupe;
        $formulaire->date_seance   = $dateSeance;
        $formulaire->appreciations = SuiviService::getAppreciations();
        $formulaire->competences   = array_values($competences);

        return $formulaire;
    }

    /**
     * Données du bilan d'un élève : compétences en lignes, séances où il a été évalué en
     * colonnes, une appréciation (avec son auteur et son observation) au croisement.
     *
     * @param int $idProfil   Élève.
     * @param int $idGroupe   Groupe de formation.
     * @param int $idCampagne Saison.
     * @return object {eleve, appreciations, competences, seances, evaluations}.
     * @throws \RuntimeException 404 si l'élève n'est pas dans le groupe ou si le groupe n'a aucune compétence.
     */
    public function getBilanEleve(int $idProfil, int $idGroupe, int $idCampagne): object
    {
        $competences = $this->getCompetencesOuEchec($idGroupe);
        $eleve       = $this->getEleveOuEchec($idProfil, $idGroupe, $idCampagne);
        $evaluations = $this->getSuiviService()->getEvaluationsEleve($idProfil, $idGroupe, $idCampagne);

        $seances = [];

        foreach ($evaluations as $parSeance) {
            foreach (array_keys($parSeance) as $dateSeance) {
                $seances[$dateSeance] = true;
            }
        }

        $seances = array_keys($seances);
        sort($seances);

        $bilan = new \stdClass();
        $bilan->eleve         = $eleve;
        $bilan->appreciations = SuiviService::getAppreciations();
        $bilan->competences   = array_values($competences);
        $bilan->seances       = $seances;
        $bilan->evaluations   = $evaluations;

        return $bilan;
    }

    /**
     * Données de l'onglet « Compétences » (Responsables de Groupe) : niveaux proposés, toutes les
     * compétences et niveau sélectionné par défaut dans le filtre.
     *
     * @return object {groupes, competences, id_groupe_defaut}.
     */
    public function getReferentielCompetences(): object
    {
        $competences = $this->getSuiviService()->getReferentielCompetences();

        $referentiel = new \stdClass();
        $referentiel->groupes          = $this->getNiveaux();
        $referentiel->competences      = $competences;
        $referentiel->id_groupe_defaut = $competences !== [] ? (int) $competences[0]->id_groupe : 0;

        return $referentiel;
    }

    /**
     * Ajoute une compétence (inactive, libellé provisoire) à un niveau.
     *
     * @param int $idGroupe Niveau (groupe de formation).
     * @return array Données du layout suivi.competence_ligne pour la nouvelle ligne.
     * @throws \RuntimeException 404 si le niveau n'existe pas, 500 si l'insertion échoue.
     */
    public function ajouterCompetence(int $idGroupe): array
    {
        return $this->getLigneCompetence($this->getSuiviService()->ajouterCompetence($idGroupe));
    }

    /**
     * Modifie un champ d'une compétence et renvoie la ligne à re-rendre.
     *
     * @param int    $idCompetence Compétence.
     * @param string $champ        competence | techniques | ordre | actif | id_groupe.
     * @param string $valeur       Nouvelle valeur saisie.
     * @return array Données du layout suivi.competence_ligne.
     * @throws \InvalidArgumentException 400 si le champ ou la valeur est invalide.
     * @throws \RuntimeException 404 si la compétence ou le niveau n'existe pas, 409 si le niveau d'une compétence évaluée est changé.
     */
    public function modifierCompetence(int $idCompetence, string $champ, string $valeur): array
    {
        $this->getSuiviService()->modifierCompetence($idCompetence, $champ, $valeur);

        return $this->getLigneCompetence($idCompetence);
    }

    /**
     * Données d'une ligne du référentiel des compétences.
     *
     * @param int $idCompetence Compétence.
     * @return array ['competence' => object, 'groupes' => array].
     * @throws \RuntimeException 404 si la compétence n'existe pas.
     */
    private function getLigneCompetence(int $idCompetence): array
    {
        $competences = $this->getSuiviService()->getReferentielCompetences($idCompetence);

        if ($competences === []) {
            throw new \RuntimeException(Text::_('COM_GDA_SUIVI_ERR_COMPETENCE_INCONNUE'), 404);
        }

        return ['competence' => $competences[0], 'groupes' => $this->getNiveaux()];
    }

    /**
     * Niveaux proposés pour une compétence : les groupes publiés.
     *
     * @return array<int, object> Groupes {id_groupe, groupe_name}.
     */
    private function getNiveaux(): array
    {
        $groupes = (new GroupesService($this->getDatabase()))->getAllGroupes();

        return array_values(array_filter($groupes, static fn (object $groupe): bool => (int) $groupe->published === 1));
    }

    /**
     * Enregistre l'évaluation d'un élève pour une séance.
     *
     * @param int    $idProfil       Élève.
     * @param int    $idGroupe       Groupe de formation.
     * @param int    $idCampagne     Saison.
     * @param string $dateSeance     Date de la séance (Y-m-d).
     * @param array  $saisies        id_competence => ['appreciation' => string, 'observation' => string].
     * @param int    $idMoniteur     Compte Joomla du moniteur connecté.
     * @param bool   $estResponsable Vrai si le moniteur est Responsable de Groupe.
     * @return int Nombre de compétences enregistrées.
     * @throws \InvalidArgumentException 400 si la date ou une saisie est invalide.
     * @throws \RuntimeException 403 sans droit de modification, 404 si l'élève n'est pas dans le groupe, 500 si l'écriture échoue.
     */
    public function sauverEvaluation(
        int $idProfil,
        int $idGroupe,
        int $idCampagne,
        string $dateSeance,
        array $saisies,
        int $idMoniteur,
        bool $estResponsable
    ): int {
        $this->assertDateSeanceValide($dateSeance);
        $this->getEleveOuEchec($idProfil, $idGroupe, $idCampagne);

        return $this->getSuiviService()->sauverEvaluation(
            $idProfil,
            $idGroupe,
            $idCampagne,
            $dateSeance,
            $saisies,
            $idMoniteur,
            $estResponsable
        );
    }

    /**
     * Données d'une case du tableau (élève x séance), re-rendue après une sauvegarde.
     *
     * @param int    $idProfil   Élève.
     * @param int    $idGroupe   Groupe de formation.
     * @param int    $idCampagne Saison.
     * @param string $dateSeance Date de la séance (Y-m-d).
     * @return array Données du layout suivi.cellule.
     */
    public function getCellule(int $idProfil, int $idGroupe, int $idCampagne, string $dateSeance): array
    {
        $service     = $this->getSuiviService();
        $competences = $service->getCompetences($idGroupe);
        $evaluations = $service->getEvaluationSeance($idProfil, $idGroupe, $idCampagne, $dateSeance);

        return [
            'id_profil'      => $idProfil,
            'id_groupe'      => $idGroupe,
            'date_seance'    => $dateSeance,
            'nb_evaluees'    => \count(array_intersect_key($evaluations, $competences)),
            'nb_competences' => \count($competences),
        ];
    }

    /**
     * Compétences actives du groupe, ou erreur si le groupe n'est pas évalué.
     *
     * @param int $idGroupe Groupe de formation.
     * @return array<int, object> Compétences indexées par id_competence.
     * @throws \RuntimeException 404 si le groupe n'a aucune compétence active.
     */
    private function getCompetencesOuEchec(int $idGroupe): array
    {
        $competences = $this->getSuiviService()->getCompetences($idGroupe);

        if ($competences === []) {
            throw new \RuntimeException(Text::_('COM_GDA_SUIVI_ERR_GROUPE_INCONNU'), 404);
        }

        return $competences;
    }

    /**
     * Élève du groupe pour la saison, ou erreur s'il n'en fait pas partie.
     *
     * @param int $idProfil   Élève.
     * @param int $idGroupe   Groupe de formation.
     * @param int $idCampagne Saison.
     * @return object Élève {id_profil, nom, prenom, photo}.
     * @throws \RuntimeException 404 si l'adhérent n'est pas dans le groupe.
     */
    private function getEleveOuEchec(int $idProfil, int $idGroupe, int $idCampagne): object
    {
        foreach ($this->getSuiviService()->getEleves($idGroupe, $idCampagne) as $eleve) {
            if ((int) $eleve->id_profil === $idProfil) {
                return $eleve;
            }
        }

        throw new \RuntimeException(Text::_('COM_GDA_SUIVI_ERR_ELEVE_INCONNU'), 404);
    }

    /**
     * Indique si une date de séance est valide : format Y-m-d et pas dans le futur.
     *
     * @param string $dateSeance Date à contrôler.
     * @return bool Vrai si la date est utilisable.
     */
    private function isDateSeanceValide(string $dateSeance): bool
    {
        return ToolsHelper::isValidSqlDate($dateSeance) && $dateSeance <= ToolsHelper::now('Y-m-d');
    }

    /**
     * Refuse une date de séance invalide ou future.
     *
     * @param string $dateSeance Date à contrôler.
     * @return void
     * @throws \InvalidArgumentException 400 si la date est invalide ou future.
     */
    private function assertDateSeanceValide(string $dateSeance): void
    {
        if (!$this->isDateSeanceValide($dateSeance)) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_SUIVI_ERR_DATE_SEANCE'), 400);
        }
    }

    /**
     * Getter pour obtenir le service Suivi (lazy loading). Même pattern que GroupesModel::getBrevetService().
     *
     * @return SuiviService Le service de suivi de formation.
     */
    private function getSuiviService(): SuiviService
    {
        if ($this->suiviService === null) {
            $this->suiviService = new SuiviService($this->getDatabase());
        }

        return $this->suiviService;
    }
}
