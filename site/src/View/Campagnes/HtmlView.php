<?php

namespace NCB\Component\Gda\Site\View\Campagnes;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    public $state;
    public $items=[];
    public $pagination;

    public function display($tpl=null): void
    {

        /** @var CMSApplication $app */
        $app = Factory::getApplication();
        /** @var AdhesionModel $model */
        $model = $this->getModel();

  
        $layout = $this->getLayout();


        $this->state      = $this->get('State');
        // $this->items      = $this->get('Items');  //deprecated
        $this->lstCampagnes = $model->getCampagnes();


        
        $this->form = $model->getForm(); 

        $this->MenuItemId = $app->getMenu()->getActive()->id;
        parent::display($tpl);
    // }
        //$this->form = $this->getModel()->getForm();
        
    }
}

