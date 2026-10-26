<?php
// /diy/speed/index.php
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
$url        = "/diy/speed/index.php";
$parent_url = "/diy/speed";
$title      = "Free Internet Speed Test by CfCbazar - Fast & Accurate";
$template   = "Generic";
$meta_title = "Free Internet Speed Test by CfCbazar - Fast & Accurate";
$meta_desc  = "Test your internet speed accurately with CfCbazar Speed Test. Measure download speed using multiple parallel streams for realistic results.";

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

<style>
.speedtest {
    --bg:      #0f172a;
    --panel:   #1e293b;
    --border:  #334155;
    --text:    #f1f5f9;
    --muted:   #94a3b8;
    --accent:  #22c55e;
    --accent-2:#38bdf8;
    --console-bg: #020617;
    --console-fg: #4ade80;

    max-width: 900px;
    margin: 0 auto;
    padding: 24px 16px 40px;
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    line-height: 1.5;
}

.speedtest * { box-sizing: border-box; }

/* --- Header --- */
.speedtest-header {
    text-align: center;
    margin-bottom: 24px;
}

.speedtest-header h1 {
    margin: 0 0 6px 0;
    font-size: 1.6rem;
    color: var(--accent-2);
    letter-spacing: 0.01em;
}

.speedtest-header p {
    margin: 0;
    color: var(--muted);
    font-size: 0.9rem;
}

/* --- Start button --- */
.speedtest-start {
    display: block;
    width: 100%;
    max-width: 320px;
    margin: 0 auto 24px;
    padding: 16px 32px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    background: var(--accent);
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: background-color 0.15s, transform 0.1s;
}

