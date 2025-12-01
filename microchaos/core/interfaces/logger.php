<?php
/**
 * Logger Interface
 *
 * Abstracts logging operations to enable testing without WP-CLI dependency.
 * Implementations can output to WP-CLI, null (testing), files, etc.
 *
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos Logger Interface
 *
 * Defines the contract for all logging implementations.
 */
interface MicroChaos_Logger_Interface {

    /**
     * Log a standard message
     *
     * @param string $message The message to log
     * @return void
     */
    public function log(string $message): void;

    /**
     * Log a success message
     *
     * @param string $message The success message
     * @return void
     */
    public function success(string $message): void;

    /**
     * Log a warning message
     *
     * @param string $message The warning message
     * @return void
     */
    public function warning(string $message): void;

    /**
     * Log an error message
     *
     * Note: Implementations may exit/throw after logging.
     * - WP_CLI_Logger: Calls \WP_CLI::error() which exits
     * - Null_Logger: Throws RuntimeException for test assertions
     *
     * @param string $message The error message
     * @return void
     */
    public function error(string $message): void;

    /**
     * Log a debug message (only visible with --debug flag in WP-CLI)
     *
     * @param string $message The debug message
     * @return void
     */
    public function debug(string $message): void;
}
