<?php
/**
 * Request Generator Component
 *
 * Handles the creation and execution of HTTP requests for load testing.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * Request Generator class
 */
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
