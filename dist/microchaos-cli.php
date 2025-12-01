<?php
/**
 * Plugin Name: MicroChaos CLI Load Tester
 * Description: Internal WP-CLI based WordPress load tester for staging environments where
 * external load testing is restricted (like Pressable).
 * Version: 3.0.0
 * Author: Phill
 */

// Bootstrap MicroChaos components

/**
 * COMPILED SINGLE-FILE VERSION
 * Generated on: 2025-12-01T17:44:14.438Z
 * 
 * This is an automatically generated file - DO NOT EDIT DIRECTLY
 * Make changes to the modular version and rebuild.
 */

if (defined('WP_CLI') && WP_CLI) {

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

interface MicroChaos_Baseline_Storage {
    /**
     * Save baseline data with a given key
     *
     * @param string $key Storage key (will be sanitized)
     * @param mixed $data Data to store
     * @param int|null $ttl Time-to-live in seconds (null for default)
     * @return bool Success status
     */
    public function save(string $key, $data, ?int $ttl = null): bool;

    /**
     * Retrieve baseline data by key
     *
     * @param string $key Storage key
     * @return mixed|null Stored data or null if not found
     */
    public function get(string $key);

    /**
     * Check if a baseline exists
     *
     * @param string $key Storage key
     * @return bool True if exists, false otherwise
     */
    public function exists(string $key): bool;

    /**
     * Delete a baseline
     *
     * @param string $key Storage key
     * @return bool Success status
     */
    public function delete(string $key): bool;
}

class MicroChaos_Transient_Baseline_Storage implements MicroChaos_Baseline_Storage {
    /**
     * Prefix for transient keys
     *
     * @var string
     */
    private $prefix;

    /**
     * Default TTL in seconds (30 days)
     *
     * @var int
     */
    private $default_ttl = MicroChaos_Constants::BASELINE_TTL;

    /**
     * Directory for file-based fallback storage
     *
     * @var string
     */
    private $storage_dir;

    /**
     * Constructor
     *
     * @param string $prefix Prefix for storage keys (e.g., 'microchaos_baseline', 'microchaos_resource_baseline')
     */
    public function __construct(string $prefix = 'microchaos_baseline') {
        $this->prefix = rtrim($prefix, '_') . '_';
        $this->storage_dir = WP_CONTENT_DIR . '/microchaos/baselines';
    }

    /**
     * Save baseline data with a given key
     *
     * @param string $key Storage key (will be sanitized)
     * @param mixed $data Data to store
     * @param int|null $ttl Time-to-live in seconds (null for default)
     * @return bool Success status
     */
    public function save(string $key, $data, ?int $ttl = null): bool {
        $ttl = $ttl ?? $this->default_ttl;
        $sanitized_key = $this->sanitize_key($key);

        // Try transients first
        if (function_exists('set_transient')) {
            $transient_key = $this->prefix . $sanitized_key;
            $result = set_transient($transient_key, $data, $ttl);

            if ($result) {
                return true;
            }
        }

        // Fallback to file storage
        return $this->save_to_file($sanitized_key, $data);
    }

    /**
     * Retrieve baseline data by key
     *
     * @param string $key Storage key
     * @return mixed|null Stored data or null if not found
     */
    public function get(string $key) {
        $sanitized_key = $this->sanitize_key($key);

        // Try transients first
        if (function_exists('get_transient')) {
            $transient_key = $this->prefix . $sanitized_key;
            $data = get_transient($transient_key);

            if ($data !== false) {
                return $data;
            }
        }

        // Fallback to file storage
        return $this->load_from_file($sanitized_key);
    }

    /**
     * Check if a baseline exists
     *
     * @param string $key Storage key
     * @return bool True if exists, false otherwise
     */
    public function exists(string $key): bool {
        $sanitized_key = $this->sanitize_key($key);

        // Check transient
        if (function_exists('get_transient')) {
            $transient_key = $this->prefix . $sanitized_key;
            if (get_transient($transient_key) !== false) {
                return true;
            }
        }

        // Check file storage
        $filepath = $this->get_file_path($sanitized_key);
        return file_exists($filepath);
    }

    /**
     * Delete a baseline
     *
     * @param string $key Storage key
     * @return bool Success status
     */
    public function delete(string $key): bool {
        $sanitized_key = $this->sanitize_key($key);
        $success = true;

        // Delete transient
        if (function_exists('delete_transient')) {
            $transient_key = $this->prefix . $sanitized_key;
            $success = delete_transient($transient_key);
        }

        // Delete file if exists
        $filepath = $this->get_file_path($sanitized_key);
        if (file_exists($filepath)) {
            $success = unlink($filepath) && $success;
        }

        return $success;
    }

    /**
     * Sanitize storage key
     *
     * @param string $key Raw key
     * @return string Sanitized key
     */
    private function sanitize_key(string $key): string {
        if (function_exists('sanitize_key')) {
            return sanitize_key($key);
        }

        // Fallback sanitization if WordPress functions unavailable
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }

    /**
     * Get file path for a given key
     *
     * @param string $sanitized_key Already sanitized key
     * @return string Full file path
     */
    private function get_file_path(string $sanitized_key): string {
        $filename = sanitize_file_name($this->prefix . $sanitized_key . '.json');
        return $this->storage_dir . '/' . $filename;
    }

    /**
     * Save data to file storage
     *
     * @param string $sanitized_key Already sanitized key
     * @param mixed $data Data to store
     * @return bool Success status
     */
    private function save_to_file(string $sanitized_key, $data): bool {
        // Create directory if needed
        if (!file_exists($this->storage_dir)) {
            if (!mkdir($this->storage_dir, 0755, true)) {
                return false;
            }
        }

        $filepath = $this->get_file_path($sanitized_key);
        $json_data = json_encode($data, JSON_PRETTY_PRINT);

        if ($json_data === false) {
            return false;
        }

        return file_put_contents($filepath, $json_data) !== false;
    }

    /**
     * Load data from file storage
     *
     * @param string $sanitized_key Already sanitized key
     * @return mixed|null Stored data or null if not found
     */
    private function load_from_file(string $sanitized_key) {
        $filepath = $this->get_file_path($sanitized_key);

        if (!file_exists($filepath)) {
            return null;
        }

        $json_data = file_get_contents($filepath);
        if ($json_data === false) {
            return null;
        }

        $data = json_decode($json_data, true);
        return $data !== null ? $data : null;
    }
}

class MicroChaos_Authentication_Manager {

    // ==================== WordPress Cookie Authentication ====================

    /**
     * Authenticate a single user by email and retrieve cookies
     *
     * @param string $email User email address
     * @return array|null Array of WP_Http_Cookie objects, or null if user not found
     */
    public static function authenticate_user(string $email): ?array {
        $user = get_user_by('email', $email);
        if (!$user) {
            return null;
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        $cookies = wp_remote_retrieve_cookies(wp_remote_get(home_url()));

        \WP_CLI::log("🔐 Authenticated as {$user->user_login}");

        return $cookies;
    }

    /**
     * Authenticate multiple users and retrieve session cookies for each
     *
     * @param array $emails Array of user email addresses
     * @return array Array of cookie session arrays (multi-auth format)
     */
    public static function authenticate_users(array $emails): array {
        $auth_sessions = [];

        foreach ($emails as $email) {
            $user = get_user_by('email', $email);
            if (!$user) {
                \WP_CLI::warning("User with email {$email} not found. Skipping.");
                continue;
            }

            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            $session_cookies = wp_remote_retrieve_cookies(wp_remote_get(home_url()));
            $auth_sessions[] = $session_cookies;

            \WP_CLI::log("🔐 Added session for {$user->user_login}");
        }

        return $auth_sessions;
    }

    // ==================== HTTP Basic Authentication ====================

    /**
     * Parse auth string in username@domain format
     *
     * @param string $auth Auth string (e.g., "username@domain.com")
     * @return array|null ['username' => string, 'domain' => string] or null if invalid format
     */
    public static function parse_auth_string(string $auth): ?array {
        if (strpos($auth, '@') === false) {
            return null;
        }

        list($username, $domain) = explode('@', $auth, 2);

        return [
            'username' => $username,
            'domain' => $domain
        ];
    }

    /**
     * Create HTTP Basic Auth header array
     *
     * @param string $username Username for Basic auth
     * @param string $password Password (defaults to 'password')
     * @return array ['Authorization' => 'Basic base64(username:password)']
     */
    public static function create_basic_auth_headers(string $username, string $password = 'password'): array {
        return [
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
        ];
    }

    // ==================== Cookie Utilities ====================

    /**
     * Detect if cookies array is multi-auth format (array of session arrays)
     *
     * Multi-auth format: [[WP_Http_Cookie, ...], [WP_Http_Cookie, ...], ...]
     * Single-auth format: [WP_Http_Cookie, WP_Http_Cookie, ...]
     *
     * @param array $cookies Cookie array to check
     * @return bool True if multi-auth format
     */
    public static function is_multi_auth(array $cookies): bool {
        return is_array($cookies) && isset($cookies[0]) && is_array($cookies[0]);
    }

    /**
     * Select a random session from multi-auth cookie array
     *
     * @param array $sessions Array of session arrays
     * @return array Single session's cookies
     */
    public static function select_random_session(array $sessions): array {
        return $sessions[array_rand($sessions)];
    }

    /**
     * Format cookies for cURL requests (semicolon-separated string)
     *
     * @param array $cookies Array of WP_Http_Cookie objects
     * @return string Cookie string in "name1=value1; name2=value2" format
     */
    public static function format_for_curl(array $cookies): string {
        return implode('; ', array_map(
            function($cookie) {
                return "{$cookie->name}={$cookie->value}";
            },
            $cookies
        ));
    }

    /**
     * Format cookies for wp_remote_request (passthrough)
     *
     * wp_remote_request expects the WP_Http_Cookie array directly,
     * so this is a passthrough for API consistency.
     *
     * @param array $cookies Array of WP_Http_Cookie objects
     * @return array Same cookie array (passthrough)
     */
    public static function format_for_wp_remote(array $cookies): array {
        return $cookies;
    }
}

class MicroChaos_Thresholds {
    // Response time thresholds (seconds)
    const RESPONSE_TIME_GOOD = 1.0;    // Response times under 1 second are good
    const RESPONSE_TIME_WARN = 2.0;    // Response times under 2 seconds are acceptable
    const RESPONSE_TIME_CRITICAL = 3.0; // Response times over 3 seconds are critical

    // Memory usage thresholds (percentage of PHP memory limit)
    const MEMORY_USAGE_GOOD = 50;      // Under 50% of PHP memory limit is good
    const MEMORY_USAGE_WARN = 70;      // Under 70% of PHP memory limit is acceptable
    const MEMORY_USAGE_CRITICAL = 85;  // Over 85% of PHP memory limit is critical

    // Error rate thresholds (percentage)
    const ERROR_RATE_GOOD = 1;         // Under 1% error rate is good
    const ERROR_RATE_WARN = 5;         // Under 5% error rate is acceptable
    const ERROR_RATE_CRITICAL = 10;    // Over 10% error rate is critical
    
    // Progressive load testing thresholds
    const PROGRESSIVE_STEP_INCREASE = 5;  // Default step size for progressive load increases
    const PROGRESSIVE_INITIAL_LOAD = 5;   // Default initial load for progressive testing
    const PROGRESSIVE_MAX_LOAD = 100;     // Default maximum load to try
    
    // Automated threshold calibration factors
    const AUTO_THRESHOLD_GOOD_FACTOR = 1.0;    // Base value multiplier for "good" threshold
    const AUTO_THRESHOLD_WARN_FACTOR = 1.5;    // Base value multiplier for "warning" threshold
    const AUTO_THRESHOLD_CRITICAL_FACTOR = 2.0; // Base value multiplier for "critical" threshold
    
    // Current custom threshold sets (dynamically set during calibration)
    private static $custom_thresholds = [];
    
    // Transient keys for stored thresholds
    const TRANSIENT_PREFIX = 'microchaos_thresholds_';
    const TRANSIENT_EXPIRY = 2592000; // 30 days in seconds

    /**
     * Format a value with color based on thresholds
     *
     * @param float $value The value to format
     * @param string $type The type of metric (response_time, memory_usage, error_rate)
     * @param string|null $profile Optional profile name for custom thresholds
     * @return string Formatted value with color codes
     */
    public static function format_value(float $value, string $type, ?string $profile = null): string {
        switch ($type) {
            case 'response_time':
                $thresholds = self::get_thresholds('response_time', $profile);
                if ($value <= $thresholds['good']) {
                    return "\033[32m{$value}s\033[0m"; // Green
                } elseif ($value <= $thresholds['warn']) {
                    return "\033[33m{$value}s\033[0m"; // Yellow
                } else {
                    return "\033[31m{$value}s\033[0m"; // Red
                }
                break;
                
            case 'memory_usage':
                // Calculate percentage of PHP memory limit
                $memory_limit = self::get_php_memory_limit_mb();
                $percentage = ($value / $memory_limit) * 100;
                
                $thresholds = self::get_thresholds('memory_usage', $profile);
                if ($percentage <= $thresholds['good']) {
                    return "\033[32m{$value} MB\033[0m"; // Green
                } elseif ($percentage <= $thresholds['warn']) {
                    return "\033[33m{$value} MB\033[0m"; // Yellow
                } else {
                    return "\033[31m{$value} MB\033[0m"; // Red
                }
                break;
                
            case 'error_rate':
                $thresholds = self::get_thresholds('error_rate', $profile);
                if ($value <= $thresholds['good']) {
                    return "\033[32m{$value}%\033[0m"; // Green
                } elseif ($value <= $thresholds['warn']) {
                    return "\033[33m{$value}%\033[0m"; // Yellow
                } else {
                    return "\033[31m{$value}%\033[0m"; // Red
                }
                break;
                
            default:
                return "{$value}";
        }
    }
    
    /**
     * Get PHP memory limit in MB
     *
     * @return float Memory limit in MB
     */
    public static function get_php_memory_limit_mb(): float {
        $memory_limit = ini_get('memory_limit');
        $value = (int) $memory_limit;
        
        // Convert to MB if necessary
        if (stripos($memory_limit, 'G') !== false) {
            $value = $value * 1024;
        } elseif (stripos($memory_limit, 'K') !== false) {
            $value = $value / 1024;
        } elseif (stripos($memory_limit, 'M') === false) {
            // If no unit, assume bytes and convert to MB
            $value = $value / 1048576;
        }
        
        return $value > 0 ? $value : 128; // Default to 128MB if limit is unlimited (-1)
    }
    
    /**
     * Generate a simple ASCII bar chart
     *
     * @param array $values Array of values to chart
     * @param string $title Chart title
     * @param int $width Chart width in characters
     * @return string ASCII chart
     */
    public static function generate_chart(array $values, string $title, int $width = 40): string {
        $max = max($values);
        if ($max == 0) $max = 1; // Avoid division by zero
        
        $output = "\n   $title:\n";
        
        foreach ($values as $label => $value) {
            $bar_length = round(($value / $max) * $width);
            $bar = str_repeat('█', $bar_length);
            $output .= sprintf("   %-10s [%-{$width}s] %s\n", $label, $bar, $value);
        }
        
        return $output;
    }
    
    /**
     * Get thresholds for a specific metric
     * 
     * @param string $type The type of metric (response_time, memory_usage, error_rate)
     * @param string|null $profile Optional profile name for custom thresholds
     * @return array Thresholds array with 'good', 'warn', and 'critical' keys
     */
    public static function get_thresholds(string $type, ?string $profile = null): array {
        // If we have custom thresholds for this profile and type, use them
        if ($profile && isset(self::$custom_thresholds[$profile][$type])) {
            return self::$custom_thresholds[$profile][$type];
        }
        
        // Otherwise use defaults
        switch ($type) {
            case 'response_time':
                return [
                    'good' => self::RESPONSE_TIME_GOOD,
                    'warn' => self::RESPONSE_TIME_WARN,
                    'critical' => self::RESPONSE_TIME_CRITICAL
                ];
            case 'memory_usage':
                return [
                    'good' => self::MEMORY_USAGE_GOOD,
                    'warn' => self::MEMORY_USAGE_WARN,
                    'critical' => self::MEMORY_USAGE_CRITICAL
                ];
            case 'error_rate':
                return [
                    'good' => self::ERROR_RATE_GOOD,
                    'warn' => self::ERROR_RATE_WARN,
                    'critical' => self::ERROR_RATE_CRITICAL
                ];
            default:
                return [
                    'good' => 0,
                    'warn' => 0,
                    'critical' => 0
                ];
        }
    }
    
    /**
     * Calibrate thresholds based on test results
     *
     * @param array $test_results Array containing test metrics
     * @param string $profile Profile name to save thresholds under
     * @param bool $persist Whether to persist thresholds to database
     * @return array Calculated thresholds
     */
    public static function calibrate_thresholds(array $test_results, string $profile = 'default', bool $persist = true): array {
        $thresholds = [];
        
        // Calculate response time thresholds if we have timing data
        if (isset($test_results['timing']) && isset($test_results['timing']['avg'])) {
            $base_response_time = $test_results['timing']['avg'];
            $thresholds['response_time'] = [
                'good' => round($base_response_time * self::AUTO_THRESHOLD_GOOD_FACTOR, 2),
                'warn' => round($base_response_time * self::AUTO_THRESHOLD_WARN_FACTOR, 2),
                'critical' => round($base_response_time * self::AUTO_THRESHOLD_CRITICAL_FACTOR, 2),
            ];
        }
        
        // Calculate error rate thresholds if we have error data
        if (isset($test_results['error_rate'])) {
            $base_error_rate = $test_results['error_rate'];
            // Add a minimum baseline for error rates
            $base_error_rate = max($base_error_rate, 0.5); // At least 0.5% for baseline
            
            $thresholds['error_rate'] = [
                'good' => round($base_error_rate * self::AUTO_THRESHOLD_GOOD_FACTOR, 1),
                'warn' => round($base_error_rate * self::AUTO_THRESHOLD_WARN_FACTOR, 1),
                'critical' => round($base_error_rate * self::AUTO_THRESHOLD_CRITICAL_FACTOR, 1),
            ];
        }
        
        // Calculate memory thresholds if we have memory data
        if (isset($test_results['memory']) && isset($test_results['memory']['avg'])) {
            $memory_limit = self::get_php_memory_limit_mb();
            $base_percentage = ($test_results['memory']['avg'] / $memory_limit) * 100;
            
            $thresholds['memory_usage'] = [
                'good' => round($base_percentage * self::AUTO_THRESHOLD_GOOD_FACTOR, 1),
                'warn' => round($base_percentage * self::AUTO_THRESHOLD_WARN_FACTOR, 1),
                'critical' => round($base_percentage * self::AUTO_THRESHOLD_CRITICAL_FACTOR, 1),
            ];
        }
        
        // Store custom thresholds in static property
        self::$custom_thresholds[$profile] = $thresholds;
        
        // Persist thresholds if requested
        if ($persist) {
            self::save_thresholds($profile, $thresholds);
        }
        
        return $thresholds;
    }
    
    /**
     * Save thresholds to database
     *
     * @param string $profile Profile name
     * @param array $thresholds Thresholds to save
     * @return bool Success status
     */
    public static function save_thresholds(string $profile, array $thresholds): bool {
        if (function_exists('set_transient')) {
            return set_transient(self::TRANSIENT_PREFIX . $profile, $thresholds, self::TRANSIENT_EXPIRY);
        }
        return false;
    }
    
    /**
     * Load thresholds from database
     *
     * @param string $profile Profile name
     * @return array|bool Thresholds array or false if not found
     */
    public static function load_thresholds(string $profile) {
        if (function_exists('get_transient')) {
            $thresholds = get_transient(self::TRANSIENT_PREFIX . $profile);
            if ($thresholds) {
                self::$custom_thresholds[$profile] = $thresholds;
                return $thresholds;
            }
        }
        return false;
    }
    
    /**
     * Generate a simple distribution histogram
     *
     * @param array $times Array of response times
     * @param int $buckets Number of buckets for distribution
     * @return string ASCII histogram
     */
    public static function generate_histogram(array $times, int $buckets = 5): string {
        if (empty($times)) {
            return "";
        }
        
        $min = min($times);
        $max = max($times);
        $range = $max - $min;
        
        // Avoid division by zero if all values are the same
        if ($range == 0) {
            $range = 0.1;
        }
        
        $bucket_size = $range / $buckets;
        $histogram = array_fill(0, $buckets, 0);
        
        foreach ($times as $time) {
            $bucket = min($buckets - 1, floor(($time - $min) / $bucket_size));
            $histogram[$bucket]++;
        }
        
        $max_count = max($histogram);
        $width = 30;
        
        $output = "\n   Response Time Distribution:\n";
        
        for ($i = 0; $i < $buckets; $i++) {
            $lower = round($min + ($i * $bucket_size), 2);
            $upper = round($min + (($i + 1) * $bucket_size), 2);
            $count = $histogram[$i];
            $bar_length = ($max_count > 0) ? round(($count / $max_count) * $width) : 0;
            $bar = str_repeat('█', $bar_length);
            
            $output .= sprintf("   %5.2fs - %5.2fs [%-{$width}s] %d\n", $lower, $upper, $bar, $count);
        }
        
        return $output;
    }
}

class MicroChaos_Integration_Logger {
    /**
     * Log prefix for all integration logs
     * 
     * @var string
     */
    const LOG_PREFIX = 'MICROCHAOS_METRICS';
    
    /**
     * Enabled status
     * 
     * @var bool
     */
    private $enabled = false;
    
    /**
     * Test ID
     * 
     * @var string
     */
    public $test_id = '';
    
    /**
     * Constructor
     * 
     * @param array $options Logger options
     */
    public function __construct($options = []) {
        $this->enabled = isset($options['enabled']) ? (bool)$options['enabled'] : false;
        $this->test_id = isset($options['test_id']) ? $options['test_id'] : uniqid('mc_');
    }
    
    /**
     * Enable integration logging
     * 
     * @param string|null $test_id Optional test ID to use
     */
    public function enable($test_id = null) {
        $this->enabled = true;
        if ($test_id) {
            $this->test_id = $test_id;
        }
    }
    
    /**
     * Disable integration logging
     */
    public function disable() {
        $this->enabled = false;
    }
    
    /**
     * Check if integration logging is enabled
     * 
     * @return bool Enabled status
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Log test start event
     * 
     * @param array $config Test configuration
     */
    public function log_test_start($config) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'test_start',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'config' => $config
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log test completion event
     *
     * @param array $summary Test summary
     * @param array|null $resource_summary Resource summary if available
     * @param array|null $execution_metrics Execution timing and throughput metrics
     */
    public function log_test_complete(array $summary, ?array $resource_summary = null, ?array $execution_metrics = null): void {
        if (!$this->enabled) {
            return;
        }

        $data = [
            'event' => 'test_complete',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'summary' => $summary
        ];

        if ($resource_summary) {
            $data['resource_summary'] = $resource_summary;
        }

        if ($execution_metrics) {
            $data['execution'] = $execution_metrics;
        }

        $this->log_event($data);
    }
    
    /**
     * Log a single request result
     * 
     * @param array $result Request result
     */
    public function log_request($result) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'request',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'result' => $result
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log resource utilization snapshot
     * 
     * @param array $resource_data Resource utilization data
     */
    public function log_resource_snapshot($resource_data) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'resource_snapshot',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'resource_data' => $resource_data
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log burst completion
     * 
     * @param int $burst_number Burst number
     * @param int $requests_count Number of requests in burst
     * @param array $burst_summary Summary data for this burst
     */
    public function log_burst_complete($burst_number, $requests_count, $burst_summary) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'burst_complete',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'burst_number' => $burst_number,
            'requests_count' => $requests_count,
            'burst_summary' => $burst_summary
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log progressive test level completion
     * 
     * @param int $concurrency Concurrency level
     * @param array $level_summary Summary for this concurrency level
     */
    public function log_progressive_level($concurrency, $level_summary) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'progressive_level',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'concurrency' => $concurrency,
            'summary' => $level_summary
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log custom metrics
     * 
     * @param string $metric_name Metric name
     * @param mixed $value Metric value
     * @param array $tags Additional tags
     */
    public function log_metric($metric_name, $value, $tags = []) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'metric',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'metric' => $metric_name,
            'value' => $value,
            'tags' => $tags
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log an event with JSON-encoded data
     * 
     * @param array $data Event data
     */
    private function log_event($data) {
        // Add site URL to all events for multi-site monitoring
        $data['site_url'] = home_url();
        
        // Format: MICROCHAOS_METRICS|event_type|json_encoded_data
        $json_data = json_encode($data);
        $log_message = self::LOG_PREFIX . '|' . $data['event'] . '|' . $json_data;
        
        error_log($log_message);
    }
}

class MicroChaos_Request_Generator {
    /**
     * Collect and process cache headers
     *
     * @var bool
     */
    private $collect_cache_headers = false;

    /**
     * Cache headers data storage
     *
     * @var array
     */
    private $cache_headers = [];

    /**
     * Last request cache headers
     *
     * @var array
     */
    private $last_request_cache_headers = [];

    /**
     * Constructor
     *
     * @param array $options Options for the request generator
     */
    public function __construct($options = []) {
        $this->collect_cache_headers = isset($options['collect_cache_headers']) ?
            $options['collect_cache_headers'] : false;
    }

    /**
     * Custom headers storage
     *
     * @var array
     */
    private $custom_headers = [];

    /**
     * Set custom headers
     *
     * @param array $headers Custom headers in key-value format
     */
    public function set_custom_headers($headers) {
        $this->custom_headers = $headers;
    }

    /**
     * Fire an asynchronous batch of requests
     *
     * @param string $url Target URL
     * @param string|null $log_path Optional path for logging
     * @param array|null $cookies Optional cookies for authentication
     * @param int $current_burst Number of concurrent requests to fire
     * @param string $method HTTP method
     * @param string|null $body Request body for POST/PUT
     * @return array Results of the requests
     */
    public function fire_requests_async($url, $log_path, $cookies, $current_burst, $method = 'GET', $body = null) {
        $results = [];
        $multi_handle = curl_multi_init();
        $curl_handles = [];

        for ($i = 0; $i < $current_burst; $i++) {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            
            // Prepare headers array
            $headers = [
                'User-Agent: ' . $this->get_random_user_agent(),
            ];
            
            // Add custom headers if any
            if (!empty($this->custom_headers)) {
                foreach ($this->custom_headers as $name => $value) {
                    $headers[] = "$name: $value";
                }
            }
            
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

            // For cache header collection
            if ($this->collect_cache_headers) {
                curl_setopt($curl, CURLOPT_HEADER, true);
            }

            // Handle body data
            if ($body) {
                if ($this->is_json($body)) {
                    // Add content-type header to existing headers
                    $headers[] = 'Content-Type: application/json';
                    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
                } else {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
                }
            }

            if ($cookies) {
                $selected = MicroChaos_Authentication_Manager::is_multi_auth($cookies)
                    ? MicroChaos_Authentication_Manager::select_random_session($cookies)
                    : $cookies;
                curl_setopt($curl, CURLOPT_COOKIE, MicroChaos_Authentication_Manager::format_for_curl($selected));
            }
            curl_setopt($curl, CURLOPT_URL, $url);
            $start = microtime(true); // record start time for this request
            curl_multi_add_handle($multi_handle, $curl);
            $curl_handles[] = ['handle' => $curl, 'url' => $url, 'start' => $start];
        }

        do {
            curl_multi_exec($multi_handle, $active);
            curl_multi_select($multi_handle);
        } while ($active);

        foreach ($curl_handles as $entry) {
            $curl = $entry['handle'];
            $url = $entry['url'];
            $start = $entry['start'];
            $response = curl_multi_getcontent($curl);
            $end = microtime(true);
            $duration = round($end - $start, 4);
            $info = curl_getinfo($curl);
            $code = $info['http_code'] ?: 'ERROR';

            // Parse headers for cache information if enabled
            if ($this->collect_cache_headers && $response) {
                $header_size = $info['header_size'];
                $header = substr($response, 0, $header_size);
                $this->process_curl_headers($header);
            }

            $message = "⏱ MicroChaos Request | Time: {$duration}s | Code: {$code} | URL: $url | Method: $method";
            error_log($message);

            if ($log_path) {
                $this->log_to_file($message, $log_path);
            }

            if (class_exists('WP_CLI')) {
                $cache_display = '';
                if ($this->collect_cache_headers && !empty($this->last_request_cache_headers)) {
                    $cache_display = ' ' . $this->format_cache_headers_for_display($this->last_request_cache_headers);
                }
                \WP_CLI::log("-> {$code} in {$duration}s{$cache_display}");
            }

            $results[] = [
                'time' => $duration,
                'code' => $code,
            ];

            curl_multi_remove_handle($multi_handle, $curl);
            curl_close($curl);
        }

        curl_multi_close($multi_handle);
        return $results;
    }

    /**
     * Fire a single request
     *
     * @param string $url Target URL
     * @param string|null $log_path Optional path for logging
     * @param array|null $cookies Optional cookies for authentication
     * @param string $method HTTP method
     * @param string|null $body Request body for POST/PUT
     * @return array Result of the request
     */
    public function fire_request($url, $log_path = null, $cookies = null, $method = 'GET', $body = null) {
        $start = microtime(true);

        $args = [
            'timeout' => 10,
            'blocking' => true,
            'user-agent' => $this->get_random_user_agent(),
            'method' => $method,
        ];
        
        // Add custom headers if any
        if (!empty($this->custom_headers)) {
            $args['headers'] = [];
            foreach ($this->custom_headers as $name => $value) {
                $args['headers'][$name] = $value;
            }
        }

        if ($body) {
            if ($this->is_json($body)) {
                if (!isset($args['headers'])) {
                    $args['headers'] = [];
                }
                $args['headers']['Content-Type'] = 'application/json';
                $args['body'] = $body;
            } else {
                // Handle URL-encoded form data or other types
                $args['body'] = $body;
            }
        }

        if ($cookies) {
            $selected = MicroChaos_Authentication_Manager::is_multi_auth($cookies)
                ? MicroChaos_Authentication_Manager::select_random_session($cookies)
                : $cookies;
            $args['cookies'] = MicroChaos_Authentication_Manager::format_for_wp_remote($selected);
        }

        $response = wp_remote_request($url, $args);
        $end = microtime(true);

        $duration = round($end - $start, 4);
        $code = is_wp_error($response)
            ? 'ERROR'
            : wp_remote_retrieve_response_code($response);

        // Collect cache headers if enabled and the response is valid
        if ($this->collect_cache_headers && !is_wp_error($response)) {
            $headers = wp_remote_retrieve_headers($response);
            $this->collect_cache_header_data($headers);
        }

        $message = "⏱ MicroChaos Request | Time: {$duration}s | Code: {$code} | URL: $url | Method: $method";

        error_log($message);
        if ($log_path) {
            $this->log_to_file($message, $log_path);
        }

        if (class_exists('WP_CLI')) {
            $cache_display = '';
            if ($this->collect_cache_headers && !empty($this->last_request_cache_headers)) {
                $cache_display = ' ' . $this->format_cache_headers_for_display($this->last_request_cache_headers);
            }
            \WP_CLI::log("-> {$code} in {$duration}s{$cache_display}");
        }

        // Return result for reporting
        return [
            'time' => $duration,
            'code' => $code,
        ];
    }

    /**
     * Resolve endpoint slug to a URL
     *
     * @param string $slug Endpoint slug or custom path
     * @return string|bool URL or false if invalid
     */
    public function resolve_endpoint($slug) {
        if (strpos($slug, 'custom:') === 0) {
            return home_url(substr($slug, 7));
        }
        switch ($slug) {
            case 'home': return home_url('/');
            case 'shop': return home_url('/shop/');
            case 'cart': return home_url('/cart/');
            case 'checkout': return home_url('/checkout/');
            default: return false;
        }
    }

    /**
     * Process headers from cURL response for cache analysis
     *
     * @param string $header_text Raw header text from cURL response
     */
    private function process_curl_headers($header_text) {
        $headers = [];
        foreach(explode("\r\n", $header_text) as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = strtolower(trim($key));
                $value = trim($value);
                $headers[$key] = $value;
            }
        }

        $this->collect_cache_header_data($headers);
    }

    /**
     * Collect and catalog cache headers from the response
     *
     * @param array $headers Response headers
     */
    public function collect_cache_header_data($headers) {
        // Headers to track (Pressable specific and general cache headers)
        $cache_headers = ['x-ac', 'x-nananana', 'x-cache', 'age', 'x-cache-hits'];

        // Store current request cache headers for display
        $this->last_request_cache_headers = [];

        foreach ($cache_headers as $header) {
            if (isset($headers[$header])) {
                $value = $headers[$header];
                
                // Store for current request display
                $this->last_request_cache_headers[$header] = $value;
                
                // Store for overall accumulation
                if (!isset($this->cache_headers[$header])) {
                    $this->cache_headers[$header] = [];
                }
                if (!isset($this->cache_headers[$header][$value])) {
                    $this->cache_headers[$header][$value] = 0;
                }
                $this->cache_headers[$header][$value]++;
            }
        }
    }

    /**
     * Get cache headers data
     *
     * @return array Collection of cache headers
     */
    public function get_cache_headers() {
        return $this->cache_headers;
    }

    /**
     * Reset cache headers collection
     *
     * Clears the accumulated cache headers data
     */
    public function reset_cache_headers() {
        $this->cache_headers = [];
    }

    /**
     * Get cache headers for the last request
     *
     * @return array Cache headers from the last request
     */
    public function get_last_request_cache_headers() {
        return $this->last_request_cache_headers;
    }

    /**
     * Format cache headers for display
     *
     * @param array $headers Cache headers to format
     * @return string Formatted cache headers string
     */
    private function format_cache_headers_for_display($headers) {
        $display_parts = [];
        
        // Focus on Pressable-specific headers
        if (isset($headers['x-ac'])) {
            $display_parts[] = "x-ac: {$headers['x-ac']}";
        }
        
        if (isset($headers['x-nananana'])) {
            $display_parts[] = "x-nananana: {$headers['x-nananana']}";
        }
        
        // Add other cache headers if present
        foreach (['x-cache', 'age'] as $header) {
            if (isset($headers[$header])) {
                $display_parts[] = "$header: {$headers[$header]}";
            }
        }
        
        return empty($display_parts) ? '' : '[' . implode('] [', $display_parts) . ']';
    }

    /**
     * Log message to a file
     *
     * @param string $message Message to log
     * @param string $path Path relative to WP_CONTENT_DIR
     */
    private function log_to_file($message, $path) {
        $path = sanitize_text_field($path);
        $filepath = trailingslashit(WP_CONTENT_DIR) . ltrim($path, '/');
        @file_put_contents($filepath, $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Get a random user agent string
     *
     * @return string Random user agent
     */
    private function get_random_user_agent() {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1'
        ];
        return $agents[array_rand($agents)];
    }

    /**
     * Check if a string is valid JSON
     *
     * @param string $string String to check
     * @return bool Whether string is valid JSON
     */
    private function is_json($string) {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}

class MicroChaos_Resource_Monitor {
    /**
     * Resource results storage
     *
     * @var array
     */
    private $resource_results = [];

    /**
     * Whether to track resource trends over time
     *
     * @var bool
     */
    private $track_trends = false;

    /**
     * Timestamp of monitoring start
     *
     * @var float
     */
    private $start_time = 0;

    /**
     * Baseline storage implementation
     *
     * @var MicroChaos_Baseline_Storage
     */
    private $baseline_storage;

    /**
     * Constructor
     *
     * @param array $options Options for resource monitoring
     * @param MicroChaos_Baseline_Storage|null $storage Optional baseline storage (will create default if not provided)
     */
    public function __construct(array $options = [], ?MicroChaos_Baseline_Storage $storage = null) {
        $this->resource_results = [];
        $this->track_trends = isset($options['track_trends']) ? (bool)$options['track_trends'] : false;
        $this->start_time = microtime(true);
        $this->baseline_storage = $storage ?? new MicroChaos_Transient_Baseline_Storage('microchaos_resource_baseline');
    }

    /**
     * Log current resource utilization
     *
     * @return array Current resource usage data
     */
    public function log_resource_utilization(): array {
        $memory_usage = round(memory_get_usage() / 1024 / 1024, 2);
        $peak_memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
        $ru = getrusage();
        $user_time = round($ru['ru_utime.tv_sec'] + $ru['ru_utime.tv_usec'] / 1e6, 2);
        $system_time = round($ru['ru_stime.tv_sec'] + $ru['ru_stime.tv_usec'] / 1e6, 2);

        if (class_exists('WP_CLI')) {
            \WP_CLI::log("🔍 Resources: Memory Usage: {$memory_usage} MB, Peak Memory: {$peak_memory} MB, CPU Time: User {$user_time}s, System {$system_time}s");
        }

        $result = [
            'memory_usage' => $memory_usage,
            'peak_memory'  => $peak_memory,
            'user_time'    => $user_time,
            'system_time'  => $system_time,
        ];
        
        // Add timestamp for trend tracking
        if ($this->track_trends) {
            $result['timestamp'] = microtime(true);
            $result['elapsed'] = round($result['timestamp'] - $this->start_time, 2);
        }

        $this->resource_results[] = $result;
        return $result;
    }

    /**
     * Get all resource utilization results
     *
     * @return array Resource utilization data
     */
    public function get_resource_results(): array {
        return $this->resource_results;
    }

    /**
     * Generate resource utilization summary report
     *
     * @return array Summary metrics
     */
    public function generate_summary(): array {
        if (empty($this->resource_results)) {
            return [];
        }

        $n = count($this->resource_results);

        // Memory usage stats
        $mem_usages = array_column($this->resource_results, 'memory_usage');
        sort($mem_usages);
        $avg_memory_usage = round(array_sum($mem_usages) / $n, 2);
        $median_memory_usage = round($mem_usages[floor($n / 2)], 2);

        // Peak memory stats
        $peak_memories = array_column($this->resource_results, 'peak_memory');
        sort($peak_memories);
        $avg_peak_memory = round(array_sum($peak_memories) / $n, 2);
        $median_peak_memory = round($peak_memories[floor($n / 2)], 2);

        // User CPU time stats
        $user_times = array_column($this->resource_results, 'user_time');
        sort($user_times);
        $avg_user_time = round(array_sum($user_times) / $n, 2);
        $median_user_time = round($user_times[floor($n / 2)], 2);

        // System CPU time stats
        $system_times = array_column($this->resource_results, 'system_time');
        sort($system_times);
        $avg_system_time = round(array_sum($system_times) / $n, 2);
        $median_system_time = round($system_times[floor($n / 2)], 2);

        return [
            'samples' => $n,
            'memory' => [
                'avg' => $avg_memory_usage,
                'median' => $median_memory_usage,
                'min' => round(min($mem_usages), 2),
                'max' => round(max($mem_usages), 2),
            ],
            'peak_memory' => [
                'avg' => $avg_peak_memory,
                'median' => $median_peak_memory,
                'min' => round(min($peak_memories), 2),
                'max' => round(max($peak_memories), 2),
            ],
            'user_time' => [
                'avg' => $avg_user_time,
                'median' => $median_user_time,
                'min' => round(min($user_times), 2),
                'max' => round(max($user_times), 2),
            ],
            'system_time' => [
                'avg' => $avg_system_time,
                'median' => $median_system_time,
                'min' => round(min($system_times), 2),
                'max' => round(max($system_times), 2),
            ],
        ];
    }

    /**
     * Output resource summary to CLI
     * 
     * @param array|null $baseline Optional baseline data for comparison
     * @param array|null $provided_summary Optional pre-generated summary
     * @param string|null $threshold_profile Optional threshold profile to use for formatting
     */
    public function report_summary(?array $baseline = null, ?array $provided_summary = null, ?string $threshold_profile = null): void {
        $summary = $provided_summary ?: $this->generate_summary();

        if (empty($summary)) {
            return;
        }

        if (class_exists('WP_CLI')) {
            // Format memory with threshold colors
            $avg_mem_formatted = MicroChaos_Thresholds::format_value($summary['memory']['avg'], 'memory_usage', $threshold_profile);
            $max_mem_formatted = MicroChaos_Thresholds::format_value($summary['memory']['max'], 'memory_usage', $threshold_profile);
            $avg_peak_formatted = MicroChaos_Thresholds::format_value($summary['peak_memory']['avg'], 'memory_usage', $threshold_profile);
            $max_peak_formatted = MicroChaos_Thresholds::format_value($summary['peak_memory']['max'], 'memory_usage', $threshold_profile);
            
            \WP_CLI::log("📊 Resource Utilization Summary:");
            \WP_CLI::log("   Memory Usage: Avg: {$avg_mem_formatted}, Median: {$summary['memory']['median']} MB, Min: {$summary['memory']['min']} MB, Max: {$max_mem_formatted}");
            \WP_CLI::log("   Peak Memory: Avg: {$avg_peak_formatted}, Median: {$summary['peak_memory']['median']} MB, Min: {$summary['peak_memory']['min']} MB, Max: {$max_peak_formatted}");
            \WP_CLI::log("   CPU Time (User): Avg: {$summary['user_time']['avg']}s, Median: {$summary['user_time']['median']}s, Min: {$summary['user_time']['min']}s, Max: {$summary['user_time']['max']}s");
            \WP_CLI::log("   CPU Time (System): Avg: {$summary['system_time']['avg']}s, Median: {$summary['system_time']['median']}s, Min: {$summary['system_time']['min']}s, Max: {$summary['system_time']['max']}s");
            
            // Add comparison with baseline if provided
            if ($baseline) {
                if (isset($baseline['memory'])) {
                    $mem_avg_change = $baseline['memory']['avg'] > 0 
                        ? (($summary['memory']['avg'] - $baseline['memory']['avg']) / $baseline['memory']['avg']) * 100 
                        : 0;
                    $mem_avg_change = round($mem_avg_change, 1);
                    
                    $change_indicator = $mem_avg_change <= 0 ? '↓' : '↑';
                    $change_color = $mem_avg_change <= 0 ? "\033[32m" : "\033[31m";
                    
                    \WP_CLI::log("   Comparison to Baseline:");
                    \WP_CLI::log("   - Avg Memory: {$change_color}{$change_indicator}{$mem_avg_change}%\033[0m vs {$baseline['memory']['avg']} MB");
                    
                    $mem_max_change = $baseline['memory']['max'] > 0 
                        ? (($summary['memory']['max'] - $baseline['memory']['max']) / $baseline['memory']['max']) * 100 
                        : 0;
                    $mem_max_change = round($mem_max_change, 1);
                    
                    $change_indicator = $mem_max_change <= 0 ? '↓' : '↑';
                    $change_color = $mem_max_change <= 0 ? "\033[32m" : "\033[31m";
                    \WP_CLI::log("   - Max Memory: {$change_color}{$change_indicator}{$mem_max_change}%\033[0m vs {$baseline['memory']['max']} MB");
                }
            }
            
            // Add memory usage visualization
            if (count($this->resource_results) >= 5) {
                $chart_data = [
                    'Memory' => $summary['memory']['avg'],
                    'Peak' => $summary['peak_memory']['avg'],
                    'MaxMem' => $summary['memory']['max'],
                    'MaxPeak' => $summary['peak_memory']['max'],
                ];
                
                $chart = MicroChaos_Thresholds::generate_chart($chart_data, "Memory Usage (MB)");
                \WP_CLI::log($chart);
            }
        }
    }
    
    /**
     * Save current results as baseline
     *
     * @param string $name Optional name for the baseline
     * @return array Baseline data
     */
    public function save_baseline(string $name = 'default'): array {
        $baseline = $this->generate_summary();
        $this->baseline_storage->save($name, $baseline);
        return $baseline;
    }

    /**
     * Get saved baseline data
     *
     * @param string $name Baseline name
     * @return array|null Baseline data or null if not found
     */
    public function get_baseline(string $name = 'default') {
        return $this->baseline_storage->get($name);
    }
    
    /**
     * Analyze resource usage trends
     *
     * @return array|null Trend analysis data
     */
    public function analyze_trends(): ?array {
        if (!$this->track_trends || count($this->resource_results) < 3) {
            return null;
        }
        
        // Sort results by elapsed time
        $sorted_results = $this->resource_results;
        usort($sorted_results, function($a, $b) {
            return $a['elapsed'] <=> $b['elapsed'];
        });
        
        // Calculate slopes for memory, peak memory, and CPU time
        $memory_slope = $this->calculate_trend_slope(array_column($sorted_results, 'elapsed'), 
                                                    array_column($sorted_results, 'memory_usage'));
        $peak_memory_slope = $this->calculate_trend_slope(array_column($sorted_results, 'elapsed'), 
                                                         array_column($sorted_results, 'peak_memory'));
        $user_time_slope = $this->calculate_trend_slope(array_column($sorted_results, 'elapsed'), 
                                                       array_column($sorted_results, 'user_time'));
        
        // Calculate percentage changes
        $first_memory = $sorted_results[0]['memory_usage'];
        $last_memory = end($sorted_results)['memory_usage'];
        $memory_change_pct = $first_memory > 0 ? (($last_memory - $first_memory) / $first_memory) * 100 : 0;
        
        $first_peak = $sorted_results[0]['peak_memory'];
        $last_peak = end($sorted_results)['peak_memory'];
        $peak_change_pct = $first_peak > 0 ? (($last_peak - $first_peak) / $first_peak) * 100 : 0;
        
        // Determine if we see an unbounded growth pattern
        $memory_growth_pattern = $this->determine_growth_pattern($sorted_results, 'memory_usage');
        $peak_memory_growth_pattern = $this->determine_growth_pattern($sorted_results, 'peak_memory');
        
        return [
            'memory_usage' => [
                'slope' => round($memory_slope, 4),
                'change_percent' => round($memory_change_pct, 1),
                'pattern' => $memory_growth_pattern
            ],
            'peak_memory' => [
                'slope' => round($peak_memory_slope, 4),
                'change_percent' => round($peak_change_pct, 1),
                'pattern' => $peak_memory_growth_pattern
            ],
            'user_time' => [
                'slope' => round($user_time_slope, 4)
            ],
            'data_points' => count($sorted_results),
            'time_span' => end($sorted_results)['elapsed'] - $sorted_results[0]['elapsed'],
            'potentially_unbounded' => ($memory_growth_pattern === 'continuous_growth' || $peak_memory_growth_pattern === 'continuous_growth')
        ];
    }
    
    /**
     * Calculate the slope of a trend line (simple linear regression)
     * 
     * @param array $x X values (time)
     * @param array $y Y values (resource metric)
     * @return float Slope of trend line
     */
    private function calculate_trend_slope($x, $y) {
        $n = count($x);
        if ($n < 2) return 0;
        
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xx = 0;
        $sum_xy = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xx += $x[$i] * $x[$i];
            $sum_xy += $x[$i] * $y[$i];
        }
        
        // Avoid division by zero
        $denominator = $n * $sum_xx - $sum_x * $sum_x;
        if ($denominator == 0) return 0;
        
        // Calculate slope (m) of the line y = mx + b
        return ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
    }
    
    /**
     * Determine the growth pattern of a resource metric
     * 
     * @param array $results Sorted resource results
     * @param string $metric Metric to analyze (memory_usage, peak_memory)
     * @return string Growth pattern description
     */
    private function determine_growth_pattern($results, $metric) {
        $n = count($results);
        if ($n < 5) return 'insufficient_data';
        
        // Split the data into segments
        $segments = 4; // Analyze in 4 segments
        $segment_size = floor($n / $segments);
        
        $segment_averages = [];
        for ($i = 0; $i < $segments; $i++) {
            $start = $i * $segment_size;
            $end = min($start + $segment_size - 1, $n - 1);
            
            $segment_data = array_slice($results, $start, $end - $start + 1);
            $segment_averages[] = array_sum(array_column($segment_data, $metric)) / count($segment_data);
        }
        
        // Check if each segment is higher than the previous
        $continuous_growth = true;
        for ($i = 1; $i < $segments; $i++) {
            if ($segment_averages[$i] <= $segment_averages[$i-1]) {
                $continuous_growth = false;
                break;
            }
        }
        
        if ($continuous_growth) {
            // Calculate growth rate between first and last segment
            $growth_pct = ($segment_averages[$segments-1] - $segment_averages[0]) / $segment_averages[0] * 100;
            
            if ($growth_pct > 50) {
                return 'continuous_growth';
            } else {
                return 'moderate_growth';
            }
        }
        
        // Check if it's stabilizing (last segment similar to previous)
        $last_diff_pct = abs(($segment_averages[$segments-1] - $segment_averages[$segments-2]) / $segment_averages[$segments-2] * 100);
        if ($last_diff_pct < 5) {
            return 'stabilizing';
        }
        
        // Check if it's fluctuating
        return 'fluctuating';
    }
    
    /**
     * Generate ASCII trend charts for resource metrics
     * 
     * @param int $width Chart width
     * @param int $height Chart height
     * @return string ASCII charts
     */
    public function generate_trend_charts(int $width = 60, int $height = 15): string {
        if (!$this->track_trends || count($this->resource_results) < 5) {
            return "Insufficient data for trend visualization (need at least 5 data points).";
        }
        
        // Sort results by elapsed time
        $sorted_results = $this->resource_results;
        usort($sorted_results, function($a, $b) {
            return $a['elapsed'] <=> $b['elapsed'];
        });
        
        // Extract data for charts
        $times = array_column($sorted_results, 'elapsed');
        $memories = array_column($sorted_results, 'memory_usage');
        $peak_memories = array_column($sorted_results, 'peak_memory');
        
        // Create memory chart
        $memory_chart = $this->create_ascii_chart(
            $times, 
            $memories, 
            "Memory Usage Trend (MB over time)", 
            $width, 
            $height
        );
        
        // Create peak memory chart
        $peak_chart = $this->create_ascii_chart(
            $times, 
            $peak_memories, 
            "Peak Memory Trend (MB over time)", 
            $width, 
            $height
        );
        
        return $memory_chart . "\n" . $peak_chart;
    }
    
    /**
     * Create ASCII chart for a metric
     * 
     * @param array $x X values (time)
     * @param array $y Y values (resource metric)
     * @param string $title Chart title
     * @param int $width Chart width
     * @param int $height Chart height
     * @return string ASCII chart
     */
    private function create_ascii_chart($x, $y, $title, $width, $height) {
        $n = count($x);
        if ($n < 2) return "";
        
        // Find min/max values
        $min_x = min($x);
        $max_x = max($x);
        $min_y = min($y);
        $max_y = max($y);
        
        // Ensure range is non-zero
        $x_range = $max_x - $min_x;
        if ($x_range == 0) $x_range = 1;
        
        $y_range = $max_y - $min_y;
        if ($y_range == 0) $y_range = 1;
        
        // Create chart canvas
        $output = "\n   $title:\n";
        $chart = [];
        for ($i = 0; $i < $height; $i++) {
            $chart[$i] = str_split(str_repeat(' ', $width));
        }
        
        // Plot data points
        for ($i = 0; $i < $n; $i++) {
            $x_pos = round(($x[$i] - $min_x) / $x_range * ($width - 1));
            $y_pos = $height - 1 - round(($y[$i] - $min_y) / $y_range * ($height - 1));
            
            // Ensure within bounds
            $x_pos = max(0, min($width - 1, $x_pos));
            $y_pos = max(0, min($height - 1, $y_pos));
            
            // Plot point
            $chart[$y_pos][$x_pos] = '•';
        }
        
        // Add trend line (linear regression)
        $slope = $this->calculate_trend_slope($x, $y);
        $y_mean = array_sum($y) / $n;
        $x_mean = array_sum($x) / $n;
        $intercept = $y_mean - $slope * $x_mean;
        
        for ($x_pos = 0; $x_pos < $width; $x_pos++) {
            $x_val = $min_x + ($x_pos / ($width - 1)) * $x_range;
            $y_val = $slope * $x_val + $intercept;
            $y_pos = $height - 1 - round(($y_val - $min_y) / $y_range * ($height - 1));
            
            // Ensure within bounds
            if ($y_pos >= 0 && $y_pos < $height) {
                // Use different character for trend line to distinguish from data points
                if ($chart[$y_pos][$x_pos] == ' ') {
                    $chart[$y_pos][$x_pos] = '-';
                }
            }
        }
        
        // Add axis labels
        $output .= "   " . str_repeat(' ', strlen((string)$max_y) + 2) . "┌" . str_repeat('─', $width) . "┐\n";
        for ($i = 0; $i < $height; $i++) {
            $label_y = round($max_y - ($i / ($height - 1)) * $y_range, 1);
            $output .= sprintf("   %'.3s │%s│\n", $label_y, implode('', $chart[$i]));
        }
        $output .= "   " . str_repeat(' ', strlen((string)$max_y) + 2) . "└" . str_repeat('─', $width) . "┘\n";
        
        // X-axis labels (start, middle, end)
        $output .= sprintf("   %'.3s %'.3s%'.3s\n", 
                       round($min_x, 1),
                       str_repeat(' ', intval($width/2)) . round($min_x + $x_range/2, 1),
                       str_repeat(' ', intval($width/2) - 2) . round($max_x, 1));
        
        return $output;
    }
    
    /**
     * Report trend analysis to CLI
     */
    public function report_trends(): void {
        if (!$this->track_trends || count($this->resource_results) < 3) {
            if (class_exists('WP_CLI')) {
                \WP_CLI::log("📈 Resource Trend Analysis: Insufficient data for trend analysis.");
            }
            return;
        }
        
        $trends = $this->analyze_trends();
        
        if (class_exists('WP_CLI')) {
            \WP_CLI::log("\n📈 Resource Trend Analysis:");
            \WP_CLI::log("   Data Points: {$trends['data_points']} over {$trends['time_span']} seconds");
            
            // Memory usage trends
            $memory_change = $trends['memory_usage']['change_percent'];
            $memory_direction = $memory_change > 0 ? '↑' : '↓';
            $memory_color = $memory_change > 20 ? "\033[31m" : ($memory_change > 5 ? "\033[33m" : "\033[32m");
            \WP_CLI::log("   Memory Usage: {$memory_color}{$memory_direction}{$memory_change}%\033[0m over test duration");
            \WP_CLI::log("   Pattern: " . ucfirst(str_replace('_', ' ', $trends['memory_usage']['pattern'])));
            
            // Peak memory trends
            $peak_change = $trends['peak_memory']['change_percent'];
            $peak_direction = $peak_change > 0 ? '↑' : '↓';
            $peak_color = $peak_change > 20 ? "\033[31m" : ($peak_change > 5 ? "\033[33m" : "\033[32m");
            \WP_CLI::log("   Peak Memory: {$peak_color}{$peak_direction}{$peak_change}%\033[0m over test duration");
            \WP_CLI::log("   Pattern: " . ucfirst(str_replace('_', ' ', $trends['peak_memory']['pattern'])));
            
            // Warning about unbounded growth if detected
            if ($trends['potentially_unbounded']) {
                \WP_CLI::warning("⚠️ Potential memory leak detected! Resource usage shows continuous growth pattern.");
            }
            
            // Generate visual trend charts
            $charts = $this->generate_trend_charts();
            \WP_CLI::log($charts);
        }
    }
}

class MicroChaos_Cache_Analyzer {
    /**
     * Cache headers storage
     *
     * @var array
     */
    private $cache_headers = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_headers = [];
    }

    /**
     * Process cache headers from response
     *
     * @param array $headers Response headers
     */
    public function collect_headers($headers) {
        // Headers to track (Pressable specific and general cache headers)
        $cache_header_names = ['x-ac', 'x-nananana', 'x-cache', 'age', 'x-cache-hits'];

        foreach ($cache_header_names as $header) {
            if (isset($headers[$header])) {
                $value = $headers[$header];
                if (!isset($this->cache_headers[$header])) {
                    $this->cache_headers[$header] = [];
                }
                if (!isset($this->cache_headers[$header][$value])) {
                    $this->cache_headers[$header][$value] = 0;
                }
                $this->cache_headers[$header][$value]++;
            }
        }
    }

    /**
     * Get collected cache headers
     *
     * @return array Cache headers data
     */
    public function get_cache_headers() {
        return $this->cache_headers;
    }

    /**
     * Generate cache header report
     *
     * @param int $total_requests Total number of requests
     * @return array Report data
     */
    public function generate_report($total_requests) {
        $report = [
            'headers' => $this->cache_headers,
            'summary' => [],
        ];

        // Calculate percentage breakdowns for each header type
        foreach ($this->cache_headers as $header => $values) {
            $total_for_header = array_sum($values);
            $report['summary'][$header . '_breakdown'] = [];
            
            foreach ($values as $value => $count) {
                $percentage = round(($count / $total_for_header) * 100, 1);
                $report['summary'][$header . '_breakdown'][$value] = [
                    'count' => $count,
                    'percentage' => $percentage
                ];
            }
        }

        // Calculate average cache age if available
        if (isset($this->cache_headers['age'])) {
            $total_age = 0;
            $age_count = 0;
            foreach ($this->cache_headers['age'] as $age => $count) {
                $total_age += intval($age) * $count;
                $age_count += $count;
            }
            if ($age_count > 0) {
                $avg_age = round($total_age / $age_count, 1);
                $report['summary']['average_cache_age'] = $avg_age;
            }
        }

        return $report;
    }

    /**
     * Output cache headers report to CLI
     *
     * @param int $total_requests Total number of requests made
     */
    public function report_summary($total_requests) {
        if (empty($this->cache_headers)) {
            if (class_exists('WP_CLI')) {
                \WP_CLI::log("ℹ️ No cache headers detected.");
            }
            return;
        }

        if (class_exists('WP_CLI')) {
            \WP_CLI::log("📦 Pressable Cache Header Summary:");

            // Output Edge Cache (x-ac) breakdown
            if (isset($this->cache_headers['x-ac'])) {
                \WP_CLI::log("   🌐 Edge Cache (x-ac):");
                $total_x_ac = array_sum($this->cache_headers['x-ac']);
                foreach ($this->cache_headers['x-ac'] as $value => $count) {
                    $percentage = round(($count / $total_x_ac) * 100, 1);
                    \WP_CLI::log("     {$value}: {$count} ({$percentage}%)");
                }
            }

            // Output Batcache (x-nananana) breakdown
            if (isset($this->cache_headers['x-nananana'])) {
                \WP_CLI::log("   🦇 Batcache (x-nananana):");
                $total_batcache = array_sum($this->cache_headers['x-nananana']);
                foreach ($this->cache_headers['x-nananana'] as $value => $count) {
                    $percentage = round(($count / $total_batcache) * 100, 1);
                    \WP_CLI::log("     {$value}: {$count} ({$percentage}%)");
                }
            }

            // Output other cache headers if present
            foreach (['x-cache', 'age', 'x-cache-hits'] as $header) {
                if (isset($this->cache_headers[$header])) {
                    \WP_CLI::log("   {$header}:");
                    $total_header = array_sum($this->cache_headers[$header]);
                    foreach ($this->cache_headers[$header] as $value => $count) {
                        $percentage = round(($count / $total_header) * 100, 1);
                        \WP_CLI::log("     {$value}: {$count} ({$percentage}%)");
                    }
                }
            }

            // Output average cache age if available
            if (isset($this->cache_headers['age'])) {
                $total_age = 0;
                $age_count = 0;
                foreach ($this->cache_headers['age'] as $age => $count) {
                    $total_age += intval($age) * $count;
                    $age_count += $count;
                }
                if ($age_count > 0) {
                    $avg_age = round($total_age / $age_count, 1);
                    \WP_CLI::log("   ⏲ Average Cache Age: {$avg_age} seconds");
                }
            }
        }
    }
}

class MicroChaos_Reporting_Engine {
    /**
     * Request results storage
     *
     * @var array
     */
    private $results = [];

    /**
     * Baseline storage implementation
     *
     * @var MicroChaos_Baseline_Storage
     */
    private $baseline_storage;

    /**
     * Constructor
     *
     * @param MicroChaos_Baseline_Storage|null $storage Optional baseline storage (will create default if not provided)
     */
    public function __construct(?MicroChaos_Baseline_Storage $storage = null) {
        $this->results = [];
        $this->baseline_storage = $storage ?? new MicroChaos_Transient_Baseline_Storage('microchaos_baseline');
    }
    
    /**
     * Reset results array (useful for progressive testing)
     */
    public function reset_results(): void {
        $this->results = [];
    }

    /**
     * Add a result
     *
     * @param array $result Result data
     */
    public function add_result(array $result): void {
        $this->results[] = $result;
    }

    /**
     * Add multiple results
     *
     * @param array $results Array of result data
     */
    public function add_results(array $results): void {
        foreach ($results as $result) {
            $this->add_result($result);
        }
    }

    /**
     * Get all results
     *
     * @return array All results
     */
    public function get_results(): array {
        return $this->results;
    }

    /**
     * Get total request count
     *
     * @return int Number of requests
     */
    public function get_request_count(): int {
        return count($this->results);
    }

    /**
     * Generate summary report
     *
     * @return array Summary report data
     */
    public function generate_summary(): array {
        $count = count($this->results);
        if ($count === 0) {
            return [
                'count' => 0,
                'success' => 0,
                'errors' => 0,
                'error_rate' => 0,
                'timing' => [
                    'avg' => 0,
                    'median' => 0,
                    'min' => 0,
                    'max' => 0,
                ],
            ];
        }

        $times = array_column($this->results, 'time');
        sort($times);

        $sum = array_sum($times);
        $avg = round($sum / $count, 4);
        $median = round($times[floor($count / 2)], 4);
        $min = round(min($times), 4);
        $max = round(max($times), 4);

        $successes = count(array_filter($this->results, fn($r) => $r['code'] === 200));
        $errors = $count - $successes;
        $error_rate = $count > 0 ? round(($errors / $count) * 100, 1) : 0;

        return [
            'count' => $count,
            'success' => $successes,
            'errors' => $errors,
            'error_rate' => $error_rate,
            'timing' => [
                'avg' => $avg,
                'median' => $median,
                'min' => $min,
                'max' => $max,
            ],
        ];
    }

    /**
     * Report summary to CLI
     *
     * @param array|null $baseline Optional baseline data for comparison
     * @param array|null $provided_summary Optional pre-generated summary (useful for progressive tests)
     * @param string|null $threshold_profile Optional threshold profile to use for formatting
     * @param array|null $execution_metrics Optional execution timing and throughput metrics
     */
    public function report_summary(?array $baseline = null, ?array $provided_summary = null, ?string $threshold_profile = null, ?array $execution_metrics = null): void {
        $summary = $provided_summary ?: $this->generate_summary();

        if ($summary['count'] === 0) {
            if (class_exists('WP_CLI')) {
                \WP_CLI::warning("No results to summarize.");
            }
            return;
        }

        if (class_exists('WP_CLI')) {
            $error_rate = $summary['error_rate'];

            \WP_CLI::log("📊 Load Test Summary");
            \WP_CLI::log("   ═══════════════════════════════════════════════════");

            // Test Execution Metrics section
            if ($execution_metrics) {
                \WP_CLI::log("   Test Execution:");
                \WP_CLI::log("     Started:    {$execution_metrics['started_at']}");
                \WP_CLI::log("     Ended:      {$execution_metrics['ended_at']}");
                \WP_CLI::log("     Duration:   {$execution_metrics['duration_seconds']}s ({$execution_metrics['duration_formatted']})");
                \WP_CLI::log("     Requests:   {$execution_metrics['total_requests']}");
                \WP_CLI::log("     Throughput: {$execution_metrics['throughput_rps']} req/s");

                if (isset($execution_metrics['capacity'])) {
                    \WP_CLI::log("");
                    \WP_CLI::log("   Capacity Projection (at current throughput):");
                    \WP_CLI::log("     Per hour:   " . number_format($execution_metrics['capacity']['per_hour']) . " requests");
                    \WP_CLI::log("     Per day:    " . number_format($execution_metrics['capacity']['per_day']) . " requests");
                    \WP_CLI::log("     Per month:  ~" . $this->format_large_number($execution_metrics['capacity']['per_month']) . " requests");
                    \WP_CLI::log("     ⚠️  Assumes sustained throughput. Actual capacity depends on workers, RAM, cache hit rate.");
                }
                \WP_CLI::log("   ═══════════════════════════════════════════════════");
                \WP_CLI::log("");
            }

            \WP_CLI::log("   Response Statistics:");
            \WP_CLI::log("     Total Requests: {$summary['count']}");
            
            $error_formatted = MicroChaos_Thresholds::format_value($error_rate, 'error_rate', $threshold_profile);
            \WP_CLI::log("     Success: {$summary['success']} | Errors: {$summary['errors']} | Error Rate: {$error_formatted}");
            
            // Format with threshold colors
            $avg_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['avg'], 'response_time', $threshold_profile);
            $median_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['median'], 'response_time', $threshold_profile);
            $max_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['max'], 'response_time', $threshold_profile);
            
            \WP_CLI::log("     Avg Time: {$avg_time_formatted} | Median: {$median_time_formatted}");
            \WP_CLI::log("     Fastest: {$summary['timing']['min']}s | Slowest: {$max_time_formatted}");

            // Add comparison with baseline if provided
            if ($baseline && isset($baseline['timing'])) {
                $avg_change = $baseline['timing']['avg'] > 0
                    ? (($summary['timing']['avg'] - $baseline['timing']['avg']) / $baseline['timing']['avg']) * 100
                    : 0;
                $avg_change = round($avg_change, 1);

                $median_change = $baseline['timing']['median'] > 0
                    ? (($summary['timing']['median'] - $baseline['timing']['median']) / $baseline['timing']['median']) * 100
                    : 0;
                $median_change = round($median_change, 1);

                $change_indicator = $avg_change <= 0 ? '↓' : '↑';
                $change_color = $avg_change <= 0 ? "\033[32m" : "\033[31m";

                \WP_CLI::log("     Comparison to Baseline:");
                \WP_CLI::log("       - Avg: {$change_color}{$change_indicator}{$avg_change}%\033[0m vs {$baseline['timing']['avg']}s");

                $change_indicator = $median_change <= 0 ? '↓' : '↑';
                $change_color = $median_change <= 0 ? "\033[32m" : "\033[31m";
                \WP_CLI::log("       - Median: {$change_color}{$change_indicator}{$median_change}%\033[0m vs {$baseline['timing']['median']}s");
            }
            
            // Add response time distribution histogram
            if (count($this->results) >= 10) {
                $times = array_column($this->results, 'time');
                $histogram = MicroChaos_Thresholds::generate_histogram($times);
                \WP_CLI::log($histogram);
            }
        }
    }
    
    /**
     * Save current results as baseline
     *
     * @param string $name Optional name for the baseline
     * @return array Baseline data
     */
    public function save_baseline(string $name = 'default'): array {
        $baseline = $this->generate_summary();
        $this->baseline_storage->save($name, $baseline);
        return $baseline;
    }

    /**
     * Get saved baseline data
     *
     * @param string $name Baseline name
     * @return array|null Baseline data or null if not found
     */
    public function get_baseline(string $name = 'default') {
        return $this->baseline_storage->get($name);
    }

    /**
     * Export results to a file
     *
     * @param string $format Export format (json, csv)
     * @param string $path File path
     * @return bool Success status
     */
    public function export_results(string $format, string $path): bool {
        $path = sanitize_text_field($path);
        $filepath = trailingslashit(WP_CONTENT_DIR) . ltrim($path, '/');

        switch (strtolower($format)) {
            case 'json':
                $data = json_encode([
                    'summary' => $this->generate_summary(),
                    'results' => $this->results,
                ], JSON_PRETTY_PRINT);
                return (bool) @file_put_contents($filepath, $data);

            case 'csv':
                if (empty($this->results)) {
                    return false;
                }

                $fp = @fopen($filepath, 'w');
                if (!$fp) {
                    return false;
                }

                // CSV headers
                fputcsv($fp, ['Time (s)', 'Status Code']);

                // Data rows
                foreach ($this->results as $result) {
                    fputcsv($fp, [
                        $result['time'],
                        $result['code'],
                    ]);
                }

                fclose($fp);
                return true;

            default:
                return false;
        }
    }

    /**
     * Format large numbers in human-readable format (K, M, B)
     *
     * @param int $number Number to format
     * @return string Formatted number (e.g., "4.1M", "137K")
     */
    private function format_large_number(int $number): string {
        if ($number >= 1000000000) {
            return round($number / 1000000000, 1) . 'B';
        } elseif ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return (string)$number;
    }
}

