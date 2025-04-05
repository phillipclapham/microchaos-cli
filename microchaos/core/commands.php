<?php
/**
 * Commands Component
 *
 * Handles WP-CLI command registration and execution.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos Commands class
 */
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
