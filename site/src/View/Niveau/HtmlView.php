<?php

namespace NCB\Component\Gda\Site\View\Niveau;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\GenericDataException;
use NCB\Component\Gda\Site\Model;

class HtmlView extends BaseHtmlView
{
    public $item;

    public function display($tpl=null): void
    {
        $app = Factory::getApplication();
        $layout = $this->getLayout();
        $model = $this->getModel();

        // $this->state      = $this->get('State');
        $this->profil = $model->getItem();

        //  if ( $this->item !== null) {

        //     $this->form = $model->getForm();
        //  }

        // $this->itemsOB =  $this->get('ItemsOB');

        // if (count($errors = $this->get('Errors')))
        // {
        //     throw new GenericDataException(implode("\n",$errors), 500);
        // }
        // $this->form = $this->getModel()->getForm();

        $this->MenuItemId = $app->getMenu()->getActive()->id;
        parent::display($tpl);
    }
}

