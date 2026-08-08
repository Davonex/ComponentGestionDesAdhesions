<?php
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class JFormFieldSwitch extends FormField
{
    protected $type = 'Switch';

    protected function getInput()
    {
        $checked = $this->value ? 'checked' : '';
        return '
                <div class="form-control">
                    <input class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="' . $this->id . '"
                        name="' . $this->name . '"
                        value="1"
                        ' . $checked . '>
                </div>
        ';
    }


        protected function getLabel($tooltips=null)
    {

        $data = $this->collectLayoutData();

        $tooltips='';
        if ( $this->description  !== "") {
            $tooltips = ' data-bs-toggle="tooltip" data-bs-custom-class="gda-tooltip" title="'.Text::_($this->description).'" ';
        }
        return '
                <label class="form-check-label input-group-text" 
                   ' . $tooltips. '
                    for="' . $this->id . '">
                ' . $data['label'] . '
                </label>
        ';
    }
}
