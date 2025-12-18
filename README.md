# ⚡️ MicroChaos CLI Load Tester

v4.0.0 — "The Headless Horseman"

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

## 🆕 What's New in v4.0.0 "The Headless Horseman"

v4.0.0 brings **first-class headless WordPress support** for testing GraphQL endpoints.

### Headless WordPress Testing

- **`--graphql` shorthand** - Sets method=POST and endpoint=/graphql automatically
- **`--user-agent` flag** - Custom User-Agent for Pressable headless apps
- **GraphQL error detection** - Automatically detects errors in 200 OK responses
- **Comprehensive guide** - Full workflow for headless WordPress capacity planning

### New Output

Per-request GraphQL error reporting:
```
-> 200 in 0.45s [GQL errors: 1]
```

Summary with GraphQL error tracking:
```
Success: 95 | HTTP Errors: 0 | GraphQL Errors: 5 | Error Rate: 5%
```

### Why "The Headless Horseman"?

Because testing a headless site without proper load testing is like Ichabod Crane riding through Sleepy Hollow without a lantern. MicroChaos is your lantern.

---

## 📜 What's New in v3.0.0

### Simplified & Focused

v3.0.0 is a **radical simplification**. We removed features that didn't work reliably on Pressable (parallel testing, progressive mode) and focused on what actually delivers value:

- **52% smaller codebase** - Removed 2,590 lines of non-functional code
- **Single command** - `wp microchaos loadtest` does everything
- **Serial execution optimized** - Works perfectly within Pressable's loopback rate limits

### New Features

- **Execution Metrics** - Every test now reports:
  - Requests per second (RPS) achieved
  - Capacity projections (hourly/daily/monthly)
  - Test timestamps (human-readable + ISO 8601)
  - Total duration with formatted display

### Under the Hood

- **100% type hints** - All public methods have PHP 8.2+ type declarations
- **Testable architecture** - Logger interface enables unit testing without WordPress
- **61 unit tests** - Core components tested in 14ms without WordPress runtime
- **Clean separation** - Thin CLI wrapper (63 lines) + LoadTestOrchestrator (656 lines)

### Removed Features

These features were removed because they don't work on Pressable due to loopback rate limiting:

- ❌ `wp microchaos parallel` - Parallel test execution
- ❌ `wp microchaos progressive` - Progressive load testing
- ❌ `--concurrency` flag - True concurrent requests

If you need these features, use an external load testing tool (k6, Artillery, etc.) that can hit your site from outside.

---

## 📦 Installation

### Standard Installation

1. Copy the file `microchaos-cli.php` from the `dist` directory to `wp-content/mu-plugins/` on your site.
2. Make sure WP-CLI is available in your environment.
3. You're ready to chaos!

---

## 🚨 Platform Considerations (Pressable & Managed Hosts)

MicroChaos is built specifically for **Pressable** and similar managed WordPress hosts where loopback requests are rate-limited (~10 concurrent max). The tool uses **serial execution** which works perfectly within these constraints.

### Understanding the `--burst` Flag

The `--burst` flag controls how many **sequential** requests fire before pausing (via `--delay`). This is NOT concurrency—it's throughput control.

**Choosing burst values:**

| Site Speed | Recommended Burst | Reasoning |
|------------|-------------------|-----------|
| Fast (<100ms) | 200-500 | High throughput, quick completion |
| Medium (100-500ms) | 50-200 | Balance throughput vs duration |
| Slow (>500ms) | 20-50 | Avoid duration overshoot |

**⚠️ Duration Overshoot Warning:** In duration-based tests (`--duration=X`), the current burst always completes before the test stops. A 1-minute test with `--burst=500` on a slow site (1s per request) could run 8+ minutes. Start conservative, scale up.

### The Essential Flag Combo

For capacity planning, always combine these three:

```bash
--resource-logging --resource-trends --cache-headers
```

- **resource-logging**: Memory/CPU per burst (are we hitting limits?)
- **resource-trends**: Memory over time (detecting leaks)
- **cache-headers**: Pressable cache behavior (x-ac, x-nananana)