class MicroChaos_LoadTest_Orchestrator {

    /**
     * Test configuration
     *
     * @var array
     */
    private array $config;

    /**
     * Constructor
     *
     * @param array $config Test configuration from CLI options
     */
    public function __construct(array $config) {
        $this->config = $this->normalize_config($config);
    }

    /**
     * Normalize config with defaults
     *
     * @param array $config Raw config
     * @return array Normalized config with defaults
     */
    private function normalize_config(array $config): array {
        return array_merge([
            'endpoint' => null,
            'endpoints' => null,
            'count' => 100,
            'duration' => null,
            'burst' => 10,
            'delay' => 2,
            'method' => 'GET',
            'body' => null,
            'warm_cache' => false,
            'flush_between' => false,
            'rampup' => false,
            'auth_user' => null,
            'multi_auth' => null,
            'custom_cookies' => null,
            'custom_headers' => null,
            'rotation_mode' => 'serial',
            'resource_logging' => false,
            'resource_trends' => false,
            'collect_cache_headers' => false,
            'auto_thresholds' => false,
            'threshold_profile' => 'default',
            'use_thresholds' => null,
            'monitoring_integration' => false,
            'monitoring_test_id' => null,
            'save_baseline' => null,
            'compare_baseline' => null,
            'log_path' => null,
        ], $config);
    }

