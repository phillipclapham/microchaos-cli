# Parallel Testing Implementation Plan

## Overview

This document serves as the project memory bank for implementing parallel testing functionality in MicroChaos CLI via a new
`wp microchaos paralleltest` command. This allows us to work on the implementation in discrete substeps across multiple
coding sessions.

## Command Structure

```
wp microchaos paralleltest [--file=<path>] [--plan=<json>] [--workers=<number>] [--output=<format>]
```

## Parameters

- `--file`: Path to a JSON file containing test plan(s)
- `--plan`: JSON string containing test plan(s) directly in the command
- `--workers`: (Optional) Number of parallel workers to use (default: 3)
- `--output`: (Optional) Output format: json|table|csv (default: table)

## JSON Schema

### Single Test Plan

```json
{
  "name": "API Load Test",
  "description": "Test API endpoint under load",
  "endpoint": "<https://example.com/api/endpoint>",
  "requests": 100,
  "concurrency": 10,
  "delay": 0,
  "timeout": 5,
  "headers": {
    "Content-Type": "application/json",
    "Authorization": "Bearer token123"
  },
  "body": {
    "param1": "value1",
    "param2": "value2"
  },
  "method": "POST",
  "thresholds": {
    "response_time": 500,
    "error_rate": 0.05
  }
}
```

### Multiple Test Plans (Array)

```json
[
  {
    "name": "API Test 1",
    "endpoint": "https://example.com/api/endpoint1",
    "requests": 50,
    "concurrency": 5
  },
  {
    "name": "API Test 2",
    "endpoint": "https://example.com/api/endpoint2",
    "requests": 75,
    "concurrency": 8
  }
]
```

## Project Plan and Milestones

### Phase 1: Command Registration & Parameter Handling (Session 1)
- [x] Create new command class `MicroChaos_ParallelTest`
- [x] Register command with WP-CLI
- [x] Implement parameter validation logic
- [x] Add JSON parsing for both file and direct input
- [x] Validate test plan schema structure

### Phase 2: Worker Management (Session 2)
- [x] Create worker pool based on `--workers` parameter
- [x] Implement job distribution strategy
- [x] Add inter-process communication mechanism
- [x] Create test execution logic for individual worker

### Phase 3: Execution & Results Collection (Session 3)
- [ ] Design progress reporting system
- [ ] Implement results collection and aggregation
- [ ] Create error handling and recovery mechanism
- [ ] Build timeout management system

### Phase 4: Reporting & Output (Session 4)
- [ ] Implement real-time progress display
- [ ] Create summary report generation
- [ ] Format output based on `--output` parameter
- [ ] Add detailed results storage option

## Technical Considerations

### Parallelization Approach
- Use PHP's `pcntl_fork()` for process management on Linux/macOS
- Fallback to sequential execution with simulated parallelism on Windows
- Implement worker pool with job queue

### Resource Management
- Monitor and limit memory usage per worker
- Implement graceful failure handling
- Add worker health checks

### Results Aggregation
- Collect metrics: response time, error rate, throughput
- Calculate aggregate statistics (min, max, avg, percentiles)
- Compare against defined thresholds

## Testing Strategy
1. Unit tests for parameter handling and plan validation
2. Integration tests for worker management
3. End-to-end tests against controlled endpoints
4. Performance benchmarks comparing sequential vs parallel execution

## Future Enhancements
- Add plugin system for custom response validators
- Implement distribution curve options (linear, gaussian, burst)
- Add support for dynamic variables and request chaining
- Create visualization for test results

## Session Progress Tracking

### Session 1 (Date: 4/6/2025)
- Tasks completed:
  - Created new command class `MicroChaos_ParallelTest` in parallel-test.php
  - Added the paralleltest command to MicroChaos_Commands class
  - Implemented parameter validation logic for file/plan, workers, and output format
  - Added JSON parsing for both file and direct input
  - Added test plan validation with required/optional field handling
  - Updated bootstrap.php to load the new component
  - Updated build.js to include new component in single-file build
  - Built and tested the updated single-file distribution
