<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;

/**
 * Champ personnalisé qui liste les articles par catégorie et/ou tag
 */
class JFormFieldArticlesByTag extends ListField
{
    protected $type = 'ArticlesByTag';

    /**
     * Récupère les options
     */
    protected function getOptions()
    {
        // $db = $this->getDbo();
         $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select(['c.id', 'c.title'])
              ->from($db->quoteName('#__content', 'c'))
              ->where('c.state = 1')
              ->order('c.title ASC');

        // Récupération des attributs XML <field catid="..." tagid="...">
        $catid = (string) ConfHelper::getValue('IdCategorieCampagne');
        $tagid = (string) $this->element['tagid'];

        if (!empty($catid))
        {
            $query->where('c.catid = ' . (int) $catid);
        }

        if (!empty($tagid))
        {
            $query->join(
                'INNER',
                $db->quoteName('#__contentitem_tag_map', 'm')
                . ' ON c.id = m.content_item_id AND m.type_alias = ' . $db->quote('com_content.article')
            )
            ->where('m.tag_id = ' . (int) $tagid);
        }

        $db->setQuery($query);
        $articles = $db->loadObjectList();

        $options = [];
        $options[] = HTMLHelper::_('select.option', 0, "Pas d'article");

        if ($articles)
        {
            foreach ($articles as $article)
            {
                $options[] = HTMLHelper::_('select.option', $article->id, $article->title);
            }
        }

        return array_merge(parent::getOptions(), $options);
    }
}
