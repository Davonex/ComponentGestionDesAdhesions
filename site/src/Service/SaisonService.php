<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use NCB\Component\Gda\Site\Helper\ConfHelper;

/**
 * Service métier pour la gestion des saisons.
 */
final class SaisonService
{
    private DatabaseInterface $db;
    private GdaConfigService $config;
    private ?object $saison = null;
    private bool $saisonChargee = false;
    private ?object $saisonCourante = null;
    private bool $saisonCouranteChargee = false;

    public function __construct(DatabaseInterface $db, GdaConfigService $config)
    {
        $this->db     = $db;
        $this->config = $config;
    }

    /**
     * Récupérer la campagne de type Saison correspondant au critère donné (colonne booléenne),
     * décorée des champs HelloAsso (formSlug, formType, url).
     *
     * @param  string      $colonne  Colonne booléenne à filtrer ('active' = ouverte, 'courante' = courante)
     * @return object|null           Objet campagne enrichi, ou null si aucune/plusieurs correspondances
     */
    private function chargerSaison(string $colonne): ?object
    {
        $id_type_saison = $this->config->getValue('IdTypeSaison');

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__gda_campagnes'))
            ->where($this->db->quoteName('id_type') . ' = :id_type_saison')
            ->where($this->db->quoteName($colonne) . ' = 1')
            ->where($this->db->quoteName('effacer') . ' = 0')
            ->bind(':id_type_saison', $id_type_saison);

        $this->db->setQuery($query);

        try {
            $items = $this->db->loadObjectList();
        } catch (\RuntimeException $e) {
            throw new \Exception(Text::_('COM_GDA_ERROR_CAMPAGNES'), 404, $e);
        }

        if (count($items) !== 1) {
            return null;
        }

        $HA = json_decode($items[0]->event_helloasso, true);
        $saison = $items[0];
        $saison->formSlug = $HA['formSlug'] ?? null;
        $saison->formType = $HA['formType'] ?? null;
        $saison->url = $HA['url'] ?? null;

        return $saison;
    }



    /**
     * Récupérer une campagne de type Saison par son identifiant.
     *
     * @param int|null $id_saison  Identifiant de la campagne saison
     * @return object|null         Objet campagne enrichi ou null si introuvable
     */
    public static function getSaison(?int $id_saison): ?object
    {
        if ($id_saison === null) {
            return null;
        }

        $id_type_saison = ConfHelper::getConfigService()->getValue('IdTypeSaison');
        $db = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gda_campagnes'))
            ->where($db->quoteName('id_type') . ' = :id_type_saison')
            ->where($db->quoteName('id_campagne') . ' = :id_saison')
            ->bind(':id_type_saison', $id_type_saison)
            ->bind(':id_saison', $id_saison, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $items = $db->loadObjectList();

            if (count($items) !== 1) {
                return null;
            }

            $HA     = json_decode($items[0]->event_helloasso, true);
            $result = $items[0];
            $result->formSlug = $HA['formSlug'] ?? null;
            $result->formType = $HA['formType'] ?? null;
            $result->url      = $HA['url'] ?? null;

            return $result;
        } catch (\RuntimeException $e) {
            return null;
        }
    }


    /**
     * Récupérer la saison ouverte (inscriptions possibles : active = 1, effacer = 0).
     *
     * @return object|null  Objet campagne ou null si aucune saison ouverte
     */
    public function getSaisonOuverte(): ?object
    {
        if (!$this->saisonChargee) {
            $this->saison = $this->chargerSaison('active');
            $this->saisonChargee = true;
        }
        return $this->saison;
    }

    /**
     * Récupérer la saison courante (saison de suivi de l'année en cours : courante = 1, effacer = 0),
     * indépendamment du fait que les inscriptions y soient encore ouvertes ou non.
     *
     * @return object|null  Objet campagne ou null si aucune saison courante déclarée
     */
    public function getSaisonCourante(): ?object
    {
        if (!$this->saisonCouranteChargee) {
            $this->saisonCourante = $this->chargerSaison('courante');
            $this->saisonCouranteChargee = true;
        }
        return $this->saisonCourante;
    }
}