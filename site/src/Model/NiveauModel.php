<?php

namespace NCB\Component\Gda\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

use NCB\Component\Gda\Site\Helper\ToolsHelper;


class NiveauModel extends ListModel
{
    protected $_item = null;

    /**
     * Propriété privée pour stocker l'instance de l'application
     */
    private $app = null;

    /**
     * Getter pour obtenir l'instance de l'application (lazy loading)
     */
    private function getApp()
    {
        if ($this->app === null) {
            $this->app = Factory::getApplication();
        }
        return $this->app;
    }

    /**
     *  Recupe les Niveau avec id = pk
     */
    function getItem($pk = null, $reload = false)
    {
        $app = $this->getApp();
        // Get ID of campgane in the session so that
        $session = $this->getApp()->getUserState('session');

        $username = (string) $pk ?: (string) $session['username'];
        if (!$username) {
            // Creation d'un nouveau user
            $this->_item = null;
            // throw new \Exception('User n\'existe pas  id', 404);
        }

        if (!$reload && $this->_item !== null && $this->_item->licence != $username) {
            return $this->_item;
        }

        if ($username) {
            // lister le Profil $Username = licence
            /** @var \Joomla\Database\DatabaseDriver $db */
            $db = $this->getDatabase();
            $query = $db->getQuery(true);

            //$query->select('profils.id_profil')
            $query->select(
                $db->quoteName([
                    'profils.id_profil',
                    'profils.licence',
                    'profils.civilite',
                    'profils.nom',
                    'profils.prenom',
                    'profils.date_de_naissance',
                    'profils.adresse',
                    'profils.ville',
                    'profils.code_postal',
                    'profils.telephone',
                    'profils.a_prevenir',
                    'profils.a_prevenir_tel',
                    'profils.photo'
                ])
            );
            $query->select('users.email');
            $query->from($db->quoteName('#__gda_profils', 'profils'));
            $query->leftjoin($db->quoteName('#__users', 'users'), 'profils.licence = users.username');
            $query->where($db->quoteName('licence') . ' LIKE :licence_key')
                ->bind(':licence_key', $username);

            $db->setQuery($query);

            try {
                $item = $db->loadObject();
            } catch (\RuntimeException $e) {
                throw new \Exception($e->getMessage(), 500);
            }

            $item->date_de_naissance = ToolsHelper::from_sqldate($item->date_de_naissance);
            if ($item->id_profil) {
                $item->niveaux = $this->getMesNiveaux($item->id_profil);
            }

            $this->_item = $item;
        }


        return $this->_item;
    }

