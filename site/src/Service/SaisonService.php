<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\GdaLogger;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * Service métier pour la gestion des saisons.
 */
final class SaisonService
{
    private DatabaseInterface $db;
    private GdaConfigService $config;
    private ?object $saison = null;
    private bool $saisonChargee = false;
    private ?object $saisonCourante = null;
    private bool $saisonCouranteChargee = false;

    public function __construct(DatabaseInterface $db, GdaConfigService $config)
    {
        $this->db     = $db;
        $this->config = $config;
    }

    /**
     * Décore une ligne de campagne avec les champs HelloAsso extraits du JSON `event_helloasso`.
     */
    private function decorerHelloAsso(object $item): object
    {
        $HA = $item->event_helloasso !== null ? json_decode($item->event_helloasso, true) : null;
        $item->formSlug = $HA['formSlug'] ?? null;
        $item->formType = $HA['formType'] ?? null;
        $item->url = $HA['url'] ?? null;

        return $item;
    }

    /**
     * Récupérer la campagne de type Saison correspondant au critère donné (colonne booléenne),
     * décorée des champs HelloAsso (formSlug, formType, url).
     *
     * @param  string      $colonne  Colonne booléenne à filtrer ('active' = ouverte, 'courante' = courante)
     * @return object|null           Objet campagne enrichi, ou null si aucune/plusieurs correspondances
     */
    private function chargerSaison(string $colonne): ?object
    {
        $id_type_saison = $this->config->getValue('IdTypeSaison');

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__gda_campagnes'))
            ->where($this->db->quoteName('id_type') . ' = :id_type_saison')
            ->where($this->db->quoteName($colonne) . ' = 1')
            ->where($this->db->quoteName('effacer') . ' = 0')
            ->bind(':id_type_saison', $id_type_saison);

        $this->db->setQuery($query);

        try {
            $items = $this->db->loadObjectList();
        } catch (\RuntimeException $e) {
            throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
        }

        if (count($items) !== 1) {
            return null;
        }

        $saison = $this->decorerHelloAsso($items[0]);

        // Vérification paresseuse : une saison ouverte dont la date de fin est dépassée
        // est fermée à la volée, ici, pour que tout appelant de getSaisonOuverte() (dashboard,
        // Adhésion, vue Saisons, CampagnesModel::SaisonOuverte()) en bénéficie sans modification.
        if ($colonne === 'active' && (int) $saison->active === 1 && strtotime($saison->date_fin) < strtotime('today')) {
            $this->fermerSaisonExpiree((int) $saison->id_campagne);
            return null;
        }

        return $saison;
    }

    /**
     * Ferme (active = 0) une saison dont la date de fin est dépassée. Un échec ponctuel de
     * l'UPDATE ne doit pas casser l'affichage : il sera retenté au prochain appel.
     */
    private function fermerSaisonExpiree(int $idCampagne): void
    {
        try {
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__gda_campagnes'))
                ->set($this->db->quoteName('active') . ' = 0')
                ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
                ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

            $this->db->setQuery($query);
            $this->db->execute();
        } catch (\RuntimeException $e) {
            GdaLogger::error(
                'Échec de la fermeture automatique de la saison expirée #' . $idCampagne . ' : ' . $e->getMessage()
            );
        }
    }

    /**
     * Liste toutes les saisons (campagnes de type Saison, non effacées), triées par date de
     * début décroissante.
     *
     * @return object[]
     */
    public function getListeSaisons(): array
    {
        $id_type_saison = $this->config->getValue('IdTypeSaison');

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__gda_campagnes'))
            ->where($this->db->quoteName('id_type') . ' = :id_type_saison')
            ->where($this->db->quoteName('effacer') . ' = 0')
            ->order($this->db->quoteName('date_debut') . ' DESC')
            ->bind(':id_type_saison', $id_type_saison);

        $this->db->setQuery($query);

        try {
            $items = $this->db->loadObjectList();
        } catch (\RuntimeException $e) {
            throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
        }

        return array_map([$this, 'decorerHelloAsso'], $items);
    }