    /**
     * Execute the load test
     *
     * @return array Result data for final message
     */
    public function execute(): array {
        $config = $this->config;

        // Default endpoint if none specified
        if (!$config['endpoint'] && !$config['endpoints']) {
            $config['endpoint'] = 'home';
        }

        // Load custom thresholds if specified
        if ($config['use_thresholds']) {
            $loaded = MicroChaos_Thresholds::load_thresholds($config['use_thresholds']);
            if ($loaded) {
                \WP_CLI::log("🎯 Using custom thresholds from profile: {$config['use_thresholds']}");
            } else {
                \WP_CLI::warning("⚠️ Could not load thresholds profile: {$config['use_thresholds']}. Using defaults.");
            }
        }

        // Initialize components
        $request_generator = new MicroChaos_Request_Generator([
            'collect_cache_headers' => $config['collect_cache_headers'],
        ]);

        $resource_monitor = new MicroChaos_Resource_Monitor([
            'track_trends' => $config['resource_trends']
        ]);
        $cache_analyzer = new MicroChaos_Cache_Analyzer();
        $reporting_engine = new MicroChaos_Reporting_Engine();

        // Initialize integration logger
        $integration_logger = new MicroChaos_Integration_Logger([
            'enabled' => $config['monitoring_integration'],
            'test_id' => $config['monitoring_test_id']
        ]);

        // Resolve endpoints
        $endpoint_list = $this->resolve_endpoints($request_generator, $config);

        // Process body if it's a file reference
        $body = $config['body'];
        if ($body && strpos($body, 'file:') === 0) {
            $file_path = substr($body, 5);
            if (file_exists($file_path)) {
                $body = file_get_contents($file_path);
            } else {
                \WP_CLI::error("Body file not found: $file_path");
            }
        }

        // Set up authentication and cookies
        $cookies = $this->setup_authentication($config);

        // Process custom headers
        if ($config['custom_headers']) {
            $headers = [];
            $header_pairs = array_map('trim', explode(',', $config['custom_headers']));

            foreach ($header_pairs as $pair) {
                list($name, $value) = array_map('trim', explode('=', $pair, 2));
                $headers[$name] = $value;
            }

            $request_generator->set_custom_headers($headers);
            \WP_CLI::log("📝 Added " . count($header_pairs) . " custom " .
                          (count($header_pairs) === 1 ? "header" : "headers"));
        }

        // Log test start
        $this->log_test_start($config, $endpoint_list, $integration_logger);

        // Warm cache if specified
        if ($config['warm_cache']) {
            \WP_CLI::log("🧤 Warming cache...");
            foreach ($endpoint_list as $endpoint_item) {
                $request_generator->fire_request(
                    $endpoint_item['url'],
                    $config['log_path'],
                    $cookies,
                    $config['method'],
                    $body
                );
                \WP_CLI::log("  Warmed {$endpoint_item['slug']}");
            }
        }

        // Execute the main test loop
        $loop_result = $this->run_test_loop(
            $config,
            $endpoint_list,
            $request_generator,
            $resource_monitor,
            $cache_analyzer,
            $reporting_engine,
            $integration_logger,
            $cookies,
            $body
        );

        // Build execution metrics
        $execution_metrics = $this->build_execution_metrics(
            $loop_result['test_start_timestamp'],
            $loop_result['test_end_timestamp'],
            $loop_result['completed']
        );

        // Handle baseline comparison
        $perf_baseline = $config['compare_baseline']
            ? $reporting_engine->get_baseline($config['compare_baseline'])
            : null;
        $resource_baseline = ($config['compare_baseline'] && $config['resource_logging'])
            ? $resource_monitor->get_baseline($config['compare_baseline'])
            : null;

        // Generate summaries
        $summary = $reporting_engine->generate_summary();
        $resource_summary = $config['resource_logging']
            ? $resource_monitor->generate_summary()
            : null;

        // Auto-calibrate thresholds if requested
        $use_thresholds = $config['use_thresholds'];
        if ($config['auto_thresholds']) {
            $use_thresholds = $this->calibrate_thresholds(
                $summary,
                $resource_summary,
                $config['threshold_profile']
            );
        }

        // Display reports
        $reporting_engine->report_summary($perf_baseline, null, $use_thresholds, $execution_metrics);

        if ($config['resource_logging']) {
            $resource_monitor->report_summary($resource_baseline, null, $use_thresholds);

            if ($config['resource_trends']) {
                $resource_monitor->report_trends();
            }
        }

        // Save baseline if specified
        if ($config['save_baseline']) {
            $reporting_engine->save_baseline($config['save_baseline']);
            if ($config['resource_logging']) {
                $resource_monitor->save_baseline($config['save_baseline']);
            }
            \WP_CLI::success("✅ Baseline '{$config['save_baseline']}' saved.");
        }

        // Report cache headers if enabled
        if ($config['collect_cache_headers']) {
            $cache_analyzer->report_summary($reporting_engine->get_request_count());
        }

        // Log test completion to integration logger
        if ($config['monitoring_integration']) {
            $final_summary = $reporting_engine->generate_summary();

            if ($config['collect_cache_headers']) {
                $cache_report = $cache_analyzer->generate_report($reporting_engine->get_request_count());
                $final_summary['cache'] = $cache_report;
            }

            $integration_logger->log_test_complete($final_summary, $resource_summary, $execution_metrics);
            \WP_CLI::log("🔌 Monitoring data logged to PHP error log (test ID: {$integration_logger->test_id})");
        }

        // Return result data for final success message
        return [
            'completed' => $loop_result['completed'],
            'count' => $config['count'],
            'run_by_duration' => $loop_result['run_by_duration'],
            'actual_minutes' => $loop_result['actual_minutes'],
        ];
    }

