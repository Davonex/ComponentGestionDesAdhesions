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
use NCB\Component\Gda\Site\Helper\AdhesionStatusHelper;

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

    /**
     * Récupère les groupes publiés avec la liste des adhérents inscrits pour la campagne donnée.
     *
     * Chaque groupe est retourné même s'il ne compte aucun adhérent pour la campagne
     * (nécessaire pour permettre le masquage des groupes vides côté affichage).
     *
     * @param int $idCampagne Identifiant de la campagne (saison courante).
     *
     * @return array<int, object> Liste des groupes, chacun portant la propriété `adherents`.
     */
    public function getGroupesAvecAdherents(int $idCampagne): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
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

            $adherent = new \stdClass();
            $adherent->id_profil = (int) $row->id_profil;
            $adherent->civilite = (string) ($row->civilite ?? '');
            $adherent->nom = (string) ($row->nom ?? '');
            $adherent->prenom = (string) ($row->prenom ?? '');
            $adherent->photo = $row->photo;
            $adherent->caci = $row->caci;
            $adherent->date_caci = $row->date_caci;
            $adherent->caci_status = AdhesionStatusHelper::getCaciFileStatus($row->caci, $row->date_caci);

            $groupes[$idGroupe]->adherents[] = $adherent;
        }

        $groupesList = array_values($groupes);

        if (!empty($groupesList)) {
            array_unshift($groupesList, $this->buildGroupeTous($groupesList));
        }

        return $groupesList;
    }

    /**
     * Construit le groupe virtuel "Tous les groupes" : l'union dédupliquée des adhérents
     * de tous les groupes (un adhérent présent dans plusieurs groupes n'apparaît qu'une fois).
     *
     * @param array<int, object> $groupes Liste des groupes réels (avec leurs adhérents).
     *
     * @return object Groupe virtuel (id_groupe = 0) prêt à être affiché comme les autres.
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
        $groupeTous->id_groupe = 0;
        $groupeTous->groupe_name = null;
        $groupeTous->icon = 'fa-users';
        $groupeTous->adherents = $adherentsUniques;

        return $groupeTous;
    }
}
