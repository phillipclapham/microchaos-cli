# ⚡️ MicroChaos CLI Load Tester

v3.0.0

Welcome to **MicroChaos**—a precision-built WP-CLI load testing tool forged in the fires of real-world WordPress hosting constraints.

When external load testing tools are blocked, when rate limits make your bots cry, and when SSH feels like a locked door... **MicroChaos lets you stress test like a ninja from the inside.**

Built for staging environments like **Pressable**, MicroChaos simulates traffic at scale—warm or cold cache, anonymous or authenticated, fast bursts or slow burns.

## Current Bugs

*No known bugs at this time.*

---

## 🎯 Purpose

- 🔐 **Run realistic load tests** *inside* WordPress with zero external traffic
- 🧠 **Simulate logged-in users**, WooCommerce flows, REST endpoints, and custom paths
- 🧰 **Profile caching**, resource usage, and performance regressions from the CLI
- 🦇 Built for **staging, QA, support engineers, TAMs, and performance-hungry devs**

---

## 📦 Installation

### Standard Installation

1. Copy the file `microchaos-cli.php` from the `dist` directory to `wp-content/mu-plugins/` on your site.
2. Make sure WP-CLI is available in your environment.
3. You're ready to chaos!

---

## 🚨 Platform Considerations (Pressable & Managed Hosts)

### Optimized for Pressable

MicroChaos v3.0.0 is optimized specifically for **Pressable** and similar managed WordPress hosts where loopback requests are rate-limited. The tool uses serial execution which works perfectly with platform restrictions.

- **Rate Limiting**: Loopback requests on Pressable are limited to ~10 concurrent requests
- **Serial Processing**: MicroChaos uses serial execution optimized for this environment
- **High Burst Counts**: Use large burst values (50-500+) to maximize throughput within rate limits

### Best Practices for Pressable

1. **Duration-Based Testing with High Bursts**: Run sustained tests to measure requests/second capacity
   ```bash
   wp microchaos loadtest --endpoints=home,shop,cart,checkout --duration=10 --burst=100 --resource-logging --resource-trends
   ```

2. **Endpoint Rotation**: Simulate realistic user flows by rotating through typical site paths
   ```bash
   wp microchaos loadtest --endpoints=home,shop,product,cart,checkout --rotation-mode=serial --duration=10 --burst=200
   ```

3. **Baseline Testing**: Start with smaller bursts to establish performance baseline, then scale up
   ```bash
   # Initial baseline
   wp microchaos loadtest --endpoints=home,shop --duration=5 --burst=50 --save-baseline=initial
   # Scale up based on site speed
   wp microchaos loadtest --endpoints=home,shop --duration=10 --burst=500 --compare-baseline=initial
   ```

4. **Resource Analysis**: Combine with Grafana/monitoring data to determine worker and RAM requirements
   ```bash
   wp microchaos loadtest --endpoints=home,shop,cart --duration=10 --burst=300 --resource-logging --resource-trends
   ```

**Note**: The `--burst` flag controls how many **serial** requests are sent before pausing, not concurrent requests. Since Pressable processes requests serially due to loopback restrictions, you can use large burst values (50-500+) effectively. The key metrics are:
- Total requests per second achieved
- Resource utilization trends
- Worker and memory requirements at target load

### What Works Well on Pressable

- ✅ High-volume serial request testing (50-500+ burst sizes)
- ✅ Duration-based load testing for sustained traffic simulation
- ✅ Endpoint rotation to simulate real user flows
- ✅ Resource monitoring and trend analysis
- ✅ Cache warmup and behavior analysis
- ✅ Authenticated user simulation
- ✅ Baseline comparisons for performance tracking
- ✅ Custom headers and cookies
- ✅ Requests/second capacity measurement

---

## 🏗️ Architecture

MicroChaos features a modular component-based architecture:

