<?php

namespace NCB\Component\Gda\Site\Helper;


\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use NCB\Component\Gda\Site\Service\GdaConfigService;
use NCB\Component\Gda\Site\Service\SaisonService;

/**
 * Façade statique pour accéder aux services GDA (singletons par requête).
 */
class ConfHelper
{
    private static ?GdaConfigService $service = null;
    private static ?SaisonService $saisonService = null;

    /**
     * Obtenir l'instance partagée de GdaConfigService.
     */
    public static function getConfigService(): GdaConfigService
    {
        return self::getService();
    }

    private static function getService(): GdaConfigService
    {
        if (self::$service === null) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            self::$service = new GdaConfigService($db);
        }
        return self::$service;
    }

    /**
     * @deprecated Utiliser getValue() directement, plus besoin d'initialiser la conf en session.
     */
    public static function setConf(): void
    {
        // No-op : la conf est désormais lazy-loaded par GdaConfigService.
        // Conservé temporairement pour ne pas casser les appels existants.
    }

    /**
     * Récupérer une variable de la conf GDA.
     */
    public static function getValue(string $key): string|false

    {
        $resultat = self::getService()->getValue($key);
        return $resultat;
    }

    /**
     * @deprecated Utiliser getValue() à la place.
     */
    public static function GetKey(string $key): string|false
    {
        return self::getValue($key);
    }

    /**
     * Récupérer toutes les clés de configuration.
     */
    public static function GetAllKeys(): array
    {
        return self::getService()->getAll();
    }

    public static function getImageSrc($module, $img)
    {
        return self::getService()->getImageSrc((string) $module, (string) $img);
    }

    /**
     * Obtenir l'instance partagée de SaisonService (singleton par requête).
     */
    public static function getSaisonService(): SaisonService
    {
        if (self::$saisonService === null) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            self::$saisonService = new SaisonService($db, self::getService());
        }
        return self::$saisonService;
    }
}