    /**
     * Resolve endpoints from config
     *
     * @param MicroChaos_Request_Generator $request_generator
     * @param array $config
     * @return array Endpoint list
     */
    private function resolve_endpoints(MicroChaos_Request_Generator $request_generator, array $config): array {
        $endpoint_list = [];

        if ($config['endpoints']) {
            $endpoint_items = array_map('trim', explode(',', $config['endpoints']));
            foreach ($endpoint_items as $item) {
                $url = $request_generator->resolve_endpoint($item);
                if ($url) {
                    $endpoint_list[] = [
                        'slug' => $item,
                        'url' => $url
                    ];
                } else {
                    \WP_CLI::warning("Invalid endpoint: $item. Skipping.");
                }
            }

            if (empty($endpoint_list)) {
                \WP_CLI::error("No valid endpoints to test.");
            }
        } elseif ($config['endpoint']) {
            $url = $request_generator->resolve_endpoint($config['endpoint']);
            if (!$url) {
                \WP_CLI::error("Invalid endpoint. Use 'home', 'shop', 'cart', 'checkout', or 'custom:/your/path'.");
            }
            $endpoint_list[] = [
                'slug' => $config['endpoint'],
                'url' => $url
            ];
        }

        return $endpoint_list;
    }

    /**
     * Set up authentication and process cookies
     *
     * @param array $config
     * @return array|null Cookies for requests
     */
    private function setup_authentication(array $config): ?array {
        $cookies = null;

        if ($config['multi_auth']) {
            $emails = array_map('trim', explode(',', $config['multi_auth']));
            $cookies = MicroChaos_Authentication_Manager::authenticate_users($emails);
            if (empty($cookies)) {
                \WP_CLI::warning("No valid multi-auth sessions. Continuing without authentication.");
            }
        } elseif ($config['auth_user']) {
            $cookies = MicroChaos_Authentication_Manager::authenticate_user($config['auth_user']);
            if ($cookies === null) {
                \WP_CLI::error("User with email {$config['auth_user']} not found.");
            }
        }

        // Process custom cookies if specified
        if ($config['custom_cookies']) {
            $custom_cookie_jar = [];
            $cookie_pairs = array_map('trim', explode(',', $config['custom_cookies']));

            foreach ($cookie_pairs as $pair) {
                list($name, $value) = array_map('trim', explode('=', $pair, 2));
                $cookie = new \WP_Http_Cookie([
                    'name' => $name,
                    'value' => $value,
                ]);
                $custom_cookie_jar[] = $cookie;
            }

            // Merge with auth cookies if present
            if ($cookies) {
                if (is_array($cookies) && isset($cookies[0]) && is_array($cookies[0])) {
                    // Handle multi-auth case
                    $cookies[0] = array_merge($cookies[0], $custom_cookie_jar);
                } else {
                    $cookies = array_merge($cookies, $custom_cookie_jar);
                }
            } else {
                $cookies = $custom_cookie_jar;
            }

            \WP_CLI::log("🍪 Added " . count($cookie_pairs) . " custom " .
                          (count($cookie_pairs) === 1 ? "cookie" : "cookies"));
        }

        return $cookies;
    }

