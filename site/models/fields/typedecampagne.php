<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Champ personnalisé qui liste les type de campagne
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
        $query = $db->getQuery(true);

        $query->select('*')
              ->from($db->quoteName('#__gda_type_de_campagne', 't'))
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