```text
microchaos/
├── bootstrap.php          # Component loader
├── core/                  # Core components
    ├── commands.php       # WP-CLI command handling
    ├── request-generator.php # HTTP request management
    ├── cache-analyzer.php # Cache header analysis
    ├── resource-monitor.php # System resource tracking
    ├── reporting-engine.php # Results collection and reporting
    ├── integration-logger.php # External monitoring integration
    ├── thresholds.php     # Performance thresholds and visualization
    └── authentication-manager.php # Auth handling
```

This architecture makes the codebase more maintainable, testable, and extensible for developers who want to customize or extend functionality.

## 🔄 Build Process

MicroChaos uses a build system that compiles the modular version into a single-file distribution:

```text
build.js                   # Node.js build script
dist/                      # Generated distribution files
└── microchaos-cli.php     # Compiled single-file version
```

### Building the Single-File Version

If you've made changes to the modular components and want to rebuild the single-file version:

```bash
# Make sure you have Node.js installed
node build.js
```

This will generate a fresh single-file version in the `dist/` directory, ready for distribution. The build script:

1. Extracts all component classes
2. Combines them into a single file
3. Maintains proper WP-CLI registration
4. Preserves backward compatibility

**Note**: Always develop in the modular version, then build for distribution. The single-file version is generated automatically and should not be edited directly.

---

## 🛠 Usage

1. Decide the real-world traffic scenario you need to test (e.g., 20 concurrent hits sustained, or a daily average of 30 hits/second at peak).
2. Run the loopback test with at least 2-3x those numbers to see if resource usage climbs to a point of concern.
3. Watch server-level metrics (PHP error logs, memory usage, CPU load) to see if you're hitting resource ceilings.

### Standard Load Testing

```bash
wp microchaos loadtest --endpoint=home --count=100
```

Or go wild:

```bash
wp microchaos loadtest --endpoint=checkout --count=50 --auth=admin@example.com --cache-headers --resource-logging
```

## 🔧 CLI Options

### Available Commands

- `wp microchaos loadtest` Run a load test with various options

### Basic Options (loadtest)

- `--endpoint=<slug>` home, shop, cart, checkout, or custom:/my-path
- `--endpoints=<endpoint-list>` Comma-separated list of endpoints to rotate through
- `--count=<n>` Total requests to send (default: 100)
- `--duration=<minutes>` Run test for specified duration instead of fixed request count
- `--burst=<n>` Requests per burst (default: 10)
- `--delay=<seconds>` Delay between bursts (default: 2)

### Request Configuration

- `--method=<method>` HTTP method to use (GET, POST, PUT, DELETE, etc.)
- `--body=<data>` POST/PUT body (string, JSON, or file:path.json)
- `--auth=<email>` Run as a specific logged-in user
- `--multi-auth=<email1,email2>` Rotate across multiple users
- `--cookie=<name=value>` Set custom cookie(s), comma-separated for multiple
- `--header=<name=value>` Set custom HTTP headers, comma-separated for multiple

### Test Behavior

- `--warm-cache` Prime the cache before testing
- `--flush-between` Flush cache before each burst
- `--log-to=<relative path>` Log results to file under wp-content/
- `--rotation-mode=<mode>` Control endpoint rotation (serial, random)
- `--rampup` Gradually increase burst size to simulate organic load

### Monitoring & Reporting

- `--resource-logging` Print memory and CPU usage during test
- `--resource-trends` Track and analyze resource usage trends over time to detect memory leaks
- `--cache-headers` Parse Pressable-specific cache headers (x-ac, x-nananana) and summarize cache behavior
- `--save-baseline=<n>` Save results as a baseline for future comparisons
- `--compare-baseline=<n>` Compare results with a saved baseline
- `--monitoring-integration` Enable external monitoring integration via PHP error log
- `--monitoring-test-id=<id>` Specify custom test ID for monitoring integration

### Threshold Calibration

- `--auto-thresholds` Automatically calibrate thresholds based on test results
- `--auto-thresholds-profile=<name>` Profile name to save calibrated thresholds (default: 'default')
- `--use-thresholds=<profile>` Use previously saved thresholds for reporting

---

## 💡 Examples

