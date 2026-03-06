<?php

namespace Modules\AttachmentSecurity\Logging;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

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

