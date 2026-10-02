<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * Service métier du suivi de formation : évaluation, séance après séance, des compétences de
 * chaque élève d'un groupe de formation (Prépa N1 pour commencer).
 *
 * Données :
 *  - #__gda_competences : référentiel des compétences, par groupe (`actif` = 0 retire une
 *    compétence du formulaire sans perdre l'historique) ;
 *  - #__gda_suivi_competences : une ligne par (saison, élève, compétence, séance), avec
 *    l'appréciation, une observation facultative, la date et l'auteur de la dernière modification.
 *
 * Il n'y a pas de table des séances : une séance existe dès qu'une évaluation y est enregistrée
 * (les colonnes de la vue Suivi sont les dates déjà évaluées pour le groupe).
 *
 * Règles portées ici :
 *  - une appréciation ne s'efface pas, elle se modifie ;
 *  - seul l'auteur d'une évaluation ou un Responsable de Groupe peut la modifier (peutModifier()) ;
 *  - une compétence sans appréciation n'est pas enregistrée (une observation seule est refusée).
 */
final class SuiviService
{
    public const APPRECIATION_EN_COURS = 'en_cours';
    public const APPRECIATION_ACQUIS   = 'acquis';
    public const APPRECIATION_MAITRISE = 'maitrise';

    public const OBSERVATION_LONGUEUR_MAX = 250;
    public const COMPETENCE_LONGUEUR_MAX  = 150;
    public const TECHNIQUES_LONGUEUR_MAX  = 2000;

    /** Valeur stockée => clé de langue, dans l'ordre de progression. */
    private const APPRECIATIONS = [
        self::APPRECIATION_EN_COURS => 'COM_GDA_SUIVI_APPRECIATION_EN_COURS',
        self::APPRECIATION_ACQUIS   => 'COM_GDA_SUIVI_APPRECIATION_ACQUIS',
        self::APPRECIATION_MAITRISE => 'COM_GDA_SUIVI_APPRECIATION_MAITRISE',
    ];

    private DatabaseInterface $db;

    /**
     * @param DatabaseInterface $db Connexion base de données (celle du composant).
     */
    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Liste des appréciations proposées, dans l'ordre de progression.
     *
     * @return array<string, string> Valeur stockée => libellé traduit.
     */
    public static function getAppreciations(): array
    {
        return array_map(static fn (string $cle): string => Text::_($cle), self::APPRECIATIONS);
    }

    /**
     * Indique si l'utilisateur peut modifier une évaluation déjà enregistrée : son auteur, ou un
     * Responsable de Groupe. Une compétence encore jamais évaluée est toujours modifiable.
     *
     * @param object|null $evaluation    Évaluation existante (propriété id_moniteur), null si aucune.
     * @param int         $idUtilisateur Compte Joomla connecté.
     * @param bool        $estResponsable Vrai si l'utilisateur est Responsable de Groupe.
     * @return bool Vrai si la modification est permise.
     */
    public static function peutModifier(?object $evaluation, int $idUtilisateur, bool $estResponsable): bool
    {
        return $evaluation === null
            || $estResponsable
            || (int) $evaluation->id_moniteur === $idUtilisateur;
    }

