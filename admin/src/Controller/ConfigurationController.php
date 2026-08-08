<?php

namespace NCB\Component\Gda\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class ConfigurationController extends BaseController
{
    public function save(): void
    {
        $app = Factory::getApplication();

        if (!$this->checkToken()) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_gdadhesions&view=configuration', false));
            return;
        }

        if (!$app->getIdentity()->authorise('core.options', 'com_gdadhesions')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_gdadhesions', false));
            return;
        }

        /** @var \NCB\Component\Gda\Administrator\Model\ConfigurationModel $model */
        $model = $this->getModel('Configuration', 'Administrator');
        $data = $this->input->post->get('jform', [], 'array');

        if (!$model->saveConfiguration($data)) {
            $app->enqueueMessage(Text::sprintf('COM_GDA_CONFIGURATION_SAVE_ERROR', $model->getError()), 'error');
            $this->setRedirect(Route::_('index.php?option=com_gdadhesions&view=configuration', false));
            return;
        }

        $app->enqueueMessage(Text::_('COM_GDA_CONFIGURATION_SAVE_SUCCESS'), 'message');
        $this->setRedirect(Route::_('index.php?option=com_gdadhesions&view=configuration', false));
    }
}
