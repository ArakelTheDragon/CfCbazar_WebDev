<?php
// /diy/pinglatency/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';
// settings_local.php sets:
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN   = false;

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/pinglatency/index.php";
$parent_url = "/diy/pinglatency";
$title      = "Ping & Latency Monitor — CfCbazar DIY Tool";
$template   = "Generic";
$meta_title = "Ping & Latency Monitor — CfCbazar DIY Tool";
$meta_desc  = "Real-time Ping & Latency Monitor by CfCbazar. Track network latency, router response, and connection stability with live charts and historical data.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<main class="content">
    <div class="container">

        <div class="tool-card">

            <h2 id="title">
                Ping &amp; Latency Monitor — Real-Time Network Diagnostic Tool
            </h2>

            <div class="tool-controls">

                <label for="timeRange">
                    Select Time Range:
                </label>

                <select id="timeRange">
                    <option value="5m">Last 5 minutes</option>
                    <option value="1h">Last 1 hour</option>
                    <option value="4h">Last 4 hours</option>
                    <option value="1d">Last 1 day</option>
                    <option value="1w">Last 1 week</option>
                    <option value="1m">Last 1 month</option>
                </select>

                <button id="clearStats">
                    Clear Stats
                </button>

            </div>

            <div id="chart-container" style="background:#fafafa;border:1px solid #ddd;border-radius:12px;padding:15px;">
                <canvas id="latencyChart" height="350"></canvas>
            </div>

            <div class="latency-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-top:25px;">

                <div class="stat-box" style="background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:18px;text-align:center;">
                    <span class="stat-label" style="display:block;color:#666;margin-bottom:8px;font-size:.9rem;">Average Latency</span>
                    <span id="avgLatency" class="stat-value" style="display:block;color:#28a745;font-size:1.8rem;font-weight:bold;">-- ms</span>
                </div>

                <div class="stat-box" style="background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:18px;text-align:center;">
                    <span class="stat-label" style="display:block;color:#666;margin-bottom:8px;font-size:.9rem;">Minimum</span>
                    <span id="minLatency" class="stat-value" style="display:block;color:#28a745;font-size:1.8rem;font-weight:bold;">-- ms</span>
                </div>

                <div class="stat-box" style="background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:18px;text-align:center;">
                    <span class="stat-label" style="display:block;color:#666;margin-bottom:8px;font-size:.9rem;">Maximum</span>
                    <span id="maxLatency" class="stat-value" style="display:block;color:#28a745;font-size:1.8rem;font-weight:bold;">-- ms</span>
                </div>

                <div class="stat-box" style="background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:18px;text-align:center;">
                    <span class="stat-label" style="display:block;color:#666;margin-bottom:8px;font-size:.9rem;">Packet Loss</span>
                    <span id="packetLoss" class="stat-value" style="display:block;color:#28a745;font-size:1.8rem;font-weight:bold;">--</span>
                </div>

            </div>

        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const targets = [
    '8.8.8.8',
    '1.1.1.1',
    '75.75.75.75',
    '75.75.76.76',
    '208.67.222.222',
    '127.0.0.1',
    '4.2.2.1',
    '4.2.2.2'
];

const localIPs = [
    '192.168.0.1',
    '192.168.1.1'
];

const historyKey = 'pingHistory';
const interval   = 2000;

const colors = [
    '#28a745',
    '#007bff',
    '#ff9800',
    '#dc3545',
    '#6f42c1',
    '#17a2b8',
    '#fd7e14',
    '#20c997'
];

function ping(ip) {
    return new Promise(resolve => {

        const img   = new Image();
        const start = performance.now();
        const url   = `http://${ip}/?cache_bust=${Math.random()}`;
        let   done  = false;

        const cleanup = () => {
            if (done) return;
            done = true;
            resolve({ ip, latency: Math.round(performance.now() - start) });
        };

        img.onload  = cleanup;
        img.onerror = cleanup;

        img.src = url;

        setTimeout(() => {
            if (!done) {
                done = true;
                resolve({ ip, latency: null });
            }
        }, 1500);
    });
}

function savePingResult(results) {
    const history = JSON.parse(localStorage.getItem(historyKey) || '[]');

    history.push({ timestamp: Date.now(), results });

    const cutoff   = Date.now() - 31 * 24 * 60 * 60 * 1000;
    const filtered = history.filter(entry => entry.timestamp >= cutoff);

    localStorage.setItem(historyKey, JSON.stringify(filtered));
}