    /**
     * Log test start information
     *
     * @param array $config
     * @param array $endpoint_list
     * @param MicroChaos_Integration_Logger $integration_logger
     */
    private function log_test_start(array $config, array $endpoint_list, MicroChaos_Integration_Logger $integration_logger): void {
        \WP_CLI::log("🚀 MicroChaos Load Test Started");

        if (count($endpoint_list) === 1) {
            \WP_CLI::log("-> URL: {$endpoint_list[0]['url']}");
        } else {
            \WP_CLI::log("-> URLs: " . count($endpoint_list) . " endpoints (" .
                          implode(', ', array_column($endpoint_list, 'slug')) . ") - Rotation mode: {$config['rotation_mode']}");
        }

        \WP_CLI::log("-> Method: {$config['method']}");

        if ($config['body']) {
            $body_preview = strlen($config['body']) > 50
                ? substr($config['body'], 0, 47) . '...'
                : $config['body'];
            \WP_CLI::log("-> Body: $body_preview");
        }

        if ($config['duration']) {
            $duration_word = $config['duration'] == 1 ? "minute" : "minutes";
            \WP_CLI::log("-> Duration: {$config['duration']} $duration_word | Burst: {$config['burst']} | Delay: {$config['delay']}s");
        } else {
            \WP_CLI::log("-> Total: {$config['count']} | Burst: {$config['burst']} | Delay: {$config['delay']}s");
        }

        if ($config['collect_cache_headers']) {
            \WP_CLI::log("-> Cache header tracking enabled");
        }

        if ($config['monitoring_integration']) {
            \WP_CLI::log("-> 🔌 Monitoring integration enabled (test ID: {$integration_logger->test_id})");

            $log_config = [
                'endpoint' => $config['endpoint'],
                'endpoints' => $config['endpoints'],
                'count' => $config['count'],
                'duration' => $config['duration'],
                'burst' => $config['burst'],
                'delay' => $config['delay'],
                'method' => $config['method'],
                'is_auth' => ($config['auth_user'] !== null || $config['multi_auth'] !== null),
                'cache_headers' => $config['collect_cache_headers'],
                'resource_logging' => $config['resource_logging'],
                'test_type' => $config['duration'] ? 'duration' : 'count'
            ];

            $integration_logger->log_test_start($log_config);
        }
    }

