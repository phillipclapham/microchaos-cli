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
