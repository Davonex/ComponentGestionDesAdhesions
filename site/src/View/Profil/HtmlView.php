<?php

namespace NCB\Component\Gda\Site\View\Profil;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\GenericDataException;

class HtmlView extends BaseHtmlView
{
    public $item;

    public function display($tpl=null): void
    {
         /** @var CMSApplication $app */
        $app = Factory::getApplication();

         /** @var ProfilModel $model */
        $model = $this->getModel();

        //$user =  $app->getIdentity();

        // $this->state      = $this->get('State');
        $this->item = $model->getItem(); 

         if ( $this->item !== null) {

            $this->form = $model->getForm();
         }
         /** Liste des elements on Behalf */
        $this->itemsOB =  $model->getItemsOB();

        /** Formulaire dédié à la mise à jour du CACI (modale distincte) */
        $this->formCaci = $model->getCaciForm();

        // if (count($errors = $this->get('Errors')))
        // {
        //     throw new GenericDataException(implode("\n",$errors), 500);
        // }
        // $this->form = $this->getModel()->getForm();

        $this->MenuItemId = $app->getMenu()->getActive()->id;
        parent::display($tpl);
    }
}

