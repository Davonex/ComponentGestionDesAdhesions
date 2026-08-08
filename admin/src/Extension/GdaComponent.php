<?php

namespace NCB\Component\Gda\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Installer\InstallerAdapter;
use Psr\Container\ContainerInterface;

class GdaComponent extends MVCComponent implements BootableExtensionInterface
{
    public function boot(ContainerInterface $container) {}

    public function install(InstallerAdapter $adapter)
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get(DatabaseInterface::class);

        // === Création catégorie ===
        $categoryTable = Table::getInstance('Category');

        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where('extension = ' . $db->quote('com_content'))
            ->where('alias = ' . $db->quote('gestiondesadhesions'));

        $db->setQuery($query);
        $categoryId = $db->loadResult();

        if (!$categoryId)
        {
            $categoryData = [
                'title' => 'GestionDesAdhésions',
                'alias' => 'gestiondesadhesions',
                'extension' => 'com_content',
                'published' => 1,
                'access' => 1,
                'language' => '*',
                'parent_id' => 1
            ];

            $categoryTable->save($categoryData);
            $categoryId = $categoryTable->id;
        }

        // === Création article ===
        $articleTable = Table::getInstance('Content');

        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__content')
            ->where('alias = ' . $db->quote('les-adhesions-sont-fermees'));

        $db->setQuery($query);
        $articleId = $db->loadResult();

        if (!$articleId)
        {
            $articleData = [
                'title' => 'Les Adhésions sont fermées',
                'alias' => 'les-adhesions-sont-fermees',
                'introtext' => '<p>Les adhésions sont fermées pour le moment.<br>Merci de revenir plus tard !</p>',
                'catid' => $categoryId,
                'state' => 1,
                'access' => 1,
                'language' => '*',
                'created_by' => Factory::getUser()->id
            ];

            $articleTable->save($articleData);
            $articleId = $articleTable->id;
        }

        // === Insert config ===
        $query = $db->getQuery(true)
            ->insert('#__gda_conf')
            ->columns(['id_conf', 'cle', 'valeur', 'description'])
            ->values('6, ' . $db->quote('IdAdhesionClos') . ', ' . (int)$articleId . ', NULL');

        $db->setQuery($query)->execute();
    }
}