function getFilteredData(timeRange) {
    const durations = {
        '5m': 5 * 60 * 1000,
        '1h': 60 * 60 * 1000,
        '4h': 4 * 60 * 60 * 1000,
        '1d': 24 * 60 * 60 * 1000,
        '1w': 7 * 24 * 60 * 60 * 1000,
        '1m': 31 * 24 * 60 * 60 * 1000
    };

    const cutoff = Date.now() - durations[timeRange];

    return JSON.parse(localStorage.getItem(historyKey) || '[]')
        .filter(entry => entry.timestamp >= cutoff);
}

function getRouterIP(filteredData) {
    const averages = {};
    localIPs.forEach(ip => { averages[ip] = []; });

    filteredData.forEach(entry => {
        entry.results.forEach(result => {
            if (localIPs.includes(result.ip) && result.latency !== null) {
                averages[result.ip].push(result.latency);
            }
        });
    });

    let router = null;
    let lowest = Infinity;

    for (const ip in averages) {
        if (!averages[ip].length) continue;

        const avg = averages[ip].reduce((a, b) => a + b, 0) / averages[ip].length;

        if (avg < lowest) {
            lowest = avg;
            router = ip;
        }
    }

    return router;
}

function updateStats(filteredData) {
    const allLatencies = [];

    filteredData.forEach(entry => {
        entry.results.forEach(result => {
            if (result.latency !== null) {
                allLatencies.push(result.latency);
            }
        });
    });

    const totalTests = filteredData.reduce((total, entry) => total + entry.results.length, 0);
    const lostTests  = filteredData.reduce(
        (loss, entry) => loss + entry.results.filter(r => r.latency === null).length,
        0
    );

    const packetLoss = totalTests > 0 ? Math.round((lostTests / totalTests) * 100) : 0;

    if (!allLatencies.length) {
        avgLatency.textContent = '-- ms';
        minLatency.textContent = '-- ms';
        maxLatency.textContent = '-- ms';
        document.getElementById('packetLoss').textContent = packetLoss + '%';
        return;
    }

    avgLatency.textContent = Math.round(allLatencies.reduce((a, b) => a + b, 0) / allLatencies.length) + ' ms';
    minLatency.textContent = Math.min(...allLatencies) + ' ms';
    maxLatency.textContent = Math.max(...allLatencies) + ' ms';
    document.getElementById('packetLoss').textContent = packetLoss + '%';
}

function updateChart(filteredData) {
    const datasets = targets.map((ip, index) => ({
        label: ip,
        data: filteredData.map(entry => {
            const result = entry.results.find(r => r.ip === ip);
            return result ? result.latency : null;
        }),
        borderColor: colors[index % colors.length],
        backgroundColor: colors[index % colors.length],
        borderWidth: 2,
        pointRadius: 2,
        pointHoverRadius: 5,
        tension: 0.3,
        fill: false
    }));

    chart.data.labels = filteredData.map(entry => new Date(entry.timestamp).toLocaleTimeString());
    chart.data.datasets = datasets;
    chart.update();

    updateStats(filteredData);

    const router = getRouterIP(filteredData);

    title.textContent = router
        ? `Ping & Latency Monitor — Router IP: ${router}`
        : 'Ping & Latency Monitor — Router IP: Not Detected';
}

async function runPingLoop() {
    const results = await Promise.all(targets.map(ping));

    savePingResult(results);
    updateChart(getFilteredData(timeRange.value));

    setTimeout(runPingLoop, interval);
}

const ctx = document.getElementById('latencyChart').getContext('2d');

const chart = new Chart(ctx, {
    type: 'line',
    data: { labels: [], datasets: [] },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        animation: false,
        interaction: { mode: 'nearest', intersect: false },
        plugins: {
            legend: { position: 'top' },
            title:  { display: true, text: 'Latency to Targets (ms)' }
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

const title      = document.getElementById('title');
const timeRange  = document.getElementById('timeRange');
const avgLatency = document.getElementById('avgLatency');
const minLatency = document.getElementById('minLatency');
const maxLatency = document.getElementById('maxLatency');
const packetLoss = document.getElementById('packetLoss');

timeRange.addEventListener('change', () => {
    updateChart(getFilteredData(timeRange.value));
});

document.getElementById('clearStats').addEventListener('click', () => {
    localStorage.removeItem(historyKey);

    chart.data.labels = [];
    chart.data.datasets = [];
    chart.update();

    avgLatency.textContent = '-- ms';
    minLatency.textContent = '-- ms';
    maxLatency.textContent = '-- ms';
    packetLoss.textContent = '--';

    title.textContent = 'Ping & Latency Monitor — Router IP: Not Detected';
});

runPingLoop();
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/pinglatency",
    "Ping & Latency Tool GitHub Source Code"
);
include_footer();
close_database();
?>
