<?php

namespace Modules\AttachmentSecurity\Logging;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Attachment Security Logger Helper
 *
 * Custom logging helper for the Attachment Security module.
 * Provides consistent logging to a dedicated log file (attachmentsecurity.log)
 * with Laravel-compatible format for better integration with FreeScout's admin interface.
 *
 * Features:
 * - Singleton pattern for consistent logger instance
 * - Laravel-compatible log format: [datetime] channel.LEVEL: message
 * - Dedicated log file: storage/logs/attachmentsecurity.log
 * - Support for all standard log levels (debug, info, notice, warning, error, critical, alert, emergency)
 * - Optional message prefix for module identification
 *
 * @package  Modules\AttachmentSecurity
 * @category Logging
 * @author   Raimundo Alba
 * @license  MIT
 * @version  3.4.0
 * @since    3.3.0
 */
class LoggerAttachmentSecurity
{
    /**
     * Singleton instance of the logger
     */
    protected static $instance = null;

    /**
     * Optional prefix for module identification (empty by default)
     */
    protected static $prefix = '';

    /**
     * Get the logger instance (singleton pattern)
     * 
     * @return MonologLogger
     */
    protected static function getLogger()
    {
        if (!self::$instance) {
            $logger = new MonologLogger('attachmentsecurity');

            $handler = new StreamHandler(
                storage_path('logs/attachmentsecurity.log'),
                MonologLogger::DEBUG
            );

            // Laravel-compatible format: [datetime] channel.LEVEL: message
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message%\n",
                "Y-m-d H:i:s",
                true,  // Allow inline line breaks
                true   // Ignore empty context
            );

            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            self::$instance = $logger;
        }

        return self::$instance;
    }

    /**
     * Nivel DEBUG
     */
    public static function debug($message, array $context = [])
    {
        self::getLogger()->debug(self::$prefix.$message, $context);
    }

    /**
     * Nivel INFO
     */
    public static function info($message, array $context = [])
    {
        self::getLogger()->info(self::$prefix.$message, $context);
    }

    /**
     * Nivel NOTICE
     */
    public static function notice($message, array $context = [])
    {
        self::getLogger()->notice(self::$prefix.$message, $context);
    }

    /**
     * Nivel WARNING
     */
    public static function warning($message, array $context = [])
    {
        self::getLogger()->warning(self::$prefix.$message, $context);
    }

    /**
     * Nivel ERROR
     */
    public static function error($message, array $context = [])
    {
        self::getLogger()->error(self::$prefix.$message, $context);
    }

    /**
     * Nivel CRITICAL
     */
    public static function critical($message, array $context = [])
    {
        self::getLogger()->critical(self::$prefix.$message, $context);
    }

    /**
     * Nivel ALERT
     */
    public static function alert($message, array $context = [])
    {
        self::getLogger()->alert(self::$prefix.$message, $context);
    }

    /**
     * Nivel EMERGENCY
     */
    public static function emergency($message, array $context = [])
    {
        self::getLogger()->emergency(self::$prefix.$message, $context);
    }
}

