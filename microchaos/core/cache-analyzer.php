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
