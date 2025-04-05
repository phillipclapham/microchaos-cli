<?php
/**
 * Thresholds Component
 *
 * Defines thresholds for performance metrics and visualization helpers.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos Thresholds class
 */
class MicroChaos_Thresholds {
    // Response time thresholds (seconds)
    const RESPONSE_TIME_GOOD = 1.0;    // Response times under 1 second are good
    const RESPONSE_TIME_WARN = 2.0;    // Response times under 2 seconds are acceptable
    const RESPONSE_TIME_CRITICAL = 3.0; // Response times over 3 seconds are critical

    // Memory usage thresholds (percentage of PHP memory limit)
    const MEMORY_USAGE_GOOD = 50;      // Under 50% of PHP memory limit is good
    const MEMORY_USAGE_WARN = 70;      // Under 70% of PHP memory limit is acceptable
    const MEMORY_USAGE_CRITICAL = 85;  // Over 85% of PHP memory limit is critical

    // Error rate thresholds (percentage)
    const ERROR_RATE_GOOD = 1;         // Under 1% error rate is good
    const ERROR_RATE_WARN = 5;         // Under 5% error rate is acceptable
    const ERROR_RATE_CRITICAL = 10;    // Over 10% error rate is critical
    
    // Progressive load testing thresholds
    const PROGRESSIVE_STEP_INCREASE = 5;  // Default step size for progressive load increases
    const PROGRESSIVE_INITIAL_LOAD = 5;   // Default initial load for progressive testing
    const PROGRESSIVE_MAX_LOAD = 100;     // Default maximum load to try
    
    // Automated threshold calibration factors
    const AUTO_THRESHOLD_GOOD_FACTOR = 1.0;    // Base value multiplier for "good" threshold
    const AUTO_THRESHOLD_WARN_FACTOR = 1.5;    // Base value multiplier for "warning" threshold
    const AUTO_THRESHOLD_CRITICAL_FACTOR = 2.0; // Base value multiplier for "critical" threshold
    
    // Current custom threshold sets (dynamically set during calibration)
    private static $custom_thresholds = [];
    
    // Transient keys for stored thresholds
    const TRANSIENT_PREFIX = 'microchaos_thresholds_';
    const TRANSIENT_EXPIRY = 2592000; // 30 days in seconds

    /**
     * Format a value with color based on thresholds
     *
     * @param float $value The value to format
     * @param string $type The type of metric (response_time, memory_usage, error_rate)
     * @param string|null $profile Optional profile name for custom thresholds
     * @return string Formatted value with color codes
     */
    public static function format_value($value, $type, $profile = null) {
        switch ($type) {
            case 'response_time':
                $thresholds = self::get_thresholds('response_time', $profile);
                if ($value <= $thresholds['good']) {
                    return "\033[32m{$value}s\033[0m"; // Green
                } elseif ($value <= $thresholds['warn']) {
                    return "\033[33m{$value}s\033[0m"; // Yellow
                } else {
                    return "\033[31m{$value}s\033[0m"; // Red
                }
                break;
                
            case 'memory_usage':
                // Calculate percentage of PHP memory limit
                $memory_limit = self::get_php_memory_limit_mb();
                $percentage = ($value / $memory_limit) * 100;
                
                $thresholds = self::get_thresholds('memory_usage', $profile);
                if ($percentage <= $thresholds['good']) {
                    return "\033[32m{$value} MB\033[0m"; // Green
                } elseif ($percentage <= $thresholds['warn']) {
                    return "\033[33m{$value} MB\033[0m"; // Yellow
                } else {
                    return "\033[31m{$value} MB\033[0m"; // Red
                }
                break;
                
            case 'error_rate':
                $thresholds = self::get_thresholds('error_rate', $profile);
                if ($value <= $thresholds['good']) {
                    return "\033[32m{$value}%\033[0m"; // Green
                } elseif ($value <= $thresholds['warn']) {
                    return "\033[33m{$value}%\033[0m"; // Yellow
                } else {
                    return "\033[31m{$value}%\033[0m"; // Red
                }
                break;
                
            default:
                return "{$value}";
        }
    }
    
