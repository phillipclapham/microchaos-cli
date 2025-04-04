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
