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
     * @var array<int, array{time: float, code: int|string}>
     */
    private array $results = [];

    /**
     * Baseline storage implementation
     *
     * @var MicroChaos_Baseline_Storage
     */
    private $baseline_storage;

    /**
     * Constructor
     *
     * @param MicroChaos_Baseline_Storage|null $storage Optional baseline storage (will create default if not provided)
     */
    public function __construct(?MicroChaos_Baseline_Storage $storage = null) {
        $this->results = [];
        $this->baseline_storage = $storage ?? new MicroChaos_Transient_Baseline_Storage('microchaos_baseline');
    }
    
    /**
     * Reset results array (useful for progressive testing)
     */
    public function reset_results(): void {
        $this->results = [];
    }

    /**
     * Add a result
     *
     * @param array $result Result data
     */
    public function add_result(array $result): void {
        $this->results[] = $result;
    }

    /**
     * Add multiple results
     *
     * @param array $results Array of result data
     */
    public function add_results(array $results): void {
        foreach ($results as $result) {
            $this->add_result($result);
        }
    }

    /**
     * Get all results
     *
     * @return array All results
     */
    public function get_results(): array {
        return $this->results;
    }

    /**
     * Get total request count
     *
     * @return int Number of requests
     */
    public function get_request_count(): int {
        return count($this->results);
    }

    /**
     * Generate summary report
     *
     * @return array Summary report data
     */
    public function generate_summary(): array {
        $count = count($this->results);
        if ($count === 0) {
            return [
                'count' => 0,
                'success' => 0,
                'http_errors' => 0,
                'graphql_errors' => 0,
                'graphql_error_requests' => 0,
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
        $http_errors = $count - $successes;

        // Count GraphQL errors (requests that returned 200 but had errors in response)
        $graphql_errors = array_sum(array_map(
            fn($r) => $r['graphql_errors'] ?? 0,
            $this->results
        ));

        // Total errors = HTTP errors + requests with GraphQL errors
        $requests_with_gql_errors = count(array_filter(
            $this->results,
            fn($r) => ($r['graphql_errors'] ?? 0) > 0
        ));
        $total_errors = $http_errors + $requests_with_gql_errors;
        $error_rate = $count > 0 ? round(($total_errors / $count) * 100, 1) : 0;

        return [
            'count' => $count,
            'success' => $successes - $requests_with_gql_errors, // True success = HTTP 200 AND no GQL errors
            'http_errors' => $http_errors,
            'graphql_errors' => $graphql_errors,
            'graphql_error_requests' => $requests_with_gql_errors,
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
     * @param array|null $execution_metrics Optional execution timing and throughput metrics
     */
    public function report_summary(?array $baseline = null, ?array $provided_summary = null, ?string $threshold_profile = null, ?array $execution_metrics = null): void {
        $summary = $provided_summary ?: $this->generate_summary();

        if ($summary['count'] === 0) {
            if (class_exists('WP_CLI')) {
                MicroChaos_Log::warning("No results to summarize.");
            }
            return;
        }

        if (class_exists('WP_CLI')) {
            $error_rate = $summary['error_rate'];

            MicroChaos_Log::log("📊 Load Test Summary");
            MicroChaos_Log::log("   ═══════════════════════════════════════════════════");

            // Test Execution Metrics section
            if ($execution_metrics) {
                MicroChaos_Log::log("   Test Execution:");
                MicroChaos_Log::log("     Started:    {$execution_metrics['started_at']}");
                MicroChaos_Log::log("     Ended:      {$execution_metrics['ended_at']}");
                MicroChaos_Log::log("     Duration:   {$execution_metrics['duration_seconds']}s ({$execution_metrics['duration_formatted']})");
                MicroChaos_Log::log("     Requests:   {$execution_metrics['total_requests']}");
                MicroChaos_Log::log("     Throughput: {$execution_metrics['throughput_rps']} req/s");

                if (isset($execution_metrics['capacity'])) {
                    MicroChaos_Log::log("");
                    MicroChaos_Log::log("   Capacity Projection (at current throughput):");
                    MicroChaos_Log::log("     Per hour:   " . number_format($execution_metrics['capacity']['per_hour']) . " requests");
                    MicroChaos_Log::log("     Per day:    " . number_format($execution_metrics['capacity']['per_day']) . " requests");
                    MicroChaos_Log::log("     Per month:  ~" . $this->format_large_number($execution_metrics['capacity']['per_month']) . " requests");
                    MicroChaos_Log::log("     ⚠️  Assumes sustained throughput. Actual capacity depends on workers, RAM, cache hit rate.");
                }
                MicroChaos_Log::log("   ═══════════════════════════════════════════════════");
                MicroChaos_Log::log("");
            }

            MicroChaos_Log::log("   Response Statistics:");
            MicroChaos_Log::log("     Total Requests: {$summary['count']}");
            
            $error_formatted = MicroChaos_Thresholds::format_value($error_rate, 'error_rate', $threshold_profile);

            // Build error display - show GraphQL errors only if any occurred
            $error_parts = ["Success: {$summary['success']}", "HTTP Errors: {$summary['http_errors']}"];
            if ($summary['graphql_errors'] > 0) {
                $error_parts[] = "GraphQL Errors: {$summary['graphql_errors']}";
            }
            $error_parts[] = "Error Rate: {$error_formatted}";
            MicroChaos_Log::log("     " . implode(" | ", $error_parts));
            
            // Format with threshold colors
            $avg_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['avg'], 'response_time', $threshold_profile);
            $median_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['median'], 'response_time', $threshold_profile);
            $max_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['max'], 'response_time', $threshold_profile);
            
            MicroChaos_Log::log("     Avg Time: {$avg_time_formatted} | Median: {$median_time_formatted}");
            MicroChaos_Log::log("     Fastest: {$summary['timing']['min']}s | Slowest: {$max_time_formatted}");

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

                MicroChaos_Log::log("     Comparison to Baseline:");
                MicroChaos_Log::log("       - Avg: {$change_color}{$change_indicator}{$avg_change}%\033[0m vs {$baseline['timing']['avg']}s");

                $change_indicator = $median_change <= 0 ? '↓' : '↑';
                $change_color = $median_change <= 0 ? "\033[32m" : "\033[31m";
                MicroChaos_Log::log("       - Median: {$change_color}{$change_indicator}{$median_change}%\033[0m vs {$baseline['timing']['median']}s");
            }
            
            // Add response time distribution histogram
            if (count($this->results) >= 10) {
                $times = array_column($this->results, 'time');
                $histogram = MicroChaos_Thresholds::generate_histogram($times);
                MicroChaos_Log::log($histogram);
            }
        }
    }
    
    /**
     * Save current results as baseline
     *
     * @param string $name Optional name for the baseline
     * @return array Baseline data
     */
    public function save_baseline(string $name = 'default'): array {
        $baseline = $this->generate_summary();
        $this->baseline_storage->save($name, $baseline);
        return $baseline;
    }

    /**
     * Get saved baseline data
     *
     * @param string $name Baseline name
     * @return array<string, mixed>|null Baseline data or null if not found
     */
    public function get_baseline(string $name = 'default'): ?array {
        return $this->baseline_storage->get($name);
    }

    /**
     * Export results to a file
     *
     * @param string $format Export format (json, csv)
     * @param string $path File path
     * @return bool Success status
     */
    public function export_results(string $format, string $path): bool {
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

    /**
     * Format large numbers in human-readable format (K, M, B)
     *
     * @param int $number Number to format
     * @return string Formatted number (e.g., "4.1M", "137K")
     */
    private function format_large_number(int $number): string {
        if ($number >= 1000000000) {
            return round($number / 1000000000, 1) . 'B';
        } elseif ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return (string)$number;
    }
}
