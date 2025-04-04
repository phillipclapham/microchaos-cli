# ⚡️ MicroChaos CLI Load Tester

Welcome to **MicroChaos**—a precision-built WP-CLI load testing tool forged in the fires of real-world WordPress hosting constraints.

When external load testing tools are blocked, when rate limits make your bots cry, and when SSH feels like a locked door... **MicroChaos lets you stress test from the inside.**

Built for staging environments like **Pressable**, MicroChaos simulates traffic at scale—warm or cold cache, anonymous or authenticated, fast bursts or slow burns.

---

## 🎯 Purpose

- 🔐 **Run realistic load tests** *inside* WordPress with zero external traffic
- 🧠 **Simulate logged-in users**, WooCommerce flows, REST endpoints, and custom paths
- 🧰 **Profile caching**, resource usage, and performance regressions from the CLI
- 🦇 Built for staging, QA, support engineers, TAMs, and performance-hungry devs

---

## 📦 Installation

1. Drop `microchaos-cli.php` into `wp-content/mu-plugins/`.
2. Make sure WP-CLI is available in your environment.
3. You’re ready to chaos.

---

## 🛠 Usage

```bash
wp microchaos loadtest --endpoint=home --count=100
```

Or go wild:

```bash
wp microchaos loadtest --endpoint=checkout --count=50 --auth=admin@example.com --concurrency-mode=async --cache-headers --resource-logging
```

## 🔧 CLI Options

- --endpoint=<slug> home, shop, cart, checkout, or custom:/my-path
- --count=<n> Total requests to send (default: 100)
- --burst=<n> Requests per burst (default: 10)
- --delay=<seconds> Delay between bursts (default: 2)
- `–method=<GET POST
- --body=<data> POST/PUT body (string, JSON, or file:path.json)
- --auth=<email> Run as a specific logged-in user
- --multi-auth=<email1,email2> Rotate across multiple users
- --warm-cache Prime the cache before testing
- --flush-between Flush cache before each burst
- --log-to=<relative path> Log results to file under wp-content/
- --concurrency-mode=async Use curl_multi_exec() for parallel bursts
- --rampup Gradually increase burst size to simulate organic load
- --resource-logging Print memory and CPU usage during test
- --cache-headers Parse cache headers (x-ac, x-nananana, etc.) and summarize hit/miss behavior

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

→ 200 in 0.0296s [EDGE_UPDATING] x-ac:3.dca_atomic_dca UPDATING
→ 200 in 0.0303s [EDGE_STALE] x-ac:3.dca _atomic_dca STALE

Each request is timestamped, status-coded, cache-labeled, and readable at a glance.

---

### 📈 Load Summary

📊 Load Test Summary:
   Total Requests: 10
   Success: 10 | Errors: 0
   Avg Time: 0.0331s | Median: 0.0296s
   Fastest: 0.0278s | Slowest: 0.0567s

---

### 💻 Resource Usage (with --resource-logging)

📊 Resource Utilization Summary:
   Avg Memory Usage: 118.34 MB, Median: 118.34 MB
   Avg Peak Memory: 118.76 MB, Median: 118.76 MB
   Avg CPU Time (User): 1.01s, Median: 1.01s
   Avg CPU Time (System): 0.33s, Median: 0.33s

---

### 📦 Cache Header Summary (with --cache-headers)

📦 Cache Header Summary:
   🔄 Overall Cache Hit Ratio: 100%
   🦇 Batcache Hit Ratio: 0%
   🌐 Edge Cache Hit Ratio: 100%

   📊 Cache Status Distribution:
     EDGE_STALE: 3 (30%)
     EDGE_UPDATING: 7 (70%)

   📋 Raw Cache Headers Found:
     x-ac:
       3.dca _atomic_dca STALE: 3
       3.dca _atomic_dca UPDATING: 7

Parsed and summarized directly from HTTP response headers—no deep instrumentation required.

---

## 🧠 Design Philosophy

“Improvisation > Perfection. Paradox is fuel.”

Test sideways. Wear lab goggles. Hit the endpoints like they owe you money and answers.

- ⚡ Internal-only, real-world load generation
- 🧬 Built for performance discovery and observability
- 🤝 Friendly for TAMS, support engineers, and even devs ;)

---

🛠 Future Ideas (Possibility Fractals):

- Test plans via JSON config (wp microchaos plan)`
- WP Dashboard UI integration/convert to plugin
- Ability to send cache breaking cookies or headers
- Add --static-delay flag to turn off randomization of burst timing
- Add ability to rotate endpoints during tests

---

🖖 Author

Built by Phill in a caffeine-fueled, chaos-aligned, performance-obsessed dev haze.

⸻

“If you stare at a site load test long enough, the site load test starts to stare back.”
— Ancient Pressable Proverb

---

### 🧾 License

This project is licensed under the GPLv3 License.
