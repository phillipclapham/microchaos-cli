<?php
/**
 * WP-CLI Logger Implementation
 *
 * Production logger that delegates to WP-CLI output methods.
 * Used when running MicroChaos via WP-CLI.
 *
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos WP-CLI Logger
 *
 * Production implementation that delegates to WP-CLI output methods.
 */
class MicroChaos_WP_CLI_Logger implements MicroChaos_Logger_Interface {

    /**
     * Log a standard message
     *
     * @param string $message The message to log
     * @return void
     */
    public function log(string $message): void {
        \WP_CLI::log($message);
    }

    /**
     * Log a success message (green)
     *
     * @param string $message The success message
     * @return void
     */
    public function success(string $message): void {
        \WP_CLI::success($message);
    }

    /**
     * Log a warning message (yellow)
     *
     * @param string $message The warning message
     * @return void
     */
    public function warning(string $message): void {
        \WP_CLI::warning($message);
    }

    /**
     * Log an error message (red) and exit
     *
     * Note: This method calls \WP_CLI::error() which exits the process.
     * This maintains backward compatibility with existing code that
     * relies on error() not returning.
     *
     * @param string $message The error message
     * @return void
     */
    public function error(string $message): void {
        \WP_CLI::error($message);
        // Note: Code after this line is unreachable - WP_CLI::error() exits
    }

    /**
     * Log a debug message (only visible with --debug flag)
     *
     * @param string $message The debug message
     * @return void
     */
    public function debug(string $message): void {
        \WP_CLI::debug($message);
    }
}