---

## 📊 Capacity Planning Guide

This is how MicroChaos is actually used for Pressable capacity audits.

### The 3-Phase Workflow

#### Phase 1: Baseline Discovery

Establish how the site performs under known conditions:

```bash
wp microchaos loadtest --endpoint=home --duration=5 --burst=50 \
  --warm-cache --resource-logging --cache-headers \
  --save-baseline=initial
```

**What you're measuring:** Warm cache response times, baseline memory usage, cache hit rates.

#### Phase 2: Sustained Load Testing

Simulate realistic traffic over time to find degradation:

```bash
wp microchaos loadtest --endpoint=home --duration=10 --burst=100 \
  --resource-logging --resource-trends --cache-headers
```

**What you're looking for:**
- Does RPS stay stable or decline?
- Does memory climb (leak) or stay flat?
- What's the cache HIT/MISS ratio under load?

#### Phase 3: Multi-Endpoint Rotation

Test realistic user flows across the site:

```bash
wp microchaos loadtest --endpoints=home,shop,cart,checkout \
  --duration=10 --burst=100 --rotation-mode=serial \
  --resource-logging --resource-trends --cache-headers
```

**Why this matters:** Single-endpoint tests miss bottlenecks. Real users hit multiple paths, triggering different code, queries, and cache patterns.

### Interpreting Results

#### Execution Metrics (Capacity)

```
Throughput: 4.74 RPS
Capacity: 17,064/hour | 409,536/day | 12.3M/month
```

**Decision logic:**
- Compare achieved RPS to traffic requirements
- If target is 10 RPS and you hit 4.7 RPS → need optimization or scaling
- Capacity projections show monthly headroom

#### Resource Trends (Stability)

```
Memory Trend: ↑12.3% over test duration
Pattern: Moderate growth
```

| Trend | Meaning | Action |
|-------|---------|--------|
| Flat (±5%) | Stable, safe for sustained load | ✅ Good to go |
| Climbing (5-15%) | Possible leak or buffer growth | ⚠️ Investigate plugins |
| Steep climb (>15%) | Memory leak, will eventually crash | 🚨 Fix before scaling |

#### Cache Headers (Efficiency)

```
Edge Cache (x-ac): HIT 20% | STALE 50% | UPDATING 30%
Batcache (x-nananana): HIT 40% | MISS 60%
```

| Pattern | Meaning | Action |
|---------|---------|--------|
| High HIT % | Cache working well | ✅ Efficient |
| High STALE % | Cache invalidating too often | Check invalidation logic |
| High MISS % | Database getting hammered | Review caching strategy |

### Common Audit Scenarios

#### "Site is slow during peak hours"

```bash
# Warm cache baseline
wp microchaos loadtest --endpoint=home --duration=5 --burst=50 \
  --warm-cache --resource-logging --cache-headers --save-baseline=warm

# Cold cache comparison (worst case)
wp microchaos loadtest --endpoint=home --duration=5 --burst=50 \
  --flush-between --resource-logging --cache-headers --compare-baseline=warm
```

**Look for:** Big gap between warm/cold = cache is critical. Small gap = cache isn't helping much.

#### "How many users can we handle?"

```bash
# Start conservative
wp microchaos loadtest --endpoint=home --duration=5 --burst=50 \
  --resource-logging --auto-thresholds

# Scale up incrementally
wp microchaos loadtest --endpoint=home --duration=10 --burst=100 \
  --resource-logging --use-thresholds=default

wp microchaos loadtest --endpoint=home --duration=10 --burst=200 \
  --resource-logging --use-thresholds=default
```

**Find the breaking point:** Where does RPS plateau? Where does memory peak? That's your capacity ceiling.

#### "WooCommerce checkout is slow"

