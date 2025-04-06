<?php
/**
 * Parallel Test Component
 *
 * Handles parallel testing functionality including test plan parsing, worker management,
 * and result aggregation for MicroChaos CLI.
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    exit;
}

/**
 * MicroChaos Parallel Test class
 */
class MicroChaos_ParallelTest {
    /**
     * Test plans storage
     *
     * @var array
     */
    private $test_plans = [];

    /**
     * Number of parallel workers
     *
     * @var int
     */
    private $workers = 3;

    /**
     * Output format
     *
     * @var string
     */
    private $output_format = 'table';

    /**
     * Results collection
     *
     * @var array
     */
    private $results = [];
    
    /**
     * Worker process IDs
     * 
     * @var array
     */
    private $worker_pids = [];
    
    /**
     * Job queue for distribution
     * 
     * @var array
     */
    private $job_queue = [];
    
    /**
     * Results storage path
     * 
     * @var string
     */
    private $temp_dir;
    
    /**
     * Is parallel execution supported
     * 
     * @var bool
     */
    private $parallel_supported = false;

    /**
     * Results summary by test plan
     *
     * @var array
     */
    private $results_summary = [];

    /**
     * Global test execution timeout (seconds)
     *
     * @var int
     */
    private $global_timeout = 600; // 10 minutes default
    
    /**
     * Run parallel load tests using multiple workers.
     *
     * ## DESCRIPTION
     *
     * Runs multiple load tests in parallel using a JSON test plan configuration.
     * This allows simulating more realistic mixed traffic patterns, such as anonymous users
     * browsing products while logged-in users checkout simultaneously.
     *
     * The test plan can be provided either as a direct JSON string or as a path to a JSON file.
     * 
     * ## OPTIONS
     *
     * [--file=<path>]
     * : Path to a JSON file containing test plan(s)
     *
     * [--plan=<json>]
     * : JSON string containing test plan(s) directly in the command
     *
     * [--workers=<number>]
     * : Number of parallel workers to use. Default: 3
     *
     * [--output=<format>]
     * : Output format. Options: json, table, csv. Default: table
     * 
     * [--timeout=<seconds>]
     * : Global timeout for test execution in seconds. Default: 600 (10 minutes)
     * 
     * [--export=<path>]
     * : Export results to specified file path (relative to wp-content directory)
     * 
     * [--export-format=<format>]
     * : Format for exporting results. Options: json, csv. Default: json
     * 
     * [--export-detail=<level>]
     * : Detail level for exported results. Options: summary, full. Default: summary
     * 
     * [--percentiles=<list>]
     * : Comma-separated list of percentiles to calculate (e.g., 90,95,99). Default: 95,99
     * 
     * [--baseline=<name>]
     * : Compare results with a previously saved baseline
     * 
     * [--save-baseline=<name>]
     * : Save current results as a baseline for future comparisons
     * 
     * [--callback-url=<url>]
     * : Send test results to this URL upon completion (HTTP POST)
     *
     * ## EXAMPLES
     *
     *     # Run parallel tests defined in a JSON file
     *     wp microchaos paralleltest --file=test-plans.json
     *
     *     # Run parallel tests with a JSON string
     *     wp microchaos paralleltest --plan='[{"name":"Homepage Test","endpoint":"home","requests":50},{"name":"Checkout Test","endpoint":"checkout","requests":25,"auth":"user@example.com"}]'
     *
     *     # Run parallel tests with 5 workers
     *     wp microchaos paralleltest --file=test-plans.json --workers=5
     *
     *     # Run parallel tests and output results as JSON
     *     wp microchaos paralleltest --file=test-plans.json --output=json
     *     
     *     # Run parallel tests with a 5-minute timeout
     *     wp microchaos paralleltest --file=test-plans.json --timeout=300
     *     
     *     # Run parallel tests and export results to a file
     *     wp microchaos paralleltest --file=test-plans.json --export=microchaos/results.json
     *     
     *     # Run tests and export results with detailed information
     *     wp microchaos paralleltest --file=test-plans.json --export=results.csv --export-format=csv --export-detail=full
     *     
     *     # Calculate additional percentiles and include them in results
     *     wp microchaos paralleltest --file=test-plans.json --percentiles=50,75,90,95,99
     *     
     *     # Save results as baseline and compare with previous baseline
     *     wp microchaos paralleltest --file=test-plans.json --save-baseline=api-test
     *     wp microchaos paralleltest --file=test-plans.json --baseline=api-test
     *
     * @param array $args Command arguments
     * @param array $assoc_args Command options
     */
    public function run($args, $assoc_args) {
        // Parse command options
        $file_path = $assoc_args['file'] ?? null;
        $json_plan = $assoc_args['plan'] ?? null;
        $this->workers = intval($assoc_args['workers'] ?? 3);
        $this->output_format = $assoc_args['output'] ?? 'table';
        $this->global_timeout = intval($assoc_args['timeout'] ?? 600);
        $export_path = $assoc_args['export'] ?? null;
        $export_format = $assoc_args['export-format'] ?? 'json';
        $export_detail = $assoc_args['export-detail'] ?? 'summary';
        $percentiles = isset($assoc_args['percentiles']) ? 
            array_map('intval', explode(',', $assoc_args['percentiles'])) : 
            [95, 99];
        $baseline = $assoc_args['baseline'] ?? null;
        $save_baseline = $assoc_args['save-baseline'] ?? null;
        $callback_url = $assoc_args['callback-url'] ?? null;

        // Validate input parameters
        if (!$file_path && !$json_plan) {
            \WP_CLI::error("You must provide either --file or --plan parameter.");
        }

        // Parse test plans
        if ($file_path) {
            $this->load_test_plans_from_file($file_path);
        } elseif ($json_plan) {
            $this->parse_test_plans_json($json_plan);
        }

        // Check if we have valid test plans
        if (empty($this->test_plans)) {
            \WP_CLI::error("No valid test plans found. Please check your input.");
        }

        // Setup signal handling for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handle_signal']);
            pcntl_signal(SIGTERM, [$this, 'handle_signal']);
        }

        \WP_CLI::log("🚀 MicroChaos Parallel Test Started");
        \WP_CLI::log("-> Test Plans: " . count($this->test_plans));
        \WP_CLI::log("-> Workers: " . $this->workers);
        \WP_CLI::log("-> Timeout: " . $this->global_timeout . " seconds");
        \WP_CLI::log("-> Output Format: " . $this->output_format);
        if ($export_path) {
            \WP_CLI::log("-> Export: " . $export_path . " (Format: " . $export_format . ", Detail: " . $export_detail . ")");
        }
        if ($percentiles) {
            \WP_CLI::log("-> Percentiles: " . implode(', ', $percentiles));
        }
        if ($baseline) {
            \WP_CLI::log("-> Comparing with baseline: " . $baseline);
        }
        if ($save_baseline) {
            \WP_CLI::log("-> Will save results as baseline: " . $save_baseline);
        }
        if ($callback_url) {
            \WP_CLI::log("-> Results will be sent to: " . $callback_url);
        }

        // Display test plan summary
        $this->display_test_plan_summary();
        
        // Check if parallel execution is supported (pcntl extension)
        $this->parallel_supported = extension_loaded('pcntl');
        if (!$this->parallel_supported) {
            \WP_CLI::warning("📢 Parallel execution not supported on this system (pcntl extension not available).");
            \WP_CLI::log("-> Falling back to sequential execution with simulated parallelism.");
        } else {
            \WP_CLI::log("-> Parallel execution enabled with {$this->workers} workers.");
        }
        
        // Create temp directory for inter-process communication
        $this->setup_temp_directory();
        
        // Prepare job queue
        $this->prepare_job_queue();
        
        // Setup integration logger for metrics
        $logger = new MicroChaos_Integration_Logger(['enabled' => true]);
        $logger->log_test_start([
            'test_plans' => count($this->test_plans),
            'workers' => $this->workers,
            'output_format' => $this->output_format,
            'parallel_mode' => $this->parallel_supported ? 'parallel' : 'sequential',
            'job_count' => count($this->job_queue)
        ]);
        
        // Start resource monitoring
        $resource_monitor = new MicroChaos_Resource_Monitor(['track_trends' => true]);
        $resource_monitor->log_resource_utilization();
        
        // Launch workers and execute tests
        $this->execute_tests();
        
        // Final resource checkpoint
        $resource_monitor->log_resource_utilization();
        $resource_summary = $resource_monitor->generate_summary();
        $resource_monitor->report_summary(null, $resource_summary);
        
        // If trend tracking is enabled, report trends
        if (count($resource_monitor->get_resource_results()) >= 3) {
            $resource_monitor->report_trends();
        }
        
        // Cleanup temp files and analyze results
        $this->cleanup_temp_files();
        
        // Calculate additional percentiles if specified
        if (!empty($percentiles)) {
            $this->calculate_percentiles($percentiles);
        }
        
        // Load baseline for comparison if specified
        $baseline_data = null;
        if ($baseline) {
            $baseline_data = $this->load_baseline($baseline);
        }
        
        // Format and display results based on output format
        $this->output_formatted_results($baseline_data);
        
        // Save as baseline if requested
        if ($save_baseline) {
            $this->save_baseline($save_baseline);
        }
        
        // Export results if requested
        if ($export_path) {
            $this->export_results($export_path, $export_format, $export_detail);
        }
        
        // Send results to callback URL if specified
        if ($callback_url) {
            $this->send_results_to_callback($callback_url);
        }
        
        // Log test completion to integration logger
        $logger->log_test_complete(
            $this->results_summary['overall'] ?? [], 
            $resource_summary
        );
        
