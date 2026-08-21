<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

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
        $query = $this->db->getQuery(true)
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

            $query = $this->db->getQuery(true);

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
}
