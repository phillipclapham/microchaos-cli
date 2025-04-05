# ⚡️ MicroChaos CLI Load Tester

v1.5

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
    └── reporting-engine.php # Results collection and reporting
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
2. Run the loopback test with at least 2–3× those numbers to see if resource usage climbs to a point of concern.
3. Watch server-level metrics (PHP error logs, memory usage, CPU load) to see if you're hitting resource ceilings.

```bash
wp microchaos loadtest --endpoint=home --count=100
```

Or go wild:

```bash
wp microchaos loadtest --endpoint=checkout --count=50 --auth=admin@example.com --concurrency-mode=async --cache-headers --resource-logging
```

## 🔧 CLI Options

- `--endpoint=<slug>` home, shop, cart, checkout, or custom:/my-path
- `--endpoints=<endpoint-list>` Comma-separated list of endpoints to rotate through
- `--count=<n>` Total requests to send (default: 100)
- `--burst=<n>` Requests per burst (default: 10)
- `--delay=<seconds>` Delay between bursts (default: 2)
- `--method=<method>` HTTP method to use (GET, POST, PUT, DELETE, etc.)
- `--body=<data>` POST/PUT body (string, JSON, or file:path.json)
- `--auth=<email>` Run as a specific logged-in user
- `--multi-auth=<email1,email2>` Rotate across multiple users
- `--cookie=<name=value>` Set custom cookie(s), comma-separated for multiple
- `--warm-cache` Prime the cache before testing
- `--flush-between` Flush cache before each burst
- `--log-to=<relative path>` Log results to file under wp-content/
- `--concurrency-mode=async` Use curl_multi_exec() for parallel bursts
- `--rotation-mode=<mode>` Control endpoint rotation (serial, random)
- `--rampup` Gradually increase burst size to simulate organic load
- `--resource-logging` Print memory and CPU usage during test
- `--cache-headers` Parse cache headers and summarize hit/miss behavior

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

---

## 📊 What You Get

### 🟢 Per-Request Log Output

```bash
→ 200 in 0.0296s [EDGE_UPDATING] x-ac:3.dca_atomic_dca UPDATING
→ 200 in 0.0303s [EDGE_STALE] x-ac:3.dca _atomic_dca STALE
```

Each request is timestamped, status-coded, cache-labeled, and readable at a glance.

---

### 📈 Load Summary

```bash
📊 Load Test Summary:
   Total Requests: 10
   Success: 10 | Errors: 0
   Avg Time: 0.0331s | Median: 0.0296s
   Fastest: 0.0278s | Slowest: 0.0567s
```

---

### 💻 Resource Usage (with --resource-logging)

```bash
📊 Resource Utilization Summary:
   Avg Memory Usage: 118.34 MB, Median: 118.34 MB
   Avg Peak Memory: 118.76 MB, Median: 118.76 MB
   Avg CPU Time (User): 1.01s, Median: 1.01s
   Avg CPU Time (System): 0.33s, Median: 0.33s
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

## 🧠 Design Philosophy

"Improvisation > Perfection. Paradox is fuel."

Test sideways. Wear lab goggles. Hit the endpoints like they owe you money and answers.

- ⚡ Internal-only, real-world load generation
- 🧬 Built for performance discovery and observability
- 🤝 Friendly for TAMs, support engineers, and even devs ;)

---

## 🛠 Future Ideas

- Add max/min CPU/memory usage tracking
- Ability to send custom headers with requests
- Test plans via JSON config (`wp microchaos plan`)
- Response body matching/diffing capabilities
- Enhanced export format options (CSV/JSON)

---

## 🖖 Author

Built by Phill in a caffeine-fueled, chaos-aligned, performance-obsessed dev haze.

⸻

"If you stare at a site load test long enough, the site load test starts to stare back."
— Ancient Pressable Proverb

---

## 🧾 License

This project is licensed under the GPLv3 License.
