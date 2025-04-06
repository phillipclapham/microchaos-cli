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
     * ## EXAMPLES
     *
     *     # Run parallel tests defined in a JSON file
     *     wp microchaos paralleltest --file=test-plans.json
     *
     *     # Run parallel tests with a JSON string
     *     wp microchaos paralleltest --plan='[{"name":"Homepage Test","target":"home","requests":50},{"name":"Checkout Test","target":"checkout","requests":25,"auth":"user@example.com"}]'
     *
     *     # Run parallel tests with 5 workers
     *     wp microchaos paralleltest --file=test-plans.json --workers=5
     *
     *     # Run parallel tests and output results as JSON
     *     wp microchaos paralleltest --file=test-plans.json --output=json
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

        \WP_CLI::log("🚀 MicroChaos Parallel Test Started");
        \WP_CLI::log("-> Test Plans: " . count($this->test_plans));
        \WP_CLI::log("-> Workers: " . $this->workers);

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
        
        // Launch workers and execute tests
        $this->execute_tests();
        
        // Phase 2 implementation completed
        \WP_CLI::log("\n🏗️ Phase 2 implementation completed");
        \WP_CLI::log("Results collection and reporting will be implemented in Phases 3 and 4.");
        
        // Cleanup temp files
        $this->cleanup_temp_files();
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
     */
    private function execute_tests() {
        if ($this->parallel_supported) {
            $this->execute_parallel();
        } else {
            $this->execute_sequential();
        }
    }
    
    /**
     * Execute tests in parallel using pcntl_fork
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
                $this->worker_pids[] = $pid;
                \WP_CLI::log("-> Worker #{$i} started (PID: {$pid})");
            } else {
                // Child process
                $this->run_worker($i, $job_file, $progress_file);
                exit(0); // Child must exit
            }
        }
        
        // Parent process monitors workers
        $this->monitor_workers($progress_file);
    }
    
    /**
     * Execute a worker process
     *
     * @param int $worker_id Worker ID
     * @param string $job_file Path to job distribution file
     * @param string $progress_file Path to progress tracking file
     */
    private function run_worker($worker_id, $job_file, $progress_file) {
        // Worker process
        $worker_log = $this->temp_dir . "/worker_{$worker_id}.log";
        $worker_results = $this->temp_dir . "/worker_{$worker_id}_results.json";
        $results = [];
        
        // Initialize result file
        file_put_contents($worker_results, json_encode($results));
        
        // Worker log header
        file_put_contents($worker_log, "Worker #{$worker_id} started at " . date('Y-m-d H:i:s') . "\n");
        
        // Initialize request generator
        $request_generator = new MicroChaos_Request_Generator();
        
        // Worker keep processing jobs until none are left
        while (true) {
            // Acquire a job with file locking to prevent race conditions
            $job = $this->acquire_job($job_file, $progress_file, $worker_id);
            
            if (!$job) {
                // No more jobs available
                file_put_contents($worker_log, "Worker #{$worker_id} finished - no more jobs\n", FILE_APPEND);
                break;
            }
            
            // Process the job
            file_put_contents($worker_log, "Worker #{$worker_id} processing job #{$job['id']} - {$job['plan']['name']} ({$job['batch_size']} requests)\n", FILE_APPEND);
            
            // Prepare request details
            $target_url = $request_generator->resolve_endpoint($job['plan']['endpoint']);
            $method = $job['plan']['method'] ?? 'GET';
            $cookies = null; // Would be set from auth credentials if provided
            $body = null;
            
            // Handle authentication if specified
            if (isset($job['plan']['auth'])) {
                // TODO: Implement authentication in Phase 3
            }
            
            // Process headers if present
            $headers = null;
            if (isset($job['plan']['headers']) && !empty($job['plan']['headers'])) {
                $request_generator->set_custom_headers($job['plan']['headers']);
            }
            
            // Process body data if present
            if (isset($job['plan']['data']) && !empty($job['plan']['data'])) {
                $body = json_encode($job['plan']['data']);
            } elseif (isset($job['plan']['body']) && !empty($job['plan']['body'])) {
                // Support for 'body' parameter for consistency with loadtest
                $body = $job['plan']['body'];
            }
            
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
                $result['worker_id'] = $worker_id;
                $result['timestamp'] = microtime(true);
            }
            
            // Append results to worker results file
            $current_results = json_decode(file_get_contents($worker_results), true) ?: [];
            $current_results = array_merge($current_results, $batch_results);
            file_put_contents($worker_results, json_encode($current_results));
            
            // Update progress file to mark job as completed
            $this->complete_job($progress_file, $job['id'], $worker_id);
            
            // Sleep briefly to prevent CPU overload
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
        
        \WP_CLI::log("👁️ Monitoring worker progress...");
        
        while (!$finished) {
            // Check if all jobs are completed
            $progress = json_decode(file_get_contents($progress_file), true) ?: [];
            $total_jobs = $progress['total_jobs'] ?? 0;
            $completed_jobs = $progress['completed_jobs'] ?? 0;
            $in_progress = $progress['in_progress'] ?? [];
            
            $percent_complete = $total_jobs > 0 ? round(($completed_jobs / $total_jobs) * 100) : 0;
            $elapsed = round(microtime(true) - $start_time, 1);
            $active_workers = count($in_progress);
            
            \WP_CLI::log("-> Progress: {$completed_jobs}/{$total_jobs} jobs ({$percent_complete}%) | Active workers: {$active_workers} | Elapsed: {$elapsed}s");
            
            // Check if all jobs are completed
            if ($completed_jobs >= $total_jobs) {
                $finished = true;
            }
            
            // Check on worker processes
            foreach ($this->worker_pids as $pid) {
                $status = pcntl_waitpid($pid, $status, WNOHANG);
                
                if ($status === $pid) {
                    // Worker has exited
                    $key = array_search($pid, $this->worker_pids);
                    if ($key !== false) {
                        unset($this->worker_pids[$key]);
                    }
                }
            }
            
            // Check if all workers have exited
            if (empty($this->worker_pids)) {
                $finished = true;
            }
            
            // Sleep before checking again
            if (!$finished) {
                sleep(1);
            }
        }
        
        $total_time = round(microtime(true) - $start_time, 1);
        \WP_CLI::log("✅ All jobs completed in {$total_time}s");
        
        // Wait for any remaining child processes
        foreach ($this->worker_pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
    }
    
    /**
     * Execute tests sequentially (fallback for systems without pcntl)
     */
    private function execute_sequential() {
        \WP_CLI::log("🧵 Sequential execution mode");
        
        // Initialize components
        $request_generator = new MicroChaos_Request_Generator();
        $total_jobs = count($this->job_queue);
        $completed_jobs = 0;
        $start_time = microtime(true);
        
        foreach ($this->job_queue as $job) {
            $completed_jobs++;
            $percent_complete = round(($completed_jobs / $total_jobs) * 100);
            
            \WP_CLI::log("⚡ Processing job #{$job['id']} - {$job['plan']['name']} ({$job['batch_size']} requests) | Progress: {$completed_jobs}/{$total_jobs} ({$percent_complete}%)");
            
            // Prepare request details
            $target_url = $request_generator->resolve_endpoint($job['plan']['endpoint']);
            $method = $job['plan']['method'] ?? 'GET';
            $cookies = null; // Would be set from auth credentials if provided
            $body = null;
            
            // Handle authentication if specified
            if (isset($job['plan']['auth'])) {
                // TODO: Implement authentication in Phase 3
            }
            
            // Process headers if present
            if (isset($job['plan']['headers']) && !empty($job['plan']['headers'])) {
                $request_generator->set_custom_headers($job['plan']['headers']);
            }
            
            // Process body data if present
            if (isset($job['plan']['data']) && !empty($job['plan']['data'])) {
                $body = json_encode($job['plan']['data']);
            } elseif (isset($job['plan']['body']) && !empty($job['plan']['body'])) {
                // Support for 'body' parameter for consistency with loadtest
                $body = $job['plan']['body'];
            }
            
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
            }
            
            // Add to results collection
            $this->results = array_merge($this->results, $batch_results);
            
            // Save interim results to temp file for recovery
            file_put_contents($this->temp_dir . '/results.json', json_encode($this->results));
        }
        
        $total_time = round(microtime(true) - $start_time, 1);
        \WP_CLI::log("✅ All jobs completed in {$total_time}s");
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanup_temp_files() {
        // In sequential mode, we need to collect results first
        if (!$this->parallel_supported) {
            return; // Keep files for now, will be cleaned up in next phase
        }
        
        // Collect result files from workers
        $all_results = [];
        
        for ($i = 0; $i < $this->workers; $i++) {
            $result_file = $this->temp_dir . "/worker_{$i}_results.json";
            if (file_exists($result_file)) {
                $worker_results = json_decode(file_get_contents($result_file), true) ?: [];
                $all_results = array_merge($all_results, $worker_results);
            }
        }
        
        // Save combined results
        file_put_contents($this->temp_dir . '/combined_results.json', json_encode($all_results));
        $this->results = $all_results;
        
        \WP_CLI::log("📊 Results collected from all workers: " . count($this->results) . " total requests");
        
        // Don't delete temp files yet - will be used in Phase 3 for reporting
        // We'll implement cleanup in Phase 4
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