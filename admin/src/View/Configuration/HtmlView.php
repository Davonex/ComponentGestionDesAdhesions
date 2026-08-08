<?php

namespace NCB\Component\Gda\Administrator\View\Configuration;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $form;
    public bool $secretConfigured = false;
    public string $releaseNotes = '';
    public string $logContent = '';

    public function display($tpl = null): void
    {
        /** @var \NCB\Component\Gda\Administrator\Model\ConfigurationModel $model */
        $model = $this->getModel();

        $this->form = $model->getForm();
        $this->secretConfigured = $model->isSecretConfigured();
        $this->releaseNotes = $model->getReleaseNotes();
        $this->logContent = $model->getLogContent();

        parent::display($tpl);
    }
}
