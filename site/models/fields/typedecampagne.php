<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;

/**
 * Champ personnalisé qui liste les type de campagne. Le type Saison est exclu : les campagnes
 * de ce type sont exclusivement gérées par la vue Saisons, pas par le formulaire Campagnes.
 */
class JFormFieldTypeDeCampagne extends ListField
{
    protected $type = 'TypeDeCampagne';

    /**
     * Récupère les options
     */
    protected function getOptions()
    {
        // $db = $this->getDbo();
         $db    = Factory::getContainer()->get('DatabaseDriver');
        $id_type_saison = (int) ConfHelper::getValue('IdTypeSaison');
        $query = $db->getQuery(true);

        $query->select('*')
              ->from($db->quoteName('#__gda_type_de_campagne', 't'))
              ->where($db->quoteName('t.id_type') . ' != :id_type_saison')
              ->bind(':id_type_saison', $id_type_saison)
              ->order('t.type_name ASC');


        $db->setQuery($query);
        $types = $db->loadObjectList();

        $options = [];

        if ($types)
        {
            foreach ($types as $type)
            {
                $options[] = HTMLHelper::_('select.option', $type->id_type, $type->type_name);
            }
        }

        return array_merge(parent::getOptions(), $options);
    }
}
