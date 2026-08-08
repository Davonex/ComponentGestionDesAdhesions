<?php

// namespace My\Module\Hello\Site\Helper;
namespace NCB\Component\Gda\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Http\HttpFactory;


class AdhesionHelper
{

    /**
     * Rend un champ de formulaire avec le style et les info-bulles appropriés.
      *
      * @param   object  $field  L'objet du champ de formulaire contenant les attributs et le HTML d'entrée.
      * @param   string|null  $label  Un label optionnel pour le champ (non utilisé dans l'implémentation actuelle). 
     */
    public static function renderField($field, $label = null): string
    {
        /* ajoute µ pour les champs requis et les tooltips */
        $required = "";
        if ($field->getAttribute('required') === "true") {
            $required = " *";
        }
        /* personalisation des tooltips avec la description */
        $tooltips = '';
        if ($field->getAttribute('description') && $field->getAttribute('description') !== "") {
            $tooltips = ' data-bs-toggle="tooltip" data-bs-custom-class="gda-tooltip" title="' . Text::_($field->getAttribute('description')) . '" ';
        }


        // Ajouter une classe is-valid si une valeur est fournie, donc si ce sont les données deja enregistrées
        $fieldInput =   $field->input;
        if ($field->value !== "") {
            $fieldInput = preg_replace('/class="([^"]*)"/', 'class="$1 is-valid"', $fieldInput, 1);
        }

        if ($field->getAttribute('type') === "switch") {


            $result = '
                    <div class="droit-image form-check form-switch input-group mb-3">
                        ' . $field->label . '
                        ' . $fieldInput . '                           
                    </div>
                ';
        } else {
            // tous les autre type
            $result =  '<div class="input-group mb-3">';
            // $result .=      '<span class="input-group-text" data-bs-toggle="tooltip" data-bs-placement="top" title="Description détaillée du champ">?</span>';
            $result .=      '<span class="input-group-text" ' . $tooltips . '>' . Text::_($field->getAttribute('label')) . $required . '</span>';
            $result .=      $fieldInput;
            $result .= '</div>';
        }
        return $result;
    }


    /**
     * Scrapes data from the given URL.
     *
     * @param   string  $url  The URL to scrape.
     * @return  array  The scraped data.
     * @throws  \Exception  If the HTTP request fails.

     */
    public static function scrap($url)
    {

        $data = array();
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'timeout' => 10,
                'header' => "User-Agent: Mozilla/5.0 Joomla5/Scanner\r\n"
            ]
        ]);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            $httpCode = 0;
            if (!empty($http_response_header)) {
                // Ex: "HTTP/1.1 404 Not Found" → extrait 404
                preg_match('/\d{3}/', $http_response_header[0], $matches);
                $httpCode = (int) ($matches[0] ?? 0);
            }
            throw new \Exception(Text::sprintf('COM_GDADHESIONS_ERROR_BAD_RESPONSE', $httpCode));
        }

        $data["brevets"] = self::ParseBrevets($response);
        $data["informations"] = self::ParseInformation($response);

        return $data;
    }


    /**
     * 
     */
    private static function ParseBrevets($html)
    {
        $result = array();
        $i = 0;
        $dom  = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        //"/html/body/div[@id='yourTagIdHere']"
        // Rechercher un élément par classe
        $elements = $xpath->query("//div[contains(@class, 'card-header')]");
        foreach ($elements as $element) {
            if ($element->textContent === "Information sur les brevets") {
                /** @var \DOMElement|null $element */
                $parent = $element->nextElementSibling;
                foreach ($parent->childNodes as  $key => $ele) {
                    if (get_class($ele) === "DOMElement") {
                        // echo "<p>$key : $ele->textContent</p>";
                        /** @var \DOMElement|null $ele */
                        if ($ele->getAttribute('class') === "font-weight-bold") {
                            $result[$i]['nom'] = $ele->textContent;
                        } else {
                            $result[$i]['obtention'] = ToolsHelper::extractDate($ele->textContent, true);
                            $result[$i]['lieu'] = self::extractLieu($ele->textContent);
                            $i++;
                        }
                    }
                }
            }
        }

        return $result;
    }


    private static function ParseInformation($html)
    {
        $result = array();
        $i = 0;
        $dom  = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        //"/html/body/div[@id='yourTagIdHere']"
        // Rechercher un élément par classe
        $elements = $xpath->query("//div[contains(@class, 'card-header')]");
        // ---- 1. Civilité + prénom + nom (premier bloc <div class="card">) ----
        $civiliteNode = $xpath->query("//div[contains(@class,'card')][1]//p[1]")->item(0);
        if ($civiliteNode) {
            $fullName = trim($civiliteNode->textContent); // ex: "Mme JULIETTE MATHIEU"
            if (preg_match('/^(Mme|Mlle|Madame|Monsieur|Mr)\s+(.+)$/ui', $fullName, $m)) {
                $result['civilite'] = $m[1];
                $parts = preg_split('/\s+/', trim($m[2]));
                if (count($parts) >= 2) {
                    $result['prenom'] = ucfirst(mb_strtolower($parts[0], 'UTF-8'));
                    $result['nom']    = mb_strtoupper(end($parts), 'UTF-8');
                }
            }
        }

        // ---- 2. Licence ----
        // Rechercher <p> qui suit immédiatement un <p> contenant "Licence"
        $licenceNode = $xpath->query("//p[contains(text(),'Licence')]/following-sibling::p[1]")->item(0);
        if ($licenceNode) {
            $result['licence'] = trim($licenceNode->textContent);
        }

        // ---- 3. Date de validité (dans carte "Information sur la licence") ----
        $validiteNode = $xpath->query("//div[contains(.,'Information sur la licence')]//p[contains(text(),'Date de validité')]/following-sibling::p[1]")->item(0);
        if ($validiteNode) {
            $result['validite'] = trim($validiteNode->textContent);
        }

        return $result;
    }


    /**
     * Extraire le lieu d'obtention d'une licence à partir d'une chaîne de texte.
     *
     * @param   string  $str  La chaîne de texte contenant le lieu d'obtention.
     * @return  string  Le lieu d'obtention extrait, ou une chaîne vide si non trouvé.

     */

    private static function extractLieu($str)
    {
        // Expression régulière pour extraire la ville entre "à " et " le"
        // $pattern = '/à\s+([A-Z]+)\s+le/';
        $pattern = '/Délivré à (.+?) le \d{2}\/\d{2}\/\d{4}/';
        preg_match($pattern, $str, $matches);

        // Retourner la ville si trouvée, sinon retourner une chaîne vide
        return isset($matches[1]) ? $matches[1] : '';
    }
}
