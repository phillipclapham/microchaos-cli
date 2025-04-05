<?php
/**
 * Integration Logger Component
 *
 * Provides standardized logging for external monitoring tools like Grafana/WP Cloud Insights.
 * Logs test events and metrics in a format that can be easily parsed by monitoring tools.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos Integration Logger class
 */
class MicroChaos_Integration_Logger {
    /**
     * Log prefix for all integration logs
     * 
     * @var string
     */
    const LOG_PREFIX = 'MICROCHAOS_METRICS';
    
    /**
     * Enabled status
     * 
     * @var bool
     */
    private $enabled = false;
    
    /**
     * Test ID
     * 
     * @var string
     */
    public $test_id = '';
    
    /**
     * Constructor
     * 
     * @param array $options Logger options
     */
    public function __construct($options = []) {
        $this->enabled = isset($options['enabled']) ? (bool)$options['enabled'] : false;
        $this->test_id = isset($options['test_id']) ? $options['test_id'] : uniqid('mc_');
    }
    
    /**
     * Enable integration logging
     * 
     * @param string|null $test_id Optional test ID to use
     */
    public function enable($test_id = null) {
        $this->enabled = true;
        if ($test_id) {
            $this->test_id = $test_id;
        }
    }
    
    /**
     * Disable integration logging
     */
    public function disable() {
        $this->enabled = false;
    }
    
    /**
     * Check if integration logging is enabled
     * 
     * @return bool Enabled status
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Log test start event
     * 
     * @param array $config Test configuration
     */
    public function log_test_start($config) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'test_start',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'config' => $config
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log test completion event
     * 
     * @param array $summary Test summary
     * @param array $resource_summary Resource summary if available
     */
    public function log_test_complete($summary, $resource_summary = null) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'test_complete',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'summary' => $summary
        ];
        
        if ($resource_summary) {
            $data['resource_summary'] = $resource_summary;
        }
        
        $this->log_event($data);
    }
    
    /**
     * Log a single request result
     * 
     * @param array $result Request result
     */
    public function log_request($result) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'request',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'result' => $result
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log resource utilization snapshot
     * 
     * @param array $resource_data Resource utilization data
     */
    public function log_resource_snapshot($resource_data) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'resource_snapshot',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'resource_data' => $resource_data
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log burst completion
     * 
     * @param int $burst_number Burst number
     * @param int $requests_count Number of requests in burst
     * @param array $burst_summary Summary data for this burst
     */
    public function log_burst_complete($burst_number, $requests_count, $burst_summary) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'burst_complete',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'burst_number' => $burst_number,
            'requests_count' => $requests_count,
            'burst_summary' => $burst_summary
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log progressive test level completion
     * 
     * @param int $concurrency Concurrency level
     * @param array $level_summary Summary for this concurrency level
     */
    public function log_progressive_level($concurrency, $level_summary) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'progressive_level',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'concurrency' => $concurrency,
            'summary' => $level_summary
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log custom metrics
     * 
     * @param string $metric_name Metric name
     * @param mixed $value Metric value
     * @param array $tags Additional tags
     */
    public function log_metric($metric_name, $value, $tags = []) {
        if (!$this->enabled) {
            return;
        }
        
        $data = [
            'event' => 'metric',
            'test_id' => $this->test_id,
            'timestamp' => time(),
            'metric' => $metric_name,
            'value' => $value,
            'tags' => $tags
        ];
        
        $this->log_event($data);
    }
    
    /**
     * Log an event with JSON-encoded data
     * 
     * @param array $data Event data
     */
    private function log_event($data) {
        // Add site URL to all events for multi-site monitoring
        $data['site_url'] = home_url();
        
        // Format: MICROCHAOS_METRICS|event_type|json_encoded_data
        $json_data = json_encode($data);
        $log_message = self::LOG_PREFIX . '|' . $data['event'] . '|' . $json_data;
        
        error_log($log_message);
    }
}