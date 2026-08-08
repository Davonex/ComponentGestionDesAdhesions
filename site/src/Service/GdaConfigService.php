<?php

namespace NCB\Component\Gda\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Helper\ToolsHelper;

/**
 * Service dédié pour la configuration GDA.
 *
 * Charge toutes les clés depuis #__gda_conf une seule fois par requête HTTP
 * (lazy-loading + cache en mémoire). Pas de stockage en session.
 */
final class GdaConfigService
{
    private ?array $config = null;
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Récupérer la valeur d'une clé de configuration.
     *
     * @param  string       $key  Nom de la clé
     * @return string|false       Valeur ou false si inexistante
     */
    public function getValue(string $key): string|false
    {
        return $this->getAll()[$key] ?? false;
    }

    /**
     * Récupérer toutes les clés de configuration sous forme de tableau associatif.
     *
     * @return array<string, string>
     */
    public function getAll(): array
    {
        if ($this->config === null) {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(['key', 'value']))
                ->from($this->db->quoteName('#__gda_conf'));

            $this->db->setQuery($query);

            try {
                $rows = $this->db->loadObjectList();
            } catch (\RuntimeException $e) {
                throw new \Exception($e->getMessage(), 500, $e);
            }

            $this->config = [];
            foreach ($rows as $row) {
                $this->config[$row->key] = $row->value;
            }
        }

        return $this->config;
    }

    /**
     * Construire l'URL source d'une image à partir de la configuration.
     *
     * @param  string      $module  Préfixe de la clé (ex: "CampagneImage")
     * @param  string      $img     Nom du fichier image
     * @return string|null          URL absolue ou null si module vide
     * @throws \Exception           Si le fichier par défaut est introuvable
     */
    public function getImageSrc(string $module, string $img): ?string
    {
        if (!$module) {
            return null;
        }

        $conf = $this->getAll();
        $imageSrc = strtolower((string) ($conf[$module . 'Path'] ?? '') . $img);

        if (empty($imageSrc) || !\is_file(JPATH_ROOT . $imageSrc)) {
            $imageSrc = (string) ($conf[$module . 'Default'] ?? '');
            if (!\is_file(JPATH_ROOT . $imageSrc)) {
                throw new \Exception('Fichier inexistant : ' . JPATH_ROOT . $imageSrc);
            }
        }

        return Uri::root() . $imageSrc . '?id=' . ToolsHelper::getUniqStr(4);
    }
}
