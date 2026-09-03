<?php

/**
 * @package     com_gdadhesions
 * @subpackage  components
 * @copyright   Copyright (C) 2024 GD Adhesions. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\UsersHelper;
use NCB\Component\Gda\Site\Service\BrevetService;

/**
 * Modèle de la vue "Trombinoscope" : liste des membres du Bureau (photo, nom, fonction),
 * automatiquement constituée à partir du groupe Joomla "Membre du Bureau" et des colonnes
 * `fonction` / `ordre_bureau` / `photo` de `#__gda_profils`.
 *
 * @since  1.0.0
 */
class TrombinoscopeModel extends ListModel
{
    /**
     * Model context string.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $context = 'com_gdadhesions.trombinoscope';

    /**
     * @var    BrevetService|null
     * @since  1.1.0
     */
    private ?BrevetService $brevetService = null;

    /**
     * Récupère les membres du Bureau, triés par `ordre_bureau` (les profils sans ordre défini
     * sont placés en fin de liste) puis par nom/prénom en repli.
     *
     * @return array<int, object>
     *
     * @since  1.0.0
     */
    public function getMembresBureau(): array
    {
        $bureauGroupId = UsersHelper::getClubGroupIds()['bureau'] ?? null;

        if ($bureauGroupId === null) {
            return [];
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('p.id_profil'),
                $db->quoteName('p.civilite'),
                $db->quoteName('p.nom'),
                $db->quoteName('p.prenom'),
                $db->quoteName('p.photo'),
                $db->quoteName('p.fonction'),
                $db->quoteName('p.ordre_bureau'),
            ])
            ->from($db->quoteName('#__gda_profils', 'p'))
            ->join(
                'INNER',
                $db->quoteName('#__user_usergroup_map', 'm')
                    . ' ON ' . $db->quoteName('m.user_id') . ' = ' . $db->quoteName('p.id_profil')
                    . ' AND ' . $db->quoteName('m.group_id') . ' = :value_bureau_group_id'
            )
            ->order($db->quoteName('p.ordre_bureau') . ' IS NULL ASC, ' . $db->quoteName('p.ordre_bureau') . ' ASC, ' . $db->quoteName('p.nom') . ' ASC, ' . $db->quoteName('p.prenom') . ' ASC')
            ->bind(':value_bureau_group_id', $bureauGroupId);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Récupère les encadrants plongée : les adhérents titulaires d'au moins un brevet reconnu du
     * référentiel FFESSM pour l'activité "Technique" et le rôle "encadrant" (E1 à E4, BEES,
     * MF1/MF2...), avec leur meilleur brevet comme fonction affichée.
     *
     * @return array<int, object> objets {id_profil, civilite, nom, prenom, photo, fonction, poids}
     *
     * @since  1.1.0
     */
    public function getEncadrantsPlongee(): array
    {
        return $this->getEncadrantsParActivite('Technique');
    }

    /**
     * Récupère les encadrants apnée : les adhérents titulaires d'au moins un brevet reconnu du
     * référentiel FFESSM pour l'activité "Apnée" et le rôle "encadrant" (IE1, IE2, MEF1, MEF2,
     * JFA1...), avec leur meilleur brevet comme fonction affichée.
     *
     * @return array<int, object> objets {id_profil, civilite, nom, prenom, photo, fonction, poids}
     *
     * @since  1.2.0
     */
    public function getEncadrantsApnee(): array
    {
        return $this->getEncadrantsParActivite('Apnée');
    }

    /**
     * Adhérents titulaires d'au moins un brevet reconnu du référentiel FFESSM pour une activité et
     * le rôle "encadrant" donnés, avec leur meilleur brevet comme fonction affichée. Triés par
     * meilleur brevet décroissant (les plus qualifiés en premier), puis par nom/prénom en repli.
     * Factorisé pour les onglets du Trombinoscope par activité (Plongée, Apnée...), qui partagent
     * exactement ce schéma — seule l'activité change.
     *
     * @param string $activite Activité du référentiel FFESSM (ex : "Technique", "Apnée").
     *
     * @return array<int, object> objets {id_profil, civilite, nom, prenom, photo, fonction, poids}
     *
     * @since  1.2.0
     */
    private function getEncadrantsParActivite(string $activite): array
    {
        $brevetService = $this->getBrevetService();

        $idProfils = $brevetService->getIdProfilsAvecBrevet($activite, 'encadrant');

        if ($idProfils === []) {
            return [];
        }

        // Meilleur brevet par (activité, rôle) : réutilise la réduction déjà en place, sans la
        // dupliquer ici. Filtre sur $activite uniquement : un profil peut aussi avoir un meilleur
        // brevet "pratiquant" pour la même activité, qu'on ne veut pas afficher ici.
        $meilleursBrevets = $brevetService->getBrevetsShortListProfils($idProfils, [$activite]);

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName(['p.id_profil', 'p.civilite', 'p.nom', 'p.prenom', 'p.photo']))
            ->from($db->quoteName('#__gda_profils', 'p'))
            ->whereIn($db->quoteName('p.id_profil'), $idProfils, ParameterType::INTEGER);

        $db->setQuery($query);

        $encadrants = $db->loadObjectList() ?: [];

        foreach ($encadrants as $encadrant) {
            $meilleurEncadrant = null;

            foreach ($meilleursBrevets[(int) $encadrant->id_profil] ?? [] as $brevet) {
                if ($brevet->role === 'encadrant') {
                    $meilleurEncadrant = $brevet;
                    break;
                }
            }

            $encadrant->fonction = $meilleurEncadrant->label_affichage ?? '';
            $encadrant->poids = (int) ($meilleurEncadrant->poids ?? 0);
        }

        usort(
            $encadrants,
            static fn($a, $b) => $b->poids <=> $a->poids
                ?: strcmp((string) $a->nom, (string) $b->nom)
                ?: strcmp((string) $a->prenom, (string) $b->prenom)
        );

        return $encadrants;
    }

    /**
     * Instancie le service des brevets à la demande (même motif que les autres modèles du
     * composant : BrevetService n'est pas déclaré dans le conteneur DI côté site).
     *
     * @return BrevetService
     *
     * @since  1.1.0
     */
    private function getBrevetService(): BrevetService
    {
        if ($this->brevetService === null) {
            $this->brevetService = new BrevetService($this->getDatabase());
        }

        return $this->brevetService;
    }
}