    /**
     * Get PHP memory limit in MB
     *
     * @return float Memory limit in MB
     */
    public static function get_php_memory_limit_mb() {
        $memory_limit = ini_get('memory_limit');
        $value = (int) $memory_limit;
        
        // Convert to MB if necessary
        if (stripos($memory_limit, 'G') !== false) {
            $value = $value * 1024;
        } elseif (stripos($memory_limit, 'K') !== false) {
            $value = $value / 1024;
        } elseif (stripos($memory_limit, 'M') === false) {
            // If no unit, assume bytes and convert to MB
            $value = $value / 1048576;
        }
        
        return $value > 0 ? $value : 128; // Default to 128MB if limit is unlimited (-1)
    }
    
    /**
     * Generate a simple ASCII bar chart
     *
     * @param array $values Array of values to chart
     * @param string $title Chart title
     * @param int $width Chart width in characters
     * @return string ASCII chart
     */
    public static function generate_chart($values, $title, $width = 40) {
        $max = max($values);
        if ($max == 0) $max = 1; // Avoid division by zero
        
        $output = "\n   $title:\n";
        
        foreach ($values as $label => $value) {
            $bar_length = round(($value / $max) * $width);
            $bar = str_repeat('█', $bar_length);
            $output .= sprintf("   %-10s [%-{$width}s] %s\n", $label, $bar, $value);
        }
        
        return $output;
    }
    
    /**
     * Get thresholds for a specific metric
     * 
     * @param string $type The type of metric (response_time, memory_usage, error_rate)
     * @param string|null $profile Optional profile name for custom thresholds
     * @return array Thresholds array with 'good', 'warn', and 'critical' keys
     */
    public static function get_thresholds($type, $profile = null) {
        // If we have custom thresholds for this profile and type, use them
        if ($profile && isset(self::$custom_thresholds[$profile][$type])) {
            return self::$custom_thresholds[$profile][$type];
        }
        
        // Otherwise use defaults
        switch ($type) {
            case 'response_time':
                return [
                    'good' => self::RESPONSE_TIME_GOOD,
                    'warn' => self::RESPONSE_TIME_WARN,
                    'critical' => self::RESPONSE_TIME_CRITICAL
                ];
            case 'memory_usage':
                return [
                    'good' => self::MEMORY_USAGE_GOOD,
                    'warn' => self::MEMORY_USAGE_WARN,
                    'critical' => self::MEMORY_USAGE_CRITICAL
                ];
            case 'error_rate':
                return [
                    'good' => self::ERROR_RATE_GOOD,
                    'warn' => self::ERROR_RATE_WARN,
                    'critical' => self::ERROR_RATE_CRITICAL
                ];
            default:
                return [
                    'good' => 0,
                    'warn' => 0,
                    'critical' => 0
                ];
        }
    }
    