    /**
     * Run the main test loop
     *
     * @param array $config
     * @param array $endpoint_list
     * @param MicroChaos_Request_Generator $request_generator
     * @param MicroChaos_Resource_Monitor $resource_monitor
     * @param MicroChaos_Cache_Analyzer $cache_analyzer
     * @param MicroChaos_Reporting_Engine $reporting_engine
     * @param MicroChaos_Integration_Logger $integration_logger
     * @param array|null $cookies
     * @param string|null $body
     * @return array Loop result data
     */
    private function run_test_loop(
        array $config,
        array $endpoint_list,
        MicroChaos_Request_Generator $request_generator,
        MicroChaos_Resource_Monitor $resource_monitor,
        MicroChaos_Cache_Analyzer $cache_analyzer,
        MicroChaos_Reporting_Engine $reporting_engine,
        MicroChaos_Integration_Logger $integration_logger,
        ?array $cookies,
        ?string $body
    ): array {
        $completed = 0;
        $current_ramp = $config['rampup'] ? 1 : $config['burst'];
        $endpoint_index = 0;

        // Capture precise test start timestamp
        $test_start_timestamp = microtime(true);

        // Set up duration-based testing
        $start_time = time();
        $end_time = $config['duration'] ? $start_time + ($config['duration'] * 60) : null;
        $run_by_duration = ($config['duration'] !== null);

        while (true) {
            // Check exit condition
            if ($run_by_duration) {
                if (time() >= $end_time) {
                    break;
                }
            } else {
                if ($completed >= $config['count']) {
                    break;
                }
            }

            // Monitor resources if enabled
            if ($config['resource_logging']) {
                $resource_data = $resource_monitor->log_resource_utilization();

                if ($config['monitoring_integration']) {
                    $integration_logger->log_resource_snapshot($resource_data);
                }
            }

            // Calculate burst size
            if ($config['rampup']) {
                $current_ramp = min($current_ramp + 1, $config['burst']);
            }

            $current_burst = $run_by_duration
                ? $current_ramp
                : min($current_ramp, $config['burst'], $config['count'] - $completed);

            \WP_CLI::log("⚡ Burst of $current_burst requests");

            // Flush cache if specified
            if ($config['flush_between']) {
                \WP_CLI::log("♻️ Flushing cache before burst...");
                wp_cache_flush();
            }

            // Select URLs for this burst
            $burst_urls = [];
            for ($i = 0; $i < $current_burst; $i++) {
                if ($config['rotation_mode'] === 'random') {
                    $selected = $endpoint_list[array_rand($endpoint_list)];
                } else {
                    $selected = $endpoint_list[$endpoint_index % count($endpoint_list)];
                    $endpoint_index++;
                }
                $burst_urls[] = $selected['url'];
            }

            // Fire requests
            $results = [];
            foreach ($burst_urls as $url) {
                $result = $request_generator->fire_request(
                    $url,
                    $config['log_path'],
                    $cookies,
                    $config['method'],
                    $body
                );
                $results[] = $result;
            }

            // Add results to reporting engine
            $reporting_engine->add_results($results);

            // Log results to integration logger
            if ($config['monitoring_integration']) {
                foreach ($results as $result) {
                    $integration_logger->log_request($result);
                }
            }

            // Process cache headers
            if ($config['collect_cache_headers']) {
                $cache_headers = $request_generator->get_cache_headers();
                foreach ($cache_headers as $header => $values) {
                    foreach ($values as $value => $header_count) {
                        $cache_analyzer->collect_headers([$header => $value]);
                    }
                }
                $request_generator->reset_cache_headers();
            }

            // Log burst completion
            if ($config['monitoring_integration']) {
                $burst_summary = [
                    'burst_size' => $current_burst,
                    'results' => $results,
                    'endpoints' => $burst_urls,
                    'completed_total' => $completed
                ];
                $integration_logger->log_burst_complete($completed / $current_burst, $current_burst, $burst_summary);
            }

            $completed += $current_burst;

            // Display elapsed time for duration mode
            if ($run_by_duration) {
                $elapsed_seconds = time() - $start_time;
                $elapsed_minutes = floor($elapsed_seconds / 60);
                $remaining_seconds = $elapsed_seconds % 60;
                $time_display = $elapsed_minutes . "m " . $remaining_seconds . "s";
                $percentage = min(round(($elapsed_seconds / ($config['duration'] * 60)) * 100), 100);
                \WP_CLI::log("⏲ Time elapsed: $time_display ($percentage% complete, $completed requests sent)");
            }

            // Delay between bursts
            $should_delay = $run_by_duration ? true : ($completed < $config['count']);

            if ($should_delay) {
                $random_delay = rand($config['delay'] * 50, $config['delay'] * 150) / 100;
                \WP_CLI::log("⏳ Sleeping for {$random_delay}s (randomized delay)");
                sleep((int)$random_delay);
            }
        }

        // Capture end timestamp
        $test_end_timestamp = microtime(true);

        // Calculate actual duration for result
        $actual_seconds = time() - $start_time;
        $actual_minutes = round($actual_seconds / 60, 1);

        return [
            'completed' => $completed,
            'test_start_timestamp' => $test_start_timestamp,
            'test_end_timestamp' => $test_end_timestamp,
            'run_by_duration' => $run_by_duration,
            'actual_minutes' => $actual_minutes,
        ];
    }

