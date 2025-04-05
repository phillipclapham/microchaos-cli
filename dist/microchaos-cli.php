<?php
/**
 * Plugin Name: MicroChaos CLI Load Tester
 * Description: Internal WP-CLI based WordPress load tester for staging environments where
 * external load testing is restricted (like Pressable).
 * Version: 1.8.2
 * Author: Phill
 */

// Bootstrap MicroChaos components

/**
 * COMPILED SINGLE-FILE VERSION
 * Generated on: 2025-04-05T18:21:31.517Z
 * 
 * This is an automatically generated file - DO NOT EDIT DIRECTLY
 * Make changes to the modular version and rebuild.
 */

if (defined('WP_CLI') && WP_CLI) {

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
    public static function format_value($value, $type, $profile = null) {
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
    public static function get_php_memory_limit_mb() {
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
    public static function generate_chart($values, $title, $width = 40) {
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
    public static function get_thresholds($type, $profile = null) {
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
    public static function calibrate_thresholds($test_results, $profile = 'default', $persist = true) {
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
    public static function save_thresholds($profile, $thresholds) {
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
    public static function load_thresholds($profile) {
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
    public static function generate_histogram($times, $buckets = 5) {
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
     * @param array $resource_summary Resource summary if available
     */
    public function log_test_complete($summary, $resource_summary = null) {
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
                if (is_array($cookies) && isset($cookies[0]) && is_array($cookies[0])) {
                    // Multi-auth sessions: pick a random session
                    $selected_cookies = $cookies[array_rand($cookies)];
                    curl_setopt($curl, CURLOPT_COOKIE, implode('; ', array_map(
                        function($cookie) {
                            return "{$cookie->name}={$cookie->value}";
                        },
                        $selected_cookies
                    )));
                } else {
                    curl_setopt($curl, CURLOPT_COOKIE, implode('; ', array_map(
                        function($cookie) {
                            return "{$cookie->name}={$cookie->value}";
                        },
                        $cookies
                    )));
                }
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
                \WP_CLI::log("-> {$code} in {$duration}s");
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
            if (is_array($cookies) && isset($cookies[0]) && is_array($cookies[0])) {
                // Multi-auth sessions: pick a random session
                $selected_cookies = $cookies[array_rand($cookies)];
                $args['cookies'] = $selected_cookies;
            } else {
                $args['cookies'] = $cookies;
            }
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
            \WP_CLI::log("-> {$code} in {$duration}s");
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

        foreach ($cache_headers as $header) {
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
     * Get cache headers data
     *
     * @return array Collection of cache headers
     */
    public function get_cache_headers() {
        return $this->cache_headers;
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
     * Constructor
     */
    public function __construct() {
        $this->resource_results = [];
    }

    /**
     * Log current resource utilization
     *
     * @return array Current resource usage data
     */
    public function log_resource_utilization() {
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

        $this->resource_results[] = $result;
        return $result;
    }

    /**
     * Get all resource utilization results
     *
     * @return array Resource utilization data
     */
    public function get_resource_results() {
        return $this->resource_results;
    }

    /**
     * Generate resource utilization summary report
     *
     * @return array Summary metrics
     */
    public function generate_summary() {
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
    public function report_summary($baseline = null, $provided_summary = null, $threshold_profile = null) {
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
    public function save_baseline($name = 'default') {
        $baseline = $this->generate_summary();
        
        // Store in a transient or option for persistence
        if (function_exists('set_transient')) {
            set_transient('microchaos_resource_baseline_' . $name, $baseline, 86400 * 30); // 30 days
        }
        
        return $baseline;
    }
    
    /**
     * Get saved baseline data
     * 
     * @param string $name Baseline name
     * @return array|null Baseline data or null if not found
     */
    public function get_baseline($name = 'default') {
        if (function_exists('get_transient')) {
            return get_transient('microchaos_resource_baseline_' . $name);
        }
        
        return null;
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

        // Calculate batcache hit ratio if x-nananana is present
        if (isset($this->cache_headers['x-nananana'])) {
            $batcache_hits = array_sum($this->cache_headers['x-nananana']);
            $hit_ratio = round(($batcache_hits / $total_requests) * 100, 2);
            $report['summary']['batcache_hit_ratio'] = $hit_ratio;
        }

        // Calculate edge cache hit ratio if x-ac is present
        if (isset($this->cache_headers['x-ac'])) {
            $edge_hits = isset($this->cache_headers['x-ac']['HIT']) ? $this->cache_headers['x-ac']['HIT'] : 0;
            $hit_ratio = round(($edge_hits / $total_requests) * 100, 2);
            $report['summary']['edge_cache_hit_ratio'] = $hit_ratio;
        }

        // If age headers present, calculate average cache age
        if (isset($this->cache_headers['age'])) {
            $total_age = 0;
            $age_count = 0;
            foreach ($this->cache_headers['age'] as $age => $count) {
                $total_age += $age * $count;
                $age_count += $count;
            }
            $avg_age = round($total_age / $age_count, 2);
            $report['summary']['average_cache_age'] = $avg_age;
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

        $report = $this->generate_report($total_requests);

        if (class_exists('WP_CLI')) {
            \WP_CLI::log("📦 Cache Header Summary:");

            // Output batcache hit ratio
            if (isset($report['summary']['batcache_hit_ratio'])) {
                \WP_CLI::log("   🦇 Batcache Hit Ratio: {$report['summary']['batcache_hit_ratio']}%");
            }

            // Output edge cache hit ratio
            if (isset($report['summary']['edge_cache_hit_ratio'])) {
                \WP_CLI::log("   🌐 Edge Cache Hit Ratio: {$report['summary']['edge_cache_hit_ratio']}%");
            }

            // Output average cache age
            if (isset($report['summary']['average_cache_age'])) {
                \WP_CLI::log("   ⏲ Average Cache Age: {$report['summary']['average_cache_age']} seconds");
            }

            // Print detailed header statistics
            foreach ($this->cache_headers as $header => $values) {
                \WP_CLI::log("   $header:");
                foreach ($values as $val => $count) {
                    \WP_CLI::log("     $val: $count");
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
     * Constructor
     */
    public function __construct() {
        $this->results = [];
    }
    
    /**
     * Reset results array (useful for progressive testing)
     */
    public function reset_results() {
        $this->results = [];
    }

    /**
     * Add a result
     *
     * @param array $result Result data
     */
    public function add_result($result) {
        $this->results[] = $result;
    }

    /**
     * Add multiple results
     *
     * @param array $results Array of result data
     */
    public function add_results($results) {
        foreach ($results as $result) {
            $this->add_result($result);
        }
    }

    /**
     * Get all results
     *
     * @return array All results
     */
    public function get_results() {
        return $this->results;
    }

    /**
     * Get total request count
     *
     * @return int Number of requests
     */
    public function get_request_count() {
        return count($this->results);
    }

    /**
     * Generate summary report
     *
     * @return array Summary report data
     */
    public function generate_summary() {
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
     */
    public function report_summary($baseline = null, $provided_summary = null, $threshold_profile = null) {
        $summary = $provided_summary ?: $this->generate_summary();

        if ($summary['count'] === 0) {
            if (class_exists('WP_CLI')) {
                \WP_CLI::warning("No results to summarize.");
            }
            return;
        }

        if (class_exists('WP_CLI')) {
            $error_rate = $summary['error_rate'];
            
            \WP_CLI::log("📊 Load Test Summary:");
            \WP_CLI::log("   Total Requests: {$summary['count']}");
            
            $error_formatted = MicroChaos_Thresholds::format_value($error_rate, 'error_rate', $threshold_profile);
            \WP_CLI::log("   Success: {$summary['success']} | Errors: {$summary['errors']} | Error Rate: {$error_formatted}");
            
            // Format with threshold colors
            $avg_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['avg'], 'response_time', $threshold_profile);
            $median_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['median'], 'response_time', $threshold_profile);
            $max_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['max'], 'response_time', $threshold_profile);
            
            \WP_CLI::log("   Avg Time: {$avg_time_formatted} | Median: {$median_time_formatted}");
            \WP_CLI::log("   Fastest: {$summary['timing']['min']}s | Slowest: {$max_time_formatted}");
            
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
                
                \WP_CLI::log("   Comparison to Baseline:");
                \WP_CLI::log("   - Avg: {$change_color}{$change_indicator}{$avg_change}%\033[0m vs {$baseline['timing']['avg']}s");
                
                $change_indicator = $median_change <= 0 ? '↓' : '↑';
                $change_color = $median_change <= 0 ? "\033[32m" : "\033[31m";
                \WP_CLI::log("   - Median: {$change_color}{$change_indicator}{$median_change}%\033[0m vs {$baseline['timing']['median']}s");
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
    public function save_baseline($name = 'default') {
        $baseline = $this->generate_summary();
        
        // Store in a transient or option for persistence
        if (function_exists('set_transient')) {
            set_transient('microchaos_baseline_' . $name, $baseline, 86400 * 30); // 30 days
        }
        
        return $baseline;
    }
    
    /**
     * Get saved baseline data
     * 
     * @param string $name Baseline name
     * @return array|null Baseline data or null if not found
     */
    public function get_baseline($name = 'default') {
        if (function_exists('get_transient')) {
            return get_transient('microchaos_baseline_' . $name);
        }
        
        return null;
    }

    /**
     * Export results to a file
     *
     * @param string $format Export format (json, csv)
     * @param string $path File path
     * @return bool Success status
     */
    public function export_results($format, $path) {
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
     * With progressive load testing, you can automatically determine the maximum load your site
     * can handle before performance degradation occurs.
     *
     * Designed for staging environments where external load testing is restricted.
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
     * 4. For automatic capacity testing, use progressive mode to gradually increase load until
     *    performance thresholds are exceeded.
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
     * [--concurrency-mode=<mode>]
     * : Use 'async' to simulate concurrent requests in each burst. Default: serial
     *
     * [--rampup]
     * : Gradually increase the number of concurrent requests from 1 up to the burst limit.
     *
     * [--resource-logging]
     * : Log resource utilization during the test.
     *
     * [--cache-headers]
     * : Collect and summarize response cache headers like x-ac and x-nananana.
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
     * [--progressive]
     * : Run in progressive load testing mode to find the maximum capacity.
     * 
     * [--progressive-start=<number>]
     * : Initial concurrency level for progressive testing (default: 5).
     * 
     * [--progressive-step=<number>]
     * : Step size to increase concurrency in progressive testing (default: 5).
     * 
     * [--progressive-max=<number>]
     * : Maximum concurrency level to try in progressive testing (default: 100).
     * 
     * [--threshold-response-time=<seconds>]
     * : Response time threshold in seconds for progressive testing (default: 3.0).
     * 
     * [--threshold-error-rate=<percentage>]
     * : Error rate threshold percentage for progressive testing (default: 10%).
     * 
     * [--threshold-memory=<percentage>]
     * : Memory usage threshold percentage for progressive testing (default: 85%).
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
     *     # Load test with async concurrency
     *     wp microchaos loadtest --endpoint=shop --count=100 --concurrency-mode=async
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
     *     # Run progressive load testing to find max capacity
     *     wp microchaos loadtest --endpoint=home --progressive --resource-logging
     *     
     *     # Run progressive load test with custom thresholds
     *     wp microchaos loadtest --endpoint=home --progressive --threshold-response-time=2 --threshold-error-rate=5
     *     
     *     # Run progressive load test with custom start/step/max values
     *     wp microchaos loadtest --endpoint=home --progressive --progressive-start=10 --progressive-step=10 --progressive-max=200
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
        // Parse command options
        $endpoint = $assoc_args['endpoint'] ?? null;
        $endpoints = $assoc_args['endpoints'] ?? null;

        if (!$endpoint && !$endpoints) {
            $endpoint = 'home'; // Default endpoint
        }

        $duration = isset($assoc_args['duration']) ? floatval($assoc_args['duration']) : null;
        $count = intval($assoc_args['count'] ?? 100);
        $burst = intval($assoc_args['burst'] ?? 10);
        $delay = intval($assoc_args['delay'] ?? 2);
        $flush = isset($assoc_args['flush-between']);
        $warm = isset($assoc_args['warm-cache']);
        $log_path = $assoc_args['log-to'] ?? null;
        $auth_user = $assoc_args['auth'] ?? null;
        $multi_auth = $assoc_args['multi-auth'] ?? null;
        $rampup = isset($assoc_args['rampup']);
        $resource_logging = isset($assoc_args['resource-logging']);
        $method = strtoupper($assoc_args['method'] ?? 'GET');
        $body = $assoc_args['body'] ?? null;
        $custom_cookies = $assoc_args['cookie'] ?? null;
        $custom_headers = $assoc_args['header'] ?? null;
        $rotation_mode = $assoc_args['rotation-mode'] ?? 'serial';
        $collect_cache_headers = isset($assoc_args['cache-headers']);
        
        // Progressive load testing parameters
        $progressive_mode = isset($assoc_args['progressive']);
        $progressive_start = intval($assoc_args['progressive-start'] ?? MicroChaos_Thresholds::PROGRESSIVE_INITIAL_LOAD);
        $progressive_step = intval($assoc_args['progressive-step'] ?? MicroChaos_Thresholds::PROGRESSIVE_STEP_INCREASE);
        $progressive_max = intval($assoc_args['progressive-max'] ?? MicroChaos_Thresholds::PROGRESSIVE_MAX_LOAD);
        
        // Custom thresholds for progressive testing
        $threshold_response_time = floatval($assoc_args['threshold-response-time'] ?? MicroChaos_Thresholds::RESPONSE_TIME_CRITICAL);
        $threshold_error_rate = floatval($assoc_args['threshold-error-rate'] ?? MicroChaos_Thresholds::ERROR_RATE_CRITICAL);
        $threshold_memory = floatval($assoc_args['threshold-memory'] ?? MicroChaos_Thresholds::MEMORY_USAGE_CRITICAL);
        
        // Threshold calibration parameters
        $auto_thresholds = isset($assoc_args['auto-thresholds']);
        $threshold_profile = $assoc_args['auto-thresholds-profile'] ?? 'default';
        $use_thresholds = $assoc_args['use-thresholds'] ?? null;
        
        // Monitoring integration parameters
        $monitoring_integration = isset($assoc_args['monitoring-integration']);
        $monitoring_test_id = $assoc_args['monitoring-test-id'] ?? null;
        
        // Load custom thresholds if specified
        if ($use_thresholds) {
            $loaded = MicroChaos_Thresholds::load_thresholds($use_thresholds);
            if ($loaded) {
                \WP_CLI::log("🎯 Using custom thresholds from profile: {$use_thresholds}");
            } else {
                \WP_CLI::warning("⚠️ Could not load thresholds profile: {$use_thresholds}. Using defaults.");
            }
        }

        // Initialize components
        $request_generator = new MicroChaos_Request_Generator([
            'collect_cache_headers' => $collect_cache_headers,
        ]);

        $resource_monitor = new MicroChaos_Resource_Monitor();
        $cache_analyzer = new MicroChaos_Cache_Analyzer();
        $reporting_engine = new MicroChaos_Reporting_Engine();
        
        // Initialize integration logger
        $integration_logger = new MicroChaos_Integration_Logger([
            'enabled' => $monitoring_integration,
            'test_id' => $monitoring_test_id
        ]);

        // Process multiple endpoints if specified
        $endpoint_list = [];
        if ($endpoints) {
            $endpoint_items = array_map('trim', explode(',', $endpoints));
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
        } elseif ($endpoint) {
            // Single endpoint
            $url = $request_generator->resolve_endpoint($endpoint);
            if (!$url) {
                \WP_CLI::error("Invalid endpoint. Use 'home', 'shop', 'cart', 'checkout', or 'custom:/your/path'.");
            }
            $endpoint_list[] = [
                'slug' => $endpoint,
                'url' => $url
            ];
        }

        // Process body if it's a file reference
        if ($body && strpos($body, 'file:') === 0) {
            $file_path = substr($body, 5);
            if (file_exists($file_path)) {
                $body = file_get_contents($file_path);
            } else {
                \WP_CLI::error("Body file not found: $file_path");
            }
        }

        // Set up authentication
        $cookies = null;

        if ($multi_auth) {
            $emails = array_map('trim', explode(',', $multi_auth));
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

            if (empty($auth_sessions)) {
                \WP_CLI::warning("No valid multi-auth sessions available. Continuing without authentication.");
            }

            $cookies = $auth_sessions;
        } elseif ($auth_user) {
            $user = get_user_by('email', $auth_user);
            if (!$user) {
                \WP_CLI::error("User with email {$auth_user} not found.");
            }

            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            $cookies = wp_remote_retrieve_cookies(wp_remote_get(home_url()));
            \WP_CLI::log("🔐 Authenticated as {$user->user_login}");
        }

        // Process custom cookies if specified
        $custom_cookie_jar = null;
        if ($custom_cookies) {
            $custom_cookie_jar = [];
            $cookie_pairs = array_map('trim', explode(',', $custom_cookies));

            foreach ($cookie_pairs as $pair) {
                list($name, $value) = array_map('trim', explode('=', $pair, 2));
                $cookie = new \WP_Http_Cookie([
                    'name' => $name,
                    'value' => $value,
                ]);
                $custom_cookie_jar[] = $cookie;
            }

            // If we already have auth cookies, merge them
            if ($cookies) {
                if (is_array($cookies) && isset($cookies[0]) && is_array($cookies[0])) {
                    // Handle multi-auth case - merge with first auth session for simplicity
                    $cookies[0] = array_merge($cookies[0], $custom_cookie_jar);
                } else {
                    // Regular auth case
                    $cookies = array_merge($cookies, $custom_cookie_jar);
                }
            } else {
                $cookies = $custom_cookie_jar;
            }

            \WP_CLI::log("🍪 Added " . count($cookie_pairs) . " custom " .
                          (count($cookie_pairs) === 1 ? "cookie" : "cookies"));
        }
        
        // Process custom headers if specified
        if ($custom_headers) {
            $headers = [];
            $header_pairs = array_map('trim', explode(',', $custom_headers));
            
            foreach ($header_pairs as $pair) {
                list($name, $value) = array_map('trim', explode('=', $pair, 2));
                $headers[$name] = $value;
            }
            
            $request_generator->set_custom_headers($headers);
            \WP_CLI::log("📝 Added " . count($header_pairs) . " custom " .
                          (count($header_pairs) === 1 ? "header" : "headers"));
        }

        \WP_CLI::log("🚀 MicroChaos Load Test Started");

        // Log the test configuration
        if (count($endpoint_list) === 1) {
            \WP_CLI::log("-> URL: {$endpoint_list[0]['url']}");
        } else {
            \WP_CLI::log("-> URLs: " . count($endpoint_list) . " endpoints (" .
                          implode(', ', array_column($endpoint_list, 'slug')) . ") - Rotation mode: $rotation_mode");
        }

        \WP_CLI::log("-> Method: $method");

        if ($body) {
            \WP_CLI::log("-> Body: " . (strlen($body) > 50 ? substr($body, 0, 47) . '...' : $body));
        }

        if ($duration) {
            \WP_CLI::log("-> Duration: {$duration} " . ($duration == 1 ? "minute" : "minutes") . " | Burst: $burst | Delay: {$delay}s");
        } else {
            \WP_CLI::log("-> Total: $count | Burst: $burst | Delay: {$delay}s");
        }

        if ($collect_cache_headers) {
            \WP_CLI::log("-> Cache header tracking enabled");
        }
        
        if ($monitoring_integration) {
            \WP_CLI::log("-> 🔌 Monitoring integration enabled (test ID: {$integration_logger->test_id})");
            
            // Log test configuration for monitoring tools
            $config = [
                'endpoint' => $endpoint,
                'endpoints' => $endpoints,
                'count' => $count,
                'duration' => $duration,
                'burst' => $burst,
                'delay' => $delay,
                'method' => $method,
                'concurrency_mode' => isset($assoc_args['concurrency-mode']) ? $assoc_args['concurrency-mode'] : 'serial',
                'progressive' => $progressive_mode,
                'is_auth' => ($auth_user !== null || $multi_auth !== null),
                'cache_headers' => $collect_cache_headers,
                'resource_logging' => $resource_logging,
                'test_type' => $progressive_mode ? 'progressive' : ($duration ? 'duration' : 'count')
            ];
            
            $integration_logger->log_test_start($config);
        }

        // Warm cache if specified
        if ($warm) {
            \WP_CLI::log("🧤 Warming cache...");

            // Warm all endpoints
            foreach ($endpoint_list as $endpoint_item) {
                $warm_result = $request_generator->fire_request($endpoint_item['url'], $log_path, $cookies, $method, $body);
                \WP_CLI::log("  Warmed {$endpoint_item['slug']}");
            }
        }

        // Run the load test
        $completed = 0;
        $current_ramp = $rampup ? 1 : $burst; // Start ramp-up at 1 concurrent request if enabled
        $endpoint_index = 0; // For serial rotation
        
        // Set up duration-based testing
        $start_time = time();
        $end_time = $duration ? $start_time + ($duration * 60) : null;
        $run_by_duration = ($duration !== null);
        
        // Keep running until we hit our target (count or time)
        while (true) {
            // Check if we should stop based on our exit condition
            if ($run_by_duration) {
                if (time() >= $end_time) {
                    break;
                }
            } else {
                if ($completed >= $count) {
                    break;
                }
            }
            
            // Monitor resources if enabled
            if ($resource_logging) {
                $resource_data = $resource_monitor->log_resource_utilization();
                
                // Log resource data to integration logger if enabled
                if ($monitoring_integration) {
                    $integration_logger->log_resource_snapshot($resource_data);
                }
            }

            // Calculate burst size
            if ($rampup) {
                $current_ramp = min($current_ramp + 1, $burst);
            }

            // For duration mode, always use the full burst
            // For count mode, limit by remaining count
            $current_burst = $run_by_duration 
                ? $current_ramp 
                : min($current_ramp, $burst, $count - $completed);
                
            \WP_CLI::log("⚡ Burst of $current_burst requests");

            // Flush cache if specified
            if ($flush) {
                \WP_CLI::log("♻️ Flushing cache before burst...");
                wp_cache_flush();
            }

            // Select URLs for this burst based on rotation mode
            $burst_urls = [];
            for ($i = 0; $i < $current_burst; $i++) {
                if ($rotation_mode === 'random') {
                    // Random selection
                    $selected = $endpoint_list[array_rand($endpoint_list)];
                } else {
                    // Serial rotation
                    $selected = $endpoint_list[$endpoint_index % count($endpoint_list)];
                    $endpoint_index++;
                }

                $burst_urls[] = $selected['url'];
            }

            // Fire requests
            $results = [];
            if (isset($assoc_args['concurrency-mode']) && $assoc_args['concurrency-mode'] === 'async') {
                // Async mode - group by URL for efficiency
                $url_groups = array_count_values($burst_urls);

                foreach ($url_groups as $url => $count) {
                    $batch_results = $request_generator->fire_requests_async(
                        $url, $log_path, $cookies, $count, $method, $body
                    );
                    $results = array_merge($results, $batch_results);
                }
            } else {
                // Serial mode
                foreach ($burst_urls as $url) {
                    $result = $request_generator->fire_request(
                        $url, $log_path, $cookies, $method, $body
                    );
                    $results[] = $result;
                }
            }

            // Add results to reporting engine
            $reporting_engine->add_results($results);
            
            // Log results to integration logger if enabled
            if ($monitoring_integration) {
                foreach ($results as $result) {
                    $integration_logger->log_request($result);
                }
            }

            // Process cache headers
            if ($collect_cache_headers) {
                $cache_headers = $request_generator->get_cache_headers();
                foreach ($cache_headers as $header => $values) {
                    foreach ($values as $value => $count) {
                        $cache_analyzer->collect_headers([$header => $value]);
                    }
                }
            }
            
            // Log burst completion to integration logger if enabled
            if ($monitoring_integration) {
                $burst_summary = [
                    'burst_size' => $current_burst,
                    'results' => $results,
                    'endpoints' => $burst_urls,
                    'completed_total' => $completed
                ];
                $integration_logger->log_burst_complete($completed / $current_burst, $current_burst, $burst_summary);
            }

            $completed += $current_burst;
            
            // Display elapsed time if running by duration
            if ($run_by_duration) {
                $elapsed_seconds = time() - $start_time;
                $elapsed_minutes = floor($elapsed_seconds / 60);
                $remaining_seconds = $elapsed_seconds % 60;
                $time_display = $elapsed_minutes . "m " . $remaining_seconds . "s";
                $percentage = min(round(($elapsed_seconds / ($duration * 60)) * 100), 100);
                \WP_CLI::log("⏲ Time elapsed: $time_display ($percentage% complete, $completed requests sent)");
            }

            // Determine if we should add delay
            $should_delay = $run_by_duration ? true : ($completed < $count);
            
            if ($should_delay) {
                $random_delay = rand($delay * 50, $delay * 150) / 100; // Random delay between 50% and 150% of base delay
                \WP_CLI::log("⏳ Sleeping for {$random_delay}s (randomized delay)");
                sleep($random_delay);
            }
        }

        // Handle baseline comparison if specified
        $compare_baseline = isset($assoc_args['compare-baseline']) ? $assoc_args['compare-baseline'] : null;
        $save_baseline = isset($assoc_args['save-baseline']) ? $assoc_args['save-baseline'] : null;
        
        // Default baseline name if provided without value
        if ($compare_baseline === null && isset($assoc_args['compare-baseline'])) {
            $compare_baseline = 'default';
        }
        
        if ($save_baseline === null && isset($assoc_args['save-baseline'])) {
            $save_baseline = 'default';
        }
        
        // Load baseline for comparison if specified
        $perf_baseline = $compare_baseline ? $reporting_engine->get_baseline($compare_baseline) : null;
        $resource_baseline = $compare_baseline && $resource_logging ? $resource_monitor->get_baseline($compare_baseline) : null;
        
        // Generate performance summary
        $summary = $reporting_engine->generate_summary();
        
        // Generate resource summary if enabled
        $resource_summary = null;
        if ($resource_logging) {
            $resource_summary = $resource_monitor->generate_summary();
        }
        
        // Auto-calibrate thresholds if requested
        if ($auto_thresholds) {
            \WP_CLI::log("🔍 Auto-calibrating thresholds based on this test run...");
            
            // Combine performance and resource data for calibration
            $calibration_data = $summary;
            if ($resource_summary) {
                $calibration_data['memory'] = $resource_summary['memory'];
                $calibration_data['peak_memory'] = $resource_summary['peak_memory'];
            }
            
            // Calibrate and save thresholds
            $thresholds = MicroChaos_Thresholds::calibrate_thresholds($calibration_data, $threshold_profile);
            
            \WP_CLI::log("✅ Custom thresholds calibrated and saved as profile: {$threshold_profile}");
            \WP_CLI::log("   Response time: Good <= {$thresholds['response_time']['good']}s | Warning <= {$thresholds['response_time']['warn']}s | Critical > {$thresholds['response_time']['critical']}s");
            if (isset($thresholds['memory_usage'])) {
                \WP_CLI::log("   Memory usage: Good <= {$thresholds['memory_usage']['good']}% | Warning <= {$thresholds['memory_usage']['warn']}% | Critical > {$thresholds['memory_usage']['critical']}%");
            }
            \WP_CLI::log("   Error rate: Good <= {$thresholds['error_rate']['good']}% | Warning <= {$thresholds['error_rate']['warn']}% | Critical > {$thresholds['error_rate']['critical']}%");
            
            // Use the newly calibrated thresholds for reporting
            $use_thresholds = $threshold_profile;
        }
        
        // Display reports with appropriate thresholds
        $reporting_engine->report_summary($perf_baseline, null, $use_thresholds);

        // Report resource utilization if enabled
        if ($resource_logging) {
            $resource_monitor->report_summary($resource_baseline, null, $use_thresholds);
        }
        
        // Save baseline if specified
        if ($save_baseline) {
            $reporting_engine->save_baseline($save_baseline);
            if ($resource_logging) {
                $resource_monitor->save_baseline($save_baseline);
            }
            \WP_CLI::success("✅ Baseline '{$save_baseline}' saved.");
        }

        // Report cache headers if enabled
        if ($collect_cache_headers) {
            $cache_analyzer->report_summary($reporting_engine->get_request_count());
        }
        
        // Log test completion to integration logger
        if ($monitoring_integration) {
            $summary = $reporting_engine->generate_summary();
            $resource_summary = $resource_logging ? $resource_monitor->generate_summary() : null;
            
            // Include cache summary if available
            if ($collect_cache_headers) {
                $cache_report = $cache_analyzer->generate_report($reporting_engine->get_request_count());
                $summary['cache'] = $cache_report;
            }
            
            $integration_logger->log_test_complete($summary, $resource_summary);
            \WP_CLI::log("🔌 Monitoring data logged to PHP error log (test ID: {$integration_logger->test_id})");
        }

        if ($progressive_mode) {
            // Switch to progressive load testing mode
            $this->run_progressive_test(
                $endpoint_list,
                $log_path,
                $cookies,
                $method,
                $body,
                $progressive_start,
                $progressive_step,
                $progressive_max,
                $threshold_response_time,
                $threshold_error_rate,
                $threshold_memory,
                $delay,
                $flush,
                $warm,
                $collect_cache_headers,
                $rotation_mode,
                $resource_logging,
                $monitoring_integration,
                $integration_logger
            );
        } elseif ($run_by_duration) {
            $total_minutes = $duration;
            $actual_seconds = time() - $start_time;
            $actual_minutes = round($actual_seconds / 60, 1);
            \WP_CLI::success("✅ Load test complete: $completed requests fired over $actual_minutes minutes.");
        } else {
            \WP_CLI::success("✅ Load test complete: $count requests fired.");
        }
    }
    
    /**
     * Run progressive load testing to find capacity limits
     *
     * @param array  $endpoint_list        List of endpoints to test
     * @param string $log_path             Path for logging
     * @param array  $cookies              Authentication cookies
     * @param string $method               HTTP method
     * @param string $body                 Request body
     * @param int    $progressive_start    Starting concurrency level
     * @param int    $progressive_step     Step size for increasing concurrency
     * @param int    $progressive_max      Maximum concurrency to test
     * @param float  $threshold_resp_time  Response time threshold in seconds
     * @param float  $threshold_error_rate Error rate threshold as percentage
     * @param float  $threshold_memory     Memory usage threshold as percentage
     * @param int    $delay                Delay between bursts
     * @param bool   $flush                Whether to flush cache between bursts
     * @param bool   $warm                 Whether to warm cache
     * @param bool   $collect_cache_headers Whether to collect cache headers
     * @param string $rotation_mode        Endpoint rotation mode
     * @param bool   $resource_logging     Whether to log resource usage
     * @param bool   $monitoring_integration Whether to log data for external monitoring
     * @param object $integration_logger   Integration logger instance
     */
    protected function run_progressive_test(
        $endpoint_list,
        $log_path,
        $cookies,
        $method,
        $body,
        $progressive_start,
        $progressive_step,
        $progressive_max,
        $threshold_resp_time,
        $threshold_error_rate,
        $threshold_memory,
        $delay,
        $flush,
        $warm,
        $collect_cache_headers,
        $rotation_mode,
        $resource_logging,
        $monitoring_integration = false,
        $integration_logger = null
    ) {
        // Initialize components
        $request_generator = new MicroChaos_Request_Generator([
            'collect_cache_headers' => $collect_cache_headers,
        ]);
        $resource_monitor = new MicroChaos_Resource_Monitor();
        $cache_analyzer = new MicroChaos_Cache_Analyzer();
        $reporting_engine = new MicroChaos_Reporting_Engine();
        
        \WP_CLI::log("🚀 MicroChaos Progressive Load Test Started");
        \WP_CLI::log("-> Testing capacity limits with progressive load increases");
        \WP_CLI::log("-> Starting at: $progressive_start concurrent requests");
        \WP_CLI::log("-> Step size: $progressive_step concurrent requests");
        \WP_CLI::log("-> Maximum: $progressive_max concurrent requests");
        \WP_CLI::log("-> Thresholds: Response time ${threshold_resp_time}s | Error rate ${threshold_error_rate}% | Memory ${threshold_memory}%");
        
        if (count($endpoint_list) === 1) {
            \WP_CLI::log("-> URL: {$endpoint_list[0]['url']}");
        } else {
            \WP_CLI::log("-> URLs: " . count($endpoint_list) . " endpoints (" . 
                          implode(', ', array_column($endpoint_list, 'slug')) . ") - Rotation mode: $rotation_mode");
        }
        
        // Warm cache if specified
        if ($warm) {
            \WP_CLI::log("🧤 Warming cache...");
            foreach ($endpoint_list as $endpoint_item) {
                $warm_result = $request_generator->fire_request($endpoint_item['url'], $log_path, $cookies, $method, $body);
                \WP_CLI::log("  Warmed {$endpoint_item['slug']}");
            }
        }
        
        // Track the breaking point metrics
        $breaking_point = null;
        $breaking_reason = null;
        $last_summary = null;
        $last_resource_summary = null;
        $total_requests = 0;
        $endpoint_index = 0; // For serial rotation
        
        // Run progressive load testing until thresholds are exceeded
        for ($concurrency = $progressive_start; $concurrency <= $progressive_max; $concurrency += $progressive_step) {
            \WP_CLI::log("\n📈 Testing concurrency level: $concurrency requests");
            
            // Reset per-iteration tracking
            $reporting_engine->reset_results();
            if ($resource_logging) {
                $resource_monitor = new MicroChaos_Resource_Monitor(); // Reset for this level
            }
            
            // Monitor resources if enabled
            if ($resource_logging) {
                $resource_data = $resource_monitor->log_resource_utilization();
            }
            
            // Flush cache if specified
            if ($flush) {
                \WP_CLI::log("♻️ Flushing cache before test level...");
                wp_cache_flush();
            }
            
            // Select URLs for this concurrency level based on rotation mode
            $burst_urls = [];
            for ($i = 0; $i < $concurrency; $i++) {
                if ($rotation_mode === 'random') {
                    // Random selection
                    $selected = $endpoint_list[array_rand($endpoint_list)];
                } else {
                    // Serial rotation
                    $selected = $endpoint_list[$endpoint_index % count($endpoint_list)];
                    $endpoint_index++;
                }
                
                $burst_urls[] = $selected['url'];
            }
            
            // Fire requests
            $results = [];
            // Always use async for progressive testing
            // Group by URL for efficiency
            $url_groups = array_count_values($burst_urls);
            
            foreach ($url_groups as $url => $count) {
                $batch_results = $request_generator->fire_requests_async(
                    $url, $log_path, $cookies, $count, $method, $body
                );
                $results = array_merge($results, $batch_results);
            }
            
            // Add results to reporting engine
            $reporting_engine->add_results($results);
            $total_requests += count($results);
            
            // Process cache headers
            if ($collect_cache_headers) {
                $cache_headers = $request_generator->get_cache_headers();
                foreach ($cache_headers as $header => $values) {
                    foreach ($values as $value => $count) {
                        $cache_analyzer->collect_headers([$header => $value]);
                    }
                }
            }
            
            // Generate summary for this concurrency level
            $summary = $reporting_engine->generate_summary();
            $last_summary = $summary;
            
            // Check if any thresholds have been exceeded
            $avg_response_time = $summary['timing']['avg'];
            $error_rate = $summary['error_rate'];
            
            // Get resource utilization if enabled
            $memory_percentage = null;
            if ($resource_logging) {
                $resource_summary = $resource_monitor->generate_summary();
                $last_resource_summary = $resource_summary;
                $memory_limit = MicroChaos_Thresholds::get_php_memory_limit_mb();
                $memory_percentage = ($resource_summary['memory']['max'] / $memory_limit) * 100;
            }
            
            // Log progressive level to integration logger if enabled
            if ($monitoring_integration && $integration_logger) {
                $level_data = [
                    'concurrency' => $concurrency,
                    'timing' => $summary['timing'],
                    'error_rate' => $error_rate
                ];
                
                if ($resource_logging) {
                    $level_data['resource'] = $resource_summary;
                    $level_data['memory_percentage'] = $memory_percentage;
                }
                
                $integration_logger->log_progressive_level($concurrency, $level_data);
            }
            
            // Report summary for this level
            \WP_CLI::log("Results at concurrency $concurrency:");
            \WP_CLI::log("  Avg response: " . MicroChaos_Thresholds::format_value($avg_response_time, 'response_time') . 
                         " | Error rate: " . MicroChaos_Thresholds::format_value($error_rate, 'error_rate'));
            
            if ($resource_logging) {
                \WP_CLI::log("  Max memory: " . MicroChaos_Thresholds::format_value($resource_summary['memory']['max'], 'memory_usage') . 
                             " (" . round($memory_percentage, 1) . "% of limit)");
            }
            
            // Check thresholds
            if ($avg_response_time > $threshold_resp_time) {
                $breaking_point = $concurrency;
                $breaking_reason = "Response time threshold exceeded ({$avg_response_time}s > {$threshold_resp_time}s)";
                break;
            }
            
            if ($error_rate > $threshold_error_rate) {
                $breaking_point = $concurrency;
                $breaking_reason = "Error rate threshold exceeded ({$error_rate}% > {$threshold_error_rate}%)";                
                break;
            }
            
            if ($resource_logging && $memory_percentage > $threshold_memory) {
                $breaking_point = $concurrency;
                $breaking_reason = "Memory usage threshold exceeded ({$memory_percentage}% > {$threshold_memory}%)";                
                break;
            }
            
            // Add delay before next level
            if ($concurrency + $progressive_step <= $progressive_max) {
                $random_delay = rand($delay * 50, $delay * 150) / 100; // Random delay between 50% and 150% of base delay
                \WP_CLI::log("⏳ Sleeping for {$random_delay}s before next level");
                sleep($random_delay);
            }
        }
        
        // Final report
        \WP_CLI::log("\n📊 Progressive Load Test Results:");
        \WP_CLI::log("   Total Requests Fired: $total_requests");
        
        if ($breaking_point) {
            \WP_CLI::log("   💥 Breaking Point: $breaking_point concurrent requests");
            \WP_CLI::log("   💥 Reason: $breaking_reason");
            
            // Calculate safe capacity (80% of breaking point as a conservative estimate)
            $safe_capacity = max(1, floor($breaking_point * 0.8));
            \WP_CLI::log("   ✓ Recommended Maximum Capacity: $safe_capacity concurrent requests");
        } else {
            \WP_CLI::log("   ✓ No breaking point found up to $progressive_max concurrent requests");
            \WP_CLI::log("   ✓ The site can handle at least $progressive_max concurrent requests");
        }
        
        // Show full summary of the last completed level
        if ($last_summary) {
            \WP_CLI::log("\n📈 Final Level Performance:");
            $reporting_engine->report_summary(null, $last_summary);
            
            if ($resource_logging && $last_resource_summary) {
                $resource_monitor->report_summary(null, $last_resource_summary);
            }
            
            if ($collect_cache_headers) {
                $cache_analyzer->report_summary($reporting_engine->get_request_count());
            }
        }
        
        // Log test completion to integration logger if enabled
        if ($monitoring_integration && $integration_logger && $last_summary) {
            $final_data = [
                'progressive_result' => [
                    'breaking_point' => $breaking_point,
                    'breaking_reason' => $breaking_reason,
                    'total_requests' => $total_requests,
                    'max_tested' => $breaking_point ?: $progressive_max,
                    'recommended_capacity' => $breaking_point ? max(1, floor($breaking_point * 0.8)) : $progressive_max
                ],
                'summary' => $last_summary
            ];
            
            if ($resource_logging && $last_resource_summary) {
                $final_data['resource_summary'] = $last_resource_summary;
            }
            
            $integration_logger->log_test_complete($final_data);
            \WP_CLI::log("🔌 Monitoring data logged to PHP error log (test ID: {$integration_logger->test_id})");
        }
        
        if ($breaking_point) {
            \WP_CLI::success("✅ Progressive load test complete: Breaking point identified at $breaking_point concurrent requests.");
        } else {
            \WP_CLI::success("✅ Progressive load test complete: No breaking point found up to $progressive_max concurrent requests.");
        }
    }
}

    // Register the MicroChaos WP-CLI command
    WP_CLI::add_command('microchaos', 'MicroChaos_Commands');
}
