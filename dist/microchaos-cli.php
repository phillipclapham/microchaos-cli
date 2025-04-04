<?php
/**
 * Plugin Name: MicroChaos CLI Load Tester
 * Description: Internal WP-CLI based WordPress load tester for staging environments where
 * external load testing is restricted (like Pressable).
 * Version: 1.5
 * Author: Phill
 */

// Bootstrap MicroChaos components

/**
 * COMPILED SINGLE-FILE VERSION
 * Generated on: 2025-04-04T19:41:54.370Z
 * 
 * This is an automatically generated file - DO NOT EDIT DIRECTLY
 * Make changes to the modular version and rebuild.
 */

if (defined('WP_CLI') && WP_CLI) {

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
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'User-Agent: ' . $this->get_random_user_agent(),
            ]);

            // For cache header collection
            if ($this->collect_cache_headers) {
                curl_setopt($curl, CURLOPT_HEADER, true);
            }

            // Handle body data
            if ($body) {
                if ($this->is_json($body)) {
                    curl_setopt($curl, CURLOPT_HTTPHEADER, [
                        'User-Agent: ' . $this->get_random_user_agent(),
                        'Content-Type: application/json',
                    ]);
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
                \WP_CLI::log("→ {$code} in {$duration}s");
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

        if ($body) {
            if ($this->is_json($body)) {
                $args['headers'] = [
                    'Content-Type' => 'application/json',
                ];
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
            \WP_CLI::log("→ {$code} in {$duration}s");
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
     */
    public function report_summary() {
        $summary = $this->generate_summary();

        if (empty($summary)) {
            return;
        }

        if (class_exists('WP_CLI')) {
            \WP_CLI::log("📊 Resource Utilization Summary:");
            \WP_CLI::log("   Avg Memory Usage: {$summary['memory']['avg']} MB, Median: {$summary['memory']['median']} MB");
            \WP_CLI::log("   Avg Peak Memory: {$summary['peak_memory']['avg']} MB, Median: {$summary['peak_memory']['median']} MB");
            \WP_CLI::log("   Avg CPU Time (User): {$summary['user_time']['avg']}s, Median: {$summary['user_time']['median']}s");
            \WP_CLI::log("   Avg CPU Time (System): {$summary['system_time']['avg']}s, Median: {$summary['system_time']['median']}s");
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

        return [
            'count' => $count,
            'success' => $successes,
            'errors' => $errors,
            'times' => [
                'avg' => $avg,
                'median' => $median,
                'min' => $min,
                'max' => $max,
            ],
        ];
    }

    /**
     * Report summary to CLI
     */
    public function report_summary() {
        $summary = $this->generate_summary();

        if ($summary['count'] === 0) {
            if (class_exists('WP_CLI')) {
                \WP_CLI::warning("No results to summarize.");
            }
            return;
        }

        if (class_exists('WP_CLI')) {
            \WP_CLI::log("📊 Load Test Summary:");
            \WP_CLI::log("   Total Requests: {$summary['count']}");
            \WP_CLI::log("   Success: {$summary['success']} | Errors: {$summary['errors']}");
            \WP_CLI::log("   Avg Time: {$summary['times']['avg']}s | Median: {$summary['times']['median']}s");
            \WP_CLI::log("   Fastest: {$summary['times']['min']}s | Slowest: {$summary['times']['max']}s");
        }
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
     * Designed for staging environments where external load testing is restricted.
     * Logs go to PHP error log and optionally to a local file under wp-content/.
     *
     * ## HOW TO USE
     *
     * 1. Decide the real-world traffic scenario you need to test (e.g., 20 concurrent hits
     * sustained, or a daily average of 30 hits/second at peak).
     *
     * 2. Run the loopback test with at least 2–3× those numbers to see if resource usage climbs
     * to a point of concern.
     *
     * 3. Watch server-level metrics (PHP error logs, memory usage, CPU load) to see if you're hitting resource ceilings.
     *
     * ## OPTIONS
     *
     * [--endpoint=<endpoint>]
     * : The page to test. Options:
     *     home       → /
     *     shop       → /shop/
     *     cart       → /cart/
     *     checkout   → /checkout/
     *     custom:/path → any relative path (e.g., custom:/my-page/)
     *
     * [--endpoints=<endpoint-list>]
     * : Comma-separated list of endpoints to rotate through (uses same format as --endpoint).
     *
     * [--count=<number>]
     * : Total number of requests to send. Default: 100
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
     *     # Test with endpoint rotation
     *     wp microchaos loadtest --endpoints=home,shop,cart --count=60 --rotation-mode=random
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
        $rotation_mode = $assoc_args['rotation-mode'] ?? 'serial';
        $collect_cache_headers = isset($assoc_args['cache-headers']);

        // Initialize components
        $request_generator = new MicroChaos_Request_Generator([
            'collect_cache_headers' => $collect_cache_headers,
        ]);

        $resource_monitor = new MicroChaos_Resource_Monitor();
        $cache_analyzer = new MicroChaos_Cache_Analyzer();
        $reporting_engine = new MicroChaos_Reporting_Engine();

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

        \WP_CLI::log("🚀 MicroChaos Load Test Started");

        // Log the test configuration
        if (count($endpoint_list) === 1) {
            \WP_CLI::log("→ URL: {$endpoint_list[0]['url']}");
        } else {
            \WP_CLI::log("→ URLs: " . count($endpoint_list) . " endpoints (" .
                          implode(', ', array_column($endpoint_list, 'slug')) . ") - Rotation mode: $rotation_mode");
        }

        \WP_CLI::log("→ Method: $method");

        if ($body) {
            \WP_CLI::log("→ Body: " . (strlen($body) > 50 ? substr($body, 0, 47) . '...' : $body));
        }

        \WP_CLI::log("→ Total: $count | Burst: $burst | Delay: {$delay}s");

        if ($collect_cache_headers) {
            \WP_CLI::log("→ Cache header tracking enabled");
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

        while ($completed < $count) {
            // Monitor resources if enabled
            if ($resource_logging) {
                $resource_data = $resource_monitor->log_resource_utilization();
            }

            // Calculate burst size
            if ($rampup) {
                $current_ramp = min($current_ramp + 1, $burst);
            }

            $current_burst = min($current_ramp, $burst, $count - $completed);
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

            // Process cache headers
            if ($collect_cache_headers) {
                $cache_headers = $request_generator->get_cache_headers();
                foreach ($cache_headers as $header => $values) {
                    foreach ($values as $value => $count) {
                        $cache_analyzer->collect_headers([$header => $value]);
                    }
                }
            }

            $completed += $current_burst;

            // Skip delay for the last burst
            if ($completed < $count) {
                $random_delay = rand($delay * 50, $delay * 150) / 100; // Random delay between 50% and 150% of base delay
                \WP_CLI::log("⏳ Sleeping for {$random_delay}s (randomized delay)");
                sleep($random_delay);
            }
        }

        // Generate reports
        $reporting_engine->report_summary();

        // Report resource utilization if enabled
        if ($resource_logging) {
            $resource_monitor->report_summary();
        }

        // Report cache headers if enabled
        if ($collect_cache_headers) {
            $cache_analyzer->report_summary($reporting_engine->get_request_count());
        }

        \WP_CLI::success("✅ Load test complete: $count requests fired.");
    }
}

    // Register the MicroChaos WP-CLI command
    WP_CLI::add_command('microchaos', 'MicroChaos_Commands');
}
