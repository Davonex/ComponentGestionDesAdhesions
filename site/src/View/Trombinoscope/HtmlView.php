<?php

namespace NCB\Component\Gda\Site\View\Trombinoscope;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use NCB\Component\Gda\Site\Model\TrombinoscopeModel;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        /** @var TrombinoscopeModel $model */
        $model = $this->getModel();
        $this->membresBureau = $model->getMembresBureau();

        parent::display($tpl);
    }
}
