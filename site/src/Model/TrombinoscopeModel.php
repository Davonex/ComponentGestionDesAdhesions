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
use NCB\Component\Gda\Site\Helper\UsersHelper;

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
}
