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
  "target": "<https://example.com/api/endpoint>",
  "requests": 100,
  "concurrency": 10,
  "delay": 0,
  "timeout": 5,
  "headers": {
    "Content-Type": "application/json",
    "Authorization": "Bearer token123"
  },
  "data": {
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
    "target": "https://example.com/api/endpoint1",
    "requests": 50,
    "concurrency": 5
  },
  {
    "name": "API Test 2",
    "target": "https://example.com/api/endpoint2",
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
- [ ] Create worker pool based on `--workers` parameter
- [ ] Implement job distribution strategy
- [ ] Add inter-process communication mechanism
- [ ] Create test execution logic for individual worker

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

### Session 2 (Date: __________)
- Tasks completed:
  - 
- Next steps:
  - 

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
