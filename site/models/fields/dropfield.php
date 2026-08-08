<?php
namespace Gdadhesions\Component\Gdadhesions\Site\Field;

use Joomla\CMS\Form\FormField;

defined('_JEXEC') or die;

/**
 * Champ personnalisé : Drag & Drop image
 */
class DropField extends FormField
{
    protected $type = 'Dropimage';

    protected function getInput()
    {
        // Identifiant unique
        $id = $this->id;
        $name = $this->name;

        // HTML du champ
        $html = <<<HTML
        <div class="dropzone" id="{$id}_dropzone">
            <input type="file" name="{$name}" id="{$id}" accept="image/*" hidden>
            <p>Déposez une image ici ou cliquez pour sélectionner</p>
        </div>

        <script>
        (function() {
            const dz = document.getElementById("{$id}_dropzone");
            const input = document.getElementById("{$id}");

            // Clic => ouvre le file picker
            dz.addEventListener("click", () => input.click());

            // Drag over
            dz.addEventListener("dragover", e => {
                e.preventDefault();
                dz.classList.add("dragover");
            });

            // Drag leave
            dz.addEventListener("dragleave", e => {
                dz.classList.remove("dragover");
            });

            // Drop
            dz.addEventListener("drop", e => {
                e.preventDefault();
                dz.classList.remove("dragover");

                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;

                    // Aperçu si image
                    if (input.files[0].type.startsWith("image/")) {
                        const reader = new FileReader();
                        reader.onload = ev => {
                            dz.innerHTML = "<img src='" + ev.target.result + "' style='max-width:100%; border-radius:8px;'/>";
                        };
                        reader.readAsDataURL(input.files[0]);
                    }
                }
            });
        })();
        </script>

        <style>
        .dropzone {
            border: 2px dashed #007bff;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            border-radius: 10px;
            transition: background 0.3s;
        }
        .dropzone.dragover {
            background: rgba(0,123,255,0.1);
        }
        .dropzone img {
            margin-top: 10px;
        }
        </style>
        HTML;

        return $html;
    }
}
