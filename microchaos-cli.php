<?php
/**
 * Plugin Name: MicroChaos CLI Load Tester
 * Description: Internal WP-CLI based WordPress load tester for staging environments where
 * external load testing is restricted (like Pressable).
 * Version: 1.7
 * Author: Phill
 */

// Bootstrap MicroChaos components
if (file_exists(dirname(__FILE__) . '/microchaos/bootstrap.php')) {
    require_once dirname(__FILE__) . '/microchaos/bootstrap.php';
}

// Legacy WP-CLI command registration for backward compatibility
if (defined('WP_CLI') && WP_CLI && !class_exists('MicroChaos_Commands')) {
    \WP_CLI::add_command('microchaos', 'MicroChaos_LoadTest_Command');

    // Legacy command class that will be used if the new component system fails to load
    class MicroChaos_LoadTest_Command {
        private $results = [];
        private $resourceResults = [];
        private $cache_profile_enabled = false;
        private $collect_cache_headers = false;
        private $cacheHeaders = [];

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
		 * 3. Watch server-level metrics (PHP error logs, memory usage, CPU load) to see if you’re hitting resource ceilings.
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
		 * ## OUTPUT
		 *
		 * Each request logs to:
		 *   ⏱ MicroChaos Request | Time: 0.253s | Code: 200 | URL: https://example.com/cart/
		 *
		 * Post-test summary includes:
		 *   📊 Load Test Summary:
		 *     Total Requests, Success/Error Count, Avg Time, Median, Fastest, Slowest
		 *
		 * When cache-headers is enabled, adds:
		 *   📦 Cache Header Summary:
		 *     Batcache Hit/Miss Ratio, Edge Cache Statistics
		 *
		 * Use WP Cloud Insights or error logs to monitor performance over time.
		 */
		public function loadtest($args, $assoc_args) {
			$endpoint = $assoc_args['endpoint'] ?? 'home';
			$count    = intval($assoc_args['count'] ?? 100);
			$burst    = intval($assoc_args['burst'] ?? 10);
			$delay    = intval($assoc_args['delay'] ?? 2);
			$flush    = isset($assoc_args['flush-between']);
			$warm     = isset($assoc_args['warm-cache']);
			$log_path = $assoc_args['log-to'] ?? null;
			$auth_user = $assoc_args['auth'] ?? null;
			$multi_auth = $assoc_args['multi-auth'] ?? null;
			$rampup = isset($assoc_args['rampup']);
			$resource_logging = isset($assoc_args['resource-logging']);
			$method = strtoupper($assoc_args['method'] ?? 'GET');
			$body = $assoc_args['body'] ?? null;
			$this->collect_cache_headers = isset($assoc_args['cache-headers']);

			// Process body if it's a file reference
			if ($body && strpos($body, 'file:') === 0) {
				$file_path = substr($body, 5);
				if (file_exists($file_path)) {
					$body = file_get_contents($file_path);
				} else {
					WP_CLI::error("Body file not found: $file_path");
				}
			}

			$url = $this->resolve_endpoint($endpoint);
			if (!$url) {
				WP_CLI::error("Invalid endpoint. Use 'home', 'shop', 'cart', 'checkout', or 'custom:/your/path'.");
			}

			$cookies = null;
			if ($multi_auth) {
				$emails = array_map('trim', explode(',', $multi_auth));
				$auth_sessions = [];
				foreach ($emails as $email) {
					$user = get_user_by('email', $email);
					if (!$user) {
						WP_CLI::warning("User with email {$email} not found. Skipping.");
						continue;
					}
					wp_set_current_user($user->ID);
					wp_set_auth_cookie($user->ID);
					$session_cookies = wp_remote_retrieve_cookies(wp_remote_get(home_url()));
					$auth_sessions[] = $session_cookies;
					WP_CLI::log("🔐 Added session for {$user->user_login}");
				}
				if (empty($auth_sessions)) {
					WP_CLI::warning("No valid multi-auth sessions available. Continuing without authentication.");
				}
				$cookies = $auth_sessions;
			} elseif (isset($assoc_args['auth'])) {
				$auth_user = $assoc_args['auth'];
				$user = get_user_by('email', $auth_user);
				if (!$user) {
					WP_CLI::error("User with email {$auth_user} not found.");
				}
				wp_set_current_user($user->ID);
				wp_set_auth_cookie($user->ID);
				$cookies = wp_remote_retrieve_cookies(wp_remote_get(home_url()));
				WP_CLI::log("🔐 Authenticated as {$user->user_login}");
			}

			WP_CLI::log("🚀 MicroChaos Load Test Started");
			WP_CLI::log("→ URL: $url");
			WP_CLI::log("→ Method: $method");
			if ($body) {
				WP_CLI::log("→ Body: " . (strlen($body) > 50 ? substr($body, 0, 47) . '...' : $body));
			}
			WP_CLI::log("→ Total: $count | Burst: $burst | Delay: {$delay}s");

			if ($this->collect_cache_headers) {
				WP_CLI::log("→ Cache header tracking enabled");
			}

			if ($warm) {
				WP_CLI::log("🧤 Warming cache...");
				$this->fire_request($url, $log_path, $cookies, $method, $body);
			}

			$completed = 0;
			if ($rampup) {
				$current_ramp = 1; // start ramp-up at 1 concurrent request
			}
			while ($completed < $count) {
				if ($rampup) {
					$current_ramp = min($current_ramp + 1, $burst);
				}
				if ($resource_logging) {
					$this->log_resource_utilization();
				}
				$current_burst = $rampup ? min($current_ramp, $burst, $count - $completed) : min($burst, $count - $completed);
				WP_CLI::log("⚡ Burst of $current_burst requests");

				if ($flush) {
					WP_CLI::log("♻️ Flushing cache before burst...");
					wp_cache_flush();
				}

				if (isset($assoc_args['concurrency-mode']) && $assoc_args['concurrency-mode'] === 'async') {
					$this->fire_requests_async($url, $log_path, $cookies, $current_burst, $method, $body);
				} else {
					for ($i = 0; $i < $current_burst; $i++) {
						$this->fire_request($url, $log_path, $cookies, $method, $body);
					}
				}

				$completed += $current_burst;
				$random_delay = rand($delay * 50, $delay * 150) / 100; // Random delay between 50% and 150% of base delay
				WP_CLI::log("⏳ Sleeping for {$random_delay}s (randomized delay)");
				sleep($random_delay);
			}

			$this->report_summary();

			// Report cache headers if we were collecting them
			if ($this->collect_cache_headers) {
				$this->report_cache_headers();
			}

			WP_CLI::success("✅ Load test complete: $count requests fired.");
		}

		private function fire_requests_async($url, $log_path, $cookies, $current_burst, $method = 'GET', $body = null) {
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
				WP_CLI::log("→ {$code} in {$duration}s");
				$this->results[] = [
					'time' => $duration,
					'code' => $code,
				];
				curl_multi_remove_handle($multi_handle, $curl);
				curl_close($curl);
			}

			curl_multi_close($multi_handle);
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

		private function fire_request_log($response, $url, $log_path, $method = 'GET') {
			$end = microtime(true);
			$duration = round($end - microtime(true), 4);
			$code = $response ? 200 : 'ERROR';

			$message = "⏱ MicroChaos Request | Time: {$duration}s | Code: {$code} | URL: $url | Method: $method";

			error_log($message);
			if ($log_path) {
				$this->log_to_file($message, $log_path);
			}
			WP_CLI::log("→ {$code} in {$duration}s");

			// Record for summary
			$this->results[] = [
				'time' => $duration,
				'code' => $code,
			];
		}

		private function report_summary() {
			$count = count($this->results);
			if ($count === 0) {
				WP_CLI::warning("No results to summarize.");
				return;
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

			WP_CLI::log("📊 Load Test Summary:");
			WP_CLI::log("   Total Requests: $count");
			WP_CLI::log("   Success: $successes | Errors: $errors");
			WP_CLI::log("   Avg Time: {$avg}s | Median: {$median}s");
			WP_CLI::log("   Fastest: {$min}s | Slowest: {$max}s");

			if (!empty($this->resourceResults)) {
				$n = count($this->resourceResults);
				$mem_usages = array_column($this->resourceResults, 'memory_usage');
				sort($mem_usages);
				$avg_memory_usage = round(array_sum($mem_usages) / $n, 2);
				$median_memory_usage = round($mem_usages[floor($n / 2)], 2);

				$peak_memories = array_column($this->resourceResults, 'peak_memory');
				sort($peak_memories);
				$avg_peak_memory = round(array_sum($peak_memories) / $n, 2);
				$median_peak_memory = round($peak_memories[floor($n / 2)], 2);

				$user_times = array_column($this->resourceResults, 'user_time');
				sort($user_times);
				$avg_user_time = round(array_sum($user_times) / $n, 2);
				$median_user_time = round($user_times[floor($n / 2)], 2);

				$system_times = array_column($this->resourceResults, 'system_time');
				sort($system_times);
				$avg_system_time = round(array_sum($system_times) / $n, 2);
				$median_system_time = round($system_times[floor($n / 2)], 2);

				WP_CLI::log("📊 Resource Utilization Summary:");
				WP_CLI::log("   Avg Memory Usage: {$avg_memory_usage} MB, Median: {$median_memory_usage} MB");
				WP_CLI::log("   Avg Peak Memory: {$avg_peak_memory} MB, Median: {$median_peak_memory} MB");
				WP_CLI::log("   Avg CPU Time (User): {$avg_user_time}s, Median: {$median_user_time}s");
				WP_CLI::log("   Avg CPU Time (System): {$avg_system_time}s, Median: {$median_system_time}s");
			}
		}

		private function resolve_endpoint($slug) {
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

		private function fire_request($url, $log_path = null, $cookies = null, $method = 'GET', $body = null) {
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
			WP_CLI::log("→ {$code} in {$duration}s");

			// Record for summary
			$this->results[] = [
				'time' => $duration,
				'code' => $code,
			];
		}

		/**
		 * Collect and catalog cache headers from the response
		 *
		 * @param array $headers Response headers
		 */
		private function collect_cache_header_data($headers) {
			// Headers to track (Pressable specific and general cache headers)
			$cache_headers = ['x-ac', 'x-nananana', 'x-cache', 'age', 'x-cache-hits'];

			foreach ($cache_headers as $header) {
				if (isset($headers[$header])) {
					$value = $headers[$header];
					if (!isset($this->cacheHeaders[$header])) {
						$this->cacheHeaders[$header] = [];
					}
					if (!isset($this->cacheHeaders[$header][$value])) {
						$this->cacheHeaders[$header][$value] = 0;
					}
					$this->cacheHeaders[$header][$value]++;
				}
			}
		}

		private function log_to_file($message, $path) {
			$path = sanitize_text_field($path);
			$filepath = trailingslashit(WP_CONTENT_DIR) . ltrim($path, '/');
			@file_put_contents($filepath, $message . PHP_EOL, FILE_APPEND);
		}

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

		private function log_resource_utilization() {
			$memory_usage = round(memory_get_usage() / 1024 / 1024, 2);
			$peak_memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
			$ru = getrusage();
			$user_time = round($ru['ru_utime.tv_sec'] + $ru['ru_utime.tv_usec'] / 1e6, 2);
			$system_time = round($ru['ru_stime.tv_sec'] + $ru['ru_stime.tv_usec'] / 1e6, 2);
			WP_CLI::log("🔍 Resources: Memory Usage: {$memory_usage} MB, Peak Memory: {$peak_memory} MB, CPU Time: User {$user_time}s, System {$system_time}s");
			$this->resourceResults[] = [
				'memory_usage' => $memory_usage,
				'peak_memory'  => $peak_memory,
				'user_time'    => $user_time,
				'system_time'  => $system_time,
			];
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

		/**
		 * Report cache headers if available.
		 */
		private function report_cache_headers() {
			if (empty($this->cacheHeaders)) {
				WP_CLI::log("ℹ️ No cache headers detected.");
				return;
			}

			WP_CLI::log("📦 Cache Header Summary:");

			// Calculate batcache hit ratio if x-nananana is present
			if (isset($this->cacheHeaders['x-nananana'])) {
				$batcache_hits = array_sum($this->cacheHeaders['x-nananana']);
				$total_requests = count($this->results);
				$hit_ratio = round(($batcache_hits / $total_requests) * 100, 2);
				WP_CLI::log("   🦇 Batcache Hit Ratio: {$hit_ratio}%");
			}

			// Calculate edge cache hit ratio if x-ac is present
			if (isset($this->cacheHeaders['x-ac'])) {
				$edge_hits = isset($this->cacheHeaders['x-ac']['HIT']) ? $this->cacheHeaders['x-ac']['HIT'] : 0;
				$total_requests = count($this->results);
				$hit_ratio = round(($edge_hits / $total_requests) * 100, 2);
				WP_CLI::log("   🌐 Edge Cache Hit Ratio: {$hit_ratio}%");
			}

			// Print detailed header statistics
			foreach ($this->cacheHeaders as $header => $values) {
				WP_CLI::log("   $header:");
				foreach ($values as $val => $count) {
					WP_CLI::log("     $val: $count");
				}
			}

			// If age headers present, calculate average cache age
			if (isset($this->cacheHeaders['age'])) {
				$total_age = 0;
				$age_count = 0;
				foreach ($this->cacheHeaders['age'] as $age => $count) {
					$total_age += $age * $count;
					$age_count += $count;
				}
				$avg_age = round($total_age / $age_count, 2);
				WP_CLI::log("   ⏲ Average Cache Age: {$avg_age} seconds");
			}
		}
	}

}
