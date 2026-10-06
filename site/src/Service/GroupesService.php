<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Service métier pour la gestion des groupes du club (#__gda_groupes).
 */
final class GroupesService
{
    /**
     * Activité par défaut d'un groupe : groupe transverse, non rattaché à une activité FFESSM
     * particulière. Valeur d'initialisation de #__gda_groupes.activite (colonne obligatoire) et
     * repli si le formulaire renvoie une activité vide.
     */
    public const ACTIVITE_TOUTES = 'Toutes';

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Liste fermée des activités proposées pour un groupe : « Toutes » (groupe transverse) suivi
     * des activités du référentiel FFESSM (#__gda_mapping_brevets, via BrevetService, seul
     * détenteur des accès à cette table).
     *
     * @return string[]
     */
    public function getActivitesDisponibles(): array
    {
        return array_merge([self::ACTIVITE_TOUTES], (new BrevetService($this->db))->getActivitesReferentiel());
    }

    /**
     * Récupère tous les groupes du club (id_groupe, groupe_name, activite, groupe_tri, icon,
     * published),
     * triés par ordre d'affichage. Utilisé par le panneau de gestion des groupes de la vue Saisons.
     *
     * @return object[]
     */
    public function getAllGroupes(): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id_groupe', 'groupe_name', 'activite', 'groupe_tri', 'icon', 'published']))
            ->from($this->db->quoteName('#__gda_groupes'))
            ->order($this->db->quoteName('groupe_tri') . ' ASC');

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Sauvegarde en lot les groupes du club (créations et modifications), tels que soumis par le
     * panneau de gestion des groupes de la vue Saisons. Une ligne avec `id_groupe` vide/0 est
     * insérée ; une ligne avec un `id_groupe` existant est mise à jour. Les lignes sans nom
     * (ligne "Ajouter un groupe" laissée vide) sont ignorées silencieusement.
     *
     * @param array<int, array{id_groupe?: mixed, groupe_name?: mixed, activite?: mixed, groupe_tri?: mixed, icon?: mixed, published?: mixed}> $groupes
     */
    public function saveGroupes(array $groupes): void
    {
        // Liste fermée chargée une seule fois pour tout le lot (une requête, pas une par ligne).
        $activitesConnues = $this->getActivitesDisponibles();

        foreach ($groupes as $groupe) {
            $nom = trim((string) ($groupe['groupe_name'] ?? ''));

            if ($nom === '') {
                continue;
            }

            $idGroupe = (int) ($groupe['id_groupe'] ?? 0);
            // Colonne obligatoire : une activité absente ou inconnue retombe sur « Toutes » plutôt
            // que d'échouer côté base (le <select> du formulaire est déjà une liste fermée).
            $activite = trim((string) ($groupe['activite'] ?? ''));

            if (!\in_array($activite, $activitesConnues, true)) {
                $activite = self::ACTIVITE_TOUTES;
            }

            $tri = (int) ($groupe['groupe_tri'] ?? 0);
            $icon = trim((string) ($groupe['icon'] ?? ''));
            $published = !empty($groupe['published']) ? 1 : 0;

            $query = $this->db->createQuery();

            if ($idGroupe > 0) {
                $query->update($this->db->quoteName('#__gda_groupes'))
                    ->set([
                        $this->db->quoteName('groupe_name') . ' = :nom',
                        $this->db->quoteName('activite') . ' = :activite',
                        $this->db->quoteName('groupe_tri') . ' = :tri',
                        $this->db->quoteName('icon') . ' = :icon',
                        $this->db->quoteName('published') . ' = :published',
                    ])
                    ->where($this->db->quoteName('id_groupe') . ' = :id_groupe')
                    ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER);
            } else {
                $query->insert($this->db->quoteName('#__gda_groupes'))
                    ->columns($this->db->quoteName(['groupe_name', 'activite', 'groupe_tri', 'icon', 'published']))
                    ->values(':nom, :activite, :tri, :icon, :published');
            }

            $query->bind(':nom', $nom)
                ->bind(':activite', $activite)
                ->bind(':tri', $tri, ParameterType::INTEGER)
                ->bind(':icon', $icon)
                ->bind(':published', $published, ParameterType::INTEGER);

            $this->db->setQuery($query);
            $this->db->execute();
        }
    }

    /**
     * Groupes publiés auxquels un adhérent est inscrit pour une saison (#__gda_composition_groupes),
     * dans l'ordre d'affichage des groupes.
     *
     * @param int $idProfil   Identifiant du profil de l'adhérent.
     * @param int $idCampagne Identifiant de la campagne Saison.
     * @return object[] Groupes {id_groupe, groupe_name, icon}.
     */
    public function getGroupesAdherent(int $idProfil, int $idCampagne): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['g.id_groupe', 'g.groupe_name', 'g.icon']))
            ->from($this->db->quoteName('#__gda_composition_groupes', 'cg'))
            ->join('INNER', $this->db->quoteName('#__gda_groupes', 'g'), $this->db->quoteName('g.id_groupe') . ' = ' . $this->db->quoteName('cg.id_groupe'))
            ->where($this->db->quoteName('cg.id_profil') . ' = :id_profil')
            ->where($this->db->quoteName('cg.id_campagne') . ' = :id_campagne')
            ->where($this->db->quoteName('g.published') . ' = 1')
            ->order($this->db->quoteName('g.groupe_tri') . ' ASC')
            ->bind(':id_profil', $idProfil, ParameterType::INTEGER)
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Remplace les groupes publiés d'un adhérent pour une saison (correction par un Responsable de
     * Groupe depuis la vue Groupes). Les inscriptions à des groupes non publiés, invisibles dans
     * cette vue, sont conservées telles quelles.
     *
     * L'adhérent doit appartenir à la saison (souscription, ou inscription à un groupe). Une
     * sélection vide le retire de tous les groupes publiés : il apparaît alors dans l'onglet
     * « Sans groupe » de la vue Groupes.
     *
     * @param int   $idProfil   Identifiant du profil de l'adhérent.
     * @param int   $idCampagne Identifiant de la campagne Saison.
     * @param int[] $idsGroupes Identifiants des groupes publiés retenus.
     * @return object[] Groupes de l'adhérent après mise à jour (voir getGroupesAdherent()).
     * @throws \InvalidArgumentException 400 si la sélection contient un groupe inconnu ou non publié.
     * @throws \RuntimeException 404 si l'adhérent n'appartient pas à la saison, 500 si l'écriture échoue.
     */
    public function remplacerGroupesAdherent(int $idProfil, int $idCampagne, array $idsGroupes): array
    {
        $idsGroupes = array_values(array_unique(array_filter(
            array_map('intval', $idsGroupes),
            static fn (int $idGroupe): bool => $idGroupe > 0
        )));

        $idsPublies = $this->getIdsGroupesPublies();

        if (array_diff($idsGroupes, $idsPublies) !== []) {
            throw new \InvalidArgumentException(Text::_('COM_GDA_GROUPES_COMPOSITION_GROUPE_INCONNU'), 400);
        }

        $groupesActuels = $this->getGroupesAdherent($idProfil, $idCampagne);

        if ($groupesActuels === [] && !$this->isAdherentDeLaSaison($idProfil, $idCampagne)) {
            throw new \RuntimeException(Text::_('COM_GDA_GROUPES_COMPOSITION_ADHERENT_INTROUVABLE'), 404);
        }

        $idsActuels = array_map(static fn (object $groupe): int => (int) $groupe->id_groupe, $groupesActuels);
        sort($idsActuels);
        sort($idsGroupes);

        if ($idsActuels === $idsGroupes) {
            return $groupesActuels;
        }

        $this->db->transactionStart();

        try {
            $query = $this->db->createQuery()
                ->delete($this->db->quoteName('#__gda_composition_groupes'))
                ->where($this->db->quoteName('id_profil') . ' = :id_profil')
                ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
                ->whereIn($this->db->quoteName('id_groupe'), $idsPublies)
                ->bind(':id_profil', $idProfil, ParameterType::INTEGER)
                ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

            $this->db->setQuery($query);
            $this->db->execute();

            if ($idsGroupes !== []) {
                // Valeurs entières déjà validées : une seule requête pour toutes les lignes.
                $query = $this->db->createQuery()
                    ->insert($this->db->quoteName('#__gda_composition_groupes'))
                    ->columns($this->db->quoteName(['id_profil', 'id_groupe', 'id_campagne']));

                foreach ($idsGroupes as $idGroupe) {
                    $query->values($idProfil . ', ' . $idGroupe . ', ' . $idCampagne);
                }

                $this->db->setQuery($query);
                $this->db->execute();
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            throw new \RuntimeException($e->getMessage(), 500, $e);
        }

        return $this->getGroupesAdherent($idProfil, $idCampagne);
    }

    /**
     * Indique si l'adhérent appartient à la saison : souscription, ou inscription à un groupe
     * (y compris non publié).
     *
     * @param int $idProfil   Identifiant du profil de l'adhérent.
     * @param int $idCampagne Identifiant de la campagne Saison.
     * @return bool True si l'adhérent appartient à la saison.
     */
    private function isAdherentDeLaSaison(int $idProfil, int $idCampagne): bool
    {
        foreach (['#__gda_souscriptions', '#__gda_composition_groupes'] as $table) {
            $query = $this->db->createQuery()
                ->select('1')
                ->from($this->db->quoteName($table))
                ->where($this->db->quoteName('id_profil') . ' = :id_profil')
                ->where($this->db->quoteName('id_campagne') . ' = :id_campagne')
                ->bind(':id_profil', $idProfil, ParameterType::INTEGER)
                ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

            $this->db->setQuery($query, 0, 1);

            if ($this->db->loadResult() !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identifiants des groupes publiés.
     *
     * @return int[]
     */
    private function getIdsGroupesPublies(): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName('id_groupe'))
            ->from($this->db->quoteName('#__gda_groupes'))
            ->where($this->db->quoteName('published') . ' = 1');

        $this->db->setQuery($query);

        return array_map('intval', $this->db->loadColumn() ?: []);
    }
}
