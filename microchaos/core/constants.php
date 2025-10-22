<?php
/**
 * MicroChaos Constants
 *
 * Centralized constants for MicroChaos CLI configuration values.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos Constants class
 */
class MicroChaos_Constants {
    /**
     * Default baseline storage TTL (30 days in seconds)
     */
    const BASELINE_TTL = 2592000; // 30 * 24 * 60 * 60

    /**
     * Default timeout for parallel test execution (10 minutes in seconds)
     */
    const DEFAULT_PARALLEL_TIMEOUT = 600;

    /**
     * Default number of parallel workers
     */
    const DEFAULT_WORKERS = 3;

    /**
     * Time conversion constants
     */
    const SECONDS_PER_MINUTE = 60;
    const SECONDS_PER_HOUR = 3600;
    const SECONDS_PER_DAY = 86400;

    /**
     * HTTP status codes (common)
     */
    const HTTP_OK = 200;
    const HTTP_NOT_FOUND = 404;
    const HTTP_SERVER_ERROR = 500;
}