.speedtest-start:hover:not(:disabled) { background: #4ade80; }
.speedtest-start:active:not(:disabled) { transform: scale(0.98); }
.speedtest-start:disabled {
    background: #334155;
    color: #94a3b8;
    cursor: not-allowed;
}

/* --- Progress bar --- */
.speedtest-progress {
    width: 100%;
    height: 8px;
    background: var(--panel);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 24px;
}

.speedtest-progress-fill {
    height: 100%;
    background: var(--accent);
    border-radius: 4px;
    width: 0;
    transition: width 0.3s ease;
}

/* --- Chart panel --- */
.speedtest-panel {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}

.speedtest-panel h2 {
    margin: 0 0 12px 0;
    font-size: 0.8rem;
    color: var(--accent-2);
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.speedtest-chart-wrap {
    position: relative;
    width: 100%;
    height: 260px;
}

/* --- Results grid --- */
.speedtest-results {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

.speedtest-result-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px 12px;
    text-align: center;
    opacity: 0.35;
    transition: opacity 0.3s ease, border-color 0.3s ease;
}

.speedtest-result-card.filled {
    opacity: 1;
    border-color: var(--accent);
}

.speedtest-result-label {
    display: block;
    font-size: 0.7rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
    margin-bottom: 8px;
}

.speedtest-result-value {
    display: block;
    font-size: 1.9rem;
    font-weight: 800;
    color: var(--accent);
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}

.speedtest-result-unit {
    display: block;
    font-size: 0.7rem;
    color: var(--muted);
    margin-top: 4px;
}

.speedtest-result-note {
    display: block;
    font-size: 0.7rem;
    color: var(--muted);
    margin-top: 8px;
    font-style: italic;
}

/* --- Console (collapsible) --- */
.speedtest-console-details {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 16px;
    overflow: hidden;
}

.speedtest-console-details summary {
    cursor: pointer;
    padding: 12px 16px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--muted);
    list-style: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.speedtest-console-details summary::-webkit-details-marker { display: none; }

.speedtest-console-details summary::before {
    content: '▸';
    color: var(--accent-2);
    transition: transform 0.15s;
}

.speedtest-console-details[open] summary::before {
    transform: rotate(90deg);
}

.speedtest-console-details[open] summary {
    border-bottom: 1px solid var(--border);
}

.speedtest-console {
    background: var(--console-bg);
    color: var(--console-fg);
    font-family: ui-monospace, "SF Mono", Consolas, "Courier New", monospace;
    font-size: 0.78rem;
    line-height: 1.5;
    padding: 12px 16px;
    max-height: 240px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-word;
}

.speedtest-console p {
    margin: 0 0 4px 0;
}

.speedtest-console p:last-child {
    margin-bottom: 0;
}

/* --- Footer --- */
.speedtest-footer {
    text-align: center;
    font-size: 0.75rem;
    color: var(--muted);
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.speedtest-footer a {
    color: var(--accent-2);
    text-decoration: none;
}

.speedtest-footer a:hover {
    text-decoration: underline;
}

/* --- Responsive --- */
@media (max-width: 600px) {
    .speedtest {
        padding: 16px 12px 32px;
    }
    .speedtest-header h1 {
        font-size: 1.3rem;
    }
    .speedtest-start {
        padding: 14px 24px;
        font-size: 1rem;
    }
    .speedtest-chart-wrap {
        height: 200px;
    }
    .speedtest-results {
        grid-template-columns: 1fr;
    }
    .speedtest-result-value {
        font-size: 1.6rem;
    }
}
</style>

<main class="content">
    <div class="speedtest">

        <header class="speedtest-header">
            <h1>Speed Test</h1>
            <p>Parallel stream benchmark · CfCbazar Rev 8.1</p>
        </header>

        <button id="startTest" class="speedtest-start" type="button">Start Test</button>

        <div class="speedtest-progress">
            <div id="progress-bar" class="speedtest-progress-fill"></div>
        </div>

        <section class="speedtest-panel">
            <h2>Live Speed by Parallel Streams</h2>
            <div class="speedtest-chart-wrap">
                <canvas id="speedChart"></canvas>
            </div>
        </section>

        <div id="results" class="speedtest-results">
            <div class="speedtest-result-card">
                <span class="speedtest-result-label">Max Achievable</span>
                <span class="speedtest-result-value" data-result="max">—</span>
                <span class="speedtest-result-unit">Mbps</span>
            </div>
            <div class="speedtest-result-card">
                <span class="speedtest-result-label">This Device</span>
                <span class="speedtest-result-value" data-result="current">—</span>
                <span class="speedtest-result-unit">Mbps</span>
            </div>
            <div class="speedtest-result-card">
                <span class="speedtest-result-label">Torrent Estimate</span>
                <span class="speedtest-result-value" data-result="torrent">—</span>
                <span class="speedtest-result-unit">Mbps</span>
                <span class="speedtest-result-note" data-result="note"></span>
            </div>
        </div>

        <details class="speedtest-console-details">
            <summary>Debug Console</summary>
            <div id="console-output" class="speedtest-console"></div>
        </details>

        <footer class="speedtest-footer">
            <a href="https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/speed" target="_blank" rel="noopener">
                Source code on GitHub
            </a>
            · Rev 8.1 · © CfCbazar Beta Test
        </footer>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const TIMEOUT_SECS = 2;

const imageUrls = [
    { url: "https://cc.free.bg/dat.jpg", size: 1 },
    { url: "https://upload.wikimedia.org/wikipedia/commons/f/fd/Alaus%C3%AD_Inner_yards_east_of_Calle_Sim%C3%B3n_Bol%C3%ADvar_2.jpg", size: 11 },
    { url: "https://upload.wikimedia.org/wikipedia/commons/6/66/Grand-view-point-003.jpg", size: 59 },
    { url: "https://speed.cloudflare.com/__down?bytes=100000000", size: 100 },
    { url: "https://speed.cloudflare.com/__down?bytes=500000000", size: 500 },
    { url: "https://speed.cloudflare.com/__down?bytes=1000000000", size: 1000 },
    { url: "https://upload.wikimedia.org/wikipedia/commons/6/69/%22Houston%2C_Tranquility_base_here_%22_%28LROC563%29.tiff", size: 1200 },
    { url: "https://speed.cloudflare.com/__down?bytes=1500000000", size: 1500 }
];

let testRunning = false;
let speedData   = [];
let chart;

const startBtn      = document.getElementById("startTest");
const progressBar   = document.getElementById("progress-bar");
const consoleOutput = document.getElementById("console-output");
const ctx           = document.getElementById("speedChart").getContext("2d");

startBtn.addEventListener("click", startSpeedTest);

async function startSpeedTest() {
    if (testRunning) return;
    testRunning = true;

    startBtn.disabled = true;
    startBtn.textContent = "Testing…";
    resetResultCards();
    consoleOutput.innerHTML = "";
    progressBar.style.width = "0%";
    speedData = [];
    initializeGraph();

    for (let p = 2; p <= 8; p++) {
        updateProgress(p);
        log(`→ Testing ${p} parallel streams…`);
        const imgs = selectImages(p);
        log(`  • URLs: ${imgs.map(i => i.size + "MB").join(", ")}`);

        const speeds = await Promise.all(
            imgs.map(img => downloadAndMeasure(img.url, TIMEOUT_SECS))
        );

        const total = speeds.reduce((a, b) => a + b, 0);
        speedData.push({ parallel: p, speed: total });

        updateGraph(p, total);
        log(`  → Group ${p} speed: ${total.toFixed(1)} Mbps\n`);
    }

    updateProgress(100);
    displayFinalResults();

    startBtn.disabled = false;
    startBtn.textContent = "Test Again";
    testRunning = false;
}

async function downloadAndMeasure(url, timeoutSecs) {
    let received = 0;
    const controller = new AbortController();
    const start = Date.now();
    const timeout = setTimeout(() => controller.abort(), timeoutSecs * 1000);

    try {
        const resp = await fetch(url, { cache: "no-store", signal: controller.signal });
        const reader = resp.body.getReader();
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            received += value.length;
            if ((Date.now() - start) / 1000 >= timeoutSecs) {
                controller.abort();
                break;
            }
        }
    } catch (e) {
        if (e.name !== "AbortError") console.error(e);
    }
    clearTimeout(timeout);

    const duration = (Date.now() - start) / 1000 || 0.001;
    const mb       = received / (1024 * 1024);
    const mbps     = (mb * 8) / duration;
    log(`    • ${mb.toFixed(2)} MB in ${duration.toFixed(2)} s → ${mbps.toFixed(1)} Mbps`);
    return mbps;
}

function selectImages(count) {
    return imageUrls.slice().sort((a, b) => a.size - b.size).slice(0, count);
}

function updateProgress(group) {
    progressBar.style.width = `${Math.min((group / 8) * 100, 100)}%`;
}

function log(msg) {
    consoleOutput.innerHTML += `<p>${msg}</p>`;
    consoleOutput.scrollTop = consoleOutput.scrollHeight;
}

function initializeGraph() {
    if (chart) chart.destroy();
    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Group Speed (Mbps)',
                data: [],
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#22c55e',
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 300 },
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Parallel Streams', color: '#94a3b8' },
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(148, 163, 184, 0.15)' }
                },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Mbps', color: '#94a3b8' },
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(148, 163, 184, 0.15)' }
                }
            }
        }
    });
}