```bash
# Test checkout as authenticated user
wp microchaos loadtest --endpoint=checkout --duration=10 --burst=50 \
  --auth=customer@example.com \
  --resource-logging --resource-trends --cache-headers --save-baseline=checkout

# Compare to homepage (same auth)
wp microchaos loadtest --endpoint=home --duration=10 --burst=50 \
  --auth=customer@example.com \
  --resource-logging --compare-baseline=checkout
```

**Look for:** Checkout 5-10x slower than home = WooCommerce overhead, plugin hooks, or external API calls.

---

## 🤖 Headless WordPress Testing Guide

*"A headless site without load testing is like Ichabod Crane without a lantern—stumbling through Sleepy Hollow hoping the horseman doesn't catch you."*

MicroChaos v4.0.0 adds first-class support for **headless WordPress** load testing via GraphQL. This guide walks you through testing decoupled architectures where a frontend (Next.js, Nuxt, Gatsby, etc.) consumes WordPress data via WPGraphQL.

### Prerequisites

Before running headless load tests, ensure:

| Requirement | How to Verify |
|-------------|---------------|
| **WPGraphQL installed** | `wp plugin list \| grep wp-graphql` or visit `/graphql` in browser |
| **MicroChaos deployed** | `wp microchaos --help` returns command info |
| **Test content exists** | Posts, pages, products—whatever your frontend queries |
| **SSH access** | You're running MicroChaos from inside WordPress via WP-CLI |

**For Pressable headless sites:**
- Custom User-Agent is **required** (format: `your-app-name/1.0`)
- Use the standard `/graphql` endpoint (rate limit exception exists)

### The 3-Phase Headless Workflow

#### Phase 1: Verify GraphQL is Working

Before load testing, confirm the endpoint responds correctly:

```bash
# Single request sanity check
wp microchaos loadtest --graphql \
  --body='{"query":"{ __typename }"}' \
  --count=1
```

**Expected:** HTTP 200, no GraphQL errors. If you see `[GQL errors: 1]`, your query has issues.

#### Phase 2: Baseline Your Queries

Test your actual frontend queries under controlled conditions:

```bash
# Test your real query with resource monitoring
wp microchaos loadtest --graphql \
  --body='{"query":"{ posts(first: 10) { nodes { id title slug content featuredImage { node { sourceUrl } } } } }"}' \
  --user-agent=my-frontend/1.0 \
  --count=50 --cache-headers --resource-logging \
  --save-baseline=posts-query
```

**What you're measuring:**
- Response times for your actual query complexity
- Memory usage on WordPress backend
- Cache behavior (POST = BYPASS, GET = cacheable)

#### Phase 3: Sustained Load Testing

Simulate real frontend traffic patterns:

```bash
# 10-minute sustained load test
wp microchaos loadtest --graphql \
  --body='{"query":"{ posts(first: 10) { nodes { id title slug } } }"}' \
  --user-agent=my-frontend/1.0 \
  --duration=10 --burst=20 \
  --resource-logging --resource-trends --cache-headers
```

**What you're looking for:**
- Does RPS stay stable or decline over time?
- Does memory climb (potential leak) or stay flat?
- Are GraphQL errors appearing under load?

### JWT Authentication for Protected Queries

Many headless setups use JWT tokens for authenticated GraphQL queries. Here's the workflow:

#### Step 1: Get a JWT Token

Using WPGraphQL JWT Authentication plugin:

```bash
# Get token via mutation (run this manually first)
curl -X POST https://your-site.com/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"mutation { login(input: {username: \"admin\", password: \"password\"}) { authToken refreshToken } }"}'
```

Response:
```json
{"data":{"login":{"authToken":"eyJ0eXAi...","refreshToken":"..."}}}
```

#### Step 2: Use Token in MicroChaos

```bash
wp microchaos loadtest --graphql \
  --body='{"query":"{ viewer { name email } }"}' \
  --header="Authorization=Bearer eyJ0eXAi..." \
  --user-agent=my-frontend/1.0 \
  --count=50
```

**Note:** JWT tokens expire. For long-duration tests, use a fresh token or test unauthenticated queries.

### Real-World Query Examples

#### Simple Post List (Blog Frontend)

