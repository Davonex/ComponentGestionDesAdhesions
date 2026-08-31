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
      * @param   object  $field      L'objet du champ de formulaire contenant les attributs et le HTML d'entrée.
      * @param   string|null  $label  Un label optionnel pour le champ (non utilisé dans l'implémentation actuelle).
      * @param   string  $extraHtml  HTML optionnel affiché juste à droite du champ (même ligne, à
      *                              l'intérieur du groupe), par exemple un message de validation
      *                              rempli dynamiquement en JS. Ignoré pour les champs de type "switch".
     */
    public static function renderField($field, $label = null, string $extraHtml = ''): string
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
            $result .=      $extraHtml;
            $result .= '</div>';
        }
        return $result;
    }


    /**
     * Met en forme le titulaire d'une carte licence scannée : "M. Jean DUPONT (062553)".
     *
     * Le scraping peut ne pas retrouver l'état civil (structure de page FFESSM modifiée) : on se
     * rabat alors sur le seul numéro de licence, toujours présent à ce stade.
     *
     * @param   array  $informations  Le sous-tableau "informations" retourné par scrap().
     */
    public static function formatPorteurLicence(array $informations): string
    {
        $licence = trim((string) ($informations['licence'] ?? ''));

        $etatCivil = trim(implode(' ', array_filter([
            $informations['civilite'] ?? '',
            $informations['prenom'] ?? '',
            $informations['nom'] ?? '',
        ])));

        return $etatCivil !== '' ? $etatCivil . ' (' . $licence . ')' : $licence;
    }


    /**
     * Hôtes autorisés pour scrap() : les cartes de licence FFESSM sont accessibles via ce
     * raccourcisseur de lien officiel (voir les QR codes scannés, ex: https://l.ffessm.fr/c.asp?id=...).
     * Restreint volontairement l'hôte INITIAL de la requête (le raccourcisseur redirige ensuite
     * vers la page réelle de la carte, redirection suivie via follow_location) : sans ce filtre,
     * scrap() ferait exécuter par le serveur une requête HTTP vers n'importe quelle URL fournie
     * par l'appelant (SSRF), y compris le réseau interne.
     */
    private const HOTES_AUTORISES = ['l.ffessm.fr'];

    /**
     * Scrapes data from the given URL.
     *
     * @param   string  $url  The URL to scrape.
     * @return  array  The scraped data.
     * @throws  \Exception  Si l'hôte de l'URL n'est pas dans HOTES_AUTORISES, ou si la requête HTTP échoue.
     */
    public static function scrap($url)
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === false || !in_array(strtolower($host), self::HOTES_AUTORISES, true)) {
            throw new \Exception(Text::_('COM_GDA_ADHESION_SCAN_INVALID_HOST'));
        }

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
