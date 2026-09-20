<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;

/**
 * Champ personnalisé qui liste les formulaires HelloAsso (events, boutiques, adhésions...) d'une
 * organisation. L'attribut XML optionnel `formtype` restreint la liste à un ou plusieurs formType
 * HelloAsso (ex: formtype="Membership", ou formtype="Event,Shop" pour en accepter plusieurs) — le
 * champ ne connaît pas la nature de campagne sélectionnée dans le formulaire (choisie sans
 * rechargement de page), il ne peut donc filtrer côté serveur que sur un ensemble fixe ; le
 * filtrage fin par nature (Formation/Loisir -> Event, Boutique -> Shop) se fait ensuite côté
 * client dans campagne.js, à partir du formType déjà encodé dans la valeur de chaque option.
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

        // formTypes autorisés pour ce champ (attribut XML `formtype`, liste séparée par des
        // virgules) : tableau vide = aucune restriction (tous les formTypes non désactivés).
        $formTypesAutorises = array_filter(array_map('trim', explode(',', (string) $this->element['formtype'])));

        $options = [];
        $options[] = HTMLHelper::_('select.option', "null", "Pas de lien avec HelloAsso");

        if ($responses) {
            foreach ($responses as $res) {
                if ($res['state'] === "Disabled") {
                    continue;
                }

                if ($formTypesAutorises && !in_array($res['formType'], $formTypesAutorises, true)) {
                    continue;
                }

                $value = json_encode([
                    'formSlug' => $res['formSlug'],
                    'formType' => $res['formType'],  // ex: "Event", "Membership"...s
                    'url' => $res['url'],
                ]);
                $label = $res['title'] . ' (' . $res['formType'] . ' - ' . $res['state'] . ')';
                $options[] = HTMLHelper::_('select.option', $value, $label);
            }
        }

        return array_merge(parent::getOptions(), $options);
    }
}
