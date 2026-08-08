<?php

namespace NCB\Component\Gda\Site\View\Groupes;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Model\GroupesModel;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        $this->saison = ConfHelper::getSaisonService()->getSaisonCourante();

        /** @var GroupesModel $model */
        $model = $this->getModel();
        $this->groupes = $this->saison
            ? $model->getGroupesAvecAdherents((int) $this->saison->id_campagne)
            : [];

        parent::display($tpl);
    }
}
