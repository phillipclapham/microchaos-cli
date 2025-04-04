<?php
/**
 * Cache Analyzer Component
 *
 * Analyzes cache headers and provides reports on cache behavior.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * Cache Analyzer class
 */
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
