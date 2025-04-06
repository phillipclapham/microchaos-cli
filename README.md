# ⚡️ MicroChaos CLI Load Tester

v1.8.4

Welcome to **MicroChaos**—a precision-built WP-CLI load testing tool forged in the fires of real-world WordPress hosting constraints.

When external load testing tools are blocked, when rate limits make your bots cry, and when SSH feels like a locked door... **MicroChaos lets you stress test like a ninja from the inside.**

Built for staging environments like **Pressable**, MicroChaos simulates traffic at scale—warm or cold cache, anonymous or authenticated, fast bursts or slow burns.

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
    └── thresholds.php     # Performance thresholds and visualization
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

```bash
wp microchaos loadtest --endpoint=home --count=100
```

Or go wild:

```bash
wp microchaos loadtest --endpoint=checkout --count=50 --auth=admin@example.com --concurrency-mode=async --cache-headers --resource-logging
```

## 🔧 CLI Options

### Basic Options

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
- `--concurrency-mode=async` Use curl_multi_exec() for parallel bursts
- `--rotation-mode=<mode>` Control endpoint rotation (serial, random)
- `--rampup` Gradually increase burst size to simulate organic load

### Monitoring & Reporting

- `--resource-logging` Print memory and CPU usage during test
- `--resource-trends` Track and analyze resource usage trends over time to detect memory leaks
- `--cache-headers` Parse cache headers and summarize hit/miss behavior
- `--save-baseline=<n>` Save results as a baseline for future comparisons
- `--compare-baseline=<n>` Compare results with a saved baseline
- `--monitoring-integration` Enable external monitoring integration via PHP error log
- `--monitoring-test-id=<id>` Specify custom test ID for monitoring integration

### Progressive Load Testing

- `--progressive` Run in progressive load testing mode to automatically find capacity limits
- `--progressive-start=<n>` Initial concurrency level for progressive testing (default: 5)
- `--progressive-step=<n>` Step size to increase concurrency in progressive testing (default: 5)
- `--progressive-max=<n>` Maximum concurrency to try in progressive testing (default: 100)
- `--threshold-response-time=<s>` Response time threshold in seconds (default: 3.0)
- `--threshold-error-rate=<p>` Error rate threshold in percentage (default: 10)
- `--threshold-memory=<p>` Memory usage threshold in percentage (default: 85)

### Threshold Calibration

- `--auto-thresholds` Automatically calibrate thresholds based on test results
- `--auto-thresholds-profile=<name>` Profile name to save calibrated thresholds (default: 'default')
- `--use-thresholds=<profile>` Use previously saved thresholds for reporting

---

## 💡 Examples

Load test the homepage with cache warmup and log output

```bash
wp microchaos loadtest --endpoint=home --count=100 --warm-cache --log-to=uploads/home-log.txt
```

Simulate async WooCommerce cart traffic

```bash
wp microchaos loadtest --endpoint=cart --count=50 --concurrency-mode=async
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

Run progressive load testing to find capacity limits

```bash
wp microchaos loadtest --endpoint=home --progressive --resource-logging
```

Run progressive load testing with custom thresholds and limits

```bash
wp microchaos loadtest --endpoint=home --progressive --threshold-response-time=2 --progressive-max=150
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
📦 Cache Header Summary:
   🦇 Batcache Hit Ratio: 0%
   🌐 Edge Cache Hit Ratio: 100%

   x-ac:
     3.dca _atomic_dca STALE: 3
     3.dca _atomic_dca UPDATING: 7

   ⏲ Average Cache Age: 42.5 seconds
```

Parsed and summarized directly from HTTP response headers—no deep instrumentation required.

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

## 🧠 Design Philosophy

"Improvisation > Perfection. Paradox is fuel."

Test sideways. Wear lab goggles. Hit the endpoints like they owe you money and answers.

- ⚡ Internal-only, real-world load generation
- 🧬 Built for performance discovery and observability
- 🤝 Friendly for TAMs, support engineers, and even devs ;)

---

## 🛠 Future Ideas

- **Parallel testing** - Add capability to fire test sequences in parallel, each with different parameters, to simulate more realistic mixed traffic patterns (e.g., anonymous users browsing products while logged-in users checkout simultaneously).

- **Snapshot comparison** - Save full detail snapshots that include all individual request data, not just summaries, for more granular analysis between test runs and historical trending.

---

## 🖖 Author

Built by Phill in a caffeine-fueled, chaos-aligned, performance-obsessed dev haze.

⸻

"If you stare at a site load test long enough, the site load test starts to stare back."
— Ancient Pressable Proverb

---

## 🧾 License

This project is licensed under the GPLv3 License.