### Load Testing Examples

Load test the homepage with cache warmup and log output

```bash
wp microchaos loadtest --endpoint=home --count=100 --warm-cache --log-to=uploads/home-log.txt
```

Test multiple endpoints with random rotation

```bash
wp microchaos loadtest --endpoints=home,shop,cart,checkout --count=100 --rotation-mode=random
```

Add custom cookies to break caching

```bash
wp microchaos loadtest --endpoint=home --count=50 --cookie="session_id=123,test_variation=B"
```

Add custom HTTP headers to requests

```bash
wp microchaos loadtest --endpoint=home --count=50 --header="X-Test=true,Authorization=Bearer token123"
```

Simulate real users hitting checkout

```bash
wp microchaos loadtest --endpoint=checkout --count=25 --auth=admin@example.com
```

Hit a REST API endpoint with JSON from file

```bash
wp microchaos loadtest --endpoint=custom:/wp-json/api/v1/orders --method=POST --body=file:data/orders.json
```

Ramp-up traffic slowly over time

```bash
wp microchaos loadtest --endpoint=shop --count=100 --rampup
```

Save test results as a baseline for future comparison

```bash
wp microchaos loadtest --endpoint=home --count=100 --save-baseline=homepage
```

Compare with previously saved baseline

```bash
wp microchaos loadtest --endpoint=home --count=100 --compare-baseline=homepage
```

Run a test for a specific duration instead of request count

```bash
wp microchaos loadtest --endpoint=home --duration=5 --burst=15 --resource-logging
```

Run a test with trend analysis to detect potential memory leaks

```bash
wp microchaos loadtest --endpoint=home --duration=10 --resource-logging --resource-trends
```

Auto-calibrate thresholds based on the site's current performance

```bash
wp microchaos loadtest --endpoint=home --count=50 --auto-thresholds
```

Run a test with previously calibrated thresholds

```bash
wp microchaos loadtest --endpoint=home --count=100 --use-thresholds=homepage
```

Run test with monitoring integration enabled for external metrics collection

```bash
wp microchaos loadtest --endpoint=home --count=50 --monitoring-integration
```

### Cache Header Analysis (Pressable)

Analyze Pressable's cache behavior with detailed per-request and summary reporting

```bash
wp microchaos loadtest --endpoint=home --count=50 --cache-headers --warm-cache
```

Example per-request output:
```
-> 200 in 0.032s [x-ac: 3.dca_atomic_dca STALE] [x-nananana: MISS]
-> 200 in 0.024s [x-ac: 3.dca_atomic_dca UPDATING] [x-nananana: HIT]
```

Example cache summary:
```
📦 Pressable Cache Header Summary:
   🌐 Edge Cache (x-ac):
     3.dca_atomic_dca STALE: 25 (50.0%)
     3.dca_atomic_dca UPDATING: 15 (30.0%)
     3.dca_atomic_dca HIT: 10 (20.0%)
     
   🦇 Batcache (x-nananana):
     MISS: 30 (60.0%)
     HIT: 20 (40.0%)
```

---

## 📊 What You Get

### 🟢 Per-Request Log Output

```bash
-> 200 in 0.0296s [EDGE_UPDATING] x-ac:3.dca_atomic_dca UPDATING
-> 200 in 0.0303s [EDGE_STALE] x-ac:3.dca _atomic_dca STALE
```

Each request is timestamped, status-coded, cache-labeled, and readable at a glance.

---

### 📈 Load Summary

```bash
📊 Load Test Summary:
   Total Requests: 10
   Success: 10 | Errors: 0 | Error Rate: 0%
   Avg Time: 0.0331s | Median: 0.0296s
   Fastest: 0.0278s | Slowest: 0.0567s

   Response Time Distribution:
   0.03s - 0.04s [██████████████████████████████] 8
   0.04s - 0.05s [█████] 1
   0.05s - 0.06s [█████] 1
```

---

### 💻 Resource Usage (with --resource-logging)

