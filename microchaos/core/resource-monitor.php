<?php
/**
 * Resource Monitor Component
 *
 * Tracks and reports on system resource usage during load testing.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * Resource Monitor class
 */
class MicroChaos_Resource_Monitor {
    /**
     * Resource results storage
     *
     * @var array
     */
    private $resource_results = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->resource_results = [];
    }

    /**
     * Log current resource utilization
     *
     * @return array Current resource usage data
     */
    public function log_resource_utilization() {
        $memory_usage = round(memory_get_usage() / 1024 / 1024, 2);
        $peak_memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
        $ru = getrusage();
        $user_time = round($ru['ru_utime.tv_sec'] + $ru['ru_utime.tv_usec'] / 1e6, 2);
        $system_time = round($ru['ru_stime.tv_sec'] + $ru['ru_stime.tv_usec'] / 1e6, 2);

        if (class_exists('WP_CLI')) {
            \WP_CLI::log("🔍 Resources: Memory Usage: {$memory_usage} MB, Peak Memory: {$peak_memory} MB, CPU Time: User {$user_time}s, System {$system_time}s");
        }

        $result = [
            'memory_usage' => $memory_usage,
            'peak_memory'  => $peak_memory,
            'user_time'    => $user_time,
            'system_time'  => $system_time,
        ];

        $this->resource_results[] = $result;
        return $result;
    }

    /**
     * Get all resource utilization results
     *
     * @return array Resource utilization data
     */
    public function get_resource_results() {
        return $this->resource_results;
    }

    /**
     * Generate resource utilization summary report
     *
     * @return array Summary metrics
     */
    public function generate_summary() {
        if (empty($this->resource_results)) {
            return [];
        }

        $n = count($this->resource_results);

        // Memory usage stats
        $mem_usages = array_column($this->resource_results, 'memory_usage');
        sort($mem_usages);
        $avg_memory_usage = round(array_sum($mem_usages) / $n, 2);
        $median_memory_usage = round($mem_usages[floor($n / 2)], 2);

        // Peak memory stats
        $peak_memories = array_column($this->resource_results, 'peak_memory');
        sort($peak_memories);
        $avg_peak_memory = round(array_sum($peak_memories) / $n, 2);
        $median_peak_memory = round($peak_memories[floor($n / 2)], 2);

        // User CPU time stats
        $user_times = array_column($this->resource_results, 'user_time');
        sort($user_times);
        $avg_user_time = round(array_sum($user_times) / $n, 2);
        $median_user_time = round($user_times[floor($n / 2)], 2);

        // System CPU time stats
        $system_times = array_column($this->resource_results, 'system_time');
        sort($system_times);
        $avg_system_time = round(array_sum($system_times) / $n, 2);
        $median_system_time = round($system_times[floor($n / 2)], 2);

        return [
            'samples' => $n,
            'memory' => [
                'avg' => $avg_memory_usage,
                'median' => $median_memory_usage,
                'min' => round(min($mem_usages), 2),
                'max' => round(max($mem_usages), 2),
            ],
            'peak_memory' => [
                'avg' => $avg_peak_memory,
                'median' => $median_peak_memory,
                'min' => round(min($peak_memories), 2),
                'max' => round(max($peak_memories), 2),
            ],
            'user_time' => [
                'avg' => $avg_user_time,
                'median' => $median_user_time,
                'min' => round(min($user_times), 2),
                'max' => round(max($user_times), 2),
            ],
            'system_time' => [
                'avg' => $avg_system_time,
                'median' => $median_system_time,
                'min' => round(min($system_times), 2),
                'max' => round(max($system_times), 2),
            ],
        ];
    }

    /**
     * Output resource summary to CLI
     */
    public function report_summary() {
        $summary = $this->generate_summary();

        if (empty($summary)) {
            return;
        }

        if (class_exists('WP_CLI')) {
            \WP_CLI::log("📊 Resource Utilization Summary:");
            \WP_CLI::log("   Avg Memory Usage: {$summary['memory']['avg']} MB, Median: {$summary['memory']['median']} MB");
            \WP_CLI::log("   Avg Peak Memory: {$summary['peak_memory']['avg']} MB, Median: {$summary['peak_memory']['median']} MB");
            \WP_CLI::log("   Avg CPU Time (User): {$summary['user_time']['avg']}s, Median: {$summary['user_time']['median']}s");
            \WP_CLI::log("   Avg CPU Time (System): {$summary['system_time']['avg']}s, Median: {$summary['system_time']['median']}s");
        }
    }
}
