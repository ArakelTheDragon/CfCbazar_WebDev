# CfCbazar Web Development Repository 🌐

Welcome to the official repository for the open-source web utilities powering our tool ecosystem. This sub-directory hosts our custom-engineered, multi-threaded client-side network diagnostic suites.

## ⚡ CC Free Speed Test

The centerpiece of this directory is a custom client-side diagnostic suite designed to accurately calculate network throughput directly inside a browser context, completely free of bulky runtime frameworks or intrusive ad networks.

### 🌐 Deployment Architecture
The production engine is configured to run out-of-the-box on a standard Apache/Nginx web server hierarchy. 
* **Production Endpoint Path:** `domain/diy/speed/index.php`
* **Repository Source Location:** `/diy/speed/`

### 🛠️ Core Architecture & Testing Methodology
Unlike generic single-stream speed tests that often bottleneck on browser performance or single-thread network sockets, this engine measures maximum line saturation by progressively scaling thread loads.

* **Progressive Parallelism:** The script loops through seven structural phases, incrementing sequential load groups from **2 to 8 concurrent parallel downloads**.
* **Payload Scaling Strategy:** The engine provisions network strain targets dynamically by mapping file sizes ranging from a light **1MB snippet up to a 1.5GB deep-buffer file**.
* **High-Capacity CDN Delivery:** To prevent local target server congestion from ruining your diagnostics, payloads are dynamically fetched directly via the distributed content delivery infrastructure of **Wikimedia** and **Cloudflare**.
* **Smart Data Averaging Engine:** To eliminate random spikes, network hiccups, or scheduling latency from corrupting the score, the analytics engine automatically drops the single lowest performing throughput calculation before mathematically averaging the remaining active blocks.

---

## 📂 Directory Structure

```text
diy/speed/
├── index.php         # Core analytic framework containing the multi-threaded testing client, tracking backend, and UI interface.
└── [config.php]      # Dependent file (called via relative path pointing up to site root configurations for database $conn mapping)
