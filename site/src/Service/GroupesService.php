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
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère tous les groupes du club (id_groupe, groupe_name, groupe_tri, icon, published),
     * triés par ordre d'affichage. Utilisé par le panneau de gestion des groupes de la vue Saisons.
     *
     * @return object[]
     */
    public function getAllGroupes(): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id_groupe', 'groupe_name', 'groupe_tri', 'icon', 'published']))
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
     * @param array<int, array{id_groupe?: mixed, groupe_name?: mixed, groupe_tri?: mixed, icon?: mixed, published?: mixed}> $groupes
     */
    public function saveGroupes(array $groupes): void
    {
        foreach ($groupes as $groupe) {
            $nom = trim((string) ($groupe['groupe_name'] ?? ''));

            if ($nom === '') {
                continue;
            }

            $idGroupe = (int) ($groupe['id_groupe'] ?? 0);
            $tri = (int) ($groupe['groupe_tri'] ?? 0);
            $icon = trim((string) ($groupe['icon'] ?? ''));
            $published = !empty($groupe['published']) ? 1 : 0;

            $query = $this->db->getQuery(true);

            if ($idGroupe > 0) {
                $query->update($this->db->quoteName('#__gda_groupes'))
                    ->set([
                        $this->db->quoteName('groupe_name') . ' = :nom',
                        $this->db->quoteName('groupe_tri') . ' = :tri',
                        $this->db->quoteName('icon') . ' = :icon',
                        $this->db->quoteName('published') . ' = :published',
                    ])
                    ->where($this->db->quoteName('id_groupe') . ' = :id_groupe')
                    ->bind(':id_groupe', $idGroupe, ParameterType::INTEGER);
            } else {
                $query->insert($this->db->quoteName('#__gda_groupes'))
                    ->columns($this->db->quoteName(['groupe_name', 'groupe_tri', 'icon', 'published']))
                    ->values(':nom, :tri, :icon, :published');
            }

            $query->bind(':nom', $nom)
                ->bind(':tri', $tri, ParameterType::INTEGER)
                ->bind(':icon', $icon)
                ->bind(':published', $published, ParameterType::INTEGER);

            $this->db->setQuery($query);
            $this->db->execute();
        }
    }
}