```bash
📊 Resource Utilization Summary:
   Memory Usage: Avg: 118.34 MB, Median: 118.34 MB, Min: 96.45 MB, Max: 127.89 MB
   Peak Memory: Avg: 118.76 MB, Median: 118.76 MB, Min: 102.32 MB, Max: 129.15 MB
   CPU Time (User): Avg: 1.01s, Median: 1.01s, Min: 0.65s, Max: 1.45s
   CPU Time (System): Avg: 0.33s, Median: 0.33s, Min: 0.12s, Max: 0.54s

   Memory Usage (MB):
   Memory     [████████████████████████████████] 118.34
   Peak       [████████████████████████████████████] 118.76
   MaxMem     [██████████████████████████████████████████████] 127.89
   MaxPeak    [███████████████████████████████████████████████] 129.15
```

---

### 📈 Resource Trend Analysis (with --resource-trends)

```bash
📈 Resource Trend Analysis:
   Data Points: 25 over 120.45 seconds
   Memory Usage: ↑12.3% over test duration
   Pattern: Moderate growth
   Peak Memory: ↑8.7% over test duration
   Pattern: Stabilizing

   Memory Usage Trend (MB over time):
     127.5 ┌────────────────────────────────────────────────────────────┐
     124.2 │                                                •••---------│
     121.8 │                                         ••----•            │
     119.5 │                                    •----                   │
     117.1 │                            ••-----•                        │
     114.8 │                       ••--•                                │
     112.4 │                 ••---•                                     │
     110.1 │             •--•                                           │
     107.8 │        •---•                                               │
     105.4 │  •••--•                                                    │
     103.1 │-•                                                          │
      10.0 └────────────────────────────────────────────────────────────┘
       0.1     30.1     60.2    90.2
```

---

### 📦 Cache Header Summary (with --cache-headers)

```bash
📦 Pressable Cache Header Summary:
   🌐 Edge Cache (x-ac):
     3.dca_atomic_dca STALE: 3 (30.0%)
     3.dca_atomic_dca UPDATING: 7 (70.0%)
     
   🦇 Batcache (x-nananana):
     MISS: 6 (60.0%)
     HIT: 4 (40.0%)

   ⏲ Average Cache Age: 42.5 seconds
```

Parsed and summarized directly from Pressable-specific HTTP response headers—no deep instrumentation required.

---

### 🔄 Baseline Comparison

```bash
📊 Load Test Summary:
   Total Requests: 10
   Success: 10 | Errors: 0 | Error Rate: 0%
   Avg Time: 0.0254s | Median: 0.0238s
   Fastest: 0.0212s | Slowest: 0.0387s

   Comparison to Baseline:
   - Avg: ↓23.5% vs 0.0331s
   - Median: ↓19.6% vs 0.0296s
```

Track performance improvements or regressions across changes.

---

### 📈 Progressive Load Testing Results

```bash
📊 Progressive Load Test Results:
   Total Requests Fired: 312
   💥 Breaking Point: 40 concurrent requests
   💥 Reason: Response time threshold exceeded (3.254s > 3.0s)
   ✓ Recommended Maximum Capacity: 32 concurrent requests

📈 Final Level Performance:
   Total Requests: 40
   Success: 36 | Errors: 4 | Error Rate: 10%
   Avg Time: 3.254s | Median: 3.126s
   Fastest: 1.854s | Slowest: 5.387s

   Memory Usage: Avg: 92.45 MB, Median: 92.45 MB, Min: 64.12 MB, Max: 103.78 MB
   Peak Memory: Avg: 94.32 MB, Median: 94.32 MB, Min: 72.56 MB, Max: 107.41 MB
```

Automatically determine maximum capacity and recommended concurrent user limits.

---

### 📊 Parallel Testing Results

