<?php
/**
 * Null Logger Implementation
 *
 * Test logger that captures messages without outputting them.
 * Provides methods to retrieve captured messages for test assertions.
 *
 * Usage in tests:
 *   $logger = new MicroChaos_Null_Logger();
 *   MicroChaos_Log::set_logger($logger);
 *
 *   // ... run code that logs ...
 *
 *   $this->assertContains('Expected message', $logger->get_logs());
 *
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos Null Logger
 *
 * Implements the Logger interface for testing. Captures all messages
 * and provides getters for assertions. Throws RuntimeException on
 * error() to simulate WP-CLI's exit behavior in a testable way.
 */
class MicroChaos_Null_Logger implements MicroChaos_Logger_Interface {

    /**
     * Captured log messages
     *
     * @var array<string>
     */
    private array $logs = [];

    /**
     * Captured success messages
     *
     * @var array<string>
     */
    private array $successes = [];

    /**
     * Captured warning messages
     *
     * @var array<string>
     */
    private array $warnings = [];

    /**
     * Captured error messages
     *
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Captured debug messages
     *
     * @var array<string>
     */
    private array $debugs = [];

    /**
     * Whether to throw on error() calls
     *
     * @var bool
     */
    private bool $throw_on_error = true;

    /**
     * Log a standard message
     *
     * @param string $message The message to log
     * @return void
     */
    public function log(string $message): void {
        $this->logs[] = $message;
    }

    /**
     * Log a success message
     *
     * @param string $message The success message
     * @return void
     */
    public function success(string $message): void {
        $this->successes[] = $message;
    }

    /**
     * Log a warning message
     *
     * @param string $message The warning message
     * @return void
     */
    public function warning(string $message): void {
        $this->warnings[] = $message;
    }

    /**
     * Log an error message
     *
     * By default, throws RuntimeException to simulate WP-CLI's exit behavior.
     * This allows tests to catch the exception and assert on the message.
     *
     * @param string $message The error message
     * @return void
     * @throws \RuntimeException When throw_on_error is true (default)
     */
    public function error(string $message): void {
        $this->errors[] = $message;

        if ($this->throw_on_error) {
            throw new \RuntimeException("MicroChaos Error: $message");
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message The debug message
     * @return void
     */
    public function debug(string $message): void {
        $this->debugs[] = $message;
    }

    // =========================================================================
    // Test Utility Methods
    // =========================================================================

    /**
     * Get all captured log messages
     *
     * @return array<string>
     */
    public function get_logs(): array {
        return $this->logs;
    }

    /**
     * Get all captured success messages
     *
     * @return array<string>
     */
    public function get_successes(): array {
        return $this->successes;
    }

    /**
     * Get all captured warning messages
     *
     * @return array<string>
     */
    public function get_warnings(): array {
        return $this->warnings;
    }

    /**
     * Get all captured error messages
     *
     * @return array<string>
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Get all captured debug messages
     *
     * @return array<string>
     */
    public function get_debugs(): array {
        return $this->debugs;
    }

    /**
     * Get all captured messages across all types
     *
     * @return array<string, array<string>>
     */
    public function get_all(): array {
        return [
            'logs' => $this->logs,
            'successes' => $this->successes,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'debugs' => $this->debugs,
        ];
    }

    /**
     * Check if a specific message was logged (any type)
     *
     * @param string $needle The message to search for
     * @return bool
     */
    public function has_message(string $needle): bool {
        $all_messages = array_merge(
            $this->logs,
            $this->successes,
            $this->warnings,
            $this->errors,
            $this->debugs
        );

        foreach ($all_messages as $message) {
            if (strpos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear all captured messages
     *
     * @return void
     */
    public function clear(): void {
        $this->logs = [];
        $this->successes = [];
        $this->warnings = [];
        $this->errors = [];
        $this->debugs = [];
    }

    /**
     * Set whether error() should throw an exception
     *
     * Use this to test error handling paths where you need code to continue
     * after an error is logged.
     *
     * @param bool $throw Whether to throw on error
     * @return void
     */
    public function set_throw_on_error(bool $throw): void {
        $this->throw_on_error = $throw;
    }

    /**
     * Get the count of messages by type
     *
     * @return array<string, int>
     */
    public function get_counts(): array {
        return [
            'logs' => count($this->logs),
            'successes' => count($this->successes),
            'warnings' => count($this->warnings),
            'errors' => count($this->errors),
            'debugs' => count($this->debugs),
            'total' => count($this->logs) + count($this->successes) +
                       count($this->warnings) + count($this->errors) +
                       count($this->debugs),
        ];
    }
}