    /**
     * Recuperer les niveaux du profile : id_profil
     *
     * @param   int   $id_profil         tous les brever du profil
     
     *
     * @return  object  tous les brevets
     *
     * @since   1.0
     * @throws  exception
     */
    function getMesNiveaux($pk)
    {
        $app = $this->getApp();
        // Get ID of campagne in the session so that
        // $session = $this->getApp()->getUserState('session') ;

        $id_profil = (string) $pk;
        if (!$id_profil) {
            // Creation d'un nouveau user
            //$this->_item = null;
            throw new \Exception('User n\'existe pas', 404);
        }

        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = $this->getDatabase();
        // Create a new query object.
        $query = $db->getQuery(true);
        // creer la requette SQL avec les functions de joomla
        $query->select(
            $db->quoteName([
                'niveaux.id_profil',
                'niveaux.id_brevet',
                'niveaux.obtention',
                'niveaux.lieu',
                'brevets.code',
                'brevets.nom',
                'brevets.section'
            ])
        );
        $query->from($db->quoteName('#__gda_niveaux', 'niveaux'));
        $query->leftjoin($db->quoteName('#__gda_brevets', 'brevets'), 'niveaux.id_brevet = brevets.id_brevet');
        $query->where($db->quoteName('niveaux.id_profil') . ' = :id_profil_key');

        // bind value for prepared statements
        $query->bind(':id_profil_key', $id_profil);
        // Reset the query using our newly populated query object
        $db->setQuery($query);

        try {
            $items = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        // Convert les date du SQL vers le format Francais
        foreach ($items as &$item) {
            if (!\is_null($item->obtention)) {
                $item->obtention  = ToolsHelper::from_sqldate($item->obtention);
            }
        }

        return $items;
    }

    /**
     * Moves an uploaded file to a destination folder
     *
     * @param   object   $profil         Tous les donnée du profil utilisateu
     * @param   boolean  $principale         The path  to move the uploaded file to
     
     *
     * @return  string  texte html d'un profil
     *
     * @since   1.0
     * @throws  
     */
    function showCardProfil($profil, $id_modal = "myModal", $principale = true)
    {
        if ($profil === null) {
            return false;
        }
        $extra_class = " text-bg-gda";
        if (! $principale) {
            $extra_class = ' text-white bg-secondary';
        }

        $result = '<div id="id_' . $profil->id_profil . '" class="col-md-6 col-sm-12">';
        $result .= '<div class="card' . $extra_class . '">';
        // card-header
        $result .= '<div class="card-header">';
        $result .= '<p class="pt-2 float-start">'
            . $this->spanModal('civilite', $profil->civilite) . ' '
            . $this->spanModal('nom', $profil->nom) . ' '
            . $this->spanModal('prenom', $profil->prenom) . ' '
            . '(' . $this->spanModal('licence', $profil->licence) . ')'
            . '</p>';
        $result .= '<a class="btn btn-success float-end" 
                            type="button"  
                            data-bs-id_profil="id_' . $profil->id_profil . '"
                            data-bs-toggle="modal" 
                            data-bs-target="#' . $id_modal . '" 
                            data-toggle="tooltip" 
                            data-placement="top" 
                            title="' . Text::_('COM_GDA_FFESSM_NIVEAU_TOOLTIP') . ' href="#" >
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> </a>';

        $result .= '</div>';
        // card-body
        $result .= '<div class="card-body">';
        $result .= $this->showTableNiveau($profil->niveaux, $principale);
        $result .= '</div>';
        $result .= '<div class="row g-0">';

        $result .= '</div> <!-- row -->';

        $result .= '</> <!-- class="card" -->';
        $result .= '</div>  <!-- class="col" -->';
        return $result;
    }



    /**
     * extract niveau avec le lien fournir sur les carte de licence
     */
    function extractNiveau()
    {
        $app = $this->getApp();

        $data = $app->getUserState('niveau.extract');
        $html = $this->fetchPage($data['url']);
        if ($html === false) {
            return null;
        }
        $tab = $this->ParseHtml($html);
        return $tab;
    }

    function showTableNiveau($niveaux, $principale = true)
    {
        $retour = '<table class="table table-primary table-striped table-hover">';
        $retour .= '<thead><tr><th>Code</th><th>Nom</th><th>Obtention</th><th>Lieu</th></tr></thead>';
        $retour .= '<tbody class="table-group-divider">';
        foreach ($niveaux as $key => $niveau) {
            $retour .= '<tr>';
            $retour .= '<td>' . $niveau->code . '</td>';
            $retour .= '<td>' . $niveau->nom . '</td>';
            $retour .= '<td>' . $niveau->obtention . '</td>';
            $retour .= '<td>' . $niveau->lieu . '</td>';
            $retour .= '</tr>';
        };
        $retour .= '</tbody></table>';
        return $retour;
    }

    /**
     * 
     */
    function showModalffessm($id_modal, $profil)
    {
        $retour = '
        <div class="modal fade" id="' . $id_modal . '" data-bs-backdrop="static" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">';
        /// modal-header
        $retour .= '<h5 class="modal-title">Modal title</h5>';

        $retour .= '<button type="button" id="btnClose" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div> <!--  class="modal-header" -->

            <div class="modal-body" id="modalFormBody">         
                    <form action="" method="post" name="extractForm" id="extractForm">  
                    
                        <div class="control-group">
                            <div class="control-label">
                                <label id="url-lbl" for="url" class="required">URL</label>
                            </div>
                            <div class="controls">  
                                <input type="text" name="extract[url]" id="url" value="http://resto/InfoLicence.html" class="form-control form-control required" size="25" placeholder="25 avenue Ampère" required="">
                            </div>
                        </div>

                        <div class="form-check">
                        <input class="form-check-input" name="extract[save]" type="checkbox" value="1" id="CheckSave">
                        <label class="form-check-label" for="CheckSave">
                            Enregistrer apres extraction
                        </label>
                        </div>
            

                        <input type="hidden" name="task" value="niveau.extract" />
                        <input type="hidden" name="extract[id_profil]" value="' . $profil->id_profil . '" />
                        ' . HtmlHelper::_('form.token') . '
                    </form>

                    <div id="resultat">
                    </div>
            </div> <!--  class="modal-body" -->
            
            
            <div class="modal-footer">	
              <button id="ScrapForm" type="button" class="btn btn-success float-end" >' . Text::_('COM_GDA_SCRAP') . '</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_GDA_CANCEL') . '</button>
            </div> <!--  class="modal-footer" -->

          </div> <!--  class="modal-content" -->
        </div><!--  class="modal-dialog" -->
        </div> <!--  class="modal fade" -->
        ';
        return $retour;
    }


    /**
     * 
     */
    function SaveAfterExtract($idProfil, $brevets)
    {

        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        /**
         * 
         */
        foreach ($brevets as $unBrevetExtrait) {
            $Brevet = $this->inBrevet($unBrevetExtrait);
            if (!is_null($Brevet)) {
                //  tester si existe in niveau
                if (! $this->inNiveau($Brevet, $idProfil)) {
                    // ajout dans la base niveau
                    $this->insertNiveau($Brevet, $idProfil, $unBrevetExtrait);
                }
            } else {
                $toto = $Brevet;
            }
        }
        $toto = "titi";
    }


    /**
     *  @param   array   $UnBrevet         
     *
     *
     *  @return  int    brevet_id ou null
     */
    private function inBrevet(array $unBrevet)
    {

        /** @var \Joomla\Database\DatabaseDriver $db */
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $db = $this->getDatabase();
        // Create a new query object.
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['brevets.id_brevet', 'brevets.code']));
        $query->from($db->quoteName('#__gda_brevets', 'brevets'));
        $query->where($db->quoteName('nom') . ' = :brevet_key')
            ->bind(':brevet_key', $unBrevet['brevet']);