```bash
🚀 MicroChaos Parallel Test Started
-> Test Plans: 3
-> Workers: 5
-> Timeout: 600 seconds
-> Output Format: table
-> Percentiles: 95, 99

📋 Test Plan Summary:
Test Plan #1: Homepage Test
  Endpoint: home
  Requests: 100 | Concurrency: 10
  Method: GET

Test Plan #2: Shop Page Test
  Endpoint: shop
  Requests: 50 | Concurrency: 5
  Auth: admin@example.com

Test Plan #3: API Order Test
  Endpoint: custom:/wp-json/wc/v3/orders
  Requests: 25 | Concurrency: 3
  Method: POST
  Headers: 2
  Body: Yes
  Thresholds: Response time: 500ms, Error rate: 0.05

-> Parallel execution enabled with 5 workers.

📊 Test Results Summary:
┌───────────────────────────────────────────────────────────────────────────┐
│ Test Results                                                              │
├───────────────────────────────────────────────────────────────────────────┤
│ OVERALL SUMMARY                                                         │
│ Total Requests: 175 | Success: 173 | Errors: 2 | Error Rate: 1.1%    │
│ Avg Time: 0.217s | Median: 0.183s | Min: 0.102s | Max: 0.786s         │
│ Percentiles: P95: 0.421s | P99: 0.654s                                 │
├───────────────────────────────────────────────────────────────────────────┤
│ RESULTS BY TEST PLAN                                                     │
│ Homepage Test                                        │
│   Requests: 100 | Success: 100 | Errors: 0 | Error Rate: 0.0%    │
│   Avg Time: 0.184s | Median: 0.165s | Min: 0.102s | Max: 0.501s    │
├───────────────────────────────────────────────────────────────────────────┤
│ Shop Page Test                                       │
│   Requests: 50 | Success: 49 | Errors: 1 | Error Rate: 2.0%    │
│   Avg Time: 0.251s | Median: 0.223s | Min: 0.142s | Max: 0.622s    │
├───────────────────────────────────────────────────────────────────────────┤
│ API Order Test                                       │
│   Requests: 25 | Success: 24 | Errors: 1 | Error Rate: 4.0%    │
│   Avg Time: 0.273s | Median: 0.231s | Min: 0.154s | Max: 0.786s    │
│ ⚠️ Threshold violations for API Order Test:                  │
│    - Error rate exceeded threshold: 4.0% > 5.0%                        │
├───────────────────────────────────────────────────────────────────────────┤
│ RESPONSE TIME DISTRIBUTION                                              │
│ 0.10s - 0.17s [████████████████████                ] 63    │
│ 0.17s - 0.24s [███████████████████████             ] 72    │
│ 0.24s - 0.31s [████████                            ] 16    │
│ 0.31s - 0.38s [████                                ] 8     │
│ 0.38s - 0.45s [███                                 ] 6     │
│ 0.45s - 0.52s [██                                  ] 4     │
│ 0.52s - 0.59s [██                                  ] 3     │
│ 0.59s - 0.66s [█                                   ] 2     │
│ 0.66s - 0.73s [                                    ] 0     │
│ 0.73s - 0.80s [█                                   ] 1     │
└───────────────────────────────────────────────────────────────────────────┘
🎉 Parallel Test Execution Complete
```

Run multiple test plans simultaneously to simulate realistic mixed traffic patterns.

---

## 🧠 Design Philosophy

"Improvisation > Perfection. Paradox is fuel."

Test sideways. Wear lab goggles. Hit the endpoints like they owe you money and answers.

- ⚡ Internal-only, real-world load generation
- 🧬 Built for performance discovery and observability
- 🤝 Friendly for TAMs, support engineers, and even devs ;)

---

## 🛠 Future Ideas

- **Advanced visualizations** - Implement interactive charts and graphs for more detailed visual analysis of test results.

- **Custom test plan templates** - Provide a library of pre-configured test plans for common testing scenarios (e.g., e-commerce checkout flows, membership sites, etc.).

---

## 🖖 Author

Built by Phill in a caffeine-fueled, chaos-aligned, performance-obsessed dev haze.

⸻

"If you stare at a site load test long enough, the site load test starts to stare back."
— Ancient Pressable Proverb

---

## 🧾 License

This project is licensed under the GPLv3 License.
