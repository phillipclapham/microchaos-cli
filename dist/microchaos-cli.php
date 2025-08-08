<?php
/**
 * Plugin Name: MicroChaos CLI Load Tester
 * Description: Internal WP-CLI based WordPress load tester for staging environments where
 * external load testing is restricted (like Pressable).
 * Version: 2.0.0
 * Author: Phill
 */

// Bootstrap MicroChaos components

/**
 * COMPILED SINGLE-FILE VERSION
 * Generated on: 2025-08-08T14:53:02.656Z
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

class MicroChaos_ParallelTest {
    /**
     * Test plans storage
     *
     * @var array
     */
    private $test_plans = [];

    /**
     * Number of parallel workers
     *
     * @var int
     */
    private $workers = 3;

    /**
     * Output format
     *
     * @var string
     */
    private $output_format = 'table';

    /**
     * Results collection
     *
     * @var array
     */
    private $results = [];
    
    /**
     * Worker process IDs
     * 
     * @var array
     */
    private $worker_pids = [];
    
    /**
     * Job queue for distribution
     * 
     * @var array
     */
    private $job_queue = [];
    
    /**
     * Results storage path
     * 
     * @var string
     */
    private $temp_dir;
    
    /**
     * Is parallel execution supported
     * 
     * @var bool
     */
    private $parallel_supported = false;

    /**
     * Results summary by test plan
     *
     * @var array
     */
    private $results_summary = [];

    /**
     * Global test execution timeout (seconds)
     *
     * @var int
     */
    private $global_timeout = 600; // 10 minutes default
    
    /**
     * Run parallel load tests using multiple workers.
     *
     * ## DESCRIPTION
     *
     * Runs multiple load tests in parallel using a JSON test plan configuration.
     * This allows simulating more realistic mixed traffic patterns, such as anonymous users
     * browsing products while logged-in users checkout simultaneously.
     *
     * The test plan can be provided either as a direct JSON string or as a path to a JSON file.
     * 
     * ## OPTIONS
     *
     * [--file=<path>]
     * : Path to a JSON file containing test plan(s)
     *
     * [--plan=<json>]
     * : JSON string containing test plan(s) directly in the command
     *
     * [--workers=<number>]
     * : Number of parallel workers to use. Default: 3
     *
     * [--output=<format>]
     * : Output format. Options: json, table, csv. Default: table
     * 
     * [--timeout=<seconds>]
     * : Global timeout for test execution in seconds. Default: 600 (10 minutes)
     * 
     * [--export=<path>]
     * : Export results to specified file path (relative to wp-content directory)
     * 
     * [--export-format=<format>]
     * : Format for exporting results. Options: json, csv. Default: json
     * 
     * [--export-detail=<level>]
     * : Detail level for exported results. Options: summary, full. Default: summary
     * 
     * [--percentiles=<list>]
     * : Comma-separated list of percentiles to calculate (e.g., 90,95,99). Default: 95,99
     * 
     * [--baseline=<name>]
     * : Compare results with a previously saved baseline
     * 
     * [--save-baseline=<name>]
     * : Save current results as a baseline for future comparisons
     * 
     * [--callback-url=<url>]
     * : Send test results to this URL upon completion (HTTP POST)
     *
     * ## EXAMPLES
     *
     *     # Run parallel tests defined in a JSON file
     *     wp microchaos paralleltest --file=test-plans.json
     *
     *     # Run parallel tests with a JSON string
     *     wp microchaos paralleltest --plan='[{"name":"Homepage Test","endpoint":"home","requests":50},{"name":"Checkout Test","endpoint":"checkout","requests":25,"auth":"user@example.com"}]'
     *
     *     # Run parallel tests with 5 workers
     *     wp microchaos paralleltest --file=test-plans.json --workers=5
     *
     *     # Run parallel tests and output results as JSON
     *     wp microchaos paralleltest --file=test-plans.json --output=json
     *     
     *     # Run parallel tests with a 5-minute timeout
     *     wp microchaos paralleltest --file=test-plans.json --timeout=300
     *     
     *     # Run parallel tests and export results to a file
     *     wp microchaos paralleltest --file=test-plans.json --export=microchaos/results.json
     *     
     *     # Run tests and export results with detailed information
     *     wp microchaos paralleltest --file=test-plans.json --export=results.csv --export-format=csv --export-detail=full
     *     
     *     # Calculate additional percentiles and include them in results
     *     wp microchaos paralleltest --file=test-plans.json --percentiles=50,75,90,95,99
     *     
     *     # Save results as baseline and compare with previous baseline
     *     wp microchaos paralleltest --file=test-plans.json --save-baseline=api-test
     *     wp microchaos paralleltest --file=test-plans.json --baseline=api-test
     *
     * @param array $args Command arguments
     * @param array $assoc_args Command options
     */
    public function run($args, $assoc_args) {
        // Parse command options
        $file_path = $assoc_args['file'] ?? null;
        $json_plan = $assoc_args['plan'] ?? null;
        $this->workers = intval($assoc_args['workers'] ?? 3);
        $this->output_format = $assoc_args['output'] ?? 'table';
        $this->global_timeout = intval($assoc_args['timeout'] ?? 600);
        $export_path = $assoc_args['export'] ?? null;
        $export_format = $assoc_args['export-format'] ?? 'json';
        $export_detail = $assoc_args['export-detail'] ?? 'summary';
        $percentiles = isset($assoc_args['percentiles']) ? 
            array_map('intval', explode(',', $assoc_args['percentiles'])) : 
            [95, 99];
        $baseline = $assoc_args['baseline'] ?? null;
        $save_baseline = $assoc_args['save-baseline'] ?? null;
        $callback_url = $assoc_args['callback-url'] ?? null;

        // Validate input parameters
        if (!$file_path && !$json_plan) {
            \WP_CLI::error("You must provide either --file or --plan parameter.");
        }

        // Parse test plans
        if ($file_path) {
            $this->load_test_plans_from_file($file_path);
        } elseif ($json_plan) {
            $this->parse_test_plans_json($json_plan);
        }

        // Check if we have valid test plans
        if (empty($this->test_plans)) {
            \WP_CLI::error("No valid test plans found. Please check your input.");
        }

        // Setup signal handling for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handle_signal']);
            pcntl_signal(SIGTERM, [$this, 'handle_signal']);
        }

        \WP_CLI::log("🚀 MicroChaos Parallel Test Started");
        \WP_CLI::log("-> Test Plans: " . count($this->test_plans));
        \WP_CLI::log("-> Workers: " . $this->workers);
        \WP_CLI::log("-> Timeout: " . $this->global_timeout . " seconds");
        \WP_CLI::log("-> Output Format: " . $this->output_format);
        if ($export_path) {
            \WP_CLI::log("-> Export: " . $export_path . " (Format: " . $export_format . ", Detail: " . $export_detail . ")");
        }
        if ($percentiles) {
            \WP_CLI::log("-> Percentiles: " . implode(', ', $percentiles));
        }
        if ($baseline) {
            \WP_CLI::log("-> Comparing with baseline: " . $baseline);
        }
        if ($save_baseline) {
            \WP_CLI::log("-> Will save results as baseline: " . $save_baseline);
        }
        if ($callback_url) {
            \WP_CLI::log("-> Results will be sent to: " . $callback_url);
        }

        // Display test plan summary
        $this->display_test_plan_summary();
        
        // Check if parallel execution is supported (pcntl extension)
        $this->parallel_supported = extension_loaded('pcntl');
        if (!$this->parallel_supported) {
            \WP_CLI::warning("📢 Parallel execution not supported on this system (pcntl extension not available).");
            \WP_CLI::log("-> Falling back to sequential execution with simulated parallelism.");
        } else {
            \WP_CLI::log("-> Parallel execution enabled with {$this->workers} workers.");
        }
        
        // Create temp directory for inter-process communication
        $this->setup_temp_directory();
        
        // Prepare job queue
        $this->prepare_job_queue();
        
        // Setup integration logger for metrics
        $logger = new MicroChaos_Integration_Logger(['enabled' => true]);
        $logger->log_test_start([
            'test_plans' => count($this->test_plans),
            'workers' => $this->workers,
            'output_format' => $this->output_format,
            'parallel_mode' => $this->parallel_supported ? 'parallel' : 'sequential',
            'job_count' => count($this->job_queue)
        ]);
        
        // Start resource monitoring
        $resource_monitor = new MicroChaos_Resource_Monitor(['track_trends' => true]);
        $resource_monitor->log_resource_utilization();
        
        // Launch workers and execute tests
        $this->execute_tests();
        
        // Final resource checkpoint
        $resource_monitor->log_resource_utilization();
        $resource_summary = $resource_monitor->generate_summary();
        $resource_monitor->report_summary(null, $resource_summary);
        
        // If trend tracking is enabled, report trends
        if (count($resource_monitor->get_resource_results()) >= 3) {
            $resource_monitor->report_trends();
        }
        
        // Cleanup temp files and analyze results
        $this->cleanup_temp_files();
        
        // Calculate additional percentiles if specified
        if (!empty($percentiles)) {
            $this->calculate_percentiles($percentiles);
        }
        
        // Load baseline for comparison if specified
        $baseline_data = null;
        if ($baseline) {
            $baseline_data = $this->load_baseline($baseline);
        }
        
        // Format and display results based on output format
        $this->output_formatted_results($baseline_data);
        
        // Save as baseline if requested
        if ($save_baseline) {
            $this->save_baseline($save_baseline);
        }
        
        // Export results if requested
        if ($export_path) {
            $this->export_results($export_path, $export_format, $export_detail);
        }
        
        // Send results to callback URL if specified
        if ($callback_url) {
            $this->send_results_to_callback($callback_url);
        }
        
        // Log test completion to integration logger
        $logger->log_test_complete(
            $this->results_summary['overall'] ?? [], 
            $resource_summary
        );
        
        \WP_CLI::success("🎉 Parallel Test Execution Complete");
    }
    
    /**
     * Handle SIGINT/SIGTERM signals for graceful shutdown
     * 
     * @param int $signo Signal number
     */
    public function handle_signal($signo) {
        // Only handle these signals in the parent process
        if (!empty($this->worker_pids)) {
            \WP_CLI::log("\n⚠️ Received termination signal. Shutting down workers...");
            
            // Terminate all child processes
            foreach ($this->worker_pids as $pid) {
                posix_kill($pid, SIGTERM);
            }
            
            // Wait for all processes to terminate
            foreach ($this->worker_pids as $pid) {
                pcntl_waitpid($pid, $status);
            }
            
            // Delete temp directory
            $this->delete_temp_directory();
            
            \WP_CLI::log("💤 Parallel test execution terminated by user.");
            exit(1);
        }
    }
    
    /**
     * Export results to a file in the specified format
     * 
     * @param string $export_path Path to export file (relative to wp-content)
     * @param string $format Export format (json, csv)
     * @param string $detail_level Detail level (summary, full)
     * @return bool Success status
     */
    private function export_results($export_path, $format = 'json', $detail_level = 'summary') {
        if (!in_array($format, ['json', 'csv'])) {
            \WP_CLI::warning("Unsupported export format '{$format}', defaulting to json");
            $format = 'json';
        }
        
        if (!in_array($detail_level, ['summary', 'full'])) {
            \WP_CLI::warning("Unsupported detail level '{$detail_level}', defaulting to summary");
            $detail_level = 'summary';
        }
        
        // Create reporting engine for export
        $reporting_engine = new MicroChaos_Reporting_Engine();
        $reporting_engine->add_results($this->results);
        
        // Format output path
        $full_export_path = trailingslashit(WP_CONTENT_DIR) . ltrim($export_path, '/');
        $dir = dirname($full_export_path);
        
        // Ensure directory exists
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Export based on format and detail level
        if ($format === 'json') {
            if ($detail_level === 'summary') {
                $data = json_encode([
                    'meta' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'test_plans' => count($this->test_plans),
                        'workers' => $this->workers,
                        'total_requests' => count($this->results)
                    ],
                    'summary' => $this->results_summary,
                ], JSON_PRETTY_PRINT);
            } else {
                $data = json_encode([
                    'meta' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'test_plans' => count($this->test_plans),
                        'workers' => $this->workers,
                        'total_requests' => count($this->results)
                    ],
                    'summary' => $this->results_summary,
                    'results' => $this->results,
                    'config' => [
                        'test_plans' => $this->test_plans,
                        'job_count' => count($this->job_queue),
                        'parallel_supported' => $this->parallel_supported
                    ]
                ], JSON_PRETTY_PRINT);
            }
            
            $success = (bool) file_put_contents($full_export_path, $data);
        } else {
            // CSV format
            $fp = fopen($full_export_path, 'w');
            if (!$fp) {
                \WP_CLI::error("Could not open file for writing: " . $full_export_path);
                return false;
            }
            
            if ($detail_level === 'summary') {
                // Write summary CSV
                // Headers
                fputcsv($fp, ['Test Plan', 'Requests', 'Success', 'Errors', 'Error Rate', 'Avg Time', 'Median Time', 'Min Time', 'Max Time']);
                
                // Add overall results first
                if (isset($this->results_summary['overall'])) {
                    $overall = $this->results_summary['overall'];
                    fputcsv($fp, [
                        'OVERALL',
                        $overall['count'],
                        $overall['success'],
                        $overall['errors'],
                        $overall['error_rate'] . '%',
                        $overall['timing']['avg'],
                        $overall['timing']['median'],
                        $overall['timing']['min'],
                        $overall['timing']['max']
                    ]);
                }
                
                // Add individual test plan results
                foreach ($this->results_summary as $plan_name => $summary) {
                    if ($plan_name === 'overall') continue;
                    
                    fputcsv($fp, [
                        $plan_name,
                        $summary['count'],
                        $summary['success'],
                        $summary['errors'],
                        $summary['error_rate'] . '%',
                        $summary['timing']['avg'],
                        $summary['timing']['median'],
                        $summary['timing']['min'],
                        $summary['timing']['max']
                    ]);
                }
            } else {
                // Detailed CSV with all results
                // Headers for detailed results
                $headers = ['Job ID', 'Plan Name', 'Worker ID', 'Timestamp', 'URL', 'Status Code', 'Response Time'];
                
                // Add percentile headers if available
                if (isset($this->results_summary['overall']['percentiles'])) {
                    foreach ($this->results_summary['overall']['percentiles'] as $percentile => $value) {
                        $headers[] = "P{$percentile}";
                    }
                }
                
                fputcsv($fp, $headers);
                
                // Add detailed results
                foreach ($this->results as $result) {
                    $row = [
                        $result['job_id'] ?? 'N/A',
                        $result['plan_name'] ?? 'N/A',
                        $result['worker_id'] ?? 'N/A',
                        date('Y-m-d H:i:s', $result['timestamp'] ?? time()),
                        $result['url'] ?? 'N/A',
                        $result['code'] ?? 0,
                        $result['time'] ?? 0
                    ];
                    
                    fputcsv($fp, $row);
                }
            }
            
            fclose($fp);
            $success = true;
        }
        
        if ($success) {
            \WP_CLI::success("Results exported to " . $full_export_path);
        } else {
            \WP_CLI::error("Failed to export results to " . $full_export_path);
        }
        
        return $success;
    }
    
    /**
     * Calculate percentiles for response times
     * 
     * @param array $percentiles Array of percentiles to calculate (e.g., [50, 90, 95, 99])
     */
    private function calculate_percentiles($percentiles) {
        if (empty($this->results)) {
            return;
        }
        
        \WP_CLI::log("📊 Calculating response time percentiles: " . implode(', ', $percentiles));
        
        // Group results by test plan
        $results_by_plan = [];
        foreach ($this->results as $result) {
            if (!isset($result['plan_name'])) {
                continue;
            }
            
            $plan_name = $result['plan_name'];
            if (!isset($results_by_plan[$plan_name])) {
                $results_by_plan[$plan_name] = [];
            }
            
            $results_by_plan[$plan_name][] = $result;
        }
        
        // Calculate percentiles for each test plan
        foreach ($results_by_plan as $plan_name => $plan_results) {
            $times = array_column($plan_results, 'time');
            sort($times);
            
            $plan_percentiles = [];
            foreach ($percentiles as $percentile) {
                $index = ceil(($percentile / 100) * count($times)) - 1;
                if ($index < 0) $index = 0;
                $plan_percentiles[$percentile] = round($times[$index], 4);
            }
            
            // Add percentiles to results summary
            if (isset($this->results_summary[$plan_name])) {
                $this->results_summary[$plan_name]['percentiles'] = $plan_percentiles;
            }
        }
        
        // Calculate overall percentiles
        $all_times = array_column($this->results, 'time');
        sort($all_times);
        
        $overall_percentiles = [];
        foreach ($percentiles as $percentile) {
            $index = ceil(($percentile / 100) * count($all_times)) - 1;
            if ($index < 0) $index = 0;
            $overall_percentiles[$percentile] = round($all_times[$index], 4);
        }
        
        // Add overall percentiles
        if (isset($this->results_summary['overall'])) {
            $this->results_summary['overall']['percentiles'] = $overall_percentiles;
        }
    }
    
    /**
     * Save current results as a baseline for future comparison
     * 
     * @param string $name Baseline name
     * @return bool Success status
     */
    private function save_baseline($name) {
        if (empty($this->results_summary)) {
            \WP_CLI::warning("No results to save as baseline.");
            return false;
        }
        
        $baseline_data = [
            'timestamp' => time(),
            'summary' => $this->results_summary,
            'metadata' => [
                'test_plans' => count($this->test_plans),
                'workers' => $this->workers,
                'request_count' => count($this->results)
            ]
        ];
        
        if (function_exists('set_transient')) {
            $transient_name = 'microchaos_paralleltest_baseline_' . sanitize_key($name);
            $result = set_transient($transient_name, $baseline_data, 60 * 60 * 24 * 30); // 30 days
            
            if ($result) {
                \WP_CLI::success("✅ Baseline '{$name}' saved successfully.");
                return true;
            } else {
                \WP_CLI::warning("Failed to save baseline '{$name}'.");
                return false;
            }
        } else {
            // Fallback to file-based storage if transients are not available
            $baseline_dir = WP_CONTENT_DIR . '/microchaos/baselines';
            if (!file_exists($baseline_dir)) {
                mkdir($baseline_dir, 0755, true);
            }
            
            $baseline_file = $baseline_dir . '/' . sanitize_file_name($name) . '.json';
            $result = file_put_contents($baseline_file, json_encode($baseline_data, JSON_PRETTY_PRINT));
            
            if ($result) {
                \WP_CLI::success("✅ Baseline '{$name}' saved to file: {$baseline_file}");
                return true;
            } else {
                \WP_CLI::warning("Failed to save baseline '{$name}' to file.");
                return false;
            }
        }
    }
    
    /**
     * Load a previously saved baseline
     * 
     * @param string $name Baseline name
     * @return array|null Baseline data or null if not found
     */
    private function load_baseline($name) {
        $baseline_data = null;
        
        if (function_exists('get_transient')) {
            $transient_name = 'microchaos_paralleltest_baseline_' . sanitize_key($name);
            $baseline_data = get_transient($transient_name);
        }
        
        if ($baseline_data === false) {
            // Try file-based storage
            $baseline_file = WP_CONTENT_DIR . '/microchaos/baselines/' . sanitize_file_name($name) . '.json';
            if (file_exists($baseline_file)) {
                $file_content = file_get_contents($baseline_file);
                $baseline_data = json_decode($file_content, true);
            }
        }
        
        if ($baseline_data) {
            \WP_CLI::log("📋 Loaded baseline '{$name}' from " . date('Y-m-d H:i:s', $baseline_data['timestamp']));
            return $baseline_data;
        } else {
            \WP_CLI::warning("⚠️ Baseline '{$name}' not found.");
            return null;
        }
    }
    
    /**
     * Send results to a callback URL
     * 
     * @param string $url Callback URL
     * @return bool Success status
     */
    private function send_results_to_callback($url) {
        if (empty($this->results_summary)) {
            \WP_CLI::warning("No results to send to callback URL.");
            return false;
        }
        
        $payload = [
            'timestamp' => time(),
            'summary' => $this->results_summary,
            'metadata' => [
                'test_plans' => count($this->test_plans),
                'workers' => $this->workers,
                'parallel_mode' => $this->parallel_supported ? 'parallel' : 'sequential',
                'request_count' => count($this->results)
            ]
        ];
        
        $args = [
            'body' => json_encode($payload),
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ];
        
        \WP_CLI::log("📡 Sending results to callback URL: {$url}");
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            \WP_CLI::warning("⚠️ Failed to send results to callback URL: " . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code >= 200 && $response_code < 300) {
            \WP_CLI::success("✅ Results sent successfully to callback URL.");
            return true;
        } else {
            \WP_CLI::warning("⚠️ Callback URL returned error code: {$response_code}");
            return false;
        }
    }
    
    /**
     * Output formatted results based on the specified output format
     * 
     * @param array|null $baseline_data Optional baseline data for comparison
     */
    private function output_formatted_results($baseline_data = null) {
        if (empty($this->results_summary)) {
            \WP_CLI::warning("No results to output.");
            return;
        }
        
        switch ($this->output_format) {
            case 'json':
                $this->output_json_results($baseline_data);
                break;
                
            case 'csv':
                $this->output_csv_results();
                break;
                
            case 'table':
            default:
                $this->output_table_results($baseline_data);
                break;
        }
    }
    
    /**
     * Output results in JSON format
     * 
     * @param array|null $baseline_data Optional baseline data for comparison
     */
    private function output_json_results($baseline_data = null) {
        $output = [
            'timestamp' => time(),
            'summary' => $this->results_summary,
            'metadata' => [
                'test_plans' => count($this->test_plans),
                'workers' => $this->workers,
                'parallel_mode' => $this->parallel_supported ? 'parallel' : 'sequential',
                'request_count' => count($this->results)
            ]
        ];
        
        if ($baseline_data) {
            $output['baseline_comparison'] = $this->generate_baseline_comparison($baseline_data);
        }
        
        \WP_CLI::log(json_encode($output, JSON_PRETTY_PRINT));
    }
    
    /**
     * Output results in CSV format
     */
    private function output_csv_results() {
        $output = "Test Plan,Requests,Success,Errors,Error Rate,Avg Time,Median Time,Min Time,Max Time\n";
        
        // Add overall results first
        if (isset($this->results_summary['overall'])) {
            $overall = $this->results_summary['overall'];
            $output .= sprintf(
                "OVERALL,%d,%d,%d,%.1f%%,%.4fs,%.4fs,%.4fs,%.4fs\n",
                $overall['count'],
                $overall['success'],
                $overall['errors'],
                $overall['error_rate'],
                $overall['timing']['avg'],
                $overall['timing']['median'],
                $overall['timing']['min'],
                $overall['timing']['max']
            );
        }
        
        // Add individual test plan results
        foreach ($this->results_summary as $plan_name => $summary) {
            if ($plan_name === 'overall') continue;
            
            $output .= sprintf(
                "%s,%d,%d,%d,%.1f%%,%.4fs,%.4fs,%.4fs,%.4fs\n",
                $plan_name,
                $summary['count'],
                $summary['success'],
                $summary['errors'],
                $summary['error_rate'],
                $summary['timing']['avg'],
                $summary['timing']['median'],
                $summary['timing']['min'],
                $summary['timing']['max']
            );
        }
        
        // Add percentiles if available
        if (isset($this->results_summary['overall']['percentiles'])) {
            $output .= "\nResponse Time Percentiles:\n";
            $output .= "Test Plan";
            
            // Get all unique percentiles
            $all_percentiles = [];
            foreach ($this->results_summary as $plan_name => $summary) {
                if (isset($summary['percentiles'])) {
                    foreach ($summary['percentiles'] as $percentile => $value) {
                        $all_percentiles[$percentile] = true;
                    }
                }
            }
            
            // Sort percentiles
            $all_percentiles = array_keys($all_percentiles);
            sort($all_percentiles);
            
            // Add percentile headers
            foreach ($all_percentiles as $percentile) {
                $output .= ",P{$percentile}";
            }
            $output .= "\n";
            
            // Add percentiles for each test plan
            foreach ($this->results_summary as $plan_name => $summary) {
                if (!isset($summary['percentiles'])) continue;
                
                $output .= $plan_name === 'overall' ? "OVERALL" : $plan_name;
                
                foreach ($all_percentiles as $percentile) {
                    $value = $summary['percentiles'][$percentile] ?? 'N/A';
                    $output .= ",{$value}";
                }
                
                $output .= "\n";
            }
        }
        
        \WP_CLI::log($output);
    }
    
    /**
     * Output results in table format (default)
     * 
     * @param array|null $baseline_data Optional baseline data for comparison
     */
    private function output_table_results($baseline_data = null) {
        \WP_CLI::log("\n📊 Test Results Summary:");
        
        // Output header
        \WP_CLI::log("┌───────────────────────────────────────────────────────────────────────────┐");
        \WP_CLI::log("│ Test Results                                                              │");
        \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        
        // Add overall results first
        if (isset($this->results_summary['overall'])) {
            $overall = $this->results_summary['overall'];
            
            $error_rate_formatted = MicroChaos_Thresholds::format_value($overall['error_rate'], 'error_rate');
            $avg_time_formatted = MicroChaos_Thresholds::format_value($overall['timing']['avg'], 'response_time');
            $median_time_formatted = MicroChaos_Thresholds::format_value($overall['timing']['median'], 'response_time');
            $max_time_formatted = MicroChaos_Thresholds::format_value($overall['timing']['max'], 'response_time');
            
            \WP_CLI::log("│ \033[1mOVERALL SUMMARY\033[0m                                                         │");
            \WP_CLI::log("│ Total Requests: {$overall['count']} | Success: {$overall['success']} | Errors: {$overall['errors']} | Error Rate: {$error_rate_formatted}    │");
            \WP_CLI::log("│ Avg Time: {$avg_time_formatted} | Median: {$median_time_formatted} | Min: {$overall['timing']['min']}s | Max: {$max_time_formatted}         │");
            
            // Add percentiles if available
            if (isset($overall['percentiles'])) {
                $percentile_line = "│ Percentiles: ";
                
                // Sort percentiles for consistent display
                $percentiles = $overall['percentiles'];
                ksort($percentiles);
                
                $percentile_values = [];
                foreach ($percentiles as $percentile => $value) {
                    $percentile_values[] = "P{$percentile}: " . MicroChaos_Thresholds::format_value($value, 'response_time');
                }
                
                $percentile_line .= implode(" | ", $percentile_values);
                $percentile_line .= str_repeat(" ", max(0, 63 - strlen(strip_tags(implode(" | ", $percentile_values)))));
                $percentile_line .= "│";
                
                \WP_CLI::log($percentile_line);
            }
            
            \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        }
        
        // Add individual test plan results
        \WP_CLI::log("│ \033[1mRESULTS BY TEST PLAN\033[0m                                                     │");
        
        foreach ($this->results_summary as $plan_name => $summary) {
            if ($plan_name === 'overall') continue;
            
            $error_rate_formatted = MicroChaos_Thresholds::format_value($summary['error_rate'], 'error_rate');
            $avg_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['avg'], 'response_time');
            
            $plan_name_short = strlen($plan_name) > 40 ? substr($plan_name, 0, 37) . '...' : $plan_name;
            $plan_name_padded = str_pad($plan_name_short, 40);
            
            \WP_CLI::log("│ {$plan_name_padded} │");
            \WP_CLI::log("│   Requests: {$summary['count']} | Success: {$summary['success']} | Errors: {$summary['errors']} | Error Rate: {$error_rate_formatted}    │");
            \WP_CLI::log("│   Avg Time: {$avg_time_formatted} | Median: {$summary['timing']['median']}s | Min: {$summary['timing']['min']}s | Max: {$summary['timing']['max']}s    │");
            
            // Check thresholds if defined in the test plan
            $this->check_thresholds($plan_name, $summary);
            
            // Add percentiles if available
            if (isset($summary['percentiles'])) {
                $percentile_line = "│   Percentiles: ";
                
                // Sort percentiles for consistent display
                $percentiles = $summary['percentiles'];
                ksort($percentiles);
                
                $percentile_values = [];
                foreach ($percentiles as $percentile => $value) {
                    $percentile_values[] = "P{$percentile}: {$value}s";
                }
                
                $percentile_line .= implode(" | ", $percentile_values);
                $percentile_line .= str_repeat(" ", max(0, 63 - strlen($percentile_line)));
                $percentile_line .= "│";
                
                \WP_CLI::log($percentile_line);
            }
            
            \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        }
        
        // Add baseline comparison if available
        if ($baseline_data) {
            $this->output_baseline_comparison($baseline_data);
        }
        
        // Display an ASCII chart of response time distribution
        $this->output_response_time_chart();
        
        \WP_CLI::log("└───────────────────────────────────────────────────────────────────────────┘");
    }
    
    /**
     * Generate a comparison between current results and a baseline
     * 
     * @param array $baseline_data Baseline data
     * @return array Comparison data
     */
    private function generate_baseline_comparison($baseline_data) {
        if (!isset($baseline_data['summary']) || !isset($this->results_summary)) {
            return [];
        }
        
        $comparison = [
            'timestamp' => [
                'current' => time(),
                'baseline' => $baseline_data['timestamp']
            ]
        ];
        
        // Compare overall results
        if (isset($baseline_data['summary']['overall']) && isset($this->results_summary['overall'])) {
            $baseline_overall = $baseline_data['summary']['overall'];
            $current_overall = $this->results_summary['overall'];
            
            $comparison['overall'] = [
                'request_count' => [
                    'baseline' => $baseline_overall['count'],
                    'current' => $current_overall['count'],
                    'difference' => $current_overall['count'] - $baseline_overall['count'],
                    'percent_change' => $baseline_overall['count'] > 0 ? 
                        round((($current_overall['count'] - $baseline_overall['count']) / $baseline_overall['count']) * 100, 1) : 
                        0
                ],
                'error_rate' => [
                    'baseline' => $baseline_overall['error_rate'],
                    'current' => $current_overall['error_rate'],
                    'difference' => $current_overall['error_rate'] - $baseline_overall['error_rate'],
                    'percent_change' => $baseline_overall['error_rate'] > 0 ? 
                        round((($current_overall['error_rate'] - $baseline_overall['error_rate']) / $baseline_overall['error_rate']) * 100, 1) : 
                        0
                ],
                'avg_time' => [
                    'baseline' => $baseline_overall['timing']['avg'],
                    'current' => $current_overall['timing']['avg'],
                    'difference' => $current_overall['timing']['avg'] - $baseline_overall['timing']['avg'],
                    'percent_change' => $baseline_overall['timing']['avg'] > 0 ? 
                        round((($current_overall['timing']['avg'] - $baseline_overall['timing']['avg']) / $baseline_overall['timing']['avg']) * 100, 1) : 
                        0
                ],
                'median_time' => [
                    'baseline' => $baseline_overall['timing']['median'],
                    'current' => $current_overall['timing']['median'],
                    'difference' => $current_overall['timing']['median'] - $baseline_overall['timing']['median'],
                    'percent_change' => $baseline_overall['timing']['median'] > 0 ? 
                        round((($current_overall['timing']['median'] - $baseline_overall['timing']['median']) / $baseline_overall['timing']['median']) * 100, 1) : 
                        0
                ],
                'max_time' => [
                    'baseline' => $baseline_overall['timing']['max'],
                    'current' => $current_overall['timing']['max'],
                    'difference' => $current_overall['timing']['max'] - $baseline_overall['timing']['max'],
                    'percent_change' => $baseline_overall['timing']['max'] > 0 ? 
                        round((($current_overall['timing']['max'] - $baseline_overall['timing']['max']) / $baseline_overall['timing']['max']) * 100, 1) : 
                        0
                ]
            ];
            
            // Compare percentiles if available
            if (isset($baseline_overall['percentiles']) && isset($current_overall['percentiles'])) {
                $comparison['overall']['percentiles'] = [];
                
                // Get all unique percentiles
                $all_percentiles = [];
                foreach ($baseline_overall['percentiles'] as $percentile => $value) {
                    $all_percentiles[$percentile] = true;
                }
                foreach ($current_overall['percentiles'] as $percentile => $value) {
                    $all_percentiles[$percentile] = true;
                }
                
                // Compare each percentile
                foreach (array_keys($all_percentiles) as $percentile) {
                    if (isset($baseline_overall['percentiles'][$percentile]) && isset($current_overall['percentiles'][$percentile])) {
                        $baseline_value = $baseline_overall['percentiles'][$percentile];
                        $current_value = $current_overall['percentiles'][$percentile];
                        
                        $comparison['overall']['percentiles']["p{$percentile}"] = [
                            'baseline' => $baseline_value,
                            'current' => $current_value,
                            'difference' => $current_value - $baseline_value,
                            'percent_change' => $baseline_value > 0 ? 
                                round((($current_value - $baseline_value) / $baseline_value) * 100, 1) : 
                                0
                        ];
                    }
                }
            }
        }
        
        // Compare individual test plan results
        $comparison['test_plans'] = [];
        
        foreach ($this->results_summary as $plan_name => $current_summary) {
            if ($plan_name === 'overall') continue;
            
            if (isset($baseline_data['summary'][$plan_name])) {
                $baseline_summary = $baseline_data['summary'][$plan_name];
                
                $comparison['test_plans'][$plan_name] = [
                    'request_count' => [
                        'baseline' => $baseline_summary['count'],
                        'current' => $current_summary['count'],
                        'difference' => $current_summary['count'] - $baseline_summary['count'],
                        'percent_change' => $baseline_summary['count'] > 0 ? 
                            round((($current_summary['count'] - $baseline_summary['count']) / $baseline_summary['count']) * 100, 1) : 
                            0
                    ],
                    'error_rate' => [
                        'baseline' => $baseline_summary['error_rate'],
                        'current' => $current_summary['error_rate'],
                        'difference' => $current_summary['error_rate'] - $baseline_summary['error_rate'],
                        'percent_change' => $baseline_summary['error_rate'] > 0 ? 
                            round((($current_summary['error_rate'] - $baseline_summary['error_rate']) / $baseline_summary['error_rate']) * 100, 1) : 
                            0
                    ],
                    'avg_time' => [
                        'baseline' => $baseline_summary['timing']['avg'],
                        'current' => $current_summary['timing']['avg'],
                        'difference' => $current_summary['timing']['avg'] - $baseline_summary['timing']['avg'],
                        'percent_change' => $baseline_summary['timing']['avg'] > 0 ? 
                            round((($current_summary['timing']['avg'] - $baseline_summary['timing']['avg']) / $baseline_summary['timing']['avg']) * 100, 1) : 
                            0
                    ],
                    'median_time' => [
                        'baseline' => $baseline_summary['timing']['median'],
                        'current' => $current_summary['timing']['median'],
                        'difference' => $current_summary['timing']['median'] - $baseline_summary['timing']['median'],
                        'percent_change' => $baseline_summary['timing']['median'] > 0 ? 
                            round((($current_summary['timing']['median'] - $baseline_summary['timing']['median']) / $baseline_summary['timing']['median']) * 100, 1) : 
                            0
                    ]
                ];
            }
        }
        
        return $comparison;
    }
    
    /**
     * Output baseline comparison in table format
     * 
     * @param array $baseline_data Baseline data
     */
    private function output_baseline_comparison($baseline_data) {
        if (!isset($baseline_data['summary']) || !isset($this->results_summary)) {
            return;
        }
        
        $baseline_timestamp = date('Y-m-d H:i:s', $baseline_data['timestamp']);
        
        \WP_CLI::log("│ \033[1mBASELINE COMPARISON\033[0m                                                      │");
        \WP_CLI::log("│ Comparing with baseline from: {$baseline_timestamp}                     │");
        \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        
        // Compare overall results
        if (isset($baseline_data['summary']['overall']) && isset($this->results_summary['overall'])) {
            $baseline_overall = $baseline_data['summary']['overall'];
            $current_overall = $this->results_summary['overall'];
            
            \WP_CLI::log("│ \033[1mOVERALL\033[0m                                                                   │");
            
            // Response time comparison
            $avg_time_diff = $current_overall['timing']['avg'] - $baseline_overall['timing']['avg'];
            $avg_time_percent = $baseline_overall['timing']['avg'] > 0 ? 
                round(($avg_time_diff / $baseline_overall['timing']['avg']) * 100, 1) : 
                0;
                
            $avg_time_indicator = $avg_time_diff <= 0 ? '↓' : '↑';
            $avg_time_color = $avg_time_diff <= 0 ? "\033[32m" : "\033[31m";
            
            $median_time_diff = $current_overall['timing']['median'] - $baseline_overall['timing']['median'];
            $median_time_percent = $baseline_overall['timing']['median'] > 0 ? 
                round(($median_time_diff / $baseline_overall['timing']['median']) * 100, 1) : 
                0;
                
            $median_time_indicator = $median_time_diff <= 0 ? '↓' : '↑';
            $median_time_color = $median_time_diff <= 0 ? "\033[32m" : "\033[31m";
            
            \WP_CLI::log("│ Response Time:                                                           │");
            \WP_CLI::log("│   - Avg: {$current_overall['timing']['avg']}s vs {$baseline_overall['timing']['avg']}s  {$avg_time_color}{$avg_time_indicator}{$avg_time_percent}%\033[0m                              │");
            \WP_CLI::log("│   - Median: {$current_overall['timing']['median']}s vs {$baseline_overall['timing']['median']}s  {$median_time_color}{$median_time_indicator}{$median_time_percent}%\033[0m                           │");
            
            // Error rate comparison
            $error_rate_diff = $current_overall['error_rate'] - $baseline_overall['error_rate'];
            $error_rate_percent = $baseline_overall['error_rate'] > 0 ? 
                round(($error_rate_diff / $baseline_overall['error_rate']) * 100, 1) : 
                0;
                
            $error_rate_indicator = $error_rate_diff <= 0 ? '↓' : '↑';
            $error_rate_color = $error_rate_diff <= 0 ? "\033[32m" : "\033[31m";
            
            \WP_CLI::log("│ Error Rate: {$current_overall['error_rate']}% vs {$baseline_overall['error_rate']}%  {$error_rate_color}{$error_rate_indicator}{$error_rate_percent}%\033[0m                                │");
            
            // Percentile comparison if available
            if (isset($baseline_overall['percentiles']) && isset($current_overall['percentiles'])) {
                \WP_CLI::log("│ Percentiles:                                                             │");
                
                // Get common percentiles
                $common_percentiles = array_intersect(
                    array_keys($baseline_overall['percentiles']),
                    array_keys($current_overall['percentiles'])
                );
                
                // Sort percentiles
                sort($common_percentiles);
                
                foreach ($common_percentiles as $percentile) {
                    $baseline_value = $baseline_overall['percentiles'][$percentile];
                    $current_value = $current_overall['percentiles'][$percentile];
                    
                    $diff = $current_value - $baseline_value;
                    $percent = $baseline_value > 0 ? 
                        round(($diff / $baseline_value) * 100, 1) : 
                        0;
                        
                    $indicator = $diff <= 0 ? '↓' : '↑';
                    $color = $diff <= 0 ? "\033[32m" : "\033[31m";
                    
                    \WP_CLI::log("│   - P{$percentile}: {$current_value}s vs {$baseline_value}s  {$color}{$indicator}{$percent}%\033[0m                             │");
                }
            }
            
            \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        }
    }
    
    /**
     * Output response time distribution chart
     */
    private function output_response_time_chart() {
        if (empty($this->results)) {
            return;
        }
        
        // Extract response times
        $times = array_column($this->results, 'time');
        
        // Generate histogram
        $buckets = 10;
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
        $chart_width = 40;
        
        \WP_CLI::log("│ \033[1mRESPONSE TIME DISTRIBUTION\033[0m                                              │");
        
        for ($i = 0; $i < $buckets; $i++) {
            $lower = round($min + ($i * $bucket_size), 2);
            $upper = round($min + (($i + 1) * $bucket_size), 2);
            $count = $histogram[$i];
            
            $bar_length = ($max_count > 0) ? round(($count / $max_count) * $chart_width) : 0;
            $bar = str_repeat('█', $bar_length);
            $padding = str_repeat(' ', $chart_width - $bar_length);
            
            $line = sprintf("│ %5.2fs - %5.2fs [%s%s] %-5d │", 
                $lower, $upper, $bar, $padding, $count);
            
            \WP_CLI::log($line);
        }
        
        \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
    }
    
    /**
     * Set up temporary directory for inter-process communication
     */
    private function setup_temp_directory() {
        // Create a unique temp directory for this test run
        $this->temp_dir = sys_get_temp_dir() . '/microchaos_' . uniqid();
        
        // Create directory if it doesn't exist
        if (!file_exists($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
        
        \WP_CLI::log("-> Temp directory: {$this->temp_dir}");
    }
    
    /**
     * Prepare the job queue for distribution
     */
    private function prepare_job_queue() {
        $job_id = 0;
        foreach ($this->test_plans as $plan) {
            // Determine how many requests to distribute
            $total_requests = $plan['requests'];
            $concurrency = $plan['concurrency'];
            
            // Create batches based on concurrency
            $remaining = $total_requests;
            while ($remaining > 0) {
                $batch_size = min($concurrency, $remaining);
                $this->job_queue[] = [
                    'id' => $job_id++,
                    'plan' => $plan,
                    'batch_size' => $batch_size
                ];
                $remaining -= $batch_size;
            }
        }
        
        \WP_CLI::log("-> Job queue prepared: " . count($this->job_queue) . " jobs");
    }
    
    /**
     * Execute tests using worker processes
     * 
     * @return MicroChaos_Resource_Monitor Resource monitor with utilization data
     */
    private function execute_tests() {
        $resource_monitor = null;
        
        // Output summary of what will be executed
        $total_requests = 0;
        foreach ($this->test_plans as $plan) {
            $total_requests += $plan['requests'];
        }
        
        \WP_CLI::log("🔥 Executing {$total_requests} total requests across " . count($this->test_plans) . " test plans");
        
        // Execute tests in parallel or sequential mode
        if ($this->parallel_supported) {
            $resource_monitor = $this->execute_parallel();
        } else {
            $resource_monitor = $this->execute_sequential();
        }
        
        return $resource_monitor;
    }
    
    /**
     * Execute tests in parallel using pcntl_fork
     * 
     * @return MicroChaos_Resource_Monitor Resource monitor with utilization data
     */
    private function execute_parallel() {
        // Create a shared data file for job distribution
        $job_file = $this->temp_dir . '/jobs.json';
        file_put_contents($job_file, json_encode($this->job_queue));
        
        // Create progress tracking file
        $progress_file = $this->temp_dir . '/progress.json';
        file_put_contents($progress_file, json_encode([
            'total_jobs' => count($this->job_queue),
            'completed_jobs' => 0,
            'in_progress' => []
        ]));
        
        // Setup signal handling to enable process cleanup
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGCHLD, SIG_IGN); // Prevent zombie processes
        }
        
        // Launch worker processes
        $worker_count = min($this->workers, count($this->job_queue));
        \WP_CLI::log("🧮 Starting {$worker_count} worker processes...");
        
        for ($i = 0; $i < $worker_count; $i++) {
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                // Fork failed
                \WP_CLI::error("Could not fork worker process #{$i}");
            } elseif ($pid) {
                // Parent process
                $this->worker_pids[$i] = $pid; // Use worker ID as array key for easier identification
                \WP_CLI::log("-> Worker #{$i} started (PID: {$pid})");
            } else {
                // Child process
                $this->run_worker($i, $job_file, $progress_file);
                exit(0); // Child must exit
            }
        }
        
        // Parent process monitors workers and tracks resources
        $resource_monitor = $this->monitor_workers($progress_file);
        
        return $resource_monitor;
    }
    
    /**
     * Execute a worker process
     *
     * @param int $worker_id Worker ID
     * @param string $job_file Path to job distribution file
     * @param string $progress_file Path to progress tracking file
     */
    private function run_worker($worker_id, $job_file, $progress_file) {
        // Set up worker environment
        $worker_log = $this->temp_dir . "/worker_{$worker_id}.log";
        $worker_results = $this->temp_dir . "/worker_{$worker_id}_results.json";
        $results = [];
        
        // Initialize result file
        file_put_contents($worker_results, json_encode($results));
        
        // Worker log header
        file_put_contents($worker_log, "Worker #{$worker_id} started at " . date('Y-m-d H:i:s') . "\n");
        
        // Initialize request generator
        $request_generator = new MicroChaos_Request_Generator();
        
        // Initialize integration logger
        $logger = new MicroChaos_Integration_Logger([
            'enabled' => true,
            'test_id' => 'worker_' . $worker_id
        ]);
        
        // Worker process loop - keep processing jobs until none are left
        while (true) {
            // Acquire a job with file locking to prevent race conditions
            $job = $this->acquire_job($job_file, $progress_file, $worker_id);
            
            if (!$job) {
                // No more jobs available
                file_put_contents($worker_log, "Worker #{$worker_id} finished - no more jobs\n", FILE_APPEND);
                break;
            }
            
            // Process the job
            $job_start_time = microtime(true);
            file_put_contents($worker_log, "Worker #{$worker_id} processing job #{$job['id']} - {$job['plan']['name']} ({$job['batch_size']} requests)\n", FILE_APPEND);
            
            // Prepare request details
            $target_url = $request_generator->resolve_endpoint($job['plan']['endpoint']);
            $method = $job['plan']['method'] ?? 'GET';
            $cookies = null;
            $body = null;
            
            try {
                // Job timeout handling
                $job_timeout = $job['plan']['timeout'] ?? 30; // Default 30 seconds per job
                
                // Handle authentication if specified
                if (isset($job['plan']['auth'])) {
                    // Basic authentication implementation
                    if (strpos($job['plan']['auth'], '@') !== false) {
                        list($username, $domain) = explode('@', $job['plan']['auth']);
                        $password = isset($job['plan']['password']) ? $job['plan']['password'] : 'password';
                        
                        // Set basic auth header
                        $request_generator->set_custom_headers(array_merge(
                            $job['plan']['headers'] ?? [],
                            ['Authorization' => 'Basic ' . base64_encode($username . ':' . $password)]
                        ));
                        
                        file_put_contents($worker_log, "Worker #{$worker_id} using basic auth for {$username}@{$domain}\n", FILE_APPEND);
                    }
                }
                
                // Process headers if present
                if (isset($job['plan']['headers']) && !empty($job['plan']['headers'])) {
                    $request_generator->set_custom_headers($job['plan']['headers']);
                }
                
                // Process body data if present
                if (isset($job['plan']['data']) && !empty($job['plan']['data'])) {
                    $body = is_array($job['plan']['data']) ? json_encode($job['plan']['data']) : $job['plan']['data'];
                } elseif (isset($job['plan']['body']) && !empty($job['plan']['body'])) {
                    // Support for 'body' parameter for consistency with loadtest
                    $body = is_array($job['plan']['body']) ? json_encode($job['plan']['body']) : $job['plan']['body'];
                }
                
                // Log burst start to integration logger
                $logger->log_burst_complete($job['id'], $job['batch_size'], [
                    'plan_name' => $job['plan']['name'],
                    'target_url' => $target_url,
                    'method' => $method,
                    'burst_size' => $job['batch_size']
                ]);
                
                // Execute the batch (async for efficiency)
                $batch_results = $request_generator->fire_requests_async(
                    $target_url,
                    null, // No log path
                    $cookies,
                    $job['batch_size'],
                    $method,
                    $body
                );
                
                // Detect if job took too long (individual timeouts are handled by the request generator)
                $job_execution_time = microtime(true) - $job_start_time;
                if ($job_execution_time > $job_timeout) {
                    file_put_contents(
                        $worker_log, 
                        "Worker #{$worker_id} - Job #{$job['id']} exceeded timeout: {$job_execution_time}s > {$job_timeout}s\n", 
                        FILE_APPEND
                    );
                }
                
                // Add job metadata to results
                foreach ($batch_results as &$result) {
                    $result['job_id'] = $job['id'];
                    $result['plan_name'] = $job['plan']['name'];
                    $result['worker_id'] = $worker_id;
                    $result['timestamp'] = microtime(true);
                    $result['execution_time'] = $job_execution_time;
                    
                    // Log individual request to integration logger
                    $logger->log_request($result);
                }
                
                // Append results to worker results file
                $current_results = json_decode(file_get_contents($worker_results), true) ?: [];
                $current_results = array_merge($current_results, $batch_results);
                file_put_contents($worker_results, json_encode($current_results));
                
                // Update progress file to mark job as completed
                $this->complete_job($progress_file, $job['id'], $worker_id);
                
                // Log job completion statistics
                file_put_contents(
                    $worker_log, 
                    "Worker #{$worker_id} completed job #{$job['id']} in {$job_execution_time}s - Results: " . 
                    count($batch_results) . " requests, " .
                    "Avg time: " . (array_sum(array_column($batch_results, 'time')) / count($batch_results)) . "s\n", 
                    FILE_APPEND
                );
                
            } catch (Exception $e) {
                // Log error and continue to next job
                file_put_contents(
                    $worker_log, 
                    "Worker #{$worker_id} ERROR processing job #{$job['id']}: " . $e->getMessage() . "\n", 
                    FILE_APPEND
                );
                
                // Still mark job as completed to avoid it getting stuck
                $this->complete_job($progress_file, $job['id'], $worker_id);
            }
            
            // Sleep briefly to prevent CPU overload and allow other processes to run
            usleep(10000); // 10ms
        }
        
        file_put_contents($worker_log, "Worker #{$worker_id} exiting\n", FILE_APPEND);
    }
    
    /**
     * Acquire a job from the queue with file locking
     *
     * @param string $job_file Path to job file
     * @param string $progress_file Path to progress file
     * @param int $worker_id Worker ID
     * @return array|false Job data or false if no jobs available
     */
    private function acquire_job($job_file, $progress_file, $worker_id) {
        $job = false;
        
        // Get exclusive lock on job file
        $fp = fopen($job_file, 'r+');
        if (!$fp) {
            return false;
        }
        
        if (flock($fp, LOCK_EX)) {
            // Read job queue
            $jobs = json_decode(file_get_contents($job_file), true) ?: [];
            
            if (!empty($jobs)) {
                // Get the first job
                $job = array_shift($jobs);
                
                // Update job file
                file_put_contents($job_file, json_encode($jobs));
                
                // Update progress file to mark job as in progress
                $fp_progress = fopen($progress_file, 'r+');
                if ($fp_progress && flock($fp_progress, LOCK_EX)) {
                    $progress = json_decode(file_get_contents($progress_file), true) ?: [
                        'total_jobs' => 0,
                        'completed_jobs' => 0,
                        'in_progress' => []
                    ];
                    
                    $progress['in_progress'][$job['id']] = [
                        'worker_id' => $worker_id,
                        'started' => microtime(true)
                    ];
                    
                    file_put_contents($progress_file, json_encode($progress));
                    flock($fp_progress, LOCK_UN);
                    fclose($fp_progress);
                }
            }
            
            flock($fp, LOCK_UN);
        }
        
        fclose($fp);
        return $job;
    }
    
    /**
     * Mark a job as completed in the progress file
     *
     * @param string $progress_file Path to progress file
     * @param int $job_id Job ID
     * @param int $worker_id Worker ID
     */
    private function complete_job($progress_file, $job_id, $worker_id) {
        $fp = fopen($progress_file, 'r+');
        if (!$fp) {
            return;
        }
        
        if (flock($fp, LOCK_EX)) {
            $progress = json_decode(file_get_contents($progress_file), true) ?: [
                'total_jobs' => 0,
                'completed_jobs' => 0,
                'in_progress' => []
            ];
            
            // Remove from in_progress and increment completed count
            if (isset($progress['in_progress'][$job_id])) {
                unset($progress['in_progress'][$job_id]);
            }
            
            $progress['completed_jobs']++;
            
            file_put_contents($progress_file, json_encode($progress));
            flock($fp, LOCK_UN);
        }
        
        fclose($fp);
    }
    
    /**
     * Monitor worker processes and progress
     *
     * @param string $progress_file Path to progress file
     */
    private function monitor_workers($progress_file) {
        $start_time = microtime(true);
        $finished = false;
        $last_resource_check = 0;
        $last_status_length = 0;
        $resource_check_interval = 5; // seconds
        $eta_samples = [];
        $active_worker_history = [];
        $stalled_threshold = 30; // seconds
        
        // Create resource monitor
        $resource_monitor = new MicroChaos_Resource_Monitor(['track_trends' => true]);
        
        \WP_CLI::log("\n👁️ Monitoring worker progress...");
        
        // Draw progress bar header
        \WP_CLI::log("┌────────────────────────────────────────────────────────────────────────────┐");
        \WP_CLI::log("│ Progress                                                                   │");
        \WP_CLI::log("├────────────────────────────────────────────────────────────────────────────┤");
        
        while (!$finished) {
            // Check global timeout
            $current_time = microtime(true);
            $elapsed = round($current_time - $start_time, 1);
            
            if ($elapsed > $this->global_timeout) {
                \WP_CLI::warning("⚠️ Global timeout reached after {$elapsed} seconds");
                
                // Terminate all worker processes
                foreach ($this->worker_pids as $pid) {
                    posix_kill($pid, SIGTERM);
                }
                
                $finished = true;
                break;
            }
            
            // Check if all jobs are completed
            $progress = json_decode(file_get_contents($progress_file), true) ?: [];
            $total_jobs = $progress['total_jobs'] ?? 0;
            $completed_jobs = $progress['completed_jobs'] ?? 0;
            $in_progress = $progress['in_progress'] ?? [];
            
            // Calculate metrics
            $percent_complete = $total_jobs > 0 ? round(($completed_jobs / $total_jobs) * 100) : 0;
            $active_workers = count($in_progress);
            
            // Store worker activity for stall detection
            $active_worker_history[] = [
                'time' => $current_time,
                'active' => $active_workers,
                'completed' => $completed_jobs
            ];
            
            // Only keep the last 60 seconds of history
            while (count($active_worker_history) > 60) {
                array_shift($active_worker_history);
            }
            
            // Calculate ETA
            if ($completed_jobs > 0 && $elapsed > 5) {
                $rate = $completed_jobs / $elapsed; // jobs per second
                $remaining_jobs = $total_jobs - $completed_jobs;
                
                if ($rate > 0) {
                    $eta_seconds = $remaining_jobs / $rate;
                    $eta_samples[] = $eta_seconds;
                    
                    // Keep only the most recent 5 samples for ETA estimation
                    if (count($eta_samples) > 5) {
                        array_shift($eta_samples);
                    }
                    
                    // Use average of recent ETAs for more stability
                    $eta = round(array_sum($eta_samples) / count($eta_samples));
                    $eta_formatted = $this->format_time_duration($eta);
                } else {
                    $eta_formatted = "Unknown";
                }
            } else {
                $eta_formatted = "Calculating...";
            }
            
            // Check for stalled workers (no progress for 30 seconds)
            $stalled = false;
            if (count($active_worker_history) > 30) {
                $old_state = $active_worker_history[0];
                $stalled = ($current_time - $old_state['time'] >= $stalled_threshold) && 
                          ($old_state['completed'] == $completed_jobs) && 
                          ($active_workers > 0);
            }
            
            // Format progress bar
            $bar_length = 60;
            $filled_length = (int)($bar_length * $percent_complete / 100);
            $bar = str_repeat('█', $filled_length) . str_repeat('░', $bar_length - $filled_length);
            
            // Clear previous line and update progress
            for ($i = 0; $i < $last_status_length; $i++) {
                echo "\r\033[K\033[A"; // Move cursor up and clear line
            }
            $last_status_length = 5; // Reset for new status
            
            // Format status with color based on conditions
            $status_color = $stalled ? "\033[33m" : "\033[32m"; // Yellow for stalled, green for active
            $progress_status = "{$status_color}│ {$bar} │\033[0m";
            $completion_status = "\033[36m│ {$completed_jobs}/{$total_jobs} jobs ({$percent_complete}%) | Active: {$active_workers} | Elapsed: {$elapsed}s | ETA: {$eta_formatted} │\033[0m";
            
            // Display health status
            $health_status = "\033[36m│ ";
            if ($stalled) {
                $health_status .= "\033[33m⚠️ Warning: Workers appear stalled - no progress for {$stalled_threshold}+ seconds ";
            } else {
                $health_status .= "\033[32m✓ Workers healthy";
                $health_status .= str_repeat(' ', 46); // Padding
            }
            $health_status .= " │\033[0m";
            
            // Output progress display
            echo $progress_status . "\n";
            echo $completion_status . "\n";
            echo $health_status . "\n";
            
            // Display active jobs if any
            echo "\033[36m│ Active jobs:";
            echo str_repeat(' ', 57);
            echo " │\033[0m\n";
            
            $job_display = '';
            $displayed_jobs = 0;
            foreach ($in_progress as $job_id => $job_info) {
                if ($displayed_jobs < 2) { // Only show first 2 jobs to keep display compact
                    $job_time = round($current_time - $job_info['started'], 1);
                    $job_display .= "\033[36m│ - Job #{$job_id} (Worker #{$job_info['worker_id']}) - Running for {$job_time}s";
                    $job_display .= str_repeat(' ', max(0, 30 - strlen($job_id) - strlen($job_info['worker_id']) - strlen((string)$job_time)));
                    $job_display .= " │\033[0m\n";
                    $displayed_jobs++;
                }
            }
            
            if (empty($in_progress)) {
                $job_display .= "\033[36m│ - No active jobs";
                $job_display .= str_repeat(' ', 51);
                $job_display .= " │\033[0m\n";
            } else if (count($in_progress) > 2) {
                // Last line shows additional jobs count
                $more_jobs = count($in_progress) - 2;
                $job_display .= "\033[36m│ - And {$more_jobs} more active job" . ($more_jobs > 1 ? 's' : '');
                $job_display .= str_repeat(' ', 47 - strlen((string)$more_jobs));
                $job_display .= " │\033[0m\n";
            }
            
            echo $job_display;
            $last_status_length += substr_count($job_display, "\n");
            
            // Bottom border
            echo "\033[36m└────────────────────────────────────────────────────────────────────────────┘\033[0m\n";
            
            // Check if we should do a resource utilization check
            if ($current_time - $last_resource_check >= $resource_check_interval) {
                $resource_data = $resource_monitor->log_resource_utilization();
                $last_resource_check = $current_time;
                
                // Log snapshots to file for later analysis
                file_put_contents(
                    $this->temp_dir . '/resource_snapshots.json', 
                    json_encode($resource_data) . "\n", 
                    FILE_APPEND
                );
            }
            
            // Check if all jobs are completed
            if ($completed_jobs >= $total_jobs) {
                $finished = true;
            }
            
            // Check on worker processes
            $unhealthy_workers = [];
            foreach ($this->worker_pids as $key => $pid) {
                $status = pcntl_waitpid($pid, $status, WNOHANG);
                
                if ($status === $pid) {
                    // Worker has exited
                    unset($this->worker_pids[$key]);
                } else if ($status === 0) {
                    // Worker is still running - check if it's healthy
                    $found_in_progress = false;
                    foreach ($in_progress as $job_info) {
                        if ($job_info['worker_id'] == $key) {
                            $found_in_progress = true;
                            
                            // Check if job has been running too long
                            $job_runtime = $current_time - $job_info['started'];
                            if ($job_runtime > 60) { // 60 seconds is too long for a single job
                                $unhealthy_workers[] = $key;
                            }
                            break;
                        }
                    }
                    
                    // Worker should be working but isn't associated with any job
                    if (!$found_in_progress && $completed_jobs < $total_jobs) {
                        $unhealthy_workers[] = $key;
                    }
                } else {
                    // Error checking worker
                    $unhealthy_workers[] = $key;
                }
            }
            
            // Handle unhealthy workers (log but don't restart for now)
            if (!empty($unhealthy_workers)) {
                foreach ($unhealthy_workers as $worker_id) {
                    file_put_contents(
                        $this->temp_dir . '/worker_health_issues.log',
                        date('Y-m-d H:i:s') . " - Worker #{$worker_id} appears unhealthy\n",
                        FILE_APPEND
                    );
                }
            }
            
            // Check if all workers have exited
            if (empty($this->worker_pids)) {
                $finished = true;
            }
            
            // Sleep before checking again
            if (!$finished) {
                // Use shorter intervals early in the test for responsive feedback
                $sleep_time = ($elapsed < 10) ? 0.5 : 1;
                usleep($sleep_time * 1000000);
            }
        }
        
        $total_time = round(microtime(true) - $start_time, 1);
        
        // Add a line of padding after progress display
        echo "\n";
        
        \WP_CLI::log("✅ All jobs completed in {$total_time}s");
        
        // Wait for any remaining child processes
        foreach ($this->worker_pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        
        // Record final resource utilization
        $resource_monitor->log_resource_utilization();
        
        return $resource_monitor;
    }
    
    /**
     * Format time duration in human-readable format
     * 
     * @param int $seconds Number of seconds
     * @return string Formatted time string
     */
    private function format_time_duration($seconds) {
        $seconds = (int)$seconds;
        
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remaining_seconds = $seconds % 60;
            return "{$minutes}m {$remaining_seconds}s";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }
    
    /**
     * Execute tests sequentially (fallback for systems without pcntl)
     * 
     * @return MicroChaos_Resource_Monitor Resource monitor with utilization data
     */
    private function execute_sequential() {
        \WP_CLI::log("🧵 Sequential execution mode");
        
        // Create resource monitor
        $resource_monitor = new MicroChaos_Resource_Monitor(['track_trends' => true]);
        $resource_monitor->log_resource_utilization();
        
        // Initialize components
        $request_generator = new MicroChaos_Request_Generator();
        $integration_logger = new MicroChaos_Integration_Logger(['enabled' => true]);
        
        $total_jobs = count($this->job_queue);
        $completed_jobs = 0;
        $start_time = microtime(true);
        $last_resource_check = $start_time;
        $resource_check_interval = 5; // seconds
        
        // Progress bar header
        \WP_CLI::log("┌────────────────────────────────────────────────────────────────────────────┐");
        \WP_CLI::log("│ Progress                                                                   │");
        \WP_CLI::log("├────────────────────────────────────────────────────────────────────────────┤");
        
        foreach ($this->job_queue as $job) {
            // Check if execution has exceeded global timeout
            $current_time = microtime(true);
            $elapsed = $current_time - $start_time;
            
            if ($elapsed > $this->global_timeout) {
                \WP_CLI::warning("⚠️ Global timeout reached after " . round($elapsed, 1) . " seconds");
                break;
            }
            
            // Perform resource check at intervals
            if ($current_time - $last_resource_check >= $resource_check_interval) {
                $resource_data = $resource_monitor->log_resource_utilization();
                $last_resource_check = $current_time;
                
                // Log resource data
                file_put_contents(
                    $this->temp_dir . '/resource_snapshots.json', 
                    json_encode($resource_data) . "\n", 
                    FILE_APPEND
                );
            }
            
            $completed_jobs++;
            $percent_complete = round(($completed_jobs / $total_jobs) * 100);
            
            // Format progress bar
            $bar_length = 60;
            $filled_length = (int)($bar_length * $percent_complete / 100);
            $bar = str_repeat('█', $filled_length) . str_repeat('░', $bar_length - $filled_length);
            
            // Calculate ETA
            if ($completed_jobs > 1) {
                $rate = $completed_jobs / $elapsed; // jobs per second
                $remaining_jobs = $total_jobs - $completed_jobs;
                $eta_seconds = $rate > 0 ? $remaining_jobs / $rate : 0;
                $eta_formatted = $this->format_time_duration($eta_seconds);
            } else {
                $eta_formatted = "Calculating...";
            }
            
            // Update progress display (don't move cursor, just print progress)
            $elapsed_formatted = round($elapsed, 1);
            echo "\r\033[K\033[36m│ {$bar} │\033[0m";
            echo "\n\r\033[K\033[36m│ {$completed_jobs}/{$total_jobs} jobs ({$percent_complete}%) | Elapsed: {$elapsed_formatted}s | ETA: {$eta_formatted} │\033[0m";
            echo "\n\r\033[K\033[36m│ Current job: {$job['plan']['name']} ({$job['batch_size']} requests)"; 
            echo str_repeat(' ', 30 - strlen($job['plan']['name']));
            echo "│\033[0m\n";
            
            // Prepare request details
            $target_url = $request_generator->resolve_endpoint($job['plan']['endpoint']);
            $method = $job['plan']['method'] ?? 'GET';
            $cookies = null; 
            $body = null;
            
            // Handle authentication if specified
            if (isset($job['plan']['auth'])) {
                // Basic authentication implementation
                if (strpos($job['plan']['auth'], '@') !== false) {
                    list($username, $domain) = explode('@', $job['plan']['auth']);
                    $password = isset($job['plan']['password']) ? $job['plan']['password'] : 'password';
                    
                    // Set basic auth header
                    $auth_header = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
                    $request_generator->set_custom_headers(array_merge(
                        $job['plan']['headers'] ?? [],
                        ['Authorization' => 'Basic ' . base64_encode($username . ':' . $password)]
                    ));
                    
                    \WP_CLI::log("\r\033[K\033[36m│ Using basic authentication for {$username}@{$domain}" . str_repeat(' ', 20) . "│\033[0m");
                }
            }
            
            // Process headers if present
            if (isset($job['plan']['headers']) && !empty($job['plan']['headers'])) {
                $request_generator->set_custom_headers($job['plan']['headers']);
            }
            
            // Process body data if present
            if (isset($job['plan']['data']) && !empty($job['plan']['data'])) {
                $body = is_array($job['plan']['data']) ? json_encode($job['plan']['data']) : $job['plan']['data'];
            } elseif (isset($job['plan']['body']) && !empty($job['plan']['body'])) {
                // Support for 'body' parameter for consistency with loadtest
                $body = is_array($job['plan']['body']) ? json_encode($job['plan']['body']) : $job['plan']['body'];
            }
            
            // Log job start to integration logger
            $integration_logger->log_burst_complete($job['id'], $job['batch_size'], [
                'plan_name' => $job['plan']['name'],
                'target_url' => $target_url,
                'method' => $method,
                'burst_size' => $job['batch_size']
            ]);
            
            // Execute the batch (async for efficiency)
            $batch_results = $request_generator->fire_requests_async(
                $target_url,
                null, // No log path
                $cookies,
                $job['batch_size'],
                $method,
                $body
            );
            
            // Add job metadata to results
            foreach ($batch_results as &$result) {
                $result['job_id'] = $job['id'];
                $result['plan_name'] = $job['plan']['name'];
                $result['worker_id'] = 0; // All jobs run in main process
                $result['timestamp'] = microtime(true);
                
                // Log individual request to integration logger
                $integration_logger->log_request($result);
            }
            
            // Add to results collection
            $this->results = array_merge($this->results, $batch_results);
            
            // Save interim results to temp file for recovery
            file_put_contents($this->temp_dir . '/results.json', json_encode($this->results));
        }
        
        $total_time = round(microtime(true) - $start_time, 1);
        
        // Bottom border and padding
        echo "\r\033[K\033[36m└────────────────────────────────────────────────────────────────────────────┘\033[0m\n\n";
        
        \WP_CLI::log("✅ All jobs completed in {$total_time}s");
        
        // Final resource utilization check
        $resource_monitor->log_resource_utilization();
        
        return $resource_monitor;
    }
    
    /**
     * Clean up temporary files and analyze results
     * 
     * @param bool $delete_temp Whether to delete temporary files
     * @return array Combined results
     */
    private function cleanup_temp_files($delete_temp = false) {
        // Collect result files from workers
        $all_results = [];
        
        if ($this->parallel_supported) {
            // In parallel mode, collect results from worker files
            for ($i = 0; $i < $this->workers; $i++) {
                $result_file = $this->temp_dir . "/worker_{$i}_results.json";
                if (file_exists($result_file)) {
                    $worker_results = json_decode(file_get_contents($result_file), true) ?: [];
                    $all_results = array_merge($all_results, $worker_results);
                }
            }
        } else {
            // In sequential mode, we already have results in $this->results
            $all_results = $this->results;
        }
        
        // Save combined results
        file_put_contents($this->temp_dir . '/combined_results.json', json_encode($all_results));
        $this->results = $all_results;
        
        \WP_CLI::log("📊 Results collected: " . count($this->results) . " total requests");
        
        // Analyze results by test plan
        $this->analyze_results();
        
        // Delete temporary files if requested
        if ($delete_temp) {
            $this->delete_temp_directory();
        }
        
        return $all_results;
    }
    
    /**
     * Delete temporary directory and all files
     */
    private function delete_temp_directory() {
        if (!$this->temp_dir || !file_exists($this->temp_dir)) {
            return;
        }
        
        $files = glob($this->temp_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        rmdir($this->temp_dir);
        \WP_CLI::log("🧹 Temporary files cleaned up");
    }
    
    /**
     * Analyze results by test plan and calculate metrics
     */
    private function analyze_results() {
        if (empty($this->results)) {
            \WP_CLI::warning("No results to analyze");
            return;
        }
        
        // Group results by test plan
        $results_by_plan = [];
        foreach ($this->results as $result) {
            if (!isset($result['plan_name'])) {
                continue;
            }
            
            $plan_name = $result['plan_name'];
            if (!isset($results_by_plan[$plan_name])) {
                $results_by_plan[$plan_name] = [];
            }
            
            $results_by_plan[$plan_name][] = $result;
        }
        
        // Create reporting engine to analyze results
        $reporting_engine = new MicroChaos_Reporting_Engine();
        
        // Process each test plan
        $this->results_summary = [];
        foreach ($results_by_plan as $plan_name => $plan_results) {
            // Reset reporting engine for this plan
            $reporting_engine->reset_results();
            $reporting_engine->add_results($plan_results);
            
            // Generate summary
            $summary = $reporting_engine->generate_summary();
            $this->results_summary[$plan_name] = $summary;
            
            // Report plan results
            \WP_CLI::log("\n📈 Results for test plan: $plan_name");
            $reporting_engine->report_summary(null, $summary);
            
            // Check thresholds if defined
            $this->check_thresholds($plan_name, $summary);
        }
        
        // Generate overall summary
        $reporting_engine->reset_results();
        $reporting_engine->add_results($this->results);
        $overall_summary = $reporting_engine->generate_summary();
        $this->results_summary['overall'] = $overall_summary;
        
        \WP_CLI::log("\n📊 Overall Test Results:");
        $reporting_engine->report_summary(null, $overall_summary);
    }
    
    /**
     * Check if results exceed thresholds defined in test plan
     * 
     * @param string $plan_name Test plan name
     * @param array $summary Result summary
     */
    private function check_thresholds($plan_name, $summary) {
        // Find this test plan in original test plans array
        $test_plan = null;
        foreach ($this->test_plans as $plan) {
            if ($plan['name'] === $plan_name) {
                $test_plan = $plan;
                break;
            }
        }
        
        if (!$test_plan || !isset($test_plan['thresholds'])) {
            return; // No thresholds defined
        }
        
        $thresholds = $test_plan['thresholds'];
        $threshold_failures = [];
        
        // Check response time threshold (ms to s conversion)
        if (isset($thresholds['response_time'])) {
            $response_time_threshold = $thresholds['response_time'] / 1000; // convert ms to seconds
            if ($summary['timing']['avg'] > $response_time_threshold) {
                $threshold_failures[] = sprintf(
                    "Response time exceeded threshold: %.2fs > %.2fs", 
                    $summary['timing']['avg'], 
                    $response_time_threshold
                );
            }
        }
        
        // Check error rate threshold (decimal to percentage conversion)
        if (isset($thresholds['error_rate'])) {
            $error_rate_threshold = $thresholds['error_rate'] * 100; // convert decimal to percentage
            if ($summary['error_rate'] > $error_rate_threshold) {
                $threshold_failures[] = sprintf(
                    "Error rate exceeded threshold: %.1f%% > %.1f%%", 
                    $summary['error_rate'], 
                    $error_rate_threshold
                );
            }
        }
        
        // Report threshold violations
        if (!empty($threshold_failures)) {
            \WP_CLI::warning("⚠️ Threshold violations for $plan_name:");
            foreach ($threshold_failures as $failure) {
                \WP_CLI::log("   - $failure");
            }
        } else if (isset($test_plan['thresholds'])) {
            \WP_CLI::success("✅ All thresholds passed for $plan_name");
        }
    }

    /**
     * Load test plans from a JSON file
     *
     * @param string $file_path Path to the JSON file
     */
    private function load_test_plans_from_file($file_path) {
        if (!file_exists($file_path)) {
            \WP_CLI::error("Test plan file not found: $file_path");
        }

        $file_content = file_get_contents($file_path);
        if (!$file_content) {
            \WP_CLI::error("Could not read test plan file: $file_path");
        }

        $this->parse_test_plans_json($file_content);
    }

    /**
     * Parse test plans from JSON string
     *
     * @param string $json_string JSON string containing test plans
     */
    private function parse_test_plans_json($json_string) {
        $json_data = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            \WP_CLI::error("Invalid JSON format: " . json_last_error_msg());
        }

        // Check if it's a single plan or an array of plans
        if (isset($json_data['name'])) {
            // Single test plan
            $this->validate_and_add_test_plan($json_data);
        } elseif (is_array($json_data)) {
            // Array of test plans
            foreach ($json_data as $plan) {
                $this->validate_and_add_test_plan($plan);
            }
        } else {
            \WP_CLI::error("Invalid test plan format. Must be a single plan object or an array of plan objects.");
        }
    }

    /**
     * Validate a test plan and add it to the collection
     *
     * @param array $plan Test plan data
     */
    private function validate_and_add_test_plan($plan) {
        // Required fields
        if (!isset($plan['name'])) {
            \WP_CLI::warning("Test plan missing 'name' field. Skipping.");
            return;
        }

        // Normalize endpoint/target field (accept both for compatibility)
        if (isset($plan['endpoint'])) {
            $plan['target'] = $plan['endpoint'];
        } elseif (isset($plan['target']) && !isset($plan['endpoint'])) {
            $plan['endpoint'] = $plan['target'];
        } else {
            \WP_CLI::warning("Test plan '{$plan['name']}' missing 'endpoint' field. Skipping.");
            return;
        }

        // Add defaults for optional fields
        $plan['requests'] = $plan['requests'] ?? $plan['count'] ?? 100;
        $plan['concurrency'] = $plan['concurrency'] ?? $plan['burst'] ?? 10;
        $plan['delay'] = $plan['delay'] ?? 0;
        $plan['timeout'] = $plan['timeout'] ?? 5;
        $plan['method'] = $plan['method'] ?? 'GET';
        
        // Validate numeric fields
        if (!is_numeric($plan['requests']) || $plan['requests'] <= 0) {
            \WP_CLI::warning("Test plan '{$plan['name']}' has invalid 'requests' value. Must be a positive number.");
            return;
        }

        if (!is_numeric($plan['concurrency']) || $plan['concurrency'] <= 0) {
            \WP_CLI::warning("Test plan '{$plan['name']}' has invalid 'concurrency' value. Must be a positive number.");
            return;
        }

        // Add the validated plan to our collection
        $this->test_plans[] = $plan;
    }

    /**
     * Display a summary of the test plans
     */
    private function display_test_plan_summary() {
        \WP_CLI::log("\n📋 Test Plan Summary:");

        foreach ($this->test_plans as $index => $plan) {
            $index_num = $index + 1;
            \WP_CLI::log("Test Plan #{$index_num}: {$plan['name']}");
            \WP_CLI::log("  Endpoint: {$plan['endpoint']}");
            \WP_CLI::log("  Requests: {$plan['requests']} | Concurrency: {$plan['concurrency']}");
            
            if (isset($plan['method']) && $plan['method'] !== 'GET') {
                \WP_CLI::log("  Method: {$plan['method']}");
            }
            
            if (isset($plan['auth'])) {
                \WP_CLI::log("  Auth: {$plan['auth']}");
            }
            
            if (isset($plan['headers']) && !empty($plan['headers'])) {
                \WP_CLI::log("  Headers: " . count($plan['headers']));
            }
            
            if (isset($plan['data']) && !empty($plan['data'])) {
                \WP_CLI::log("  Data: Yes");
            } elseif (isset($plan['body']) && !empty($plan['body'])) {
                \WP_CLI::log("  Body: Yes");
            }
            
            if (isset($plan['thresholds'])) {
                $thresholds = [];
                if (isset($plan['thresholds']['response_time'])) {
                    $thresholds[] = "Response time: {$plan['thresholds']['response_time']}ms";
                }
                if (isset($plan['thresholds']['error_rate'])) {
                    $thresholds[] = "Error rate: {$plan['thresholds']['error_rate']}";
                }
                if (!empty($thresholds)) {
                    \WP_CLI::log("  Thresholds: " . implode(', ', $thresholds));
                }
            }
            
            \WP_CLI::log("");
        }
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
     * Constructor
     *
     * @param array $options Options for resource monitoring
     */
    public function __construct($options = []) {
        $this->resource_results = [];
        $this->track_trends = isset($options['track_trends']) ? (bool)$options['track_trends'] : false;
        $this->start_time = microtime(true);
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
    
    /**
     * Analyze resource usage trends
     * 
     * @return array Trend analysis data
     */
    public function analyze_trends() {
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
    public function generate_trend_charts($width = 60, $height = 15) {
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
    public function report_trends() {
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
     * Run parallel load tests using multiple workers.
     *
     * ## DESCRIPTION
     *
     * Runs multiple load tests in parallel using a JSON test plan configuration.
     * This allows simulating more realistic mixed traffic patterns, such as anonymous users
     * browsing products while logged-in users checkout simultaneously.
     *
     * The test plan can be provided either as a direct JSON string or as a path to a JSON file.
     * 
     * ## OPTIONS
     *
     * [--file=<path>]
     * : Path to a JSON file containing test plan(s)
     *
     * [--plan=<json>]
     * : JSON string containing test plan(s) directly in the command
     *
     * [--workers=<number>]
     * : Number of parallel workers to use. Default: 3
     *
     * [--output=<format>]
     * : Output format. Options: json, table, csv. Default: table
     *
     * ## EXAMPLES
     *
     *     # Run parallel tests defined in a JSON file
     *     wp microchaos paralleltest --file=test-plans.json
     *
     *     # Run parallel tests with a JSON string
     *     wp microchaos paralleltest --plan='[{"name":"Homepage Test","target":"home","requests":50},{"name":"Checkout Test","target":"checkout","requests":25,"auth":"user@example.com"}]'
     *
     *     # Run parallel tests with 5 workers
     *     wp microchaos paralleltest --file=test-plans.json --workers=5
     *
     *     # Run parallel tests and output results as JSON
     *     wp microchaos paralleltest --file=test-plans.json --output=json
     *
     * @param array $args Command arguments
     * @param array $assoc_args Command options
     */
    public function paralleltest($args, $assoc_args) {
        $parallel_test = new MicroChaos_ParallelTest();
        $parallel_test->run($args, $assoc_args);
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
     * [--resource-trends]
     * : Track and analyze resource utilization trends over time. Useful for detecting memory leaks.
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
     *     # Run load test with resource trend tracking to detect memory leaks
     *     wp microchaos loadtest --endpoint=home --duration=10 --resource-logging --resource-trends
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
        $resource_trends = isset($assoc_args['resource-trends']);
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

        $resource_monitor = new MicroChaos_Resource_Monitor([
            'track_trends' => $resource_trends
        ]);
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
                sleep((int)$random_delay);
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
            
            // Report resource trends if enabled
            if ($resource_trends) {
                $resource_monitor->report_trends();
            }
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
                $integration_logger,
                $resource_trends
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
     * @param bool   $resource_trends      Whether to track and analyze resource trends
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
        $integration_logger = null,
        $resource_trends = false
    ) {
        // Initialize components
        $request_generator = new MicroChaos_Request_Generator([
            'collect_cache_headers' => $collect_cache_headers,
        ]);
        $resource_monitor = new MicroChaos_Resource_Monitor([
            'track_trends' => $resource_trends
        ]);
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
                sleep((int)$random_delay);
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
                
                // Report resource trends if enabled
                if ($resource_trends) {
                    $resource_monitor->report_trends();
                }
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
