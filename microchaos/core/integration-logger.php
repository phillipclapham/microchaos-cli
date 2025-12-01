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
    private bool $enabled = false;

    /**
     * Test ID
     *
     * @var string
     */
    public string $test_id = '';
    
    /**
     * Constructor
     *
     * @param array<string, mixed> $options Logger options
     */
    public function __construct(array $options = []) {
        $this->enabled = isset($options['enabled']) ? (bool)$options['enabled'] : false;
        $this->test_id = isset($options['test_id']) ? $options['test_id'] : uniqid('mc_');
    }
    
    /**
     * Enable integration logging
     *
     * @param string|null $test_id Optional test ID to use
     */
    public function enable(?string $test_id = null): void {
        $this->enabled = true;
        if ($test_id) {
            $this->test_id = $test_id;
        }
    }

    /**
     * Disable integration logging
     */
    public function disable(): void {
        $this->enabled = false;
    }

    /**
     * Check if integration logging is enabled
     *
     * @return bool Enabled status
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }
    
    /**
     * Log test start event
     *
     * @param array<string, mixed> $config Test configuration
     */
    public function log_test_start(array $config): void {
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
     * @param array|null $resource_summary Resource summary if available
     * @param array|null $execution_metrics Execution timing and throughput metrics
     */
    public function log_test_complete(array $summary, ?array $resource_summary = null, ?array $execution_metrics = null): void {
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

        if ($execution_metrics) {
            $data['execution'] = $execution_metrics;
        }

        $this->log_event($data);
    }
    
    /**
     * Log a single request result
     *
     * @param array<string, mixed> $result Request result
     */
    public function log_request(array $result): void {
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
     * @param array<string, mixed> $resource_data Resource utilization data
     */
    public function log_resource_snapshot(array $resource_data): void {
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
     * @param array<string, mixed> $burst_summary Summary data for this burst
     */
    public function log_burst_complete(int $burst_number, int $requests_count, array $burst_summary): void {
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
     * @param array<string, mixed> $level_summary Summary for this concurrency level
     */
    public function log_progressive_level(int $concurrency, array $level_summary): void {
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
     * @param array<string, mixed> $tags Additional tags
     */
    public function log_metric(string $metric_name, mixed $value, array $tags = []): void {
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
     * @param array<string, mixed> $data Event data
     */
    private function log_event(array $data): void {
        // Add site URL to all events for multi-site monitoring
        $data['site_url'] = home_url();
        
        // Format: MICROCHAOS_METRICS|event_type|json_encoded_data
        $json_data = json_encode($data);
        $log_message = self::LOG_PREFIX . '|' . $data['event'] . '|' . $json_data;
        
        error_log($log_message);
    }
}