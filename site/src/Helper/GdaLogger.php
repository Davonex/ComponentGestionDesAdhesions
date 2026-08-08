<?php

/***
 * Logger helper for the Gdadhesions component.
 * Provides methods to log messages with different severity levels.
 */

namespace NCB\Component\Gda\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

class GdaLogger
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // Active uniquement en mode Debug
        // if (!Factory::getApplication()->get('debug')) {
        //     return;
        // }

        Log::addLogger(
            [
                'text_file'         => 'com_gdadhesions.php',
                'text_entry_format' => '{DATETIME} [{PRIORITY}] {MESSAGE}',
            ],
            Log::ALL,
            ['com_gdadhesions']
        );
    }


    /**
     * Logs an informational message.
     *
     * @param string $message The message to log.
     * 
     */
    public static function info(string $message): void
    {
        self::init();
        Log::add($message, Log::INFO, 'com_gdadhesions');
    }

    /**
     * Logs a warning message.
     *
     * @param string $message The message to log.
     * 
     */
    public static function warning(string $message): void
    {
        self::init();
        Log::add($message, Log::WARNING, 'com_gdadhesions');
    }

    /**
     * Logs an error message.
     *
     * @param string $message The message to log.
     * 
     */
    public static function error(string $message): void
    {
        self::init();
        Log::add($message, Log::ERROR, 'com_gdadhesions');
    }


    /**
     * Logs a debug message.
     *
     * @param string $message The message to log.
     * 
     */
    public static function debug(string $message): void
    {
        self::init();
        Log::add($message, Log::DEBUG, 'com_gdadhesions');
    }
}


// GdaLogger::debug(...)
// GdaLogger::info(...)
// GdaLogger::warning(...)
// GdaLogger::error(...)
// GdaLogger::sql(...)
// GdaLogger::mail(...)