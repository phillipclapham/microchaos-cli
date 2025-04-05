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
     * Whether to track resource trends over time
     *
     * @var bool
     */
    private $track_trends = false;
    
    /**
     * Timestamp of monitoring start
     *
     * @var float
     */
    private $start_time = 0;

    /**
     * Constructor
     *
     * @param array $options Options for resource monitoring
     */
    public function __construct($options = []) {
        $this->resource_results = [];
        $this->track_trends = isset($options['track_trends']) ? (bool)$options['track_trends'] : false;
        $this->start_time = microtime(true);
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
        
        // Add timestamp for trend tracking
        if ($this->track_trends) {
            $result['timestamp'] = microtime(true);
            $result['elapsed'] = round($result['timestamp'] - $this->start_time, 2);
        }

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
     * 
     * @param array|null $baseline Optional baseline data for comparison
     * @param array|null $provided_summary Optional pre-generated summary
     * @param string|null $threshold_profile Optional threshold profile to use for formatting
     */
    public function report_summary($baseline = null, $provided_summary = null, $threshold_profile = null) {
        $summary = $provided_summary ?: $this->generate_summary();

        if (empty($summary)) {
            return;
        }

        if (class_exists('WP_CLI')) {
            // Format memory with threshold colors
            $avg_mem_formatted = MicroChaos_Thresholds::format_value($summary['memory']['avg'], 'memory_usage', $threshold_profile);
            $max_mem_formatted = MicroChaos_Thresholds::format_value($summary['memory']['max'], 'memory_usage', $threshold_profile);
            $avg_peak_formatted = MicroChaos_Thresholds::format_value($summary['peak_memory']['avg'], 'memory_usage', $threshold_profile);
            $max_peak_formatted = MicroChaos_Thresholds::format_value($summary['peak_memory']['max'], 'memory_usage', $threshold_profile);
            
            \WP_CLI::log("📊 Resource Utilization Summary:");
            \WP_CLI::log("   Memory Usage: Avg: {$avg_mem_formatted}, Median: {$summary['memory']['median']} MB, Min: {$summary['memory']['min']} MB, Max: {$max_mem_formatted}");
            \WP_CLI::log("   Peak Memory: Avg: {$avg_peak_formatted}, Median: {$summary['peak_memory']['median']} MB, Min: {$summary['peak_memory']['min']} MB, Max: {$max_peak_formatted}");
            \WP_CLI::log("   CPU Time (User): Avg: {$summary['user_time']['avg']}s, Median: {$summary['user_time']['median']}s, Min: {$summary['user_time']['min']}s, Max: {$summary['user_time']['max']}s");
            \WP_CLI::log("   CPU Time (System): Avg: {$summary['system_time']['avg']}s, Median: {$summary['system_time']['median']}s, Min: {$summary['system_time']['min']}s, Max: {$summary['system_time']['max']}s");
            
            // Add comparison with baseline if provided
            if ($baseline) {
                if (isset($baseline['memory'])) {
                    $mem_avg_change = $baseline['memory']['avg'] > 0 
                        ? (($summary['memory']['avg'] - $baseline['memory']['avg']) / $baseline['memory']['avg']) * 100 
                        : 0;
                    $mem_avg_change = round($mem_avg_change, 1);
                    
                    $change_indicator = $mem_avg_change <= 0 ? '↓' : '↑';
                    $change_color = $mem_avg_change <= 0 ? "\033[32m" : "\033[31m";
                    
                    \WP_CLI::log("   Comparison to Baseline:");
                    \WP_CLI::log("   - Avg Memory: {$change_color}{$change_indicator}{$mem_avg_change}%\033[0m vs {$baseline['memory']['avg']} MB");
                    
                    $mem_max_change = $baseline['memory']['max'] > 0 
                        ? (($summary['memory']['max'] - $baseline['memory']['max']) / $baseline['memory']['max']) * 100 
                        : 0;
                    $mem_max_change = round($mem_max_change, 1);
                    
                    $change_indicator = $mem_max_change <= 0 ? '↓' : '↑';
                    $change_color = $mem_max_change <= 0 ? "\033[32m" : "\033[31m";
                    \WP_CLI::log("   - Max Memory: {$change_color}{$change_indicator}{$mem_max_change}%\033[0m vs {$baseline['memory']['max']} MB");
                }
            }
            
            // Add memory usage visualization
            if (count($this->resource_results) >= 5) {
                $chart_data = [
                    'Memory' => $summary['memory']['avg'],
                    'Peak' => $summary['peak_memory']['avg'],
                    'MaxMem' => $summary['memory']['max'],
                    'MaxPeak' => $summary['peak_memory']['max'],
                ];
                
                $chart = MicroChaos_Thresholds::generate_chart($chart_data, "Memory Usage (MB)");
                \WP_CLI::log($chart);
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
            set_transient('microchaos_resource_baseline_' . $name, $baseline, 86400 * 30); // 30 days
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
            return get_transient('microchaos_resource_baseline_' . $name);
        }
        
        return null;
    }
    
    /**
     * Analyze resource usage trends
     * 
     * @return array Trend analysis data
     */
    public function analyze_trends() {
        if (!$this->track_trends || count($this->resource_results) < 3) {
            return null;
        }
        
        // Sort results by elapsed time
        $sorted_results = $this->resource_results;
        usort($sorted_results, function($a, $b) {
            return $a['elapsed'] <=> $b['elapsed'];
        });
        
        // Calculate slopes for memory, peak memory, and CPU time
        $memory_slope = $this->calculate_trend_slope(array_column($sorted_results, 'elapsed'), 
                                                    array_column($sorted_results, 'memory_usage'));
        $peak_memory_slope = $this->calculate_trend_slope(array_column($sorted_results, 'elapsed'), 
                                                         array_column($sorted_results, 'peak_memory'));
        $user_time_slope = $this->calculate_trend_slope(array_column($sorted_results, 'elapsed'), 
                                                       array_column($sorted_results, 'user_time'));
        
        // Calculate percentage changes
        $first_memory = $sorted_results[0]['memory_usage'];
        $last_memory = end($sorted_results)['memory_usage'];
        $memory_change_pct = $first_memory > 0 ? (($last_memory - $first_memory) / $first_memory) * 100 : 0;
        
        $first_peak = $sorted_results[0]['peak_memory'];
        $last_peak = end($sorted_results)['peak_memory'];
        $peak_change_pct = $first_peak > 0 ? (($last_peak - $first_peak) / $first_peak) * 100 : 0;
        
        // Determine if we see an unbounded growth pattern
        $memory_growth_pattern = $this->determine_growth_pattern($sorted_results, 'memory_usage');
        $peak_memory_growth_pattern = $this->determine_growth_pattern($sorted_results, 'peak_memory');
        
        return [
            'memory_usage' => [
                'slope' => round($memory_slope, 4),
                'change_percent' => round($memory_change_pct, 1),
                'pattern' => $memory_growth_pattern
            ],
            'peak_memory' => [
                'slope' => round($peak_memory_slope, 4),
                'change_percent' => round($peak_change_pct, 1),
                'pattern' => $peak_memory_growth_pattern
            ],
            'user_time' => [
                'slope' => round($user_time_slope, 4)
            ],
            'data_points' => count($sorted_results),
            'time_span' => end($sorted_results)['elapsed'] - $sorted_results[0]['elapsed'],
            'potentially_unbounded' => ($memory_growth_pattern === 'continuous_growth' || $peak_memory_growth_pattern === 'continuous_growth')
        ];
    }
    
    /**
     * Calculate the slope of a trend line (simple linear regression)
     * 
     * @param array $x X values (time)
     * @param array $y Y values (resource metric)
     * @return float Slope of trend line
     */
    private function calculate_trend_slope($x, $y) {
        $n = count($x);
        if ($n < 2) return 0;
        
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xx = 0;
        $sum_xy = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xx += $x[$i] * $x[$i];
            $sum_xy += $x[$i] * $y[$i];
        }
        
        // Avoid division by zero
        $denominator = $n * $sum_xx - $sum_x * $sum_x;
        if ($denominator == 0) return 0;
        
        // Calculate slope (m) of the line y = mx + b
        return ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
    }
    
    /**
     * Determine the growth pattern of a resource metric
     * 
     * @param array $results Sorted resource results
     * @param string $metric Metric to analyze (memory_usage, peak_memory)
     * @return string Growth pattern description
     */
    private function determine_growth_pattern($results, $metric) {
        $n = count($results);
        if ($n < 5) return 'insufficient_data';
        
        // Split the data into segments
        $segments = 4; // Analyze in 4 segments
        $segment_size = floor($n / $segments);
        
        $segment_averages = [];
        for ($i = 0; $i < $segments; $i++) {
            $start = $i * $segment_size;
            $end = min($start + $segment_size - 1, $n - 1);
            
            $segment_data = array_slice($results, $start, $end - $start + 1);
            $segment_averages[] = array_sum(array_column($segment_data, $metric)) / count($segment_data);
        }
        
        // Check if each segment is higher than the previous
        $continuous_growth = true;
        for ($i = 1; $i < $segments; $i++) {
            if ($segment_averages[$i] <= $segment_averages[$i-1]) {
                $continuous_growth = false;
                break;
            }
        }
        
        if ($continuous_growth) {
            // Calculate growth rate between first and last segment
            $growth_pct = ($segment_averages[$segments-1] - $segment_averages[0]) / $segment_averages[0] * 100;
            
            if ($growth_pct > 50) {
                return 'continuous_growth';
            } else {
                return 'moderate_growth';
            }
        }
        
        // Check if it's stabilizing (last segment similar to previous)
        $last_diff_pct = abs(($segment_averages[$segments-1] - $segment_averages[$segments-2]) / $segment_averages[$segments-2] * 100);
        if ($last_diff_pct < 5) {
            return 'stabilizing';
        }
        
        // Check if it's fluctuating
        return 'fluctuating';
    }
    
    /**
     * Generate ASCII trend charts for resource metrics
     * 
     * @param int $width Chart width
     * @param int $height Chart height
     * @return string ASCII charts
     */
    public function generate_trend_charts($width = 60, $height = 15) {
        if (!$this->track_trends || count($this->resource_results) < 5) {
            return "Insufficient data for trend visualization (need at least 5 data points).";
        }
        
        // Sort results by elapsed time
        $sorted_results = $this->resource_results;
        usort($sorted_results, function($a, $b) {
            return $a['elapsed'] <=> $b['elapsed'];
        });
        
        // Extract data for charts
        $times = array_column($sorted_results, 'elapsed');
        $memories = array_column($sorted_results, 'memory_usage');
        $peak_memories = array_column($sorted_results, 'peak_memory');
        
        // Create memory chart
        $memory_chart = $this->create_ascii_chart(
            $times, 
            $memories, 
            "Memory Usage Trend (MB over time)", 
            $width, 
            $height
        );
        
        // Create peak memory chart
        $peak_chart = $this->create_ascii_chart(
            $times, 
            $peak_memories, 
            "Peak Memory Trend (MB over time)", 
            $width, 
            $height
        );
        
        return $memory_chart . "\n" . $peak_chart;
    }
    
    /**
     * Create ASCII chart for a metric
     * 
     * @param array $x X values (time)
     * @param array $y Y values (resource metric)
     * @param string $title Chart title
     * @param int $width Chart width
     * @param int $height Chart height
     * @return string ASCII chart
     */
    private function create_ascii_chart($x, $y, $title, $width, $height) {
        $n = count($x);
        if ($n < 2) return "";
        
        // Find min/max values
        $min_x = min($x);
        $max_x = max($x);
        $min_y = min($y);
        $max_y = max($y);
        
        // Ensure range is non-zero
        $x_range = $max_x - $min_x;
        if ($x_range == 0) $x_range = 1;
        
        $y_range = $max_y - $min_y;
        if ($y_range == 0) $y_range = 1;
        
        // Create chart canvas
        $output = "\n   $title:\n";
        $chart = [];
        for ($i = 0; $i < $height; $i++) {
            $chart[$i] = str_split(str_repeat(' ', $width));
        }
        
        // Plot data points
        for ($i = 0; $i < $n; $i++) {
            $x_pos = round(($x[$i] - $min_x) / $x_range * ($width - 1));
            $y_pos = $height - 1 - round(($y[$i] - $min_y) / $y_range * ($height - 1));
            
            // Ensure within bounds
            $x_pos = max(0, min($width - 1, $x_pos));
            $y_pos = max(0, min($height - 1, $y_pos));
            
            // Plot point
            $chart[$y_pos][$x_pos] = '•';
        }
        
        // Add trend line (linear regression)
        $slope = $this->calculate_trend_slope($x, $y);
        $y_mean = array_sum($y) / $n;
        $x_mean = array_sum($x) / $n;
        $intercept = $y_mean - $slope * $x_mean;
        
        for ($x_pos = 0; $x_pos < $width; $x_pos++) {
            $x_val = $min_x + ($x_pos / ($width - 1)) * $x_range;
            $y_val = $slope * $x_val + $intercept;
            $y_pos = $height - 1 - round(($y_val - $min_y) / $y_range * ($height - 1));
            
            // Ensure within bounds
            if ($y_pos >= 0 && $y_pos < $height) {
                // Use different character for trend line to distinguish from data points
                if ($chart[$y_pos][$x_pos] == ' ') {
                    $chart[$y_pos][$x_pos] = '-';
                }
            }
        }
        
        // Add axis labels
        $output .= "   " . str_repeat(' ', strlen((string)$max_y) + 2) . "┌" . str_repeat('─', $width) . "┐\n";
        for ($i = 0; $i < $height; $i++) {
            $label_y = round($max_y - ($i / ($height - 1)) * $y_range, 1);
            $output .= sprintf("   %'.3s │%s│\n", $label_y, implode('', $chart[$i]));
        }
        $output .= "   " . str_repeat(' ', strlen((string)$max_y) + 2) . "└" . str_repeat('─', $width) . "┘\n";
        
        // X-axis labels (start, middle, end)
        $output .= sprintf("   %'.3s %'.3s%'.3s\n", 
                       round($min_x, 1),
                       str_repeat(' ', intval($width/2)) . round($min_x + $x_range/2, 1),
                       str_repeat(' ', intval($width/2) - 2) . round($max_x, 1));
        
        return $output;
    }
    
    /**
     * Report trend analysis to CLI
     */
    public function report_trends() {
        if (!$this->track_trends || count($this->resource_results) < 3) {
            if (class_exists('WP_CLI')) {
                \WP_CLI::log("📈 Resource Trend Analysis: Insufficient data for trend analysis.");
            }
            return;
        }
        
        $trends = $this->analyze_trends();
        
        if (class_exists('WP_CLI')) {
            \WP_CLI::log("\n📈 Resource Trend Analysis:");
            \WP_CLI::log("   Data Points: {$trends['data_points']} over {$trends['time_span']} seconds");
            
            // Memory usage trends
            $memory_change = $trends['memory_usage']['change_percent'];
            $memory_direction = $memory_change > 0 ? '↑' : '↓';
            $memory_color = $memory_change > 20 ? "\033[31m" : ($memory_change > 5 ? "\033[33m" : "\033[32m");
            \WP_CLI::log("   Memory Usage: {$memory_color}{$memory_direction}{$memory_change}%\033[0m over test duration");
            \WP_CLI::log("   Pattern: " . ucfirst(str_replace('_', ' ', $trends['memory_usage']['pattern'])));
            
            // Peak memory trends
            $peak_change = $trends['peak_memory']['change_percent'];
            $peak_direction = $peak_change > 0 ? '↑' : '↓';
            $peak_color = $peak_change > 20 ? "\033[31m" : ($peak_change > 5 ? "\033[33m" : "\033[32m");
            \WP_CLI::log("   Peak Memory: {$peak_color}{$peak_direction}{$peak_change}%\033[0m over test duration");
            \WP_CLI::log("   Pattern: " . ucfirst(str_replace('_', ' ', $trends['peak_memory']['pattern'])));
            
            // Warning about unbounded growth if detected
            if ($trends['potentially_unbounded']) {
                \WP_CLI::warning("⚠️ Potential memory leak detected! Resource usage shows continuous growth pattern.");
            }
            
            // Generate visual trend charts
            $charts = $this->generate_trend_charts();
            \WP_CLI::log($charts);
        }
    }
}
