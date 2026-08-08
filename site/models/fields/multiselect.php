<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

class JFormFieldMultiselect extends ListField
{
    protected $type = 'Multiselect';

    protected function getOptions()
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        try {
            $table      = (string) $this->element['table'];
            $keyField   = (string) $this->element['key_field'];
            $valueField = (string) $this->element['value_field'];
            $ordering   = (string) $this->element['ordering'] ?: $valueField . ' ASC';

            $query->select([$db->quoteName($keyField), $db->quoteName($valueField)])
                ->from($db->quoteName($table))
                ->where($db->quoteName('published') . ' = 1')
                ->order($ordering);

            $db->setQuery($query);
            $rows = $db->loadObjectList();

            $options = [];
            foreach ($rows as $row) {
                $options[] = HTMLHelper::_('select.option', $row->$keyField, $row->$valueField);
            }

            return array_merge(parent::getOptions(), $options);
        } catch (\Exception $e) {
            // Log the error message
            Factory::getApplication()->enqueueMessage('Error fetching options for multiselect field: ' . $e->getMessage(), 'error');
            return parent::getOptions();
        }
    }
}