        \WP_CLI::success("🎉 Parallel Test Execution Complete");
        \WP_CLI::log("🏗️ Phase 4 implementation completed: Reporting & Output");
    }
    
    /**
     * Handle SIGINT/SIGTERM signals for graceful shutdown
     * 
     * @param int $signo Signal number
     */
    public function handle_signal($signo) {
        // Only handle these signals in the parent process
        if (!empty($this->worker_pids)) {
            \WP_CLI::log("\n⚠️ Received termination signal. Shutting down workers...");
            
            // Terminate all child processes
            foreach ($this->worker_pids as $pid) {
                posix_kill($pid, SIGTERM);
            }
            
            // Wait for all processes to terminate
            foreach ($this->worker_pids as $pid) {
                pcntl_waitpid($pid, $status);
            }
            
            // Delete temp directory
            $this->delete_temp_directory();
            
            \WP_CLI::log("💤 Parallel test execution terminated by user.");
            exit(1);
        }
    }
    
    /**
     * Export results to a file in the specified format
     * 
     * @param string $export_path Path to export file (relative to wp-content)
     * @param string $format Export format (json, csv)
     * @param string $detail_level Detail level (summary, full)
     * @return bool Success status
     */
    private function export_results($export_path, $format = 'json', $detail_level = 'summary') {
        if (!in_array($format, ['json', 'csv'])) {
            \WP_CLI::warning("Unsupported export format '{$format}', defaulting to json");
            $format = 'json';
        }
        
        if (!in_array($detail_level, ['summary', 'full'])) {
            \WP_CLI::warning("Unsupported detail level '{$detail_level}', defaulting to summary");
            $detail_level = 'summary';
        }
        
        // Create reporting engine for export
        $reporting_engine = new MicroChaos_Reporting_Engine();
        $reporting_engine->add_results($this->results);
        
        // Format output path
        $full_export_path = trailingslashit(WP_CONTENT_DIR) . ltrim($export_path, '/');
        $dir = dirname($full_export_path);
        
        // Ensure directory exists
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Export based on format and detail level
        if ($format === 'json') {
            if ($detail_level === 'summary') {
                $data = json_encode([
                    'meta' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'test_plans' => count($this->test_plans),
                        'workers' => $this->workers,
                        'total_requests' => count($this->results)
                    ],
                    'summary' => $this->results_summary,
                ], JSON_PRETTY_PRINT);
            } else {
                $data = json_encode([
                    'meta' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'test_plans' => count($this->test_plans),
                        'workers' => $this->workers,
                        'total_requests' => count($this->results)
                    ],
                    'summary' => $this->results_summary,
                    'results' => $this->results,
                    'config' => [
                        'test_plans' => $this->test_plans,
                        'job_count' => count($this->job_queue),
                        'parallel_supported' => $this->parallel_supported
                    ]
                ], JSON_PRETTY_PRINT);
            }
            
            $success = (bool) file_put_contents($full_export_path, $data);
        } else {
            // CSV format
            $fp = fopen($full_export_path, 'w');
            if (!$fp) {
                \WP_CLI::error("Could not open file for writing: " . $full_export_path);
                return false;
            }
            
            if ($detail_level === 'summary') {
                // Write summary CSV
                // Headers
                fputcsv($fp, ['Test Plan', 'Requests', 'Success', 'Errors', 'Error Rate', 'Avg Time', 'Median Time', 'Min Time', 'Max Time']);
                
                // Add overall results first
                if (isset($this->results_summary['overall'])) {
                    $overall = $this->results_summary['overall'];
                    fputcsv($fp, [
                        'OVERALL',
                        $overall['count'],
                        $overall['success'],
                        $overall['errors'],
                        $overall['error_rate'] . '%',
                        $overall['timing']['avg'],
                        $overall['timing']['median'],
                        $overall['timing']['min'],
                        $overall['timing']['max']
                    ]);
                }
                
                // Add individual test plan results
                foreach ($this->results_summary as $plan_name => $summary) {
                    if ($plan_name === 'overall') continue;
                    
                    fputcsv($fp, [
                        $plan_name,
                        $summary['count'],
                        $summary['success'],
                        $summary['errors'],
                        $summary['error_rate'] . '%',
                        $summary['timing']['avg'],
                        $summary['timing']['median'],
                        $summary['timing']['min'],
                        $summary['timing']['max']
                    ]);
                }
            } else {
                // Detailed CSV with all results
                // Headers for detailed results
                $headers = ['Job ID', 'Plan Name', 'Worker ID', 'Timestamp', 'URL', 'Status Code', 'Response Time'];
                
                // Add percentile headers if available
                if (isset($this->results_summary['overall']['percentiles'])) {
                    foreach ($this->results_summary['overall']['percentiles'] as $percentile => $value) {
                        $headers[] = "P{$percentile}";
                    }
                }
                
                fputcsv($fp, $headers);
                
                // Add detailed results
                foreach ($this->results as $result) {
                    $row = [
                        $result['job_id'] ?? 'N/A',
                        $result['plan_name'] ?? 'N/A',
                        $result['worker_id'] ?? 'N/A',
                        date('Y-m-d H:i:s', $result['timestamp'] ?? time()),
                        $result['url'] ?? 'N/A',
                        $result['code'] ?? 0,
                        $result['time'] ?? 0
                    ];
                    
                    fputcsv($fp, $row);
                }
            }
            
            fclose($fp);
            $success = true;
        }
        
        if ($success) {
            \WP_CLI::success("Results exported to " . $full_export_path);
        } else {
            \WP_CLI::error("Failed to export results to " . $full_export_path);
        }
        
        return $success;
    }
    
    /**
     * Calculate percentiles for response times
     * 
     * @param array $percentiles Array of percentiles to calculate (e.g., [50, 90, 95, 99])
     */
    private function calculate_percentiles($percentiles) {
        if (empty($this->results)) {
            return;
        }
        
        \WP_CLI::log("📊 Calculating response time percentiles: " . implode(', ', $percentiles));
        
        // Group results by test plan
        $results_by_plan = [];
        foreach ($this->results as $result) {
            if (!isset($result['plan_name'])) {
                continue;
            }
            
            $plan_name = $result['plan_name'];
            if (!isset($results_by_plan[$plan_name])) {
                $results_by_plan[$plan_name] = [];
            }
            
            $results_by_plan[$plan_name][] = $result;
        }
        
        // Calculate percentiles for each test plan
        foreach ($results_by_plan as $plan_name => $plan_results) {
            $times = array_column($plan_results, 'time');
            sort($times);
            
            $plan_percentiles = [];
            foreach ($percentiles as $percentile) {
                $index = ceil(($percentile / 100) * count($times)) - 1;
                if ($index < 0) $index = 0;
                $plan_percentiles[$percentile] = round($times[$index], 4);
            }
            
            // Add percentiles to results summary
            if (isset($this->results_summary[$plan_name])) {
                $this->results_summary[$plan_name]['percentiles'] = $plan_percentiles;
            }
        }
        
        // Calculate overall percentiles
        $all_times = array_column($this->results, 'time');
        sort($all_times);
        
        $overall_percentiles = [];
        foreach ($percentiles as $percentile) {
            $index = ceil(($percentile / 100) * count($all_times)) - 1;
            if ($index < 0) $index = 0;
            $overall_percentiles[$percentile] = round($all_times[$index], 4);
        }
        
        // Add overall percentiles
        if (isset($this->results_summary['overall'])) {
            $this->results_summary['overall']['percentiles'] = $overall_percentiles;
        }
    }
    
    /**
     * Save current results as a baseline for future comparison
     * 
     * @param string $name Baseline name
     * @return bool Success status
     */
    private function save_baseline($name) {
        if (empty($this->results_summary)) {
            \WP_CLI::warning("No results to save as baseline.");
            return false;
        }
        
        $baseline_data = [
            'timestamp' => time(),
            'summary' => $this->results_summary,
            'metadata' => [
                'test_plans' => count($this->test_plans),
                'workers' => $this->workers,
                'request_count' => count($this->results)
            ]
        ];
        
        if (function_exists('set_transient')) {
            $transient_name = 'microchaos_paralleltest_baseline_' . sanitize_key($name);
            $result = set_transient($transient_name, $baseline_data, 60 * 60 * 24 * 30); // 30 days
            
            if ($result) {
                \WP_CLI::success("✅ Baseline '{$name}' saved successfully.");
                return true;
            } else {
                \WP_CLI::warning("Failed to save baseline '{$name}'.");
                return false;
            }
        } else {
            // Fallback to file-based storage if transients are not available
            $baseline_dir = WP_CONTENT_DIR . '/microchaos/baselines';
            if (!file_exists($baseline_dir)) {
                mkdir($baseline_dir, 0755, true);
            }
            
            $baseline_file = $baseline_dir . '/' . sanitize_file_name($name) . '.json';
            $result = file_put_contents($baseline_file, json_encode($baseline_data, JSON_PRETTY_PRINT));
            
            if ($result) {
                \WP_CLI::success("✅ Baseline '{$name}' saved to file: {$baseline_file}");
                return true;
            } else {
                \WP_CLI::warning("Failed to save baseline '{$name}' to file.");
                return false;
            }
        }
    }
    
    /**
     * Load a previously saved baseline
     * 
     * @param string $name Baseline name
     * @return array|null Baseline data or null if not found
     */
    private function load_baseline($name) {
        $baseline_data = null;
        
        if (function_exists('get_transient')) {
            $transient_name = 'microchaos_paralleltest_baseline_' . sanitize_key($name);
            $baseline_data = get_transient($transient_name);
        }
        
        if ($baseline_data === false) {
            // Try file-based storage
            $baseline_file = WP_CONTENT_DIR . '/microchaos/baselines/' . sanitize_file_name($name) . '.json';
            if (file_exists($baseline_file)) {
                $file_content = file_get_contents($baseline_file);
                $baseline_data = json_decode($file_content, true);
            }
        }
        
        if ($baseline_data) {
            \WP_CLI::log("📋 Loaded baseline '{$name}' from " . date('Y-m-d H:i:s', $baseline_data['timestamp']));
            return $baseline_data;
        } else {
            \WP_CLI::warning("⚠️ Baseline '{$name}' not found.");
            return null;
        }
    }
    
    /**
     * Send results to a callback URL
     * 
     * @param string $url Callback URL
     * @return bool Success status
     */
    private function send_results_to_callback($url) {
        if (empty($this->results_summary)) {
            \WP_CLI::warning("No results to send to callback URL.");
            return false;
        }
        
        $payload = [
            'timestamp' => time(),
            'summary' => $this->results_summary,
            'metadata' => [
                'test_plans' => count($this->test_plans),
                'workers' => $this->workers,
                'parallel_mode' => $this->parallel_supported ? 'parallel' : 'sequential',
                'request_count' => count($this->results)
            ]
        ];
        
        $args = [
            'body' => json_encode($payload),
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ];
        
        \WP_CLI::log("📡 Sending results to callback URL: {$url}");
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            \WP_CLI::warning("⚠️ Failed to send results to callback URL: " . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code >= 200 && $response_code < 300) {
            \WP_CLI::success("✅ Results sent successfully to callback URL.");
            return true;
        } else {
            \WP_CLI::warning("⚠️ Callback URL returned error code: {$response_code}");
            return false;
        }
    }
    
    /**
     * Output formatted results based on the specified output format
     * 
     * @param array|null $baseline_data Optional baseline data for comparison
     */
    private function output_formatted_results($baseline_data = null) {
        if (empty($this->results_summary)) {
            \WP_CLI::warning("No results to output.");
            return;
        }
        
        switch ($this->output_format) {
            case 'json':
                $this->output_json_results($baseline_data);
                break;
                
            case 'csv':
                $this->output_csv_results();
                break;
                
            case 'table':
            default:
                $this->output_table_results($baseline_data);
                break;
        }
    }
    
    /**
     * Output results in JSON format
     * 
     * @param array|null $baseline_data Optional baseline data for comparison
     */
    private function output_json_results($baseline_data = null) {
        $output = [
            'timestamp' => time(),
            'summary' => $this->results_summary,
            'metadata' => [
                'test_plans' => count($this->test_plans),
                'workers' => $this->workers,
                'parallel_mode' => $this->parallel_supported ? 'parallel' : 'sequential',
                'request_count' => count($this->results)
            ]
        ];
        
        if ($baseline_data) {
            $output['baseline_comparison'] = $this->generate_baseline_comparison($baseline_data);
        }
        
        \WP_CLI::log(json_encode($output, JSON_PRETTY_PRINT));
    }
    
    /**
     * Output results in CSV format
     */
    private function output_csv_results() {
        $output = "Test Plan,Requests,Success,Errors,Error Rate,Avg Time,Median Time,Min Time,Max Time\n";
        
        // Add overall results first
        if (isset($this->results_summary['overall'])) {
            $overall = $this->results_summary['overall'];
            $output .= sprintf(
                "OVERALL,%d,%d,%d,%.1f%%,%.4fs,%.4fs,%.4fs,%.4fs\n",
                $overall['count'],
                $overall['success'],
                $overall['errors'],
                $overall['error_rate'],
                $overall['timing']['avg'],
                $overall['timing']['median'],
                $overall['timing']['min'],
                $overall['timing']['max']
            );
        }
        
        // Add individual test plan results
        foreach ($this->results_summary as $plan_name => $summary) {
            if ($plan_name === 'overall') continue;
            
            $output .= sprintf(
                "%s,%d,%d,%d,%.1f%%,%.4fs,%.4fs,%.4fs,%.4fs\n",
                $plan_name,
                $summary['count'],
                $summary['success'],
                $summary['errors'],
                $summary['error_rate'],
                $summary['timing']['avg'],
                $summary['timing']['median'],
                $summary['timing']['min'],
                $summary['timing']['max']
            );
        }
        
        // Add percentiles if available
        if (isset($this->results_summary['overall']['percentiles'])) {
            $output .= "\nResponse Time Percentiles:\n";
            $output .= "Test Plan";
            
            // Get all unique percentiles
            $all_percentiles = [];
            foreach ($this->results_summary as $plan_name => $summary) {
                if (isset($summary['percentiles'])) {
                    foreach ($summary['percentiles'] as $percentile => $value) {
                        $all_percentiles[$percentile] = true;
                    }
                }
            }
            
            // Sort percentiles
            $all_percentiles = array_keys($all_percentiles);
            sort($all_percentiles);
            
            // Add percentile headers
            foreach ($all_percentiles as $percentile) {
                $output .= ",P{$percentile}";
            }
            $output .= "\n";
            
            // Add percentiles for each test plan
            foreach ($this->results_summary as $plan_name => $summary) {
                if (!isset($summary['percentiles'])) continue;
                
                $output .= $plan_name === 'overall' ? "OVERALL" : $plan_name;
                
                foreach ($all_percentiles as $percentile) {
                    $value = $summary['percentiles'][$percentile] ?? 'N/A';
                    $output .= ",{$value}";
                }
                
                $output .= "\n";
            }
        }
        
        \WP_CLI::log($output);
    }
    
    /**
     * Output results in table format (default)
     * 
     * @param array|null $baseline_data Optional baseline data for comparison
     */
    private function output_table_results($baseline_data = null) {
        \WP_CLI::log("\n📊 Test Results Summary:");
        
        // Output header
        \WP_CLI::log("┌───────────────────────────────────────────────────────────────────────────┐");
        \WP_CLI::log("│ Test Results                                                              │");
        \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        
        // Add overall results first
        if (isset($this->results_summary['overall'])) {
            $overall = $this->results_summary['overall'];
            
            $error_rate_formatted = MicroChaos_Thresholds::format_value($overall['error_rate'], 'error_rate');
            $avg_time_formatted = MicroChaos_Thresholds::format_value($overall['timing']['avg'], 'response_time');
            $median_time_formatted = MicroChaos_Thresholds::format_value($overall['timing']['median'], 'response_time');
            $max_time_formatted = MicroChaos_Thresholds::format_value($overall['timing']['max'], 'response_time');
            
            \WP_CLI::log("│ \033[1mOVERALL SUMMARY\033[0m                                                         │");
            \WP_CLI::log("│ Total Requests: {$overall['count']} | Success: {$overall['success']} | Errors: {$overall['errors']} | Error Rate: {$error_rate_formatted}    │");
            \WP_CLI::log("│ Avg Time: {$avg_time_formatted} | Median: {$median_time_formatted} | Min: {$overall['timing']['min']}s | Max: {$max_time_formatted}         │");
            
            // Add percentiles if available
            if (isset($overall['percentiles'])) {
                $percentile_line = "│ Percentiles: ";
                
                // Sort percentiles for consistent display
                $percentiles = $overall['percentiles'];
                ksort($percentiles);
                
                $percentile_values = [];
                foreach ($percentiles as $percentile => $value) {
                    $percentile_values[] = "P{$percentile}: " . MicroChaos_Thresholds::format_value($value, 'response_time');
                }
                
                $percentile_line .= implode(" | ", $percentile_values);
                $percentile_line .= str_repeat(" ", max(0, 63 - strlen(strip_tags(implode(" | ", $percentile_values)))));
                $percentile_line .= "│";
                
                \WP_CLI::log($percentile_line);
            }
            
            \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        }
        
        // Add individual test plan results
        \WP_CLI::log("│ \033[1mRESULTS BY TEST PLAN\033[0m                                                     │");
        
        foreach ($this->results_summary as $plan_name => $summary) {
            if ($plan_name === 'overall') continue;
            
            $error_rate_formatted = MicroChaos_Thresholds::format_value($summary['error_rate'], 'error_rate');
            $avg_time_formatted = MicroChaos_Thresholds::format_value($summary['timing']['avg'], 'response_time');
            
            $plan_name_short = strlen($plan_name) > 40 ? substr($plan_name, 0, 37) . '...' : $plan_name;
            $plan_name_padded = str_pad($plan_name_short, 40);
            
            \WP_CLI::log("│ {$plan_name_padded} │");
            \WP_CLI::log("│   Requests: {$summary['count']} | Success: {$summary['success']} | Errors: {$summary['errors']} | Error Rate: {$error_rate_formatted}    │");
            \WP_CLI::log("│   Avg Time: {$avg_time_formatted} | Median: {$summary['timing']['median']}s | Min: {$summary['timing']['min']}s | Max: {$summary['timing']['max']}s    │");
            
            // Check thresholds if defined in the test plan
            $this->check_thresholds($plan_name, $summary);
            
            // Add percentiles if available
            if (isset($summary['percentiles'])) {
                $percentile_line = "│   Percentiles: ";
                
                // Sort percentiles for consistent display
                $percentiles = $summary['percentiles'];
                ksort($percentiles);
                
                $percentile_values = [];
                foreach ($percentiles as $percentile => $value) {
                    $percentile_values[] = "P{$percentile}: {$value}s";
                }
                
                $percentile_line .= implode(" | ", $percentile_values);
                $percentile_line .= str_repeat(" ", max(0, 63 - strlen($percentile_line)));
                $percentile_line .= "│";
                
                \WP_CLI::log($percentile_line);
            }
            
            \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        }
        
        // Add baseline comparison if available
        if ($baseline_data) {
            $this->output_baseline_comparison($baseline_data);
        }
        
        // Display an ASCII chart of response time distribution
        $this->output_response_time_chart();
        
        \WP_CLI::log("└───────────────────────────────────────────────────────────────────────────┘");
    }
    
    /**
     * Generate a comparison between current results and a baseline
     * 
     * @param array $baseline_data Baseline data
     * @return array Comparison data
     */
    private function generate_baseline_comparison($baseline_data) {
        if (!isset($baseline_data['summary']) || !isset($this->results_summary)) {
            return [];
        }
        
        $comparison = [
            'timestamp' => [
                'current' => time(),
                'baseline' => $baseline_data['timestamp']
            ]
        ];
        
        // Compare overall results
        if (isset($baseline_data['summary']['overall']) && isset($this->results_summary['overall'])) {
            $baseline_overall = $baseline_data['summary']['overall'];
            $current_overall = $this->results_summary['overall'];
            
            $comparison['overall'] = [
                'request_count' => [
                    'baseline' => $baseline_overall['count'],
                    'current' => $current_overall['count'],
                    'difference' => $current_overall['count'] - $baseline_overall['count'],
                    'percent_change' => $baseline_overall['count'] > 0 ? 
                        round((($current_overall['count'] - $baseline_overall['count']) / $baseline_overall['count']) * 100, 1) : 
                        0
                ],
                'error_rate' => [
                    'baseline' => $baseline_overall['error_rate'],
                    'current' => $current_overall['error_rate'],
                    'difference' => $current_overall['error_rate'] - $baseline_overall['error_rate'],
                    'percent_change' => $baseline_overall['error_rate'] > 0 ? 
                        round((($current_overall['error_rate'] - $baseline_overall['error_rate']) / $baseline_overall['error_rate']) * 100, 1) : 
                        0
                ],
                'avg_time' => [
                    'baseline' => $baseline_overall['timing']['avg'],
                    'current' => $current_overall['timing']['avg'],
                    'difference' => $current_overall['timing']['avg'] - $baseline_overall['timing']['avg'],
                    'percent_change' => $baseline_overall['timing']['avg'] > 0 ? 
                        round((($current_overall['timing']['avg'] - $baseline_overall['timing']['avg']) / $baseline_overall['timing']['avg']) * 100, 1) : 
                        0
                ],
                'median_time' => [
                    'baseline' => $baseline_overall['timing']['median'],
                    'current' => $current_overall['timing']['median'],
                    'difference' => $current_overall['timing']['median'] - $baseline_overall['timing']['median'],
                    'percent_change' => $baseline_overall['timing']['median'] > 0 ? 
                        round((($current_overall['timing']['median'] - $baseline_overall['timing']['median']) / $baseline_overall['timing']['median']) * 100, 1) : 
                        0
                ],
                'max_time' => [
                    'baseline' => $baseline_overall['timing']['max'],
                    'current' => $current_overall['timing']['max'],
                    'difference' => $current_overall['timing']['max'] - $baseline_overall['timing']['max'],
                    'percent_change' => $baseline_overall['timing']['max'] > 0 ? 
                        round((($current_overall['timing']['max'] - $baseline_overall['timing']['max']) / $baseline_overall['timing']['max']) * 100, 1) : 
                        0
                ]
            ];
            
            // Compare percentiles if available
            if (isset($baseline_overall['percentiles']) && isset($current_overall['percentiles'])) {
                $comparison['overall']['percentiles'] = [];
                
                // Get all unique percentiles
                $all_percentiles = [];
                foreach ($baseline_overall['percentiles'] as $percentile => $value) {
                    $all_percentiles[$percentile] = true;
                }
                foreach ($current_overall['percentiles'] as $percentile => $value) {
                    $all_percentiles[$percentile] = true;
                }
                
                // Compare each percentile
                foreach (array_keys($all_percentiles) as $percentile) {
                    if (isset($baseline_overall['percentiles'][$percentile]) && isset($current_overall['percentiles'][$percentile])) {
                        $baseline_value = $baseline_overall['percentiles'][$percentile];
                        $current_value = $current_overall['percentiles'][$percentile];
                        
                        $comparison['overall']['percentiles']["p{$percentile}"] = [
                            'baseline' => $baseline_value,
                            'current' => $current_value,
                            'difference' => $current_value - $baseline_value,
                            'percent_change' => $baseline_value > 0 ? 
                                round((($current_value - $baseline_value) / $baseline_value) * 100, 1) : 
                                0
                        ];
                    }
                }
            }
        }
        
        // Compare individual test plan results
        $comparison['test_plans'] = [];
        
        foreach ($this->results_summary as $plan_name => $current_summary) {
            if ($plan_name === 'overall') continue;
            
            if (isset($baseline_data['summary'][$plan_name])) {
                $baseline_summary = $baseline_data['summary'][$plan_name];
                
                $comparison['test_plans'][$plan_name] = [
                    'request_count' => [
                        'baseline' => $baseline_summary['count'],
                        'current' => $current_summary['count'],
                        'difference' => $current_summary['count'] - $baseline_summary['count'],
                        'percent_change' => $baseline_summary['count'] > 0 ? 
                            round((($current_summary['count'] - $baseline_summary['count']) / $baseline_summary['count']) * 100, 1) : 
                            0
                    ],
                    'error_rate' => [
                        'baseline' => $baseline_summary['error_rate'],
                        'current' => $current_summary['error_rate'],
                        'difference' => $current_summary['error_rate'] - $baseline_summary['error_rate'],
                        'percent_change' => $baseline_summary['error_rate'] > 0 ? 
                            round((($current_summary['error_rate'] - $baseline_summary['error_rate']) / $baseline_summary['error_rate']) * 100, 1) : 
                            0
                    ],
                    'avg_time' => [
                        'baseline' => $baseline_summary['timing']['avg'],
                        'current' => $current_summary['timing']['avg'],
                        'difference' => $current_summary['timing']['avg'] - $baseline_summary['timing']['avg'],
                        'percent_change' => $baseline_summary['timing']['avg'] > 0 ? 
                            round((($current_summary['timing']['avg'] - $baseline_summary['timing']['avg']) / $baseline_summary['timing']['avg']) * 100, 1) : 
                            0
                    ],
                    'median_time' => [
                        'baseline' => $baseline_summary['timing']['median'],
                        'current' => $current_summary['timing']['median'],
                        'difference' => $current_summary['timing']['median'] - $baseline_summary['timing']['median'],
                        'percent_change' => $baseline_summary['timing']['median'] > 0 ? 
                            round((($current_summary['timing']['median'] - $baseline_summary['timing']['median']) / $baseline_summary['timing']['median']) * 100, 1) : 
                            0
                    ]
                ];
            }
        }
        
        return $comparison;
    }
    
    /**
     * Output baseline comparison in table format
     * 
     * @param array $baseline_data Baseline data
     */
    private function output_baseline_comparison($baseline_data) {
        if (!isset($baseline_data['summary']) || !isset($this->results_summary)) {
            return;
        }
        
        $baseline_timestamp = date('Y-m-d H:i:s', $baseline_data['timestamp']);
        
        \WP_CLI::log("│ \033[1mBASELINE COMPARISON\033[0m                                                      │");
        \WP_CLI::log("│ Comparing with baseline from: {$baseline_timestamp}                     │");
        \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        
        // Compare overall results
        if (isset($baseline_data['summary']['overall']) && isset($this->results_summary['overall'])) {
            $baseline_overall = $baseline_data['summary']['overall'];
            $current_overall = $this->results_summary['overall'];
            
            \WP_CLI::log("│ \033[1mOVERALL\033[0m                                                                   │");
            
            // Response time comparison
            $avg_time_diff = $current_overall['timing']['avg'] - $baseline_overall['timing']['avg'];
            $avg_time_percent = $baseline_overall['timing']['avg'] > 0 ? 
                round(($avg_time_diff / $baseline_overall['timing']['avg']) * 100, 1) : 
                0;
                
            $avg_time_indicator = $avg_time_diff <= 0 ? '↓' : '↑';
            $avg_time_color = $avg_time_diff <= 0 ? "\033[32m" : "\033[31m";
            
            $median_time_diff = $current_overall['timing']['median'] - $baseline_overall['timing']['median'];
            $median_time_percent = $baseline_overall['timing']['median'] > 0 ? 
                round(($median_time_diff / $baseline_overall['timing']['median']) * 100, 1) : 
                0;
                
            $median_time_indicator = $median_time_diff <= 0 ? '↓' : '↑';
            $median_time_color = $median_time_diff <= 0 ? "\033[32m" : "\033[31m";
            
            \WP_CLI::log("│ Response Time:                                                           │");
            \WP_CLI::log("│   - Avg: {$current_overall['timing']['avg']}s vs {$baseline_overall['timing']['avg']}s  {$avg_time_color}{$avg_time_indicator}{$avg_time_percent}%\033[0m                              │");
            \WP_CLI::log("│   - Median: {$current_overall['timing']['median']}s vs {$baseline_overall['timing']['median']}s  {$median_time_color}{$median_time_indicator}{$median_time_percent}%\033[0m                           │");
            
            // Error rate comparison
            $error_rate_diff = $current_overall['error_rate'] - $baseline_overall['error_rate'];
            $error_rate_percent = $baseline_overall['error_rate'] > 0 ? 
                round(($error_rate_diff / $baseline_overall['error_rate']) * 100, 1) : 
                0;
                
            $error_rate_indicator = $error_rate_diff <= 0 ? '↓' : '↑';
            $error_rate_color = $error_rate_diff <= 0 ? "\033[32m" : "\033[31m";
            
            \WP_CLI::log("│ Error Rate: {$current_overall['error_rate']}% vs {$baseline_overall['error_rate']}%  {$error_rate_color}{$error_rate_indicator}{$error_rate_percent}%\033[0m                                │");
            
            // Percentile comparison if available
            if (isset($baseline_overall['percentiles']) && isset($current_overall['percentiles'])) {
                \WP_CLI::log("│ Percentiles:                                                             │");
                
                // Get common percentiles
                $common_percentiles = array_intersect(
                    array_keys($baseline_overall['percentiles']),
                    array_keys($current_overall['percentiles'])
                );
                
                // Sort percentiles
                sort($common_percentiles);
                
                foreach ($common_percentiles as $percentile) {
                    $baseline_value = $baseline_overall['percentiles'][$percentile];
                    $current_value = $current_overall['percentiles'][$percentile];
                    
                    $diff = $current_value - $baseline_value;
                    $percent = $baseline_value > 0 ? 
                        round(($diff / $baseline_value) * 100, 1) : 
                        0;
                        
                    $indicator = $diff <= 0 ? '↓' : '↑';
                    $color = $diff <= 0 ? "\033[32m" : "\033[31m";
                    
                    \WP_CLI::log("│   - P{$percentile}: {$current_value}s vs {$baseline_value}s  {$color}{$indicator}{$percent}%\033[0m                             │");
                }
            }
            
            \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
        }
    }
    
    /**
     * Output response time distribution chart
     */
    private function output_response_time_chart() {
        if (empty($this->results)) {
            return;
        }
        
        // Extract response times
        $times = array_column($this->results, 'time');
        
        // Generate histogram
        $buckets = 10;
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
        $chart_width = 40;
        
        \WP_CLI::log("│ \033[1mRESPONSE TIME DISTRIBUTION\033[0m                                              │");
        
        for ($i = 0; $i < $buckets; $i++) {
            $lower = round($min + ($i * $bucket_size), 2);
            $upper = round($min + (($i + 1) * $bucket_size), 2);
            $count = $histogram[$i];
            
            $bar_length = ($max_count > 0) ? round(($count / $max_count) * $chart_width) : 0;
            $bar = str_repeat('█', $bar_length);
            $padding = str_repeat(' ', $chart_width - $bar_length);
            
            $line = sprintf("│ %5.2fs - %5.2fs [%s%s] %-5d │", 
                $lower, $upper, $bar, $padding, $count);
            
            \WP_CLI::log($line);
        }
        
        \WP_CLI::log("├───────────────────────────────────────────────────────────────────────────┤");
    }
    
    /**
     * Set up temporary directory for inter-process communication
     */
    private function setup_temp_directory() {
        // Create a unique temp directory for this test run
        $this->temp_dir = sys_get_temp_dir() . '/microchaos_' . uniqid();
        
        // Create directory if it doesn't exist
        if (!file_exists($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
        
        \WP_CLI::log("-> Temp directory: {$this->temp_dir}");
    }
    
    /**
     * Prepare the job queue for distribution
     */
    private function prepare_job_queue() {
        $job_id = 0;
        foreach ($this->test_plans as $plan) {
            // Determine how many requests to distribute
            $total_requests = $plan['requests'];
            $concurrency = $plan['concurrency'];
            
            // Create batches based on concurrency
            $remaining = $total_requests;
            while ($remaining > 0) {
                $batch_size = min($concurrency, $remaining);
                $this->job_queue[] = [
                    'id' => $job_id++,
                    'plan' => $plan,
                    'batch_size' => $batch_size
                ];
                $remaining -= $batch_size;
            }
        }
        
        \WP_CLI::log("-> Job queue prepared: " . count($this->job_queue) . " jobs");
    }
    
    /**
     * Execute tests using worker processes
     * 
     * @return MicroChaos_Resource_Monitor Resource monitor with utilization data
     */
    private function execute_tests() {
        $resource_monitor = null;
        
        // Output summary of what will be executed
        $total_requests = 0;
        foreach ($this->test_plans as $plan) {
            $total_requests += $plan['requests'];
        }
        
        \WP_CLI::log("🔥 Executing {$total_requests} total requests across " . count($this->test_plans) . " test plans");
        
        // Execute tests in parallel or sequential mode
        if ($this->parallel_supported) {
            $resource_monitor = $this->execute_parallel();
        } else {
            $resource_monitor = $this->execute_sequential();
        }
        
        return $resource_monitor;
    }
    
    /**
     * Execute tests in parallel using pcntl_fork
     * 
     * @return MicroChaos_Resource_Monitor Resource monitor with utilization data
     */
    private function execute_parallel() {
        // Create a shared data file for job distribution
        $job_file = $this->temp_dir . '/jobs.json';
        file_put_contents($job_file, json_encode($this->job_queue));
        
        // Create progress tracking file
        $progress_file = $this->temp_dir . '/progress.json';
        file_put_contents($progress_file, json_encode([
            'total_jobs' => count($this->job_queue),
            'completed_jobs' => 0,
            'in_progress' => []
        ]));
        
        // Setup signal handling to enable process cleanup
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGCHLD, SIG_IGN); // Prevent zombie processes
        }
        
        // Launch worker processes
        $worker_count = min($this->workers, count($this->job_queue));
        \WP_CLI::log("🧮 Starting {$worker_count} worker processes...");
        
        for ($i = 0; $i < $worker_count; $i++) {
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                // Fork failed
                \WP_CLI::error("Could not fork worker process #{$i}");
            } elseif ($pid) {
                // Parent process
                $this->worker_pids[$i] = $pid; // Use worker ID as array key for easier identification
                \WP_CLI::log("-> Worker #{$i} started (PID: {$pid})");
            } else {
                // Child process
                $this->run_worker($i, $job_file, $progress_file);
                exit(0); // Child must exit
            }
        }
        
        // Parent process monitors workers and tracks resources
        $resource_monitor = $this->monitor_workers($progress_file);
        
        return $resource_monitor;
    }
    
    /**
     * Execute a worker process
     *
     * @param int $worker_id Worker ID
     * @param string $job_file Path to job distribution file
     * @param string $progress_file Path to progress tracking file
     */
    private function run_worker($worker_id, $job_file, $progress_file) {
        // Set up worker environment
        $worker_log = $this->temp_dir . "/worker_{$worker_id}.log";
        $worker_results = $this->temp_dir . "/worker_{$worker_id}_results.json";
        $results = [];
        
        // Initialize result file
        file_put_contents($worker_results, json_encode($results));
        
        // Worker log header
        file_put_contents($worker_log, "Worker #{$worker_id} started at " . date('Y-m-d H:i:s') . "\n");
        
        // Initialize request generator
        $request_generator = new MicroChaos_Request_Generator();
        
        // Initialize integration logger
        $logger = new MicroChaos_Integration_Logger([
            'enabled' => true,
            'test_id' => 'worker_' . $worker_id
        ]);
        
        // Worker process loop - keep processing jobs until none are left
        while (true) {
            // Acquire a job with file locking to prevent race conditions
            $job = $this->acquire_job($job_file, $progress_file, $worker_id);
            
            if (!$job) {
                // No more jobs available
                file_put_contents($worker_log, "Worker #{$worker_id} finished - no more jobs\n", FILE_APPEND);
                break;
            }
            
            // Process the job
            $job_start_time = microtime(true);
            file_put_contents($worker_log, "Worker #{$worker_id} processing job #{$job['id']} - {$job['plan']['name']} ({$job['batch_size']} requests)\n", FILE_APPEND);
            
            // Prepare request details
            $target_url = $request_generator->resolve_endpoint($job['plan']['endpoint']);
            $method = $job['plan']['method'] ?? 'GET';
            $cookies = null;
            $body = null;
            
            try {
                // Job timeout handling
                $job_timeout = $job['plan']['timeout'] ?? 30; // Default 30 seconds per job
                
                // Handle authentication if specified
                if (isset($job['plan']['auth'])) {
                    // Basic authentication implementation
                    if (strpos($job['plan']['auth'], '@') !== false) {
                        list($username, $domain) = explode('@', $job['plan']['auth']);
                        $password = isset($job['plan']['password']) ? $job['plan']['password'] : 'password';
                        
                        // Set basic auth header
                        $request_generator->set_custom_headers(array_merge(
                            $job['plan']['headers'] ?? [],
                            ['Authorization' => 'Basic ' . base64_encode($username . ':' . $password)]
                        ));
                        
                        file_put_contents($worker_log, "Worker #{$worker_id} using basic auth for {$username}@{$domain}\n", FILE_APPEND);
                    }
                }
                
                // Process headers if present
                if (isset($job['plan']['headers']) && !empty($job['plan']['headers'])) {
                    $request_generator->set_custom_headers($job['plan']['headers']);
                }
                
                // Process body data if present
                if (isset($job['plan']['data']) && !empty($job['plan']['data'])) {
                    $body = is_array($job['plan']['data']) ? json_encode($job['plan']['data']) : $job['plan']['data'];
                } elseif (isset($job['plan']['body']) && !empty($job['plan']['body'])) {
                    // Support for 'body' parameter for consistency with loadtest
                    $body = is_array($job['plan']['body']) ? json_encode($job['plan']['body']) : $job['plan']['body'];
                }
                
                // Log burst start to integration logger
                $logger->log_burst_complete($job['id'], $job['batch_size'], [
                    'plan_name' => $job['plan']['name'],
                    'target_url' => $target_url,
                    'method' => $method,
                    'burst_size' => $job['batch_size']
                ]);
                
                // Execute the batch (async for efficiency)
                $batch_results = $request_generator->fire_requests_async(
                    $target_url,
                    null, // No log path
                    $cookies,
                    $job['batch_size'],
                    $method,
                    $body
                );
                
                // Detect if job took too long (individual timeouts are handled by the request generator)
                $job_execution_time = microtime(true) - $job_start_time;
                if ($job_execution_time > $job_timeout) {
                    file_put_contents(
                        $worker_log, 
                        "Worker #{$worker_id} - Job #{$job['id']} exceeded timeout: {$job_execution_time}s > {$job_timeout}s\n", 
                        FILE_APPEND
                    );
                }
                
                // Add job metadata to results
                foreach ($batch_results as &$result) {
                    $result['job_id'] = $job['id'];
                    $result['plan_name'] = $job['plan']['name'];
                    $result['worker_id'] = $worker_id;
                    $result['timestamp'] = microtime(true);
                    $result['execution_time'] = $job_execution_time;
                    
                    // Log individual request to integration logger
                    $logger->log_request($result);
                }
                
                // Append results to worker results file
                $current_results = json_decode(file_get_contents($worker_results), true) ?: [];
                $current_results = array_merge($current_results, $batch_results);
                file_put_contents($worker_results, json_encode($current_results));
                
                // Update progress file to mark job as completed
                $this->complete_job($progress_file, $job['id'], $worker_id);
                
                // Log job completion statistics
                file_put_contents(
                    $worker_log, 
                    "Worker #{$worker_id} completed job #{$job['id']} in {$job_execution_time}s - Results: " . 
                    count($batch_results) . " requests, " .
                    "Avg time: " . (array_sum(array_column($batch_results, 'time')) / count($batch_results)) . "s\n", 
                    FILE_APPEND
                );
                
            } catch (Exception $e) {
                // Log error and continue to next job
                file_put_contents(
                    $worker_log, 
                    "Worker #{$worker_id} ERROR processing job #{$job['id']}: " . $e->getMessage() . "\n", 
                    FILE_APPEND
                );
                
                // Still mark job as completed to avoid it getting stuck
                $this->complete_job($progress_file, $job['id'], $worker_id);
            }
            
            // Sleep briefly to prevent CPU overload and allow other processes to run
            usleep(10000); // 10ms
        }
        
        file_put_contents($worker_log, "Worker #{$worker_id} exiting\n", FILE_APPEND);
    }
    
    /**
     * Acquire a job from the queue with file locking
     *
     * @param string $job_file Path to job file
     * @param string $progress_file Path to progress file
     * @param int $worker_id Worker ID
     * @return array|false Job data or false if no jobs available
     */
    private function acquire_job($job_file, $progress_file, $worker_id) {
        $job = false;
        
        // Get exclusive lock on job file
        $fp = fopen($job_file, 'r+');
        if (!$fp) {
            return false;
        }
        
        if (flock($fp, LOCK_EX)) {
            // Read job queue
            $jobs = json_decode(file_get_contents($job_file), true) ?: [];
            
            if (!empty($jobs)) {
                // Get the first job
                $job = array_shift($jobs);
                
                // Update job file
                file_put_contents($job_file, json_encode($jobs));
                
                // Update progress file to mark job as in progress
                $fp_progress = fopen($progress_file, 'r+');
                if ($fp_progress && flock($fp_progress, LOCK_EX)) {
                    $progress = json_decode(file_get_contents($progress_file), true) ?: [
                        'total_jobs' => 0,
                        'completed_jobs' => 0,
                        'in_progress' => []
                    ];
                    
                    $progress['in_progress'][$job['id']] = [
                        'worker_id' => $worker_id,
                        'started' => microtime(true)
                    ];
                    
                    file_put_contents($progress_file, json_encode($progress));
                    flock($fp_progress, LOCK_UN);
                    fclose($fp_progress);
                }
            }
            
            flock($fp, LOCK_UN);
        }
        
        fclose($fp);
        return $job;
    }
    
    /**
     * Mark a job as completed in the progress file
     *
     * @param string $progress_file Path to progress file
     * @param int $job_id Job ID
     * @param int $worker_id Worker ID
     */
    private function complete_job($progress_file, $job_id, $worker_id) {
        $fp = fopen($progress_file, 'r+');
        if (!$fp) {
            return;
        }
        
        if (flock($fp, LOCK_EX)) {
            $progress = json_decode(file_get_contents($progress_file), true) ?: [
                'total_jobs' => 0,
                'completed_jobs' => 0,
                'in_progress' => []
            ];
            
            // Remove from in_progress and increment completed count
            if (isset($progress['in_progress'][$job_id])) {
                unset($progress['in_progress'][$job_id]);
            }
            
            $progress['completed_jobs']++;
            
            file_put_contents($progress_file, json_encode($progress));
            flock($fp, LOCK_UN);
        }
        
        fclose($fp);
    }
    
    /**
     * Monitor worker processes and progress
     *
     * @param string $progress_file Path to progress file
     */
    private function monitor_workers($progress_file) {
        $start_time = microtime(true);
        $finished = false;
        $last_resource_check = 0;
        $last_status_length = 0;
        $resource_check_interval = 5; // seconds
        $eta_samples = [];
        $active_worker_history = [];
        $stalled_threshold = 30; // seconds
        
        // Create resource monitor
        $resource_monitor = new MicroChaos_Resource_Monitor(['track_trends' => true]);
        
        \WP_CLI::log("\n👁️ Monitoring worker progress...");
        
        // Draw progress bar header
        \WP_CLI::log("┌────────────────────────────────────────────────────────────────────────────┐");
        \WP_CLI::log("│ Progress                                                                   │");
        \WP_CLI::log("├────────────────────────────────────────────────────────────────────────────┤");
        
        while (!$finished) {
            // Check global timeout
            $current_time = microtime(true);
            $elapsed = round($current_time - $start_time, 1);
            
            if ($elapsed > $this->global_timeout) {
                \WP_CLI::warning("⚠️ Global timeout reached after {$elapsed} seconds");
                
                // Terminate all worker processes
                foreach ($this->worker_pids as $pid) {
                    posix_kill($pid, SIGTERM);
                }
                
                $finished = true;
                break;
            }
            
            // Check if all jobs are completed
            $progress = json_decode(file_get_contents($progress_file), true) ?: [];
            $total_jobs = $progress['total_jobs'] ?? 0;
            $completed_jobs = $progress['completed_jobs'] ?? 0;
            $in_progress = $progress['in_progress'] ?? [];
            
            // Calculate metrics
            $percent_complete = $total_jobs > 0 ? round(($completed_jobs / $total_jobs) * 100) : 0;
            $active_workers = count($in_progress);
            
            // Store worker activity for stall detection
            $active_worker_history[] = [
                'time' => $current_time,
                'active' => $active_workers,
                'completed' => $completed_jobs
            ];
            
            // Only keep the last 60 seconds of history
            while (count($active_worker_history) > 60) {
                array_shift($active_worker_history);
            }
            
            // Calculate ETA
            if ($completed_jobs > 0 && $elapsed > 5) {
                $rate = $completed_jobs / $elapsed; // jobs per second
                $remaining_jobs = $total_jobs - $completed_jobs;
                
                if ($rate > 0) {
                    $eta_seconds = $remaining_jobs / $rate;
                    $eta_samples[] = $eta_seconds;
                    
                    // Keep only the most recent 5 samples for ETA estimation
                    if (count($eta_samples) > 5) {
                        array_shift($eta_samples);
                    }
                    
                    // Use average of recent ETAs for more stability
                    $eta = round(array_sum($eta_samples) / count($eta_samples));
                    $eta_formatted = $this->format_time_duration($eta);
                } else {
                    $eta_formatted = "Unknown";
                }
            } else {
                $eta_formatted = "Calculating...";
            }
            
            // Check for stalled workers (no progress for 30 seconds)
            $stalled = false;
            if (count($active_worker_history) > 30) {
                $old_state = $active_worker_history[0];
                $stalled = ($current_time - $old_state['time'] >= $stalled_threshold) && 
                          ($old_state['completed'] == $completed_jobs) && 
                          ($active_workers > 0);
            }
            
            // Format progress bar
            $bar_length = 60;
            $filled_length = (int)($bar_length * $percent_complete / 100);
            $bar = str_repeat('█', $filled_length) . str_repeat('░', $bar_length - $filled_length);
            
            // Clear previous line and update progress
            for ($i = 0; $i < $last_status_length; $i++) {
                echo "\r\033[K\033[A"; // Move cursor up and clear line
            }
            $last_status_length = 5; // Reset for new status
            
            // Format status with color based on conditions
            $status_color = $stalled ? "\033[33m" : "\033[32m"; // Yellow for stalled, green for active
            $progress_status = "{$status_color}│ {$bar} │\033[0m";
            $completion_status = "\033[36m│ {$completed_jobs}/{$total_jobs} jobs ({$percent_complete}%) | Active: {$active_workers} | Elapsed: {$elapsed}s | ETA: {$eta_formatted} │\033[0m";
            
            // Display health status
            $health_status = "\033[36m│ ";
            if ($stalled) {
                $health_status .= "\033[33m⚠️ Warning: Workers appear stalled - no progress for {$stalled_threshold}+ seconds ";
            } else {
                $health_status .= "\033[32m✓ Workers healthy";
                $health_status .= str_repeat(' ', 46); // Padding
            }
            $health_status .= " │\033[0m";
            
            // Output progress display
            echo $progress_status . "\n";
            echo $completion_status . "\n";
            echo $health_status . "\n";
            
            // Display active jobs if any
            echo "\033[36m│ Active jobs:";
            echo str_repeat(' ', 57);
            echo " │\033[0m\n";
            
            $job_display = '';
            $displayed_jobs = 0;
            foreach ($in_progress as $job_id => $job_info) {
                if ($displayed_jobs < 2) { // Only show first 2 jobs to keep display compact
                    $job_time = round($current_time - $job_info['started'], 1);
                    $job_display .= "\033[36m│ - Job #{$job_id} (Worker #{$job_info['worker_id']}) - Running for {$job_time}s";
                    $job_display .= str_repeat(' ', max(0, 30 - strlen($job_id) - strlen($job_info['worker_id']) - strlen((string)$job_time)));
                    $job_display .= " │\033[0m\n";
                    $displayed_jobs++;
                }
            }
            
            if (empty($in_progress)) {
                $job_display .= "\033[36m│ - No active jobs";
                $job_display .= str_repeat(' ', 51);
                $job_display .= " │\033[0m\n";
            } else if (count($in_progress) > 2) {
                // Last line shows additional jobs count
                $more_jobs = count($in_progress) - 2;
                $job_display .= "\033[36m│ - And {$more_jobs} more active job" . ($more_jobs > 1 ? 's' : '');
                $job_display .= str_repeat(' ', 47 - strlen((string)$more_jobs));
                $job_display .= " │\033[0m\n";
            }
            
            echo $job_display;
            $last_status_length += substr_count($job_display, "\n");
            
            // Bottom border
            echo "\033[36m└────────────────────────────────────────────────────────────────────────────┘\033[0m\n";
            
            // Check if we should do a resource utilization check
            if ($current_time - $last_resource_check >= $resource_check_interval) {
                $resource_data = $resource_monitor->log_resource_utilization();
                $last_resource_check = $current_time;
                
                // Log snapshots to file for later analysis
                file_put_contents(
                    $this->temp_dir . '/resource_snapshots.json', 
                    json_encode($resource_data) . "\n", 
                    FILE_APPEND
                );
            }
            
            // Check if all jobs are completed
            if ($completed_jobs >= $total_jobs) {
                $finished = true;
            }
            
            // Check on worker processes
            $unhealthy_workers = [];
            foreach ($this->worker_pids as $key => $pid) {
                $status = pcntl_waitpid($pid, $status, WNOHANG);
                
                if ($status === $pid) {
                    // Worker has exited
                    unset($this->worker_pids[$key]);
                } else if ($status === 0) {
                    // Worker is still running - check if it's healthy
                    $found_in_progress = false;
                    foreach ($in_progress as $job_info) {
                        if ($job_info['worker_id'] == $key) {
                            $found_in_progress = true;
                            
                            // Check if job has been running too long
                            $job_runtime = $current_time - $job_info['started'];
                            if ($job_runtime > 60) { // 60 seconds is too long for a single job
                                $unhealthy_workers[] = $key;
                            }
                            break;
                        }
                    }
                    
                    // Worker should be working but isn't associated with any job
                    if (!$found_in_progress && $completed_jobs < $total_jobs) {
                        $unhealthy_workers[] = $key;
                    }
                } else {
                    // Error checking worker
                    $unhealthy_workers[] = $key;
                }
            }
            
            // Handle unhealthy workers (log but don't restart for now)
            if (!empty($unhealthy_workers)) {
                foreach ($unhealthy_workers as $worker_id) {
                    file_put_contents(
                        $this->temp_dir . '/worker_health_issues.log',
                        date('Y-m-d H:i:s') . " - Worker #{$worker_id} appears unhealthy\n",
                        FILE_APPEND
                    );
                }
            }
            
            // Check if all workers have exited
            if (empty($this->worker_pids)) {
                $finished = true;
            }
            
            // Sleep before checking again
            if (!$finished) {
                // Use shorter intervals early in the test for responsive feedback
                $sleep_time = ($elapsed < 10) ? 0.5 : 1;
                usleep($sleep_time * 1000000);
            }
        }
        
        $total_time = round(microtime(true) - $start_time, 1);
        
        // Add a line of padding after progress display
        echo "\n";
        
        \WP_CLI::log("✅ All jobs completed in {$total_time}s");
        
        // Wait for any remaining child processes
        foreach ($this->worker_pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        
        // Record final resource utilization
        $resource_monitor->log_resource_utilization();
        
        return $resource_monitor;
    }
    
    /**
     * Format time duration in human-readable format
     * 
     * @param int $seconds Number of seconds
     * @return string Formatted time string
     */
    private function format_time_duration($seconds) {
        $seconds = (int)$seconds;
        
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remaining_seconds = $seconds % 60;
            return "{$minutes}m {$remaining_seconds}s";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }
    
    /**
     * Execute tests sequentially (fallback for systems without pcntl)
     * 
     * @return MicroChaos_Resource_Monitor Resource monitor with utilization data
     */
    private function execute_sequential() {
        \WP_CLI::log("🧵 Sequential execution mode");
        
        // Create resource monitor
        $resource_monitor = new MicroChaos_Resource_Monitor(['track_trends' => true]);
        $resource_monitor->log_resource_utilization();
        
        // Initialize components
        $request_generator = new MicroChaos_Request_Generator();
        $integration_logger = new MicroChaos_Integration_Logger(['enabled' => true]);
        
        $total_jobs = count($this->job_queue);
        $completed_jobs = 0;
        $start_time = microtime(true);
        $last_resource_check = $start_time;
        $resource_check_interval = 5; // seconds
        
        // Progress bar header
        \WP_CLI::log("┌────────────────────────────────────────────────────────────────────────────┐");
        \WP_CLI::log("│ Progress                                                                   │");
        \WP_CLI::log("├────────────────────────────────────────────────────────────────────────────┤");
        
        foreach ($this->job_queue as $job) {
            // Check if execution has exceeded global timeout
            $current_time = microtime(true);
            $elapsed = $current_time - $start_time;
            
            if ($elapsed > $this->global_timeout) {
                \WP_CLI::warning("⚠️ Global timeout reached after " . round($elapsed, 1) . " seconds");
                break;
            }
            
            // Perform resource check at intervals
            if ($current_time - $last_resource_check >= $resource_check_interval) {
                $resource_data = $resource_monitor->log_resource_utilization();
                $last_resource_check = $current_time;
                
                // Log resource data
                file_put_contents(
                    $this->temp_dir . '/resource_snapshots.json', 
                    json_encode($resource_data) . "\n", 
                    FILE_APPEND
                );
            }
            
            $completed_jobs++;
            $percent_complete = round(($completed_jobs / $total_jobs) * 100);
            
            // Format progress bar
            $bar_length = 60;
            $filled_length = (int)($bar_length * $percent_complete / 100);
            $bar = str_repeat('█', $filled_length) . str_repeat('░', $bar_length - $filled_length);
            
            // Calculate ETA
            if ($completed_jobs > 1) {
                $rate = $completed_jobs / $elapsed; // jobs per second
                $remaining_jobs = $total_jobs - $completed_jobs;
                $eta_seconds = $rate > 0 ? $remaining_jobs / $rate : 0;
                $eta_formatted = $this->format_time_duration($eta_seconds);
            } else {
                $eta_formatted = "Calculating...";
            }
            
            // Update progress display (don't move cursor, just print progress)
            $elapsed_formatted = round($elapsed, 1);
            echo "\r\033[K\033[36m│ {$bar} │\033[0m";
            echo "\n\r\033[K\033[36m│ {$completed_jobs}/{$total_jobs} jobs ({$percent_complete}%) | Elapsed: {$elapsed_formatted}s | ETA: {$eta_formatted} │\033[0m";
            echo "\n\r\033[K\033[36m│ Current job: {$job['plan']['name']} ({$job['batch_size']} requests)"; 
            echo str_repeat(' ', 30 - strlen($job['plan']['name']));
            echo "│\033[0m\n";
            
            // Prepare request details
            $target_url = $request_generator->resolve_endpoint($job['plan']['endpoint']);
            $method = $job['plan']['method'] ?? 'GET';
            $cookies = null; 
            $body = null;
            
            // Handle authentication if specified
            if (isset($job['plan']['auth'])) {
                // Basic authentication implementation
                if (strpos($job['plan']['auth'], '@') !== false) {
                    list($username, $domain) = explode('@', $job['plan']['auth']);
                    $password = isset($job['plan']['password']) ? $job['plan']['password'] : 'password';
                    
                    // Set basic auth header
                    $auth_header = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
                    $request_generator->set_custom_headers(array_merge(
                        $job['plan']['headers'] ?? [],
                        ['Authorization' => 'Basic ' . base64_encode($username . ':' . $password)]
                    ));
                    
                    \WP_CLI::log("\r\033[K\033[36m│ Using basic authentication for {$username}@{$domain}" . str_repeat(' ', 20) . "│\033[0m");
                }
            }
            
            // Process headers if present
            if (isset($job['plan']['headers']) && !empty($job['plan']['headers'])) {
                $request_generator->set_custom_headers($job['plan']['headers']);
            }
            
            // Process body data if present
            if (isset($job['plan']['data']) && !empty($job['plan']['data'])) {
                $body = is_array($job['plan']['data']) ? json_encode($job['plan']['data']) : $job['plan']['data'];
            } elseif (isset($job['plan']['body']) && !empty($job['plan']['body'])) {
                // Support for 'body' parameter for consistency with loadtest
                $body = is_array($job['plan']['body']) ? json_encode($job['plan']['body']) : $job['plan']['body'];
            }
            
            // Log job start to integration logger
            $integration_logger->log_burst_complete($job['id'], $job['batch_size'], [
                'plan_name' => $job['plan']['name'],
                'target_url' => $target_url,
                'method' => $method,
                'burst_size' => $job['batch_size']
            ]);
            
            // Execute the batch (async for efficiency)
            $batch_results = $request_generator->fire_requests_async(
                $target_url,
                null, // No log path
                $cookies,
                $job['batch_size'],
                $method,
                $body
            );
            
            // Add job metadata to results
            foreach ($batch_results as &$result) {
                $result['job_id'] = $job['id'];
                $result['plan_name'] = $job['plan']['name'];
                $result['worker_id'] = 0; // All jobs run in main process
                $result['timestamp'] = microtime(true);
                
                // Log individual request to integration logger
                $integration_logger->log_request($result);
            }
            
            // Add to results collection
            $this->results = array_merge($this->results, $batch_results);
            
            // Save interim results to temp file for recovery
            file_put_contents($this->temp_dir . '/results.json', json_encode($this->results));
        }
        
        $total_time = round(microtime(true) - $start_time, 1);
        
        // Bottom border and padding
        echo "\r\033[K\033[36m└────────────────────────────────────────────────────────────────────────────┘\033[0m\n\n";
        
        \WP_CLI::log("✅ All jobs completed in {$total_time}s");
        
        // Final resource utilization check
        $resource_monitor->log_resource_utilization();
        
        return $resource_monitor;
    }
    
    /**
     * Clean up temporary files and analyze results
     * 
     * @param bool $delete_temp Whether to delete temporary files
     * @return array Combined results
     */
    private function cleanup_temp_files($delete_temp = false) {
        // Collect result files from workers
        $all_results = [];
        
        if ($this->parallel_supported) {
            // In parallel mode, collect results from worker files
            for ($i = 0; $i < $this->workers; $i++) {
                $result_file = $this->temp_dir . "/worker_{$i}_results.json";
                if (file_exists($result_file)) {
                    $worker_results = json_decode(file_get_contents($result_file), true) ?: [];
                    $all_results = array_merge($all_results, $worker_results);
                }
            }
        } else {
            // In sequential mode, we already have results in $this->results
            $all_results = $this->results;
        }
        
        // Save combined results
        file_put_contents($this->temp_dir . '/combined_results.json', json_encode($all_results));
        $this->results = $all_results;
        
        \WP_CLI::log("📊 Results collected: " . count($this->results) . " total requests");
        
        // Analyze results by test plan
        $this->analyze_results();
        
        // Delete temporary files if requested
        if ($delete_temp) {
            $this->delete_temp_directory();
        }
        
        return $all_results;
    }
    
    /**
     * Delete temporary directory and all files
     */
    private function delete_temp_directory() {
        if (!$this->temp_dir || !file_exists($this->temp_dir)) {
            return;
        }
        
        $files = glob($this->temp_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        rmdir($this->temp_dir);
        \WP_CLI::log("🧹 Temporary files cleaned up");
    }
    
    /**
     * Analyze results by test plan and calculate metrics
     */
    private function analyze_results() {
        if (empty($this->results)) {
            \WP_CLI::warning("No results to analyze");
            return;
        }
        
        // Group results by test plan
        $results_by_plan = [];
        foreach ($this->results as $result) {
            if (!isset($result['plan_name'])) {
                continue;
            }
            
            $plan_name = $result['plan_name'];
            if (!isset($results_by_plan[$plan_name])) {
                $results_by_plan[$plan_name] = [];
            }
            
            $results_by_plan[$plan_name][] = $result;
        }
        
        // Create reporting engine to analyze results
        $reporting_engine = new MicroChaos_Reporting_Engine();
        
        // Process each test plan
        $this->results_summary = [];
        foreach ($results_by_plan as $plan_name => $plan_results) {
            // Reset reporting engine for this plan
            $reporting_engine->reset_results();
            $reporting_engine->add_results($plan_results);
            
            // Generate summary
            $summary = $reporting_engine->generate_summary();
            $this->results_summary[$plan_name] = $summary;
            
            // Report plan results
            \WP_CLI::log("\n📈 Results for test plan: $plan_name");
            $reporting_engine->report_summary(null, $summary);
            
            // Check thresholds if defined
            $this->check_thresholds($plan_name, $summary);
        }
        
        // Generate overall summary
        $reporting_engine->reset_results();
        $reporting_engine->add_results($this->results);
        $overall_summary = $reporting_engine->generate_summary();
        $this->results_summary['overall'] = $overall_summary;
        
        \WP_CLI::log("\n📊 Overall Test Results:");
        $reporting_engine->report_summary(null, $overall_summary);
    }
    
    /**
     * Check if results exceed thresholds defined in test plan
     * 
     * @param string $plan_name Test plan name
     * @param array $summary Result summary
     */
    private function check_thresholds($plan_name, $summary) {
        // Find this test plan in original test plans array
        $test_plan = null;
        foreach ($this->test_plans as $plan) {
            if ($plan['name'] === $plan_name) {
                $test_plan = $plan;
                break;
            }
        }
        
        if (!$test_plan || !isset($test_plan['thresholds'])) {
            return; // No thresholds defined
        }
        
        $thresholds = $test_plan['thresholds'];
        $threshold_failures = [];
        
        // Check response time threshold (ms to s conversion)
        if (isset($thresholds['response_time'])) {
            $response_time_threshold = $thresholds['response_time'] / 1000; // convert ms to seconds
            if ($summary['timing']['avg'] > $response_time_threshold) {
                $threshold_failures[] = sprintf(
                    "Response time exceeded threshold: %.2fs > %.2fs", 
                    $summary['timing']['avg'], 
                    $response_time_threshold
                );
            }
        }
        
        // Check error rate threshold (decimal to percentage conversion)
        if (isset($thresholds['error_rate'])) {
            $error_rate_threshold = $thresholds['error_rate'] * 100; // convert decimal to percentage
            if ($summary['error_rate'] > $error_rate_threshold) {
                $threshold_failures[] = sprintf(
                    "Error rate exceeded threshold: %.1f%% > %.1f%%", 
                    $summary['error_rate'], 
                    $error_rate_threshold
                );
            }
        }
        
        // Report threshold violations
        if (!empty($threshold_failures)) {
            \WP_CLI::warning("⚠️ Threshold violations for $plan_name:");
            foreach ($threshold_failures as $failure) {
                \WP_CLI::log("   - $failure");
            }
        } else if (isset($test_plan['thresholds'])) {
            \WP_CLI::success("✅ All thresholds passed for $plan_name");
        }
    }

    /**
     * Load test plans from a JSON file
     *
     * @param string $file_path Path to the JSON file
     */
    private function load_test_plans_from_file($file_path) {
        if (!file_exists($file_path)) {
            \WP_CLI::error("Test plan file not found: $file_path");
        }

        $file_content = file_get_contents($file_path);
        if (!$file_content) {
            \WP_CLI::error("Could not read test plan file: $file_path");
        }

        $this->parse_test_plans_json($file_content);
    }

    /**
     * Parse test plans from JSON string
     *
     * @param string $json_string JSON string containing test plans
     */
    private function parse_test_plans_json($json_string) {
        $json_data = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            \WP_CLI::error("Invalid JSON format: " . json_last_error_msg());
        }

        // Check if it's a single plan or an array of plans
        if (isset($json_data['name'])) {
            // Single test plan
            $this->validate_and_add_test_plan($json_data);
        } elseif (is_array($json_data)) {
            // Array of test plans
            foreach ($json_data as $plan) {
                $this->validate_and_add_test_plan($plan);
            }
        } else {
            \WP_CLI::error("Invalid test plan format. Must be a single plan object or an array of plan objects.");
        }
    }

    /**
     * Validate a test plan and add it to the collection
     *
     * @param array $plan Test plan data
     */
    private function validate_and_add_test_plan($plan) {
        // Required fields
        if (!isset($plan['name'])) {
            \WP_CLI::warning("Test plan missing 'name' field. Skipping.");
            return;
        }

        // Normalize endpoint/target field (accept both for compatibility)
        if (isset($plan['endpoint'])) {
            $plan['target'] = $plan['endpoint'];
        } elseif (isset($plan['target']) && !isset($plan['endpoint'])) {
            $plan['endpoint'] = $plan['target'];
        } else {
            \WP_CLI::warning("Test plan '{$plan['name']}' missing 'endpoint' field. Skipping.");
            return;
        }

        // Add defaults for optional fields
        $plan['requests'] = $plan['requests'] ?? $plan['count'] ?? 100;
        $plan['concurrency'] = $plan['concurrency'] ?? $plan['burst'] ?? 10;
        $plan['delay'] = $plan['delay'] ?? 0;
        $plan['timeout'] = $plan['timeout'] ?? 5;
        $plan['method'] = $plan['method'] ?? 'GET';
        
        // Validate numeric fields
        if (!is_numeric($plan['requests']) || $plan['requests'] <= 0) {
            \WP_CLI::warning("Test plan '{$plan['name']}' has invalid 'requests' value. Must be a positive number.");
            return;
        }

        if (!is_numeric($plan['concurrency']) || $plan['concurrency'] <= 0) {
            \WP_CLI::warning("Test plan '{$plan['name']}' has invalid 'concurrency' value. Must be a positive number.");
            return;
        }

        // Add the validated plan to our collection
        $this->test_plans[] = $plan;
    }

    /**
     * Display a summary of the test plans
     */
    private function display_test_plan_summary() {
        \WP_CLI::log("\n📋 Test Plan Summary:");

        foreach ($this->test_plans as $index => $plan) {
            $index_num = $index + 1;
            \WP_CLI::log("Test Plan #{$index_num}: {$plan['name']}");
            \WP_CLI::log("  Endpoint: {$plan['endpoint']}");
            \WP_CLI::log("  Requests: {$plan['requests']} | Concurrency: {$plan['concurrency']}");
            
            if (isset($plan['method']) && $plan['method'] !== 'GET') {
                \WP_CLI::log("  Method: {$plan['method']}");
            }
            
            if (isset($plan['auth'])) {
                \WP_CLI::log("  Auth: {$plan['auth']}");
            }
            
            if (isset($plan['headers']) && !empty($plan['headers'])) {
                \WP_CLI::log("  Headers: " . count($plan['headers']));
            }
            
            if (isset($plan['data']) && !empty($plan['data'])) {
                \WP_CLI::log("  Data: Yes");
            } elseif (isset($plan['body']) && !empty($plan['body'])) {
                \WP_CLI::log("  Body: Yes");
            }
            
            if (isset($plan['thresholds'])) {
                $thresholds = [];
                if (isset($plan['thresholds']['response_time'])) {
                    $thresholds[] = "Response time: {$plan['thresholds']['response_time']}ms";
                }
                if (isset($plan['thresholds']['error_rate'])) {
                    $thresholds[] = "Error rate: {$plan['thresholds']['error_rate']}";
                }
                if (!empty($thresholds)) {
                    \WP_CLI::log("  Thresholds: " . implode(', ', $thresholds));
                }
            }
            
            \WP_CLI::log("");
        }
    }
}