<?php
// /diy/internet-drop-monitor/index.php
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
$url        = "/diy/internet-drop-monitor/index.php";
$parent_url = "/diy";
$title      = "Internet Connection Drop Monitor";
$template   = "Generic";
$meta_title = "Internet Connection Drop Monitor";
$meta_desc  = "Monitor your internet connection for drops and log outages with timestamps. Download logs locally.";

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

        <h1>Internet Connection Drop Monitor</h1>

        <div id="status" class="autodiag-status on" style="display:inline-block;padding:10px 20px;border-radius:6px;font-weight:700;margin-bottom:16px;">Checking connection...</div>

        <div class="card" style="background:#fff3cd;border-color:#ffeeba;color:#856404;">
            <strong>Warning:</strong> If you navigate away from this page, put your PC/mobile to sleep, or close the browser, monitoring will stop.
        </div>

        <div class="card">
            <div class="btn-group" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
                <button type="button" onclick="downloadLogs()" class="btn-primary" style="padding:10px 15px;border:none;border-radius:5px;background:#0b5394;color:#fff;cursor:pointer;">Download Logs</button>
                <button type="button" onclick="clearLogs()" class="btn-danger" style="padding:10px 15px;border:none;border-radius:5px;background:#ef4444;color:#fff;cursor:pointer;">Clear Logs</button>
            </div>

            <pre id="logDisplay" style="background:#ffffff;padding:15px;border:1px solid #ccc;max-height:300px;overflow-y:auto;white-space:pre-wrap;border-radius:6px;"></pre>
        </div>

    </div>
</main>

<script>
    let offlineTime = null;
    let logs = JSON.parse(localStorage.getItem('connectionLogs') || '[]');
    let wasHidden = false;

    function updateStatus() {
        const statusEl = document.getElementById('status');
        const now = new Date().toLocaleTimeString();

        if (navigator.onLine) {
            statusEl.textContent = 'Online';
            statusEl.classList.remove('off');
            statusEl.classList.add('on');
            if (offlineTime !== null) {
                const duration = ((Date.now() - offlineTime) / 1000).toFixed(2);
                const logEntry = `Reconnected after ${duration}s at ${now}`;
                logs.push(logEntry);
                localStorage.setItem('connectionLogs', JSON.stringify(logs));
                offlineTime = null;
                logNetworkInfo();
            }
        } else {
            statusEl.textContent = 'Offline';
            statusEl.classList.remove('on');
            statusEl.classList.add('off');
            if (offlineTime === null) {
                offlineTime = Date.now();
                const logEntry = `Connection lost at ${now}`;
                logs.push(logEntry);
                localStorage.setItem('connectionLogs', JSON.stringify(logs));
            }
        }

        updateLogDisplay();
    }

    function logNetworkInfo() {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (connection) {
            const now = new Date().toLocaleTimeString();
            const info = `Network Info at ${now}:
  Type: ${connection.type || 'unknown'}
  Effective Type: ${connection.effectiveType || 'unknown'}
  Downlink (Mbps): ${connection.downlink || 'unknown'}
  RTT (ms): ${connection.rtt || 'unknown'}
  Save Data: ${connection.saveData || false}`;

            logs.push(info);
            localStorage.setItem('connectionLogs', JSON.stringify(logs));
        }
    }

    function downloadLogs() {
        const content = logs.join('\n');
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'connection_logs.txt';
        a.click();
        URL.revokeObjectURL(url);
    }

    function clearLogs() {
        logs = [];
        localStorage.removeItem('connectionLogs');
        updateLogDisplay();
        const statusEl = document.getElementById('status');
        statusEl.textContent = 'Logs cleared. Monitoring...';
        statusEl.classList.remove('off');
        statusEl.classList.add('on');
    }

    function updateLogDisplay() {
        document.getElementById('logDisplay').textContent = logs.join('\n');
    }

    document.addEventListener("visibilitychange", () => {
        const now = new Date().toLocaleTimeString();
        if (document.hidden) {
            wasHidden = true;
            logs.push(`[Paused] Monitoring paused: Tab inactive or minimized at ${now}`);
        } else if (wasHidden) {
            logs.push(`[Resumed] You returned at ${now}. Monitoring may have been paused due to tab inactivity or device sleep.`);
            wasHidden = false;
        }
        localStorage.setItem('connectionLogs', JSON.stringify(logs));
        updateLogDisplay();
    });

    window.addEventListener('online', updateStatus);
    window.addEventListener('offline', updateStatus);

    setInterval(updateStatus, 5000);
    updateStatus();
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/internet-drop-monitor",
    "Internet Drop Monitor Source Code"
);
include_footer();
close_database();
?>
