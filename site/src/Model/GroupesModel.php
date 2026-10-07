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
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;
use NCB\Component\Gda\Site\Service\BrevetService;
use NCB\Component\Gda\Site\Service\CotisationService;
use NCB\Component\Gda\Site\Service\GroupesService;

/**
 * Groupes Model
 *
 * @since  1.0.0
 */
class GroupesModel extends ListModel
{
    /**
     * Model context string.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $context = 'com_gdadhesions.groupes';

    /** Identifiant du groupe virtuel « Tous les groupes » (premier onglet). */
    public const ID_GROUPE_TOUS = 0;

    /** Identifiant du groupe virtuel « Sans groupe » (dernier onglet). */
    public const ID_GROUPE_SANS = -1;

    private ?BrevetService $brevetService = null;

    private ?GroupesService $groupesService = null;

    /**
     * Onglets de la vue Groupes avec leur nombre d'adhérents, sans charger les adhérents :
     * « Tous les groupes », les groupes publiés (ordre d'affichage), puis « Sans groupe ».
     *
     * Chaque groupe publié est retourné même s'il ne compte aucun adhérent (masquage des groupes
     * vides côté affichage).
     *
     * @param int $idCampagne Identifiant de la campagne (saison courante).
     * @return object[] Onglets {id_groupe, groupe_name, icon, nb_adherents} ; vide s'il n'existe
     *                  aucun groupe publié.
     */
    public function getOngletsGroupes(int $idCampagne): array
    {
        $db = $this->getDatabase();

        $query = $db->createQuery()
            ->select([
                $db->quoteName('g.id_groupe'),
                $db->quoteName('g.groupe_name'),
                $db->quoteName('g.icon'),
                'COUNT(DISTINCT ' . $db->quoteName('cg.id_profil') . ') AS ' . $db->quoteName('nb_adherents'),
            ])
            ->from($db->quoteName('#__gda_groupes', 'g'))
            ->join(
                'LEFT',
                $db->quoteName('#__gda_composition_groupes', 'cg'),
                $db->quoteName('cg.id_groupe') . ' = ' . $db->quoteName('g.id_groupe')
                    . ' AND ' . $db->quoteName('cg.id_campagne') . ' = :id_campagne'
            )
            ->where($db->quoteName('g.published') . ' = 1')
            ->group($db->quoteName(['g.id_groupe', 'g.groupe_name', 'g.icon', 'g.groupe_tri']))
            ->order($db->quoteName('g.groupe_tri') . ' ASC')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);

        $db->setQuery($query);
        $onglets = $db->loadObjectList() ?: [];

        if ($onglets === []) {
            return [];
        }

        foreach ($onglets as $onglet) {
            $onglet->id_groupe = (int) $onglet->id_groupe;
            $onglet->groupe_name = (string) $onglet->groupe_name;
            $onglet->icon = (string) ($onglet->icon ?? '');
            $onglet->nb_adherents = (int) $onglet->nb_adherents;
        }

        $db->setQuery($this->getRequeteSansGroupe($idCampagne)->select('COUNT(*)'));
        $nbSansGroupe = (int) $db->loadResult();

