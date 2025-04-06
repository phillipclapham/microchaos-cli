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

        // Phase 1 only includes parameter validation and JSON parsing
        \WP_CLI::log("\n🏗️ Phase 1 implementation completed");
        \WP_CLI::log("Test execution will be implemented in Phase 2.");
        
        // Future phases will implement:
        // - Phase 2: Worker Management
        // - Phase 3: Execution & Results Collection
        // - Phase 4: Reporting & Output
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

        if (!isset($plan['target'])) {
            \WP_CLI::warning("Test plan '{$plan['name']}' missing 'target' field. Skipping.");
            return;
        }

        // Add defaults for optional fields
        $plan['requests'] = $plan['requests'] ?? 100;
        $plan['concurrency'] = $plan['concurrency'] ?? 10;
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
            \WP_CLI::log("  Target: {$plan['target']}");
            \WP_CLI::log("  Requests: {$plan['requests']} | Concurrency: {$plan['concurrency']}");
            
            if (isset($plan['method']) && $plan['method'] !== 'GET') {
                \WP_CLI::log("  Method: {$plan['method']}");
            }
            
            if (isset($plan['headers']) && !empty($plan['headers'])) {
                \WP_CLI::log("  Headers: " . count($plan['headers']));
            }
            
            if (isset($plan['data']) && !empty($plan['data'])) {
                \WP_CLI::log("  Data: Yes");
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