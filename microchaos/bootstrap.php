<?php
/**
 * MicroChaos Bootstrap Loader
 *
 * Handles component loading and initialization for MicroChaos.
 * Implements hybrid mu-plugin architecture with bootstrap pattern.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

// Define constants
define('MICROCHAOS_VERSION', '3.0.0');
define('MICROCHAOS_PATH', dirname(__FILE__));
define('MICROCHAOS_CORE_PATH', MICROCHAOS_PATH . '/core');

/**
 * Bootstrap class for MicroChaos
 */
class MicroChaos_Bootstrap {
    /**
     * Initialize the bootstrap process
     */
    public static function init() {
        // Load core components
        self::load_core_components();

        // Initialize components based on context
        if (defined('WP_CLI') && WP_CLI) {
            self::init_cli_components();
        }

        // Future: Admin components will be loaded here
        // if (is_admin()) {
        //     self::init_admin_components();
        // }
    }

    /**
     * Load core component files
     */
    private static function load_core_components() {
        // Load constants first
        require_once MICROCHAOS_CORE_PATH . '/constants.php';

        // Load interfaces
        require_once MICROCHAOS_CORE_PATH . '/interfaces/baseline-storage.php';

        // Load storage implementations
        require_once MICROCHAOS_CORE_PATH . '/storage/transient-baseline-storage.php';

        // Load authentication manager (before commands that use it)
        require_once MICROCHAOS_CORE_PATH . '/authentication-manager.php';

        // Load core components
        $core_components = [
            'thresholds.php',
            'integration-logger.php',
            'commands.php',
            'request-generator.php',
            'cache-analyzer.php',
            'resource-monitor.php',
            'reporting-engine.php',
        ];

        foreach ($core_components as $component) {
            $file_path = MICROCHAOS_CORE_PATH . '/' . $component;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Initialize CLI components
     */
    private static function init_cli_components() {
        if (class_exists('MicroChaos_Commands')) {
            MicroChaos_Commands::register();
        }
    }

    /**
     * Future: Initialize admin components
     */
    private static function init_admin_components() {
        // To be implemented in Phase 2
    }
}

// Initialize the bootstrap
MicroChaos_Bootstrap::init();