    /**
     * Build execution metrics
     *
     * @param float $start_timestamp
     * @param float $end_timestamp
     * @param int $completed
     * @return array Execution metrics
     */
    private function build_execution_metrics(float $start_timestamp, float $end_timestamp, int $completed): array {
        $duration = $end_timestamp - $start_timestamp;
        $rps = $duration > 0 ? round($completed / $duration, 2) : 0;

        return [
            'started_at' => date('Y-m-d H:i:s', (int)$start_timestamp),
            'started_at_iso' => date('c', (int)$start_timestamp),
            'ended_at' => date('Y-m-d H:i:s', (int)$end_timestamp),
            'ended_at_iso' => date('c', (int)$end_timestamp),
            'duration_seconds' => round($duration, 2),
            'duration_formatted' => $this->format_duration($duration),
            'total_requests' => $completed,
            'throughput_rps' => $rps,
            'capacity' => [
                'per_hour' => (int)($rps * 3600),
                'per_day' => (int)($rps * 86400),
                'per_month' => (int)($rps * 2592000),
            ],
        ];
    }

    /**
     * Calibrate thresholds based on test results
     *
     * @param array $summary Performance summary
     * @param array|null $resource_summary Resource summary
     * @param string $profile Profile name
     * @return string Profile name to use
     */
    private function calibrate_thresholds(array $summary, ?array $resource_summary, string $profile): string {
        \WP_CLI::log("🔍 Auto-calibrating thresholds based on this test run...");

        $calibration_data = $summary;
        if ($resource_summary) {
            $calibration_data['memory'] = $resource_summary['memory'];
            $calibration_data['peak_memory'] = $resource_summary['peak_memory'];
        }

        $thresholds = MicroChaos_Thresholds::calibrate_thresholds($calibration_data, $profile);

        \WP_CLI::log("✅ Custom thresholds calibrated and saved as profile: {$profile}");
        \WP_CLI::log("   Response time: Good <= {$thresholds['response_time']['good']}s | Warning <= {$thresholds['response_time']['warn']}s | Critical > {$thresholds['response_time']['critical']}s");

        if (isset($thresholds['memory_usage'])) {
            \WP_CLI::log("   Memory usage: Good <= {$thresholds['memory_usage']['good']}% | Warning <= {$thresholds['memory_usage']['warn']}% | Critical > {$thresholds['memory_usage']['critical']}%");
        }

        \WP_CLI::log("   Error rate: Good <= {$thresholds['error_rate']['good']}% | Warning <= {$thresholds['error_rate']['warn']}% | Critical > {$thresholds['error_rate']['critical']}%");

        return $profile;
    }

