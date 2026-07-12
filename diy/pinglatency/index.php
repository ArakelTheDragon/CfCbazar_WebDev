<?php
// CfCbazar DIY Ping & Latency Monitor Tool
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$title = 'CfCbazar DIY Ping & Latency Monitor Tool';

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;

    if (function_exists('trackVisit')) {
        trackVisit("index-main.php");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">

  <title>Ping & Latency Monitor — CfCbazar DIY Tool</title>

  <!-- Global CfCbazar visual style -->
  <link rel="stylesheet" href="/style.css">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- SEO Meta Tags -->
  <meta name="description" content="Real-time Ping & Latency Monitor by CfCbazar. Track network latency, router response, and connection stability with live charts and historical data.">
  <meta name="keywords" content="ping monitor, latency monitor, network tools, internet speed, router latency, CfCbazar DIY, WorkToken tools, real-time ping test">
  <meta name="author" content="CfCbazar">

  <!-- Open Graph -->
  <meta property="og:title" content="Ping & Latency Monitor — CfCbazar DIY Tool">
  <meta property="og:description" content="Monitor ping, latency, and router response in real time with live charts and historical tracking.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://cfcbazar.42web.io/diy/pinglatency/">
  <meta property="og:image" content="https://cfcbazar.42web.io/assets/cfcbazar-preview.png">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Ping & Latency Monitor — CfCbazar DIY">
  <meta name="twitter:description" content="Real-time network latency tracking with live charts and router detection.">
  <meta name="twitter:image" content="https://cfcbazar.42web.io/assets/cfcbazar-preview.png">

  <!-- Canonical URL -->
  <link rel="canonical" href="https://cfcbazar.42web.io/diy/pinglatency/">

  <!-- Robots -->
  <meta name="robots" content="index, follow">

  <!-- Structured Data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "Ping & Latency Monitor",
    "url": "https://cfcbazar.42web.io/diy/pinglatency/",
    "applicationCategory": "Utility",
    "operatingSystem": "All",
    "creator": {
      "@type": "Organization",
      "name": "CfCbazar",
      "url": "https://cfcbazar.42web.io"
    },
    "description": "Real-time ping and latency monitoring tool with live charts, router detection, and historical tracking.",
    "keywords": "ping monitor, latency monitor, network tools, CfCbazar DIY, WorkToken"
  }
  </script>

  <!-- Local styling for layout only -->
  <style>
    .tool-wrapper {
      max-width: 900px;
      margin: 40px auto;
      padding: 20px;
    }

    .tool-card {
      background: rgba(15, 23, 42, 0.9);
      border: 1px solid rgba(148, 163, 184, 0.35);
      border-radius: 18px;
      padding: 20px;
      box-shadow: 0 18px 45px rgba(0,0,0,0.45);
    }

    .tool-card h2 {
      margin-bottom: 12px;
      font-size: 22px;
      font-weight: 600;
    }

    .tool-controls {
      margin-bottom: 20px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    select, button {
      padding: 8px 12px;
      border-radius: 10px;
      border: 1px solid rgba(148,163,184,0.4);
      background: rgba(15,23,42,0.7);
      color: #f9fafb;
      font-size: 14px;
    }

    select:focus, button:hover {
      border-color: var(--accent);
      color: var(--accent);
      cursor: pointer;
    }

    #chart-container {
      margin-top: 20px;
    }

    canvas {
      background: rgba(0,0,0,0.2);
      border-radius: 12px;
      border: 1px solid rgba(148,163,184,0.25);
    }

    /* Latency Stats */
    .latency-stats {
      margin-top: 20px;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 12px;
    }

    .stat-box {
      background: rgba(15, 23, 42, 0.85);
      border: 1px solid rgba(148, 163, 184, 0.35);
      padding: 12px 14px;
      border-radius: 14px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.35);
    }

    .stat-label {
      font-size: 12px;
      color: var(--muted);
    }

    .stat-value {
      display: block;
      margin-top: 4px;
      font-size: 18px;
      font-weight: 600;
      color: var(--accent);
    }
  </style>
