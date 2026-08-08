<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;

/**
 * Champ personnalisé qui liste les Event HelloAsso par catégorie et/ou tag
 */
class JFormFieldEventsHelloAsso extends ListField
{
    protected $type = 'EventsHelloAsso';

    /**
     * Récupère les options
     */
    protected function getOptions()
    {
        try {
            $service = new \NCB\Component\Gda\Site\Service\HelloAssoService();

            $service->getAccessToken();
            $responses = $service->getForms();
        } catch (\RuntimeException $e) {
            $options[] = HTMLHelper::_('select.option', "", $e->getMessage());
            return array_merge(parent::getOptions(), $options);
        }


        // $forms = $db->loadObjectList();

        $options = [];
        $options[] = HTMLHelper::_('select.option', "null", "Pas de lien avec HelloAsso");

        if ($responses) {
            foreach ($responses as $res) {
                $value = json_encode([
                    'formSlug' => $res['formSlug'],
                    'formType' => $res['formType'],  // ex: "Event", "Membership"...s
                    'url' => $res['url'],
                ]);
                if ($res['state'] !== "Disabled") {
                    $label = $res['title'] . ' (' . $res['formType'] . ' - ' . $res['state'] . ')';
                    $options[] = HTMLHelper::_('select.option', $value, $label);
                }
            }
        }

        return array_merge(parent::getOptions(), $options);
    }
}