        $query = $db->createQuery()
            ->select('COUNT(DISTINCT ' . $db->quoteName('cg.id_profil') . ')')
            ->from($db->quoteName('#__gda_composition_groupes', 'cg'))
            ->join('INNER', $db->quoteName('#__gda_groupes', 'g'), $db->quoteName('g.id_groupe') . ' = ' . $db->quoteName('cg.id_groupe'))
            ->where($db->quoteName('cg.id_campagne') . ' = :id_campagne')
            ->where($db->quoteName('g.published') . ' = 1')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);
        $db->setQuery($query);
        $nbAvecGroupe = (int) $db->loadResult();

        $ongletTous = $this->buildGroupeTous([]);
        $ongletTous->nb_adherents = $nbAvecGroupe + $nbSansGroupe;
        unset($ongletTous->adherents);

        $ongletSans = $this->buildGroupeSans([]);
        $ongletSans->nb_adherents = $nbSansGroupe;
        unset($ongletSans->adherents);

        return array_merge([$ongletTous], $onglets, [$ongletSans]);
    }

    /**
     * Un onglet de la vue Groupes avec ses adhérents (chargé en ajax à l'ouverture de l'onglet).
     *
     * @param int $idGroupe   Identifiant du groupe, ou ID_GROUPE_TOUS / ID_GROUPE_SANS.
     * @param int $idCampagne Identifiant de la campagne (saison courante).
     * @return object Onglet {id_groupe, groupe_name, icon, adherents}, chaque adhérent portant
     *                `groupes` et `brevets_shortlist`.
     * @throws \RuntimeException 404 si le groupe n'existe pas ou n'est pas publié.
     */
    public function getOngletGroupe(int $idGroupe, int $idCampagne): object
    {
        $groupes = $this->getGroupesAvecAdherents($idCampagne);
        $onglet = null;

        if ($idGroupe === self::ID_GROUPE_TOUS) {
            $onglet = $this->buildGroupeTous($groupes);
        } else {
            foreach ($groupes as $groupe) {
                if ($groupe->id_groupe === $idGroupe) {
                    $onglet = $groupe;
                    break;
                }
            }
        }

        if ($onglet === null) {
            throw new \RuntimeException(Text::_('COM_GDA_GROUPES_ONGLET_INTROUVABLE'), 404);
        }

        // Brevets calculés pour le seul onglet affiché.
        $this->enrichirBrevetsShortList([$onglet]);

        return $onglet;
    }

    /**
     * Groupes publiés avec leurs adhérents pour la campagne, suivis du groupe « Sans groupe ».
     * Chaque adhérent porte `groupes` (ses groupes publiés, colonne Groupes).
     *
     * @param int $idCampagne Identifiant de la campagne (saison courante).
     * @return object[] Groupes {id_groupe, groupe_name, icon, adherents}.
     */
    private function getGroupesAvecAdherents(int $idCampagne): array
    {
        $db = $this->getDatabase();

        $query = $db->createQuery()
            ->select([
                $db->quoteName('g.id_groupe'),
                $db->quoteName('g.groupe_name'),
                $db->quoteName('g.groupe_tri'),
                $db->quoteName('g.icon'),
                $db->quoteName('p.id_profil'),
                $db->quoteName('p.civilite'),
                $db->quoteName('p.nom'),
                $db->quoteName('p.prenom'),
                $db->quoteName('p.photo'),
                $db->quoteName('p.caci'),
                $db->quoteName('p.date_caci'),
                $db->quoteName('p.date_licence'),
            ])
            ->from($db->quoteName('#__gda_groupes', 'g'))
            ->join(
                'LEFT',
                $db->quoteName('#__gda_composition_groupes', 'cg')
                    . ' ON ' . $db->quoteName('cg.id_groupe') . ' = ' . $db->quoteName('g.id_groupe')
                    . ' AND ' . $db->quoteName('cg.id_campagne') . ' = :value_id_campagne'
            )
            ->join('LEFT', $db->quoteName('#__gda_profils', 'p') . ' ON ' . $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('cg.id_profil'))
            ->where($db->quoteName('g.published') . ' = 1')
            ->order($db->quoteName('g.groupe_tri') . ' ASC')
            ->bind(':value_id_campagne', $idCampagne);

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $groupes = [];
        // Groupes de chaque adhérent, dans l'ordre d'affichage (colonne Groupes de l'onglet « Tous les groupes »).
        $groupesParProfil = [];

        foreach ($rows as $row) {
            $idGroupe = (int) $row->id_groupe;

            if (!isset($groupes[$idGroupe])) {
                $groupe = new \stdClass();
                $groupe->id_groupe = $idGroupe;
                $groupe->groupe_name = (string) $row->groupe_name;
                $groupe->icon = (string) ($row->icon ?? '');
                $groupe->adherents = [];

                $groupes[$idGroupe] = $groupe;
            }

            if (empty($row->id_profil)) {
                continue;
            }

            $adherent = $this->construireAdherent($row);

            $groupes[$idGroupe]->adherents[] = $adherent;
            $groupesParProfil[$adherent->id_profil][] = (object) [
                'id_groupe'   => $idGroupe,
                'groupe_name' => $groupes[$idGroupe]->groupe_name,
                'icon'        => $groupes[$idGroupe]->icon,
            ];
        }

        foreach ($groupes as $groupe) {
            foreach ($groupe->adherents as $adherent) {
                $adherent->groupes = $groupesParProfil[$adherent->id_profil];
            }
        }

        $groupesList = array_values($groupes);

        // Adhérents de la saison sans groupe (« Licence seule », ou aucun groupe choisi) : onglet
        // dédié en fin de liste, et repris dans « Tous les groupes ».
        $groupesList[] = $this->buildGroupeSans($this->getAdherentsSansGroupe($idCampagne));

        return $groupesList;
    }

    /**
     * Requête de base des adhérents ayant souscrit à la saison sans être inscrits à aucun groupe
     * publié (sans SELECT), partagée par la liste et le compteur de l'onglet « Sans groupe ».
     *
     * @param int $idCampagne Identifiant de la campagne (saison courante).
     * @return QueryInterface Requête sur `#__gda_souscriptions` (alias s) et `#__gda_profils` (alias p).
     */
    private function getRequeteSansGroupe(int $idCampagne): QueryInterface
    {
        $db = $this->getDatabase();

        $inscritAUnGroupe = $db->createQuery()
            ->select('1')
            ->from($db->quoteName('#__gda_composition_groupes', 'cg'))
            ->join('INNER', $db->quoteName('#__gda_groupes', 'g'), $db->quoteName('g.id_groupe') . ' = ' . $db->quoteName('cg.id_groupe'))
            ->where($db->quoteName('cg.id_profil') . ' = ' . $db->quoteName('s.id_profil'))
            ->where($db->quoteName('cg.id_campagne') . ' = ' . $db->quoteName('s.id_campagne'))
            ->where($db->quoteName('g.published') . ' = 1');

        return $db->createQuery()
            ->from($db->quoteName('#__gda_souscriptions', 's'))
            ->join('INNER', $db->quoteName('#__gda_profils', 'p'), $db->quoteName('p.id_profil') . ' = ' . $db->quoteName('s.id_profil'))
            ->where($db->quoteName('s.id_campagne') . ' = :id_campagne')
            ->where('NOT EXISTS (' . $inscritAUnGroupe . ')')
            ->bind(':id_campagne', $idCampagne, ParameterType::INTEGER);
    }

    /**
     * Adhérents ayant souscrit à la saison sans être inscrits à aucun groupe publié : option
     * « Licence seule », ou groupe non choisi lors de l'adhésion.
     *
     * @param int $idCampagne Identifiant de la campagne (saison courante).
     * @return object[] Adhérents (même forme que ceux des groupes), triés par nom et prénom, avec
     *                  `groupes` vide et `licence_seule`.
     */
    private function getAdherentsSansGroupe(int $idCampagne): array
    {
        $db = $this->getDatabase();

        $query = $this->getRequeteSansGroupe($idCampagne)
            ->select($db->quoteName([
                'p.id_profil',
                'p.civilite',
                'p.nom',
                'p.prenom',
                'p.photo',
                'p.caci',
                'p.date_caci',
                'p.date_licence',
                's.cotisation_code',
            ]))
            ->order($db->quoteName(['p.nom', 'p.prenom']));

        $db->setQuery($query);

        $adherents = [];

        foreach ($db->loadObjectList() ?: [] as $row) {
            $adherent = $this->construireAdherent($row);
            $adherent->groupes = [];
            $adherent->licence_seule = CotisationService::isCodeLicenceSeule((string) ($row->cotisation_code ?? ''));

            $adherents[] = $adherent;
        }

        return $adherents;
    }

    /**
     * Construit l'objet adhérent affiché par les layouts groupes.detail et groupes.vignette.
     *
     * @param object $row Ligne SQL portant les colonnes du profil (id_profil, civilite, nom,
     *                    prenom, photo, caci, date_caci, date_licence).
     * @return object Adhérent avec statuts CACI et licence calculés.
     */
    private function construireAdherent(object $row): object
    {
        $adherent = new \stdClass();
        $adherent->id_profil = (int) $row->id_profil;
        $adherent->civilite = (string) ($row->civilite ?? '');
        $adherent->nom = (string) ($row->nom ?? '');
        $adherent->prenom = (string) ($row->prenom ?? '');
        $adherent->photo = $row->photo;
        $adherent->caci = $row->caci;
        $adherent->date_caci = $row->date_caci;
        $adherent->caci_status = AdhesionStatusHelper::getCaciFileStatus($row->caci, $row->date_caci);
        $adherent->date_licence = $row->date_licence;
        $adherent->licence_status = AdhesionStatusHelper::getLicenceValidityStatus($row->date_licence);

        return $adherent;
    }

    /**
     * Construit le groupe virtuel « Sans groupe ».
     *
     * @param object[] $adherents Adhérents de la saison sans groupe.
     * @return object Groupe virtuel (id_groupe = ID_GROUPE_SANS) affiché comme les autres.
     */
    private function buildGroupeSans(array $adherents): object
    {
        $groupeSans = new \stdClass();
        $groupeSans->id_groupe = self::ID_GROUPE_SANS;
        $groupeSans->groupe_name = Text::_('COM_GDA_GROUPES_SANS_GROUPE_TAB');
        $groupeSans->icon = 'fa-user-slash';
        $groupeSans->adherents = $adherents;

        return $groupeSans;
    }

    /**
     * Enrichit chaque adhérent de sa shortlist de brevets (getBrevetsShortListProfils), en un
     * seul appel pour tous les profils de tous les groupes — évite le N+1 requêtes.
     *
     * Un adhérent présent dans plusieurs groupes existe comme autant d'objets distincts (voir la
     * boucle de construction ci-dessus, chaque ligne de #__gda_composition_groupes crée un nouvel
     * stdClass) : les propriétés sont donc bien affectées à chaque instance individuellement, pas
     * une seule fois via une référence partagée.
     *
     * @param array<int, object> $groupes Liste des groupes (avec leurs adhérents) ; les objets
     *                                     adhérent sont modifiés en place (ajout de brevets_shortlist).
     */
    private function enrichirBrevetsShortList(array $groupes): void
    {
        $idProfils = [];

        foreach ($groupes as $groupe) {
            foreach ($groupe->adherents as $adherent) {
                $idProfils[] = $adherent->id_profil;
            }
        }

        $idProfils = array_values(array_unique($idProfils));

        if ($idProfils === []) {
            return;
        }

        $shortLists = $this->getBrevetService()->getBrevetsShortListProfils($idProfils);

        foreach ($groupes as $groupe) {
            foreach ($groupe->adherents as $adherent) {
                $adherent->brevets_shortlist = $shortLists[$adherent->id_profil] ?? [];
            }
        }
    }

    /**
     * Remplace les groupes publiés d'un adhérent pour la saison donnée (édition de la colonne
     * Groupes de l'onglet « Tous les groupes », réservée aux Responsables de Groupe).
     *
     * @param int   $idProfil   Identifiant du profil de l'adhérent.
     * @param int   $idCampagne Identifiant de la campagne Saison courante.
     * @param int[] $idsGroupes Identifiants des groupes retenus.
     * @return object[] Groupes de l'adhérent après mise à jour {id_groupe, groupe_name, icon}.
     * @throws \InvalidArgumentException 400 si la sélection est vide ou contient un groupe inconnu.
     * @throws \RuntimeException 404 si l'adhérent n'appartient à aucun groupe de la saison, 500 si l'écriture échoue.
     */
    public function updateGroupesAdherent(int $idProfil, int $idCampagne, array $idsGroupes): array
    {
        return $this->getGroupesService()->remplacerGroupesAdherent($idProfil, $idCampagne, $idsGroupes);
    }

    /**
     * Getter pour obtenir le service Groupes (lazy loading).
     *
     * @return GroupesService Le service.
     */
    private function getGroupesService(): GroupesService
    {
        if ($this->groupesService === null) {
            $this->groupesService = new GroupesService($this->getDatabase());
        }

        return $this->groupesService;
    }

    /**
     * Getter pour obtenir le service Brevet (lazy loading). Même pattern que ProfilModel::getBrevetService().
     */
    private function getBrevetService(): BrevetService
    {
        if ($this->brevetService === null) {
            $this->brevetService = new BrevetService($this->getDatabase());
        }

        return $this->brevetService;
    }

    /**
     * Construit le groupe virtuel "Tous les groupes" : l'union dédupliquée des adhérents
     * de tous les groupes (un adhérent présent dans plusieurs groupes n'apparaît qu'une fois).
     *
     * @param array<int, object> $groupes Liste des groupes réels et du groupe « Sans groupe » (avec leurs adhérents).
     *
     * @return object Groupe virtuel (id_groupe = ID_GROUPE_TOUS) prêt à être affiché comme les autres.
     */
    private function buildGroupeTous(array $groupes): object
    {
        $adherentsUniques = [];
        $idsVus = [];

        foreach ($groupes as $groupe) {
            foreach ($groupe->adherents as $adherent) {
                if (isset($idsVus[$adherent->id_profil])) {
                    continue;
                }

                $idsVus[$adherent->id_profil] = true;
                $adherentsUniques[] = $adherent;
            }
        }

        $groupeTous = new \stdClass();
        $groupeTous->id_groupe = self::ID_GROUPE_TOUS;
        $groupeTous->groupe_name = null;
        $groupeTous->icon = 'fa-users';
        $groupeTous->adherents = $adherentsUniques;

        return $groupeTous;
    }
}