    /**
     * Groupes publiés qui portent au moins une compétence active : un onglet par groupe dans la vue.
     *
     * @return array<int, object> Liste {id_groupe, groupe_name, icon}, dans l'ordre des groupes.
     */
    public function getGroupesEvalues(): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['g.id_groupe', 'g.groupe_name', 'g.icon']))
            ->from($this->db->quoteName('#__gda_groupes', 'g'))
            ->where($this->db->quoteName('g.published') . ' = 1')
            ->where(
                'EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__gda_competences', 'c')
                . ' WHERE ' . $this->db->quoteName('c.id_groupe') . ' = ' . $this->db->quoteName('g.id_groupe')
                . ' AND ' . $this->db->quoteName('c.actif') . ' = 1)'
            )
            ->order($this->db->quoteName('g.groupe_tri') . ' ASC');

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Compétences actives d'un groupe, dans l'ordre d'affichage.
     *
     * @param int $idGroupe Groupe de formation.
     * @return array<int, object> Compétences {id_competence, competence, techniques (string[])}, indexées par id_competence.
     */
    public function getCompetences(int $idGroupe): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id_competence', 'competence', 'techniques']))
            ->from($this->db->quoteName('#__gda_competences'))
            ->where($this->db->quoteName('id_groupe') . ' = :id_groupe')
            ->where($this->db->quoteName('actif') . ' = 1')
            ->order($this->db->quoteName('ordre') . ' ASC')
            ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $competences = $this->db->loadObjectList('id_competence') ?: [];

        foreach ($competences as $competence) {
            $competence->techniques = self::decouperTechniques($competence->techniques);
        }

        return $competences;
    }

    /**
     * Référentiel complet des compétences (actives ou non), pour l'onglet d'administration réservé
     * aux Responsables de Groupe, trié par groupe puis par ordre d'affichage.
     *
     * @param int|null $idCompetence Limite à une compétence (re-rendu d'une ligne), null pour toutes.
     * @return array<int, object> {id_competence, competence, techniques (texte brut), liste_techniques (string[]), id_groupe, groupe_name, ordre, actif}.
     */
    public function getReferentielCompetences(?int $idCompetence = null): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName([
                'c.id_competence', 'c.competence', 'c.techniques', 'c.id_groupe', 'c.ordre', 'c.actif', 'g.groupe_name',
            ]))
            ->from($this->db->quoteName('#__gda_competences', 'c'))
            ->join('INNER', $this->db->quoteName('#__gda_groupes', 'g') . ' ON ' . $this->db->quoteName('g.id_groupe') . ' = ' . $this->db->quoteName('c.id_groupe'))
            ->order($this->db->quoteName(['g.groupe_tri', 'c.ordre', 'c.id_competence']));

        if ($idCompetence !== null) {
            $query->where($this->db->quoteName('c.id_competence') . ' = :id_competence')
                ->bind(':id_competence', $idCompetence, ParameterType::INTEGER);
        }

        $this->db->setQuery($query);

        $competences = $this->db->loadObjectList() ?: [];

        foreach ($competences as $competence) {
            $competence->liste_techniques = self::decouperTechniques($competence->techniques);
        }

        return $competences;
    }

    /**
     * Ajoute une compétence à un groupe : libellé provisoire, dernière position, inactive tant que
     * le responsable ne l'a pas complétée (elle n'apparaît pas encore dans les évaluations).
     *
     * @param int $idGroupe Groupe de formation.
     * @return int Identifiant de la compétence créée.
     * @throws \RuntimeException 404 si le groupe n'existe pas, 500 si l'insertion échoue.
     */
    public function ajouterCompetence(int $idGroupe): int
    {
        $this->assertGroupeExiste($idGroupe);

        $query = $this->db->createQuery()
            ->select('COALESCE(MAX(' . $this->db->quoteName('ordre') . '), 0) + 1')
            ->from($this->db->quoteName('#__gda_competences'))
            ->where($this->db->quoteName('id_groupe') . ' = :id_groupe')
            ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $ordre = (int) $this->db->loadResult();
        $libelle = Text::_('COM_GDA_SUIVI_COMPETENCE_NOUVELLE');

        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__gda_competences'))
            ->columns($this->db->quoteName(['competence', 'id_groupe', 'ordre', 'actif']))
            ->values(':competence, :id_groupe, :ordre, 0')
            ->bind(':competence', $libelle)
            ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER)
            ->bind(':ordre', $ordre, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return (int) $this->db->insertid();
    }

    /**
     * Modifie un champ d'une compétence (édition en ligne de l'onglet d'administration).
     *
     * Champs modifiables : competence (1 à 150 caractères), techniques (une par ligne, 2000
     * caractères au plus, vide = aucune), ordre (0 à 9999), actif (0/1) et id_groupe (groupe
     * existant, refusé si la compétence a déjà été évaluée : ses évaluations changeraient de niveau).
     *
     * @param int    $idCompetence Compétence.
     * @param string $champ        Nom du champ.
     * @param string $valeur       Nouvelle valeur saisie.
     * @return void
     * @throws \InvalidArgumentException 400 si le champ ou la valeur est invalide.
     * @throws \RuntimeException 404 si la compétence ou le groupe n'existe pas, 409 si le niveau d'une compétence évaluée est changé.
     */
    public function modifierCompetence(int $idCompetence, string $champ, string $valeur): void
    {
        if ($this->getReferentielCompetences($idCompetence) === []) {
            throw new \RuntimeException(Text::_('COM_GDA_SUIVI_ERR_COMPETENCE_INCONNUE'), 404);
        }

        $valeur = trim($valeur);
        $type = ParameterType::STRING;

        switch ($champ) {
            case 'competence':
                if ($valeur === '' || mb_strlen($valeur) > self::COMPETENCE_LONGUEUR_MAX) {
                    throw new \InvalidArgumentException(Text::sprintf('COM_GDA_SUIVI_ERR_COMPETENCE_LIBELLE', self::COMPETENCE_LONGUEUR_MAX), 400);
                }
                break;

            case 'techniques':
                $valeur = implode("\n", array_map('trim', preg_split('/\R/u', $valeur) ?: []));

                if (mb_strlen($valeur) > self::TECHNIQUES_LONGUEUR_MAX) {
                    throw new \InvalidArgumentException(Text::sprintf('COM_GDA_SUIVI_ERR_TECHNIQUES_TROP_LONGUES', self::TECHNIQUES_LONGUEUR_MAX), 400);
                }

                if ($valeur === '') {
                    $valeur = null;
                    $type = ParameterType::NULL;
                }
                break;

            case 'ordre':
                if (!preg_match('/^\d{1,4}$/', $valeur)) {
                    throw new \InvalidArgumentException(Text::_('COM_GDA_SUIVI_ERR_COMPETENCE_ORDRE'), 400);
                }
                $valeur = (int) $valeur;
                $type = ParameterType::INTEGER;
                break;

            case 'actif':
                if (!\in_array($valeur, ['0', '1'], true)) {
                    throw new \InvalidArgumentException(Text::_('COM_GDA_SUIVI_ERR_CHAMP_INVALIDE'), 400);
                }
                $valeur = (int) $valeur;
                $type = ParameterType::INTEGER;
                break;

            case 'id_groupe':
                $valeur = (int) $valeur;
                $this->assertGroupeExiste($valeur);

                if ($this->compterEvaluations($idCompetence) > 0) {
                    throw new \RuntimeException(Text::_('COM_GDA_SUIVI_ERR_COMPETENCE_EVALUEE'), 409);
                }

                $type = ParameterType::INTEGER;
                break;

            default:
                throw new \InvalidArgumentException(Text::_('COM_GDA_SUIVI_ERR_CHAMP_INVALIDE'), 400);
        }

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__gda_competences'))
            ->set($this->db->quoteName($champ) . ' = :valeur')
            ->where($this->db->quoteName('id_competence') . ' = :id_competence')
            ->bind(':valeur', $valeur, $type)
            ->bind(':id_competence', $idCompetence, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Nombre d'évaluations enregistrées pour une compétence (toutes saisons).
     *
     * @param int $idCompetence Compétence.
     * @return int Nombre de lignes de #__gda_suivi_competences.
     */
    private function compterEvaluations(int $idCompetence): int
    {
        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__gda_suivi_competences'))
            ->where($this->db->quoteName('id_competence') . ' = :id_competence')
            ->bind(':id_competence', $idCompetence, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * Refuse un groupe inexistant.
     *
     * @param int $idGroupe Groupe de formation.
     * @return void
     * @throws \RuntimeException 404 si le groupe n'existe pas.
     */
    private function assertGroupeExiste(int $idGroupe): void
    {
        $query = $this->db->createQuery()
            ->select('1')
            ->from($this->db->quoteName('#__gda_groupes'))
            ->where($this->db->quoteName('id_groupe') . ' = :id_groupe')
            ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER);

        $this->db->setQuery($query, 0, 1);

        if (!$this->db->loadResult()) {
            throw new \RuntimeException(Text::_('COM_GDA_SUIVI_ERR_NIVEAU_INCONNU'), 404);
        }
    }

    /**
     * Découpe le texte des techniques d'une compétence (une par ligne, puce « - » ou « • »
     * facultative) en liste.
     *
     * @param string|null $techniques Texte stocké dans #__gda_competences.techniques.
     * @return string[] Techniques non vides, dans l'ordre de saisie.
     */
    private static function decouperTechniques(?string $techniques): array
    {
        $lignes = preg_split('/\R/u', (string) $techniques) ?: [];
        $lignes = array_map(static fn (string $ligne): string => trim(preg_replace('/^\s*[-•*]\s*/u', '', $ligne)), $lignes);

        return array_values(array_filter($lignes, static fn (string $ligne): bool => $ligne !== ''));
    }

    /**
     * Élèves d'un groupe pour une saison (composition des groupes), triés par nom puis prénom.
     *
     * @param int $idGroupe   Groupe de formation.
     * @param int $idCampagne Saison.
     * @return array<int, object> Élèves {id_profil, nom, prenom, photo}.
     */
    public function getEleves(int $idGroupe, int $idCampagne): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['p.id_profil', 'p.nom', 'p.prenom', 'p.photo']))
            ->from($this->db->quoteName('#__gda_composition_groupes', 'cg'))
            ->join('INNER', $this->db->quoteName('#__gda_profils', 'p') . ' ON ' . $this->db->quoteName('p.id_profil') . ' = ' . $this->db->quoteName('cg.id_profil'))
            ->where($this->db->quoteName('cg.id_groupe') . ' = :id_groupe')
            ->where($this->db->quoteName('cg.id_campagne') . ' = :id_campagne')
            ->order($this->db->quoteName(['p.nom', 'p.prenom']))
            ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Indique si un adhérent fait partie d'un groupe pour une saison.
     *
     * @param int $idProfil   Adhérent.
     * @param int $idGroupe   Groupe de formation.
     * @param int $idCampagne Saison.
     * @return bool Vrai s'il est inscrit dans le groupe.
     */
    public function isEleveDuGroupe(int $idProfil, int $idGroupe, int $idCampagne): bool
    {
        $query = $this->db->createQuery()
            ->select('1')
            ->from($this->db->quoteName('#__gda_composition_groupes'))
            ->where($this->db->quoteName('id_profil') . ' = :id_profil')
            ->where($this->db->quoteName('id_groupe') . ' = :id_groupe')
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER)
            ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

        $this->db->setQuery($query, 0, 1);

        return (bool) $this->db->loadResult();
    }

    /**
     * Nombre de compétences actives évaluées par élève et par séance, pour un groupe et une saison,
     * en une seule requête groupée (alimente toutes les cellules de la vue).
     *
     * @param int $idGroupe   Groupe de formation.
     * @param int $idCampagne Saison.
     * @return array<int, array<string, int>> id_profil => [date_seance (Y-m-d) => nombre évalué].
     */
    public function getSyntheseSeances(int $idGroupe, int $idCampagne): array
    {
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('s.id_profil'),
                $this->db->quoteName('s.date_seance'),
                'COUNT(*) AS ' . $this->db->quoteName('nb_evaluees'),
            ])
            ->from($this->db->quoteName('#__gda_suivi_competences', 's'))
            ->join(
                'INNER',
                $this->db->quoteName('#__gda_competences', 'c')
                    . ' ON ' . $this->db->quoteName('c.id_competence') . ' = ' . $this->db->quoteName('s.id_competence')
            )
            ->where($this->db->quoteName('s.id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('c.id_groupe') . ' = :id_groupe')
            ->where($this->db->quoteName('c.actif') . ' = 1')
            ->group($this->db->quoteName(['s.id_profil', 's.date_seance']))
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $synthese = [];

        foreach ($this->db->loadObjectList() ?: [] as $ligne) {
            $synthese[(int) $ligne->id_profil][(string) $ligne->date_seance] = (int) $ligne->nb_evaluees;
        }

        return $synthese;
    }

    /**
     * Évaluations d'un élève pour une séance, avec le nom de leur auteur.
     *
     * @param int    $idProfil   Élève.
     * @param int    $idGroupe   Groupe de formation (limite aux compétences du groupe).
     * @param int    $idCampagne Saison.
     * @param string $dateSeance Date de la séance (Y-m-d).
     * @return array<int, object> id_competence => {appreciation, observation, date_evaluation, id_moniteur, moniteur}.
     */
    public function getEvaluationSeance(int $idProfil, int $idGroupe, int $idCampagne, string $dateSeance): array
    {
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('s.id_competence'),
                $this->db->quoteName('s.appreciation'),
                $this->db->quoteName('s.observation'),
                $this->db->quoteName('s.date_evaluation'),
                $this->db->quoteName('s.id_moniteur'),
                $this->db->quoteName('u.name', 'moniteur'),
            ])
            ->from($this->db->quoteName('#__gda_suivi_competences', 's'))
            ->join(
                'INNER',
                $this->db->quoteName('#__gda_competences', 'c')
                    . ' ON ' . $this->db->quoteName('c.id_competence') . ' = ' . $this->db->quoteName('s.id_competence')
            )
            ->join('LEFT', $this->db->quoteName('#__users', 'u') . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('s.id_moniteur'))
            ->where($this->db->quoteName('s.id_profil') . ' = :id_profil')
            ->where($this->db->quoteName('s.id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('s.date_seance') . ' = :date_seance')
            ->where($this->db->quoteName('c.id_groupe') . ' = :id_groupe')
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':date_seance', $dateSeance)
            ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadObjectList('id_competence') ?: [];
    }

    /**
     * Toutes les évaluations d'un élève pour un groupe et une saison, avec le nom de leur auteur
     * (alimente le récapitulatif compétences x séances de l'élève).
     *
     * @param int $idProfil   Élève.
     * @param int $idGroupe   Groupe de formation (limite aux compétences actives du groupe).
     * @param int $idCampagne Saison.
     * @return array<int, array<string, object>> id_competence => [date_seance (Y-m-d) => {appreciation, observation, moniteur}].
     */
    public function getEvaluationsEleve(int $idProfil, int $idGroupe, int $idCampagne): array
    {
        $query = $this->db->createQuery()
            ->select([
                $this->db->quoteName('s.id_competence'),
                $this->db->quoteName('s.date_seance'),
                $this->db->quoteName('s.appreciation'),
                $this->db->quoteName('s.observation'),
                $this->db->quoteName('u.name', 'moniteur'),
            ])
            ->from($this->db->quoteName('#__gda_suivi_competences', 's'))
            ->join(
                'INNER',
                $this->db->quoteName('#__gda_competences', 'c')
                    . ' ON ' . $this->db->quoteName('c.id_competence') . ' = ' . $this->db->quoteName('s.id_competence')
            )
            ->join('LEFT', $this->db->quoteName('#__users', 'u') . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('s.id_moniteur'))
            ->where($this->db->quoteName('s.id_profil') . ' = :id_profil')
            ->where($this->db->quoteName('s.id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('c.id_groupe') . ' = :id_groupe')
            ->where($this->db->quoteName('c.actif') . ' = 1')
            ->order($this->db->quoteName('s.date_seance') . ' ASC')
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $evaluations = [];

        foreach ($this->db->loadObjectList() ?: [] as $ligne) {
            $evaluations[(int) $ligne->id_competence][(string) $ligne->date_seance] = $ligne;
        }

        return $evaluations;
    }

    /**
     * Enregistre l'évaluation d'un élève pour une séance, compétence par compétence.
     *
     * Une compétence sans appréciation est ignorée ; une compétence inchangée n'est pas réécrite
     * (son auteur et sa date sont conservés) ; une compétence modifiée prend le moniteur connecté
     * comme auteur. Tout se fait dans une transaction : une erreur n'enregistre rien.
     *
     * @param int    $idProfil       Élève.
     * @param int    $idGroupe       Groupe de formation.
     * @param int    $idCampagne     Saison.
     * @param string $dateSeance     Date de la séance (Y-m-d).
     * @param array  $saisies        id_competence => ['appreciation' => string, 'observation' => string].
     * @param int    $idMoniteur     Compte Joomla du moniteur connecté.
     * @param bool   $estResponsable Vrai si le moniteur est Responsable de Groupe.
     * @return int Nombre de compétences enregistrées (ajoutées ou modifiées).
     * @throws \InvalidArgumentException 400 si une saisie est invalide.
     * @throws \RuntimeException 403 si une évaluation d'un autre moniteur est modifiée sans en avoir le droit, 500 si l'écriture échoue.
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
        $competences = $this->getCompetences($idGroupe);
        $existantes  = $this->getEvaluationSeance($idProfil, $idGroupe, $idCampagne, $dateSeance);
        $maintenant  = ToolsHelper::now();
        $aEcrire     = [];

        foreach ($competences as $idCompetence => $competence) {
            $saisie = $saisies[$idCompetence] ?? null;

            if (!\is_array($saisie)) {
                continue;
            }

            $appreciation = trim((string) ($saisie['appreciation'] ?? ''));
            $observation  = trim((string) ($saisie['observation'] ?? ''));

            if ($appreciation === '') {
                if ($observation !== '') {
                    throw new \InvalidArgumentException(Text::sprintf('COM_GDA_SUIVI_ERR_APPRECIATION_REQUISE', $competence->competence), 400);
                }

                continue;
            }

            if (!isset(self::APPRECIATIONS[$appreciation])) {
                throw new \InvalidArgumentException(Text::_('COM_GDA_SUIVI_ERR_APPRECIATION_INVALIDE'), 400);
            }

            if (mb_strlen($observation) > self::OBSERVATION_LONGUEUR_MAX) {
                throw new \InvalidArgumentException(Text::sprintf('COM_GDA_SUIVI_ERR_OBSERVATION_TROP_LONGUE', $competence->competence, self::OBSERVATION_LONGUEUR_MAX), 400);
            }

            $existante = $existantes[$idCompetence] ?? null;

            if ($existante !== null
                && $existante->appreciation === $appreciation
                && (string) $existante->observation === $observation) {
                continue;
            }

            if (!self::peutModifier($existante, $idMoniteur, $estResponsable)) {
                throw new \RuntimeException(Text::sprintf('COM_GDA_SUIVI_ERR_NON_AUTEUR', $competence->competence), 403);
            }

            $aEcrire[$idCompetence] = [
                'appreciation' => $appreciation,
                'observation'  => $observation !== '' ? $observation : null,
                'existe'       => $existante !== null,
            ];
        }

        if ($aEcrire === []) {
            return 0;
        }

        $this->db->transactionStart();

        try {
            foreach ($aEcrire as $idCompetence => $ecriture) {
                $this->ecrireEvaluation($idProfil, $idCampagne, (int) $idCompetence, $dateSeance, $ecriture, $idMoniteur, $maintenant);
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            throw new \RuntimeException($e->getMessage(), 500, $e);
        }

        return \count($aEcrire);
    }

    /**
     * Insère ou met à jour la ligne d'une compétence pour une séance.
     *
     * @param int    $idProfil      Élève.
     * @param int    $idCampagne    Saison.
     * @param int    $idCompetence  Compétence.
     * @param string $dateSeance    Date de la séance (Y-m-d).
     * @param array  $ecriture      ['appreciation' => string, 'observation' => ?string, 'existe' => bool].
     * @param int    $idMoniteur    Auteur de la modification.
     * @param string $maintenant    Horodatage de l'évaluation (Y-m-d H:i:s).
     * @return void
     * @throws \RuntimeException Si la requête échoue.
     */
    private function ecrireEvaluation(
        int $idProfil,
        int $idCampagne,
        int $idCompetence,
        string $dateSeance,
        array $ecriture,
        int $idMoniteur,
        string $maintenant
    ): void {
        $appreciation = $ecriture['appreciation'];
        $observation  = $ecriture['observation'];
        $typeObservation = $observation === null ? ParameterType::NULL : ParameterType::STRING;

        if ($ecriture['existe']) {
            $query = $this->db->createQuery()
                ->update($this->db->quoteName('#__gda_suivi_competences'))
                ->set($this->db->quoteName('appreciation') . ' = :appreciation')
                ->set($this->db->quoteName('observation') . ' = :observation')
                ->set($this->db->quoteName('date_evaluation') . ' = :date_evaluation')
                ->set($this->db->quoteName('id_moniteur') . ' = :id_moniteur')
                ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
                ->where($this->db->quoteName('id_profil') . ' = :id_profil')
                ->where($this->db->quoteName('id_competence') . ' = :id_competence')
                ->where($this->db->quoteName('date_seance') . ' = :date_seance');
        } else {
            $query = $this->db->createQuery()
                ->insert($this->db->quoteName('#__gda_suivi_competences'))
                ->columns($this->db->quoteName([
                    'id_campagne', 'id_profil', 'id_competence', 'date_seance',
                    'appreciation', 'observation', 'date_evaluation', 'id_moniteur',
                ]))
                ->values(':id_campagne, :id_profil, :id_competence, :date_seance, :appreciation, :observation, :date_evaluation, :id_moniteur');
        }

        $query
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER)
            ->bind(':id_competence', $idCompetence, ParameterType::INTEGER)
            ->bind(':date_seance', $dateSeance)
            ->bind(':appreciation', $appreciation)
            ->bind(':observation', $observation, $typeObservation)
            ->bind(':date_evaluation', $maintenant)
            ->bind(':id_moniteur', $idMoniteur, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }
}
