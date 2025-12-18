<?php
/**
 * Commands Component
 *
 * Handles WP-CLI command registration and option parsing.
 * Delegates test execution to LoadTestOrchestrator.
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
    public static function register(): void {
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
     * [--user-agent=<user_agent>]
     * : Custom User-Agent header. Required for Pressable headless apps. Format: your-app-name/1.0
     *
     * [--graphql]
     * : Shorthand for GraphQL testing. Sets method=POST and endpoint=/graphql if not specified.
     *   Use with --body to provide your GraphQL query.
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
     *     # GraphQL load testing (using shorthand)
     *     wp microchaos loadtest --graphql --body='{"query":"{ posts { nodes { title } } }"}' --count=100
     *
     *     # GraphQL with custom User-Agent (required for Pressable headless)
     *     wp microchaos loadtest --graphql --body='{"query":"{ posts { nodes { title } } }"}' --user-agent=my-app/1.0 --count=50
     *
     *     # GraphQL with explicit endpoint and method (override defaults)
     *     wp microchaos loadtest --graphql --endpoint=custom:/api/graphql --method=GET --count=50
     *
     * @param array<int, string> $args Command arguments
     * @param array<string, mixed> $assoc_args Command options
     */
    public function loadtest(array $args, array $assoc_args): void {
        // Build config from CLI options
        $config = $this->parse_options($assoc_args);

        // Create and execute orchestrator
        $orchestrator = new MicroChaos_LoadTest_Orchestrator($config);
        $result = $orchestrator->execute();

        // Final success message
        if ($result['run_by_duration']) {
            MicroChaos_Log::success("✅ Load test complete: {$result['completed']} requests fired over {$result['actual_minutes']} minutes.");
        } else {
            MicroChaos_Log::success("✅ Load test complete: {$result['count']} requests fired.");
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

        // Handle --graphql shorthand (apply defaults, allow overrides)
        $graphql_mode = isset($assoc_args['graphql']);
        $method = strtoupper($assoc_args['method'] ?? ($graphql_mode ? 'POST' : 'GET'));
        $endpoint = $assoc_args['endpoint'] ?? ($graphql_mode ? 'custom:/graphql' : null);

        return [
            'endpoint' => $endpoint,
            'endpoints' => $assoc_args['endpoints'] ?? null,
            'count' => intval($assoc_args['count'] ?? 100),
            'duration' => isset($assoc_args['duration']) ? floatval($assoc_args['duration']) : null,
            'burst' => intval($assoc_args['burst'] ?? 10),
            'delay' => intval($assoc_args['delay'] ?? 2),
            'method' => $method,
            'body' => $assoc_args['body'] ?? null,
            'user_agent' => $assoc_args['user-agent'] ?? null,
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