    /**
     * Calibrate thresholds based on test results
     *
     * @param array $test_results Array containing test metrics
     * @param string $profile Profile name to save thresholds under
     * @param bool $persist Whether to persist thresholds to database
     * @return array Calculated thresholds
     */
    public static function calibrate_thresholds($test_results, $profile = 'default', $persist = true) {
        $thresholds = [];
        
        // Calculate response time thresholds if we have timing data
        if (isset($test_results['timing']) && isset($test_results['timing']['avg'])) {
            $base_response_time = $test_results['timing']['avg'];
            $thresholds['response_time'] = [
                'good' => round($base_response_time * self::AUTO_THRESHOLD_GOOD_FACTOR, 2),
                'warn' => round($base_response_time * self::AUTO_THRESHOLD_WARN_FACTOR, 2),
                'critical' => round($base_response_time * self::AUTO_THRESHOLD_CRITICAL_FACTOR, 2),
            ];
        }
        
        // Calculate error rate thresholds if we have error data
        if (isset($test_results['error_rate'])) {
            $base_error_rate = $test_results['error_rate'];
            // Add a minimum baseline for error rates
            $base_error_rate = max($base_error_rate, 0.5); // At least 0.5% for baseline
            
            $thresholds['error_rate'] = [
                'good' => round($base_error_rate * self::AUTO_THRESHOLD_GOOD_FACTOR, 1),
                'warn' => round($base_error_rate * self::AUTO_THRESHOLD_WARN_FACTOR, 1),
                'critical' => round($base_error_rate * self::AUTO_THRESHOLD_CRITICAL_FACTOR, 1),
            ];
        }
        
        // Calculate memory thresholds if we have memory data
        if (isset($test_results['memory']) && isset($test_results['memory']['avg'])) {
            $memory_limit = self::get_php_memory_limit_mb();
            $base_percentage = ($test_results['memory']['avg'] / $memory_limit) * 100;
            
            $thresholds['memory_usage'] = [
                'good' => round($base_percentage * self::AUTO_THRESHOLD_GOOD_FACTOR, 1),
                'warn' => round($base_percentage * self::AUTO_THRESHOLD_WARN_FACTOR, 1),
                'critical' => round($base_percentage * self::AUTO_THRESHOLD_CRITICAL_FACTOR, 1),
            ];
        }
        
        // Store custom thresholds in static property
        self::$custom_thresholds[$profile] = $thresholds;
        
        // Persist thresholds if requested
        if ($persist) {
            self::save_thresholds($profile, $thresholds);
        }
        
        return $thresholds;
    }
    
    /**
     * Save thresholds to database
     *
     * @param string $profile Profile name
     * @param array $thresholds Thresholds to save
     * @return bool Success status
     */
    public static function save_thresholds($profile, $thresholds) {
        if (function_exists('set_transient')) {
            return set_transient(self::TRANSIENT_PREFIX . $profile, $thresholds, self::TRANSIENT_EXPIRY);
        }
        return false;
    }
    
    /**
     * Load thresholds from database
     *
     * @param string $profile Profile name
     * @return array|bool Thresholds array or false if not found
     */
    public static function load_thresholds($profile) {
        if (function_exists('get_transient')) {
            $thresholds = get_transient(self::TRANSIENT_PREFIX . $profile);
            if ($thresholds) {
                self::$custom_thresholds[$profile] = $thresholds;
                return $thresholds;
            }
        }
        return false;
    }
    
    /**
     * Generate a simple distribution histogram
     *
     * @param array $times Array of response times
     * @param int $buckets Number of buckets for distribution
     * @return string ASCII histogram
     */
    public static function generate_histogram($times, $buckets = 5) {
        if (empty($times)) {
            return "";
        }
        
        $min = min($times);
        $max = max($times);
        $range = $max - $min;
        
        // Avoid division by zero if all values are the same
        if ($range == 0) {
            $range = 0.1;
        }
        
        $bucket_size = $range / $buckets;
        $histogram = array_fill(0, $buckets, 0);
        
        foreach ($times as $time) {
            $bucket = min($buckets - 1, floor(($time - $min) / $bucket_size));
            $histogram[$bucket]++;
        }
        
        $max_count = max($histogram);
        $width = 30;
        
        $output = "\n   Response Time Distribution:\n";
        
        for ($i = 0; $i < $buckets; $i++) {
            $lower = round($min + ($i * $bucket_size), 2);
            $upper = round($min + (($i + 1) * $bucket_size), 2);
            $count = $histogram[$i];
            $bar_length = ($max_count > 0) ? round(($count / $max_count) * $width) : 0;
            $bar = str_repeat('█', $bar_length);
            
            $output .= sprintf("   %5.2fs - %5.2fs [%-{$width}s] %d\n", $lower, $upper, $bar, $count);
        }
        
        return $output;
    }
}