- Next steps:
  - Implement worker pool management in Phase 2
  - Develop job distribution strategy
  - Create inter-process communication mechanism
  - Create test execution logic for individual workers

### Session 2 (Date: 4/6/2025)
- Tasks completed:
  - Created worker pool management based on --workers parameter
  - Implemented job distribution strategy using file-based queuing
  - Added inter-process communication mechanism using temp files and file locking
  - Created test execution logic for individual workers
  - Implemented platform detection to run in parallel mode (Linux/macOS) or sequential mode (Windows/unsupported)
  - Added progress monitoring system for real-time updates
  - Implemented results collection from all workers
  - Standardized parameter naming between paralleltest and loadtest commands:
    - Changed `target` to `endpoint` for consistency
    - Added support for `body` in addition to `data`
    - Added support for both `requests` and `count`
    - Added support for both `concurrency` and `burst`

#### Implementation Details
- **Worker Pool Management**:
  - Uses PHP's `pcntl_fork()` for parallel execution on Linux/macOS
  - Falls back to sequential execution on Windows or systems without pcntl
  - Creates worker processes based on `--workers` parameter (default: 3)
  - Job queue is prepared by breaking down test plans into concurrency-sized batches

- **Inter-Process Communication**:
  - Uses temporary directory for file-based communication (`microchaos_[uniqid]` in system temp path)
  - Files created:
    - `jobs.json`: Contains the job queue for workers to consume
    - `progress.json`: Tracks overall progress and active jobs
    - `worker_[id].log`: Individual log files for each worker
    - `worker_[id]_results.json`: Results collected by each worker
    - `combined_results.json`: Aggregated results from all workers

- **Job Distribution System**:
  - Job format: `{"id": job_id, "plan": plan_data, "batch_size": batch_size}`
  - File locking (flock) used to prevent race conditions during job acquisition
  - Each worker processes jobs until queue is empty

- **Current State**:
  - Worker processes can successfully execute test plans
  - Parent process monitors progress and collects results
  - Results are saved but not yet analyzed or presented
  - Temporary files are kept for Phase 3 processing
  - Basic error handling implemented but needs enhancement

#### Code Architecture
- Main execution flow in `run()` method
- Platform detection in `$this->parallel_supported = extension_loaded('pcntl')`
- Worker pool created in `execute_parallel()` or `execute_sequential()`
- Worker execution logic in `run_worker()` method
- Job acquisition with locking in `acquire_job()` method
- Progress tracking in `monitor_workers()` method
- Result collection in `cleanup_temp_files()` method

- Next steps:
  - Design and implement detailed progress reporting system
  - Refine results collection and aggregation for analytics 
  - Implement error handling and recovery mechanism
  - Build a timeout management system
  - Add authentication support for test plans

### Session 3 Planning
The next session will focus on Phase 3: Execution & Results Collection. Here are the key areas that need to be addressed:

#### Progress Reporting System
- Design a real-time progress indicator for the parent process
- Create a standardized format for reporting status updates
- Implement color-coded output for different status levels
- Add ETA calculation based on completed jobs and average time per job

#### Results Collection and Aggregation
- Define the metrics to be collected (response time, error rate, throughput)
- Implement statistical calculations (min, max, avg, median, percentiles)
- Create data structures for storing results by test plan and overall
- Add comparison against thresholds defined in test plans

#### Error Handling and Recovery
- Implement worker process health checks
- Add detection and handling of failed workers
- Create mechanism to retry failed jobs
- Implement graceful shutdown on fatal errors
- Add signal handling (SIGINT, SIGTERM) for clean termination

#### Timeout Management
- Add global test execution timeout
- Implement per-job timeout tracking
- Create mechanism to abort long-running jobs
- Handle timeout notification and reporting

### Session 3 (Date: __________)
- Tasks completed:
  - 
- Next steps:
  - 

### Session 4 (Date: __________)
- Tasks completed:
  - 
- Next steps:
  - 