    /**
     * Crée une nouvelle saison avec les informations minimum (titre, dates). `active` et
     * `courante` démarrent à 0 : le membre du bureau les active explicitement ensuite.
     *
     * @return int  Identifiant de la campagne créée
     */
    public function creerSaison(array $data): int
    {
        $titre = trim((string) ($data['titre'] ?? ''));

        if ($titre === '') {
            throw new \Exception(Text::_('COM_GDA_SAISONS_TITRE_REQUIRED'), 500);
        }

        $dateDebut = ToolsHelper::to_sqldate((string) ($data['date_debut'] ?? ''));
        $dateFin   = ToolsHelper::to_sqldate((string) ($data['date_fin'] ?? ''));

        if ($dateDebut === null || $dateFin === null) {
            throw new \Exception(Text::_('COM_GDA_SAISONS_DATES_INVALID'), 500);
        }

        if (strtotime($dateFin) < strtotime($dateDebut)) {
            throw new \Exception(Text::_('COM_GDA_SAISONS_DATE_FIN_AVANT_DEBUT'), 500);
        }

        $id_type_saison = (int) $this->config->getValue('IdTypeSaison');
        $description = '';

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__gda_campagnes'))
            ->columns($this->db->quoteName(['titre', 'description', 'date_debut', 'date_fin', 'id_type', 'active', 'courante']))
            ->values(':titre, :description, :date_debut, :date_fin, :id_type, 0, 0')
            ->bind(':titre', $titre)
            ->bind(':description', $description)
            ->bind(':date_debut', $dateDebut)
            ->bind(':date_fin', $dateFin)
            ->bind(':id_type', $id_type_saison, ParameterType::INTEGER);

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        return (int) $this->db->insertid();
    }