    /**
     * Format duration in human-readable format
     *
     * @param float $seconds Duration in seconds
     * @return string Formatted duration (e.g., "5m 15s" or "45s")
     */
    private function format_duration(float $seconds): string {
        $minutes = floor($seconds / 60);
        $secs = round($seconds % 60);

        if ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        }
        return "{$secs}s";
    }
}

class MicroChaos_Commands {
    /**
     * Register WP-CLI commands
     */
    public static function register() {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('microchaos', 'MicroChaos_Commands');
        }
    }

    /**
     * Run an internal load test using loopback requests.
     *
     * ## DESCRIPTION
     *
     * Fires synthetic internal requests to simulate performance load and high traffic behavior
     * on a given endpoint to allow monitoring of how the site responds under burst or sustained
     * load. Supports authenticated user testing, cache behavior toggles, and generates
     * a post-test summary report with timing metrics.
     *
     * Designed for staging environments where external load testing is restricted.
     * Optimized for Pressable and other managed WordPress hosts with loopback rate limiting.
     * Logs go to PHP error log and optionally to a local file under wp-content/.
     *
     * ## HOW TO USE
     *
     * 1. Decide the real-world traffic scenario you need to test (e.g., 20 concurrent hits
     * sustained, or a daily average of 30 hits/second at peak).
     *
     * 2. Run the loopback test with at least 2-3x those numbers to see if resource usage climbs
     * to a point of concern.
     *
     * 3. Watch server-level metrics (PHP error logs, memory usage, CPU load) to see if you're hitting resource ceilings.
     *
     * ## OPTIONS
     *
     * [--endpoint=<endpoint>]
     * : The page to test. Options:
     *     home       -> /
     *     shop       -> /shop/
     *     cart       -> /cart/
     *     checkout   -> /checkout/
     *     custom:/path -> any relative path (e.g., custom:/my-page/)
     *
     * [--endpoints=<endpoint-list>]
     * : Comma-separated list of endpoints to rotate through (uses same format as --endpoint).
     *
     * [--count=<number>]
     * : Total number of requests to send. Default: 100
     *
     * [--duration=<minutes>]
     * : Run test for specified duration in minutes instead of fixed request count.
     *   When specified, this takes precedence over --count option.
     *
     * [--burst=<number>]
     * : Number of concurrent requests to fire per burst. Default: 10
     *
     * [--delay=<seconds>]
     * : Delay between bursts in seconds. Default: 2
     *
     * [--method=<http_method>]
     * : HTTP method to use for requests (GET, POST, PUT, DELETE, etc.). Default: GET
     *
     * [--body=<request_body>]
     * : Request body for methods like POST/PUT. Can be URL-encoded string, JSON string,
     *   or path to a local JSON file (prefix with file:). For JSON, content type will be set automatically.
     *
     * [--warm-cache]
     * : Fires a single warm-up request before the test to prime caches.
     *
     * [--flush-between]
     * : Calls wp_cache_flush() before each burst to simulate cold cache conditions.
     *
     * [--log-to=<relative_path>]
     * : Log output to a file under wp-content/. Example: uploads/mc-log.txt
     *
     * [--auth=<email>]
     * : Run test as a logged-in user. Email must match a valid WP user.
     *
     * [--multi-auth=<emails>]
     * : Run test as multiple logged-in users. Comma-separated list of valid WP user emails.
     *
     * [--cookie=<cookie>]
     * : Set custom cookie(s) in name=value format. Use comma for multiple cookies.
     *
     * [--header=<header>]
     * : Set custom HTTP headers in name=value format. Use comma for multiple headers. Example: X-Test=123,Authorization=Bearer abc123
     *
     * [--rampup]
     * : Gradually increase the number of concurrent requests from 1 up to the burst limit.
     *
     * [--resource-logging]
     * : Log resource utilization during the test.
     *
     * [--resource-trends]
     * : Track and analyze resource utilization trends over time. Useful for detecting memory leaks.
     *
     * [--cache-headers]
     * : Collect and analyze Pressable-specific cache headers (x-ac for Edge Cache, x-nananana for Batcache).
     *
     * [--rotation-mode=<mode>]
     * : How to rotate through endpoints when multiple are specified. Options: serial, random. Default: serial.
     *
     * [--save-baseline=<name>]
     * : Save the results of this test as a baseline for future comparisons (optional name).
     *
     * [--compare-baseline=<name>]
     * : Compare results with a previously saved baseline (defaults to 'default').
     *
     * [--auto-thresholds]
     * : Automatically calibrate thresholds based on this test run.
     *
     * [--auto-thresholds-profile=<name>]
     * : Profile name to save or load auto-calibrated thresholds (default: 'default').
     *
     * [--use-thresholds=<profile>]
     * : Use previously saved thresholds from specified profile.
     *
     * [--monitoring-integration]
     * : Enable external monitoring integration by logging structured test data to error log.
     *
     * [--monitoring-test-id=<id>]
     * : Custom test ID for monitoring integration. Default: auto-generated.
     *
     * ## EXAMPLES
     *
     *     # Load test homepage with warm cache and log output
     *     wp microchaos loadtest --endpoint=home --count=100 --warm-cache --log-to=uploads/home-log.txt
     *
     *     # Test cart page with cache flush between bursts
     *     wp microchaos loadtest --endpoint=cart --count=60 --burst=15 --flush-between
     *
     *     # Simulate 50 logged-in user hits on the checkout page
     *     wp microchaos loadtest --endpoint=checkout --count=50 --auth=shopadmin@example.com
     *
     *     # Hit a custom endpoint and export all data
     *     wp microchaos loadtest --endpoint=custom:/my-page --count=25 --log-to=uploads/mypage-log.txt
     *
     *     # Load test with ramp-up
     *     wp microchaos loadtest --endpoint=shop --count=100 --rampup
     *
     *     # Test a POST endpoint with form data
     *     wp microchaos loadtest --endpoint=custom:/wp-json/api/v1/orders --count=20 --method=POST --body="product_id=123&quantity=1"
     *
     *     # Test a REST API endpoint with JSON data
     *     wp microchaos loadtest --endpoint=custom:/wp-json/wc/v3/products --method=POST --body='{"name":"Test Product","regular_price":"9.99"}'
     *
     *     # Use a JSON file as request body
     *     wp microchaos loadtest --endpoint=custom:/wp-json/wc/v3/orders/batch --method=POST --body=file:path/to/orders.json
     *
     *     # Test with cache header analysis
     *     wp microchaos loadtest --endpoint=home --count=50 --cache-headers
     *
     *     # Test with custom cookies
     *     wp microchaos loadtest --endpoint=home --count=50 --cookie="test_cookie=1,another_cookie=value"
     *
     *     # Test with custom HTTP headers
     *     wp microchaos loadtest --endpoint=home --count=50 --header="X-Test=true,Authorization=Bearer token123"
     *
     *     # Test with endpoint rotation
     *     wp microchaos loadtest --endpoints=home,shop,cart --count=60 --rotation-mode=random
     *
     *     # Save test results as a baseline for future comparison
     *     wp microchaos loadtest --endpoint=home --count=100 --save-baseline=homepage
     *
     *     # Compare with previously saved baseline
     *     wp microchaos loadtest --endpoint=home --count=100 --compare-baseline=homepage
     *
     *     # Run load test for a specific duration
     *     wp microchaos loadtest --endpoint=home --duration=5 --burst=10
     *
     *     # Run load test with resource trend tracking to detect memory leaks
     *     wp microchaos loadtest --endpoint=home --duration=10 --resource-logging --resource-trends
     *
     *     # Auto-calibrate thresholds based on site's current performance
     *     wp microchaos loadtest --endpoint=home --count=50 --auto-thresholds
     *
     *     # Use previously calibrated thresholds for reporting
     *     wp microchaos loadtest --endpoint=home --count=100 --use-thresholds=homepage
     *
     *     # Save thresholds with a custom profile name
     *     wp microchaos loadtest --endpoint=home --count=50 --auto-thresholds --auto-thresholds-profile=homepage
     *
     * @param array $args Command arguments
     * @param array $assoc_args Command options
     */
    public function loadtest($args, $assoc_args) {
        // Build config from CLI options
        $config = $this->parse_options($assoc_args);

        // Create and execute orchestrator
        $orchestrator = new MicroChaos_LoadTest_Orchestrator($config);
        $result = $orchestrator->execute();

        // Final success message
        if ($result['run_by_duration']) {
            \WP_CLI::success("✅ Load test complete: {$result['completed']} requests fired over {$result['actual_minutes']} minutes.");
        } else {
            \WP_CLI::success("✅ Load test complete: {$result['count']} requests fired.");
        }
    }

    /**
     * Parse CLI options into config array
     *
     * @param array $assoc_args CLI associative arguments
     * @return array Config array for orchestrator
     */
    private function parse_options(array $assoc_args): array {
        // Handle baseline options that might be flags (no value) or have values
        $compare_baseline = isset($assoc_args['compare-baseline'])
            ? ($assoc_args['compare-baseline'] ?: 'default')
            : null;

        $save_baseline = isset($assoc_args['save-baseline'])
            ? ($assoc_args['save-baseline'] ?: 'default')
            : null;

        return [
            'endpoint' => $assoc_args['endpoint'] ?? null,
            'endpoints' => $assoc_args['endpoints'] ?? null,
            'count' => intval($assoc_args['count'] ?? 100),
            'duration' => isset($assoc_args['duration']) ? floatval($assoc_args['duration']) : null,
            'burst' => intval($assoc_args['burst'] ?? 10),
            'delay' => intval($assoc_args['delay'] ?? 2),
            'method' => strtoupper($assoc_args['method'] ?? 'GET'),
            'body' => $assoc_args['body'] ?? null,
            'warm_cache' => isset($assoc_args['warm-cache']),
            'flush_between' => isset($assoc_args['flush-between']),
            'rampup' => isset($assoc_args['rampup']),
            'auth_user' => $assoc_args['auth'] ?? null,
            'multi_auth' => $assoc_args['multi-auth'] ?? null,
            'custom_cookies' => $assoc_args['cookie'] ?? null,
            'custom_headers' => $assoc_args['header'] ?? null,
            'rotation_mode' => $assoc_args['rotation-mode'] ?? 'serial',
            'resource_logging' => isset($assoc_args['resource-logging']),
            'resource_trends' => isset($assoc_args['resource-trends']),
            'collect_cache_headers' => isset($assoc_args['cache-headers']),
            'auto_thresholds' => isset($assoc_args['auto-thresholds']),
            'threshold_profile' => $assoc_args['auto-thresholds-profile'] ?? 'default',
            'use_thresholds' => $assoc_args['use-thresholds'] ?? null,
            'monitoring_integration' => isset($assoc_args['monitoring-integration']),
            'monitoring_test_id' => $assoc_args['monitoring-test-id'] ?? null,
            'save_baseline' => $save_baseline,
            'compare_baseline' => $compare_baseline,
            'log_path' => $assoc_args['log-to'] ?? null,
        ];
    }
}

    // Register the MicroChaos WP-CLI command
    WP_CLI::add_command('microchaos', 'MicroChaos_Commands');
}