</head>

<body>

<div class="tool-wrapper">
  <div class="tool-card">

    <h2 id="title">Ping & Latency Monitor — Real-Time Network Diagnostic Tool</h2>

    <div class="tool-controls">
      <label for="timeRange">Select Time Range:</label>
      <select id="timeRange">
        <option value="5m">Last 5 minutes</option>
        <option value="1h">Last 1 hour</option>
        <option value="4h">Last 4 hours</option>
        <option value="1d">Last 1 day</option>
        <option value="1w">Last 1 week</option>
        <option value="1m">Last 1 month</option>
      </select>

      <button id="clearStats">Clear Stats</button>
    </div>

    <div id="chart-container">
      <canvas id="latencyChart" height="350"></canvas>
    </div>

    <!-- Latency Stats -->
    <div id="latency-stats" class="latency-stats">
      <div class="stat-box">
        <span class="stat-label">Average Latency:</span>
        <span id="avgLatency" class="stat-value">-- ms</span>
      </div>

      <div class="stat-box">
        <span class="stat-label">Minimum:</span>
        <span id="minLatency" class="stat-value">-- ms</span>
      </div>

      <div class="stat-box">
        <span class="stat-label">Maximum:</span>
        <span id="maxLatency" class="stat-value">-- ms</span>
      </div>

      <div class="stat-box">
        <span class="stat-label">Packet Loss:</span>
        <span id="packetLoss" class="stat-value">--</span>
      </div>
    </div>

  </div>
</div>

<script>
/* -----------------------------
   Ping & Latency Monitor Logic
--------------------------------*/

const targets = ['8.8.8.8', '1.1.1.1', '75.75.75.75', '75.75.76.76', '208.67.222.222', '127.0.0.1', '4.2.2.1', '4.2.2.2'];
const localIPs = ['192.168.0.1', '192.168.1.1'];
const historyKey = 'pingHistory';
const interval = 2000;

function ping(ip) {
  return new Promise((resolve) => {
    const img = new Image();
    const start = performance.now();
    const url = `http://${ip}/?cache_bust=${Math.random()}`;
    let done = false;

    const cleanup = () => {
      if (done) return;
      done = true;
      const duration = Math.round(performance.now() - start);
      resolve({ ip, latency: duration });
    };

    img.onload = cleanup;
    img.onerror = cleanup;
    img.src = url;

    setTimeout(() => {
      if (!done) resolve({ ip, latency: null });
    }, 1500);
  });
}

function savePingResult(results) {
  const history = JSON.parse(localStorage.getItem(historyKey) || '[]');
  history.push({ timestamp: Date.now(), results });

  const cutoff = Date.now() - 31 * 24 * 60 * 60 * 1000;
  const filtered = history.filter(entry => entry.timestamp >= cutoff);

  localStorage.setItem(historyKey, JSON.stringify(filtered));
}

function getFilteredData(timeRange) {
  const now = Date.now();
  const durations = {
    '5m': 5 * 60 * 1000,
    '1h': 60 * 60 * 1000,
    '4h': 4 * 60 * 60 * 1000,
    '1d': 24 * 60 * 60 * 1000,
    '1w': 7 * 24 * 60 * 60 * 1000,
    '1m': 31 * 24 * 60 * 60 * 1000
  };

  const cutoff = now - durations[timeRange];
  const history = JSON.parse(localStorage.getItem(historyKey) || '[]');
  return history.filter(entry => entry.timestamp >= cutoff);
}

