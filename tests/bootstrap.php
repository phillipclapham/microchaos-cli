<?php
/**
 * PHPUnit Bootstrap for MicroChaos CLI
 *
 * Loads MicroChaos classes without WordPress runtime.
 * Provides minimal stubs for WordPress functions used by testable components.
 */

// Define constants to allow file loading (bypasses direct access guards)
define('WP_CLI', true);
define('ABSPATH', '/tmp/fake-wordpress/');

// Define path constants that bootstrap.php normally sets
define('MICROCHAOS_VERSION', '3.0.0-test');
define('MICROCHAOS_PATH', dirname(__DIR__) . '/microchaos');
define('MICROCHAOS_CORE_PATH', MICROCHAOS_PATH . '/core');

/**
 * WordPress Stubs
 *
 * Minimal implementations of WordPress functions used by testable components.
 * These are NOT meant to replicate WordPress behavior - just prevent fatal errors.
 */

// Transient stubs (used by Thresholds save/load)
function set_transient(string $key, mixed $value, int $expiry = 0): bool {
    return true; // Always succeeds in tests
}

function get_transient(string $key): mixed {
    return false; // Always returns "not found" in tests
}

/**
 * Load MicroChaos Components
 *
 * Order matters - matches bootstrap.php loading sequence.
 * Only load what we need for current tests.
 */

// Core constants (other components may reference these)
require_once MICROCHAOS_CORE_PATH . '/constants.php';

// Interfaces (load before implementations)
require_once MICROCHAOS_CORE_PATH . '/interfaces/logger.php';

// Logging infrastructure (needed for components that log)
require_once MICROCHAOS_CORE_PATH . '/log.php';
require_once MICROCHAOS_CORE_PATH . '/logging/null-logger.php';

// Components under test
require_once MICROCHAOS_CORE_PATH . '/thresholds.php';
require_once MICROCHAOS_CORE_PATH . '/cache-analyzer.php';
require_once MICROCHAOS_CORE_PATH . '/authentication-manager.php';

/**
 * WordPress Stubs - Classes
 *
 * Simple stub classes for WordPress types used by testable components.
 */

/**
 * Stub for WP_Http_Cookie
 *
 * AuthManager's format_for_curl() expects objects with name/value properties.
 */
class WP_Http_Cookie {
    public string $name;
    public string $value;

    public function __construct(string $name, string $value) {
        $this->name = $name;
        $this->value = $value;
    }
}

/**
 * Initialize Test Logger
 *
 * Use Null_Logger so components can log without WP-CLI dependency.
 */
MicroChaos_Log::set_logger(new MicroChaos_Null_Logger());