        $db->setQuery($query);

        try {
            $result = $db->loadObject();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        return $result;
    }

    /**
     *  @param   array   $UnBrevet         
     *
     *  @return  int    brevet_id ou null
     */
    private function inNiveau($leBrevet, $idProfil)
    {

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        // Create a new query object.
        $query = $db->getQuery(true);

        $query->select('count(*) as "ct"');
        $query->from($db->quoteName('#__gda_niveaux', 'niveaux'));
        $query->where($db->quoteName('id_profil') . ' = :id_profil_key')
            ->where($db->quoteName('id_brevet') . ' = :id_brevet_key')
            ->bind(':id_brevet_key', $leBrevet->id_brevet)
            ->bind(':id_profil_key', $idProfil);

        $db->setQuery($query);

        try {
            $result = $db->loadObject();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        return $result->ct;
    }


    private function insertNiveau($leBrevet, $idProfil, $BrevetExtrait)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $db = $this->getDatabase();
        // Create a new query object.
        $query = $db->getQuery(true);

        // Insert columns.
        $columns = array('id_profil', 'id_brevet', 'code', 'obtention', 'lieu');

        // Prepare the insert query.
        $query
            ->insert($db->quoteName('#__gda_niveaux'))
            ->columns($db->quoteName($columns))
            ->values(':id_profil_value, :id_brevet_value, :code_value, :obtention_value, :lieu_value');

        // Bind values
        $query
            ->bind(':id_profil_value', $idProfil)
            ->bind(':id_brevet_value', $leBrevet->id_brevet)
            ->bind(':code_value', $leBrevet->code)
            ->bind(':obtention_value', ToolsHelper::to_sqldate($BrevetExtrait['obtention']))
            ->bind(':lieu_value', $BrevetExtrait['lieu']);

        $db->setQuery($query);

        try {
            $result = $db->execute();
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        return $result;
    }

    private function fetchPage($url)
    {

        $html = file_get_contents($url);
        // $ch = curl_init();

        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Suit les redirections
        // // curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; MyScraperBot/1.0)"); // Simule un navigateur

        // $html = curl_exec($ch);
        // $toto = curl_error($ch);
        // curl_close($ch);
        return ($html);
    }

    private function ParseHtml($html)
    {
        $tab = array();
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
                            $tab[$i]['brevet'] = $ele->textContent;
                        } else {
                            $tab[$i]['obtention'] = ToolsHelper::extractDate($ele->textContent);
                            $tab[$i]['lieu'] = $this->extractLieu($ele->textContent);
                            $i++;
                        }
                    }
                }
            }
        }

        return $tab;
    }



    private function extractLieu($str)
    {
        // Expression régulière pour extraire la ville entre "à " et " le"
        // $pattern = '/à\s+([A-Z]+)\s+le/';
        $pattern = '/Délivré à (.+?) le \d{2}\/\d{2}\/\d{4}/';
        preg_match($pattern, $str, $matches);

        // Retourner la ville si trouvée, sinon retourner une chaîne vide
        return isset($matches[1]) ? $matches[1] : '';
    }



    private function spanModal($name, $value, $class = '')
    {
        $ret = '<span class="' . $class . '" data-bs name="' . $name . '">' . $value . '</span>';
        return $ret;
    }
};