function getRouterIP(filteredData) {
  const avgLatencies = {};
  localIPs.forEach(ip => avgLatencies[ip] = []);

  filteredData.forEach(entry => {
    entry.results.forEach(({ ip, latency }) => {
      if (localIPs.includes(ip) && latency !== null) {
        avgLatencies[ip].push(latency);
      }
    });
  });

  let router = null;
  let lowest = Infinity;

  for (const ip in avgLatencies) {
    const values = avgLatencies[ip];
    const avg = values.length ? values.reduce((a, b) => a + b) / values.length : Infinity;
    if (avg < lowest) {
      lowest = avg;
      router = ip;
    }
  }

  return router || null;
}

function updateStats(filteredData) {
  const allLatencies = [];

  filteredData.forEach(entry => {
    entry.results.forEach(r => {
      if (r.latency !== null) allLatencies.push(r.latency);
    });
  });

  const packetLoss = filteredData.reduce((loss, entry) => {
    return loss + entry.results.filter(r => r.latency === null).length;
  }, 0);

  if (allLatencies.length === 0) {
    document.getElementById('avgLatency').textContent = "-- ms";
    document.getElementById('minLatency').textContent = "-- ms";
    document.getElementById('maxLatency').textContent = "-- ms";
    document.getElementById('packetLoss').textContent = packetLoss;
    return;
  }

  const avg = Math.round(allLatencies.reduce((a,b) => a+b) / allLatencies.length);
  const min = Math.min(...allLatencies);
  const max = Math.max(...allLatencies);

  document.getElementById('avgLatency').textContent = avg + " ms";
  document.getElementById('minLatency').textContent = min + " ms";
  document.getElementById('maxLatency').textContent = max + " ms";
  document.getElementById('packetLoss').textContent = packetLoss;
}

function updateChart(filteredData) {
  const datasets = targets.map(ip => ({
    label: ip,
    data: filteredData.map(entry => {
      const result = entry.results.find(r => r.ip === ip);
      return result ? result.latency : null;
    }),
    borderWidth: 2,
    fill: false,
    tension: 0.2
  }));

  const labels = filteredData.map(entry =>
    new Date(entry.timestamp).toLocaleTimeString()
  );

  chart.data.labels = labels;
  chart.data.datasets = datasets;
  chart.update();

  updateStats(filteredData);

  const router = getRouterIP(filteredData);
  const routerText = router
    ? `Router IP: ${router}`
    : 'Router IP: Not Detected';

  document.getElementById('title').textContent =
    `Ping & Latency Monitor — ${routerText}`;
}

async function runPingLoop() {
  const results = await Promise.all(targets.map(ping));
  savePingResult(results);

  const timeRange = document.getElementById('timeRange').value;
  const data = getFilteredData(timeRange);
  updateChart(data);

  setTimeout(runPingLoop, interval);
}

// Setup chart
const ctx = document.getElementById('latencyChart').getContext('2d');
const chart = new Chart(ctx, {
  type: 'line',
  data: { labels: [], datasets: [] },
  options: {
    responsive: true,
    animation: false,
    interaction: { mode: 'nearest', intersect: false },
    plugins: {
      legend: { position: 'top' },
      title: { display: true, text: 'Latency to Targets (ms)' }
    },
    scales: {
      y: {
        beginAtZero: true,
        title: { display: true, text: 'Latency (ms)' }
      },
      x: {
        ticks: { maxRotation: 90, minRotation: 45 }
      }
    }
  }
});

// Events
document.getElementById('timeRange').addEventListener('change', () => {
  const data = getFilteredData(document.getElementById('timeRange').value);
  updateChart(data);
});

document.getElementById('clearStats').addEventListener('click', () => {
  localStorage.removeItem(historyKey);
  chart.data.labels = [];
  chart.data.datasets = [];
  chart.update();
  document.getElementById('title').textContent =
    'Ping & Latency Monitor — Router IP: Not Detected (cleared)';
});

// Start
runPingLoop();
</script>

</body>
</html>

<?php
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/pdf-editor",
    "Ping & Latency Tool GitHub Source Code"
);
?>

<?php include_footer(); ?>
</body>
</html>