```bash
wp microchaos loadtest --graphql \
  --body='{"query":"{ posts(first: 10) { nodes { id title slug excerpt date } } }"}' \
  --user-agent=blog-frontend/1.0 \
  --count=100 --cache-headers
```

#### WooCommerce Products (E-commerce Frontend)

```bash
wp microchaos loadtest --graphql \
  --body='{"query":"{ products(first: 20) { nodes { id name slug ... on SimpleProduct { price regularPrice } image { sourceUrl } } } }"}' \
  --user-agent=shop-frontend/1.0 \
  --count=100 --resource-logging
```

#### Complex Nested Query (Heavy Load)

```bash
wp microchaos loadtest --graphql \
  --body='{"query":"{ posts(first: 5) { nodes { id title author { node { name posts(first: 3) { nodes { title } } } } categories { nodes { name posts(first: 3) { nodes { title } } } } } } }"}' \
  --user-agent=complex-frontend/1.0 \
  --count=50 --resource-logging --resource-trends
```

**Warning:** Deeply nested queries can be expensive. Watch memory usage carefully.

### Interpreting Headless Results

#### What "Good" Looks Like

| Metric | Good | Warning | Critical |
|--------|------|---------|----------|
| **Response Time** | <200ms | 200-500ms | >500ms |
| **GraphQL Errors** | 0 | 1-5% | >5% |
| **Memory Growth** | Flat (±5%) | 5-15% climb | >15% climb |
| **Cache HITs (GET)** | >80% | 50-80% | <50% |

#### Red Flags to Watch For

| Symptom | Likely Cause | Action |
|---------|--------------|--------|
| `[GQL errors: N]` appearing | Query syntax issue or schema mismatch | Fix query before load testing |
| Response times climbing over duration | Memory pressure or connection pooling | Check `--resource-trends` output |
| 100% BYPASS on all requests | Using POST (expected) or cache misconfigured | Use GET for cacheable queries |
| Memory growing >20% over test | Potential memory leak in resolver | Profile WPGraphQL resolvers |

### POST vs GET: Cache Strategy

**POST requests** (default with `--graphql`):
- Always bypass Pressable Edge Cache
- Every request hits PHP/WordPress
- Use for: mutations, authenticated queries, cache-busting tests

**GET requests** (with `--method=GET`):
- Can be cached by Edge Cache (with WPGraphQL Smart Cache)
- Cache HITs are ~30ms vs ~450ms uncached
- Use for: public queries, production traffic simulation

```bash
# Test cache effectiveness with GET
wp microchaos loadtest --graphql --method=GET \
  --body='{"query":"{ posts { nodes { title } } }"}' \
  --count=50 --cache-headers
```

**Look for:** First few requests MISS, then HITs. If all MISS, Smart Cache may not be configured.

### Pressable-Specific Considerations

1. **User-Agent Required**: Pressable headless apps need a custom UA for rate limit exceptions
2. **Standard `/graphql` Path**: Non-standard paths may be rate limited
3. **Edge Cache for GET**: Works with WPGraphQL Smart Cache plugin
4. **Loopback Limits**: MicroChaos uses serial requests (~10 concurrent max)

```bash
# Full Pressable headless test with all the trimmings
wp microchaos loadtest --graphql \
  --body='{"query":"{ posts(first: 10) { nodes { title slug } } }"}' \
  --user-agent=my-nextjs-app/1.0 \
  --duration=5 --burst=50 \
  --resource-logging --resource-trends --cache-headers \
  --save-baseline=headless-baseline
```

---

## 🏗️ Architecture

MicroChaos features a modular component-based architecture with clean separation of concerns:

