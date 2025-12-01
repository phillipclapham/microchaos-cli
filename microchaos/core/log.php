<?php
/**
 * Log Facade
 *
 * Static facade for logging operations. Uses the registry pattern to allow
 * different logger implementations (WP-CLI, Null for testing, etc.).
 *
 * Usage:
 *   MicroChaos_Log::set_logger(new MicroChaos_WP_CLI_Logger());
 *   MicroChaos_Log::log("Hello world");
 *
 * For testing:
 *   MicroChaos_Log::set_logger(new MicroChaos_Null_Logger());
 *
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos Log Facade
 *
 * Provides static methods for logging that delegate to the configured
 * logger implementation. If no logger is set, calls are silently ignored.
 */
class MicroChaos_Log {

    /**
     * The logger instance
     *
     * @var MicroChaos_Logger_Interface|null
     */
    private static ?MicroChaos_Logger_Interface $logger = null;

    /**
     * Set the logger implementation
     *
     * @param MicroChaos_Logger_Interface $logger The logger to use
     * @return void
     */
    public static function set_logger(MicroChaos_Logger_Interface $logger): void {
        self::$logger = $logger;
    }

    /**
     * Get the current logger implementation
     *
     * @return MicroChaos_Logger_Interface|null
     */
    public static function get_logger(): ?MicroChaos_Logger_Interface {
        return self::$logger;
    }

    /**
     * Check if a logger is configured
     *
     * @return bool
     */
    public static function has_logger(): bool {
        return self::$logger !== null;
    }

    /**
     * Log a standard message
     *
     * @param string $message The message to log
     * @return void
     */
    public static function log(string $message): void {
        if (self::$logger !== null) {
            self::$logger->log($message);
        }
    }

    /**
     * Log a success message
     *
     * @param string $message The success message
     * @return void
     */
    public static function success(string $message): void {
        if (self::$logger !== null) {
            self::$logger->success($message);
        }
    }

    /**
     * Log a warning message
     *
     * @param string $message The warning message
     * @return void
     */
    public static function warning(string $message): void {
        if (self::$logger !== null) {
            self::$logger->warning($message);
        }
    }

    /**
     * Log an error message
     *
     * Note: The underlying logger may exit or throw after logging.
     *
     * @param string $message The error message
     * @return void
     */
    public static function error(string $message): void {
        if (self::$logger !== null) {
            self::$logger->error($message);
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message The debug message
     * @return void
     */
    public static function debug(string $message): void {
        if (self::$logger !== null) {
            self::$logger->debug($message);
        }
    }

    /**
     * Reset the logger (primarily for testing)
     *
     * @return void
     */
    public static function reset(): void {
        self::$logger = null;
    }
}
