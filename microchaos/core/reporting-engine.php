<?php
/**
 * Reporting Engine Component
 *
 * Handles results aggregation and reporting for load tests.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * Reporting Engine class
 */
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