```text
microchaos/
├── bootstrap.php                    # Component loader (v3.0.0)
└── core/
    ├── interfaces/
    │   ├── logger.php               # Logger interface (testability)
    │   └── baseline-storage.php     # Storage abstraction
    ├── logging/
    │   ├── wp-cli-logger.php        # Production logger
    │   └── null-logger.php          # Test logger
    ├── storage/
    │   └── transient-baseline-storage.php
    ├── orchestrators/
    │   └── loadtest-orchestrator.php  # Test execution (656 lines)
    ├── commands.php                 # Thin WP-CLI wrapper (63 lines)
    ├── log.php                      # Static logger facade
    ├── constants.php                # Centralized constants
    ├── authentication-manager.php   # Auth utilities (8 static methods)
    ├── request-generator.php        # HTTP request management
    ├── cache-analyzer.php           # Cache header analysis
    ├── resource-monitor.php         # System resource tracking
    ├── reporting-engine.php         # Results and reporting
    ├── integration-logger.php       # External monitoring
    └── thresholds.php               # Thresholds and visualization
```

**Key Design Decisions:**
- **Thin CLI wrapper**: `commands.php` is just 63 lines - all logic lives in `LoadTestOrchestrator`
- **Interface-based logging**: Swap `WP_CLI_Logger` for `Null_Logger` in tests
- **100% type hints**: All public methods have PHP 8.2+ type declarations
- **61 unit tests**: Pure PHP components tested without WordPress runtime

## 🔄 Build Process

MicroChaos uses a build system that compiles the modular version into a single-file distribution:

```text
build.js                   # Node.js build script
dist/                      # Generated distribution files
└── microchaos-cli.php     # Compiled single-file version (~123 KB)
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
- `--user-agent=<string>` Custom User-Agent header (required for Pressable headless apps)
- `--graphql` Shorthand for GraphQL testing (sets method=POST, endpoint=/graphql)

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

### GraphQL / Headless WordPress

Quick examples for headless WordPress testing. **See the full [Headless WordPress Testing Guide](#-headless-wordpress-testing-guide) for complete workflows, JWT authentication, and best practices.**

```bash
# Basic GraphQL test
wp microchaos loadtest --graphql --body='{"query":"{ posts { nodes { title } } }"}' --count=100

# With User-Agent (required for Pressable headless)
wp microchaos loadtest --graphql \
  --body='{"query":"{ posts { nodes { title } } }"}' \
  --user-agent=my-frontend/1.0 \
  --count=50 --cache-headers

# Cacheable GET query
wp microchaos loadtest --graphql --method=GET --count=50 --cache-headers
```

GraphQL errors are automatically detected—even in HTTP 200 responses:
```
-> 200 in 0.45s [GQL errors: 1]
Success: 0 | HTTP Errors: 0 | GraphQL Errors: 3 | Error Rate: 100%
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

## 🧠 Design Philosophy

"Improvisation > Perfection. Paradox is fuel."

Test sideways. Wear lab goggles. Hit the endpoints like they owe you money and answers.

- ⚡ Internal-only, real-world load generation
- 🧬 Built for performance discovery and observability
- 🤝 Friendly for TAMs, support engineers, and even devs ;)

---

## 🛠 Roadmap

### Phase 4: GraphQL & Headless WordPress ✅ Complete

MicroChaos now supports **GraphQL endpoint testing** for headless WordPress:

- ✅ **`--graphql` shorthand** - Sets method=POST and endpoint=/graphql automatically
- ✅ **`--user-agent` flag** - Custom User-Agent required for Pressable headless apps
- ✅ **Override support** - Combine with `--method=GET` for cacheable queries
- ✅ **Documented Pressable behavior** - POST=BYPASS, GET=cacheable
- ✅ **GraphQL error detection** - Automatically detect and report `errors` in GraphQL responses

### Future Ideas

- **Advanced visualizations** - Interactive charts for test results
- **Custom test templates** - Pre-configured plans for e-commerce, membership sites, etc.
- **Query complexity metrics** - Track resolver performance

---

## 🖖 Author

Built by Phill in a caffeine-fueled, chaos-aligned, performance-obsessed dev haze.

⸻

"If you stare at a site load test long enough, the site load test starts to stare back."
— Ancient Pressable Proverb

---

## 🧾 License

This project is licensed under the GPLv3 License.