    /**
     * Met à jour les champs de contenu de la saison courante (titre, description, dates,
     * article, HelloAsso). Ne touche jamais `active`/`courante`, gérés par des tâches dédiées
     * (toggleActive/toggleCourante), ni `id_groupes` (colonne héritée non gérée par cette vue :
     * la gestion des groupes du club se fait désormais globalement via GroupesModel::saveGroupes()).
     */
    public function sauvegarderCourante(int $idCampagne, array $data): bool
    {
        $titre = trim((string) ($data['titre'] ?? ''));

        if ($titre === '') {
            throw new \Exception(Text::_('COM_GDA_SAISONS_TITRE_REQUIRED'), 500);
        }

        $dateDebut = ToolsHelper::to_sqldate((string) ($data['date_debut'] ?? ''));
        $dateFin   = ToolsHelper::to_sqldate((string) ($data['date_fin'] ?? ''));

        if ($dateDebut === null || $dateFin === null) {
            throw new \Exception(Text::_('COM_GDA_SAISONS_DATES_INVALID'), 500);
        }

        $description   = (string) ($data['description'] ?? '');
        $idArticle     = (int) ($data['id_article'] ?? 0);
        $eventHelloAsso = $data['event_helloasso'] ?? null;
        $id_type_saison = (int) $this->config->getValue('IdTypeSaison');

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_campagnes'))
            ->set([
                $this->db->quoteName('titre') . ' = :titre',
                $this->db->quoteName('description') . ' = :description',
                $this->db->quoteName('date_debut') . ' = :date_debut',
                $this->db->quoteName('date_fin') . ' = :date_fin',
                $this->db->quoteName('id_article') . ' = :id_article',
                $this->db->quoteName('event_helloasso') . ' = :event_helloasso',
            ])
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('id_type') . ' = :id_type_saison')
            ->bind(':titre', $titre)
            ->bind(':description', $description)
            ->bind(':date_debut', $dateDebut)
            ->bind(':date_fin', $dateFin)
            ->bind(':id_article', $idArticle, ParameterType::INTEGER)
            ->bind(':event_helloasso', $eventHelloAsso)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER)
            ->bind(':id_type_saison', $id_type_saison, ParameterType::INTEGER);

        $this->db->setQuery($query);

        try {
            $result = $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        if ($this->saisonCourante !== null && (int) $this->saisonCourante->id_campagne === $idCampagne) {
            $this->saisonCouranteChargee = false;
        }

        return $result;
    }

    /**
     * Ouvre ou ferme une saison aux inscriptions. Une seule saison peut être ouverte à la
     * fois : ouvrir une saison alors qu'une autre est déjà ouverte est refusé (la fermeture
     * n'est jamais bloquée).
     */
    public function toggleActive(int $idCampagne, bool $active): bool
    {
        if ($active) {
            $saisonOuverte = $this->getSaisonOuverte();
            if ($saisonOuverte !== null && (int) $saisonOuverte->id_campagne !== $idCampagne) {
                throw new \Exception(Text::_('COM_GDA_CAMPAGNE_SAISON_ALLREADY_OPEN'), 501);
            }
        }

        $activeValue = (int) $active;

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__gda_campagnes'))
            ->set($this->db->quoteName('active') . ' = :active')
            ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
            ->bind(':active', $activeValue, ParameterType::INTEGER)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

        $this->db->setQuery($query);

        try {
            $result = $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        $this->saisonChargee = false;

        return $result;
    }

    /**
     * Déclare ou retire une saison comme saison courante (suivi CACI/licence/groupes). Une seule
     * saison peut être courante à la fois. Une saison qui perd son statut courant — qu'elle soit
     * explicitement désactivée, ou qu'elle soit remplacée par une nouvelle saison courante — est
     * automatiquement fermée aux adhésions (`active = 0`) : une saison non suivie ne doit pas
     * rester ouverte aux inscriptions.
     */
    public function toggleCourante(int $idCampagne, bool $courante): bool
    {
        $id_type_saison = $this->config->getValue('IdTypeSaison');

        $this->db->transactionStart();

        try {
            if ($courante) {
                // Ferme et retire le statut courant de l'ancienne saison courante (s'il y en avait
                // une). Grâce à l'exclusivité déjà garantie, au plus une ligne est concernée.
                $queryReset = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__gda_campagnes'))
                    ->set([
                        $this->db->quoteName('courante') . ' = 0',
                        $this->db->quoteName('active') . ' = 0',
                    ])
                    ->where($this->db->quoteName('id_type') . ' = :id_type_saison')
                    ->where($this->db->quoteName('courante') . ' = 1')
                    ->where($this->db->quoteName('id_campagne') . ' != :id_campagne_exclu')
                    ->bind(':id_type_saison', $id_type_saison)
                    ->bind(':id_campagne_exclu', $idCampagne, ParameterType::INTEGER);
                $this->db->setQuery($queryReset);
                $this->db->execute();

                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__gda_campagnes'))
                    ->set($this->db->quoteName('courante') . ' = 1')
                    ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
                    ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);
            } else {
                // Désactivation explicite : la saison n'est plus suivie, elle ne doit plus non
                // plus rester ouverte aux adhésions.
                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__gda_campagnes'))
                    ->set([
                        $this->db->quoteName('courante') . ' = 0',
                        $this->db->quoteName('active') . ' = 0',
                    ])
                    ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
                    ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);
            }

            $this->db->setQuery($query);
            $result = $this->db->execute();

            $this->db->transactionCommit();
        } catch (\RuntimeException $e) {
            $this->db->transactionRollback();
            throw new \Exception($e->getMessage(), 500);
        }

        $this->saisonCouranteChargee = false;
        $this->saisonChargee = false;

        return $result;
    }



    /**
     * Récupérer une campagne de type Saison par son identifiant.
     *
     * @param int|null $id_saison  Identifiant de la campagne saison
     * @return object|null         Objet campagne enrichi ou null si introuvable
     */
    public static function getSaison(?int $id_saison): ?object
    {
        if ($id_saison === null) {
            return null;
        }

        $id_type_saison = ConfHelper::getConfigService()->getValue('IdTypeSaison');
        $db = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gda_campagnes'))
            ->where($db->quoteName('id_type') . ' = :id_type_saison')
            ->where($db->quoteName('id_campagne') . ' = :id_saison')
            ->bind(':id_type_saison', $id_type_saison)
            ->bind(':id_saison', $id_saison, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $items = $db->loadObjectList();

            if (count($items) !== 1) {
                return null;
            }

            $HA     = $items[0]->event_helloasso !== null ? json_decode($items[0]->event_helloasso, true) : null;
            $result = $items[0];
            $result->formSlug = $HA['formSlug'] ?? null;
            $result->formType = $HA['formType'] ?? null;
            $result->url      = $HA['url'] ?? null;

            return $result;
        } catch (\RuntimeException $e) {
            return null;
        }
    }


    /**
     * Récupérer la saison ouverte (inscriptions possibles : active = 1, effacer = 0).
     *
     * @return object|null  Objet campagne ou null si aucune saison ouverte
     */
    public function getSaisonOuverte(): ?object
    {
        if (!$this->saisonChargee) {
            $this->saison = $this->chargerSaison('active');
            $this->saisonChargee = true;
        }
        return $this->saison;
    }

    /**
     * Récupérer la saison courante (saison de suivi de l'année en cours : courante = 1, effacer = 0),
     * indépendamment du fait que les inscriptions y soient encore ouvertes ou non.
     *
     * @return object|null  Objet campagne ou null si aucune saison courante déclarée
     */
    public function getSaisonCourante(): ?object
    {
        if (!$this->saisonCouranteChargee) {
            $this->saisonCourante = $this->chargerSaison('courante');
            $this->saisonCouranteChargee = true;
        }
        return $this->saisonCourante;
    }
}