function updateGraph(parallel, speed) {
    chart.data.labels.push(parallel);
    chart.data.datasets[0].data.push(speed);
    chart.update();
}

function resetResultCards() {
    document.querySelectorAll('.speedtest-result-card').forEach(c => c.classList.remove('filled'));
    document.querySelector('[data-result="max"]').textContent = '—';
    document.querySelector('[data-result="current"]').textContent = '—';
    document.querySelector('[data-result="torrent"]').textContent = '—';
    document.querySelector('[data-result="note"]').textContent = '';
}

function displayFinalResults() {
    const speeds = speedData.map(d => d.speed).sort((a, b) => a - b);
    const dropped = speeds.shift();
    const maxAchievable = Math.max(...speeds);
    const avgRemaining  = speeds.reduce((a, b) => a + b, 0) / speeds.length;
    const TorrentSpeed  = avgRemaining * 35;

    document.querySelector('[data-result="max"]').textContent     = maxAchievable.toFixed(1);
    document.querySelector('[data-result="current"]').textContent = avgRemaining.toFixed(1);
    document.querySelector('[data-result="torrent"]').textContent = TorrentSpeed.toFixed(1);
    document.querySelector('[data-result="note"]').textContent    = `dropped: ${dropped.toFixed(1)}`;

    document.querySelectorAll('.speedtest-result-card').forEach(c => c.classList.add('filled'));
}
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/speed",
    "Speed Test Source Code"
);
include_footer();
close_database();
?>
