<?php
// /diy/goal-tracker/index.php
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

// --- Data directory ----------------------------------------------------------
$dataDir = __DIR__ . '/data';
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// --- Helper ------------------------------------------------------------------
function getFilePath(string $email, string $dir): ?string {
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $hash = md5(strtolower(trim($email)));
    return $dir . '/' . $hash . '.json';
}

// --- AJAX handling -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input  = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $email  = $input['email']  ?? '';

    $filePath = getFilePath($email, $dataDir);
    if (!$filePath) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address provided.']);
        exit;
    }

    if ($action === 'load') {
        if (file_exists($filePath)) {
            $userData = json_decode(file_get_contents($filePath), true) ?? [];
            echo json_encode(['status' => 'success', 'data' => $userData]);
        } else {
            echo json_encode(['status' => 'success', 'data' => []]);
        }
        exit;
    }

    if ($action === 'save') {
        $entry = $input['entry'] ?? null;
        if (!$entry || empty($entry['date'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid entry data.']);
            exit;
        }

        $userData = file_exists($filePath) ? json_decode(file_get_contents($filePath), true) : [];
        if (!is_array($userData)) {
            $userData = [];
        }

        $userData[$entry['date']] = $entry;

        // Auto-purge records older than 7 days
        $sevenDaysAgo = strtotime('-7 days');
        foreach ($userData as $dateKey => $data) {
            if (strtotime($dateKey) < $sevenDaysAgo) {
                unset($userData[$dateKey]);
            }
        }

        if (file_put_contents($filePath, json_encode($userData, JSON_PRETTY_PRINT))) {
            echo json_encode(['status' => 'success', 'data' => $userData]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to write to data folder. Check server permissions.']);
        }
        exit;
    }

    if ($action === 'clear') {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        echo json_encode(['status' => 'success']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    exit;
}

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/goal-tracker/index.php";
$parent_url = "/diy";
$title      = "Daily Goal Tracker & Weekly Log";
$template   = "Generic";
$meta_title = "Daily Goal Tracker & Weekly Log";
$meta_desc  = "Track daily objectives, micro-metrics, reflection and weekly progress. Export to CSV, PNG or PDF.";

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

        <div class="container" id="trackerCard">
            <h1>DAILY GOAL TRACKER</h1>

            <div class="email-bar" data-html2canvas-ignore="true">
                <label for="userEmail">Account Email (Reads/Writes to Server JSON Storage)</label>
                <div style="display: flex; gap: 10px;">
                    <input type="email" id="userEmail" placeholder="user@example.com" required>
                    <button type="button" onclick="loadUserData()" class="btn-primary" style="white-space: nowrap;">Load Records</button>
                </div>
            </div>

            <form id="trackerForm">
                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Date</label>
                        <input type="date" id="entryDate" required>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label>Daily Target Focus</label>
                        <input type="text" id="targetFocus" placeholder="Main objective for the day...">
                    </div>
                </div>

                <h2>Core Objectives &amp; Priorities</h2>
                <div class="form-group"><input type="text" id="priority1" placeholder="Priority Goal 1"></div>
                <div class="form-group"><input type="text" id="priority2" placeholder="Priority Goal 2"></div>
                <div class="form-group"><input type="text" id="priority3" placeholder="Priority Goal 3"></div>

                <h2>Daily Micro-Metrics</h2>
                <table id="metricsTable">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Metric</th>
                            <th style="width: 30%;">Target</th>
                            <th style="width: 30%;">Status / Result</th>
                            <th style="width: 10%;" data-html2canvas-ignore="true">Action</th>
                        </tr>
                    </thead>
                    <tbody id="metricsBody"></tbody>
                </table>
                <button type="button" onclick="addMetricRow()" class="btn-add" data-html2canvas-ignore="true">+ Add Custom Metric Row</button>

                <h2>Reflection &amp; Output Rating</h2>
                <div class="form-group">
                    <label>Daily Output Rating (1-5 Stars)</label>
                    <select id="rating">
                        <option value="5">5 - Exceptional</option>
                        <option value="4" selected>4 - Solid</option>
                        <option value="3">3 - Average</option>
                        <option value="2">2 - Needs Improvement</option>
                        <option value="1">1 - Blocked</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reflection / Blockers</label>
                    <textarea id="reflection" rows="2" placeholder="What went well or needs fixing?"></textarea>
                </div>

                <div class="btn-group" data-html2canvas-ignore="true">
                    <button type="submit" class="btn-primary">Save to Server JSON</button>
                    <button type="button" onclick="exportToCSV()" class="btn-secondary">Export 7-Day CSV</button>
                    <button type="button" onclick="exportToPNG()" class="btn-export">Export Image (PNG)</button>
                    <button type="button" onclick="exportToPDF()" class="btn-export">Export PDF</button>
                    <button type="button" onclick="clearServerData()" class="btn-danger">Clear Server History</button>
                </div>
            </form>

            <div class="history-panel" data-html2canvas-ignore="true">
                <strong>Saved Days on Server (Past 7 Days):</strong>
                <div id="historyList" class="history-list" style="margin-top: 8px;">Enter email to load records.</div>
            </div>
        </div>

    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    document.getElementById('entryDate').valueAsDate = new Date();
    let currentStore = {};

    const defaultMetrics = [
        { name: "Water Intake", target: "8 Glasses", status: "" },
        { name: "Exercise", target: "30 Mins", status: "" },
        { name: "Deep Work Focus", target: "3 Hours", status: "" }
    ];

    document.getElementById('entryDate').addEventListener('change', populateFormForDate);

    document.getElementById('trackerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveToServer();
    });

    function addMetricRow(name = "", target = "", status = "") {
        const tbody = document.getElementById('metricsBody');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" class="metric-name" value="${name}" placeholder="Metric Name"></td>
            <td><input type="text" class="metric-target" value="${target}" placeholder="Target"></td>
            <td><input type="text" class="metric-status" value="${status}" placeholder="Result"></td>
            <td style="text-align: center;" data-html2canvas-ignore="true"><button type="button" onclick="removeRow(this)" class="btn-remove">X</button></td>
        `;
        tbody.appendChild(tr);
    }

    function removeRow(btn) { btn.closest('tr').remove(); }

    async function apiRequest(action, payload = {}) {
        const email = document.getElementById('userEmail').value.trim();
        if (!email) {
            alert("Please enter a valid email address first.");
            return null;
        }

        try {
            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, email, ...payload })
            });
            return await res.json();
        } catch (err) {
            alert("Network error communicating with PHP backend.");
            return null;
        }
    }

    async function loadUserData() {
        const res = await apiRequest('load');
        if (res && res.status === 'success') {
            currentStore = res.data;
            populateFormForDate();
            updateHistoryList();
        } else if (res) {
            alert(res.message);
        }
    }

    async function saveToServer() {
        const dateKey = document.getElementById('entryDate').value;
        if (!dateKey) return;

        const metrics = [];
        document.querySelectorAll('#metricsBody tr').forEach(row => {
            const name = row.querySelector('.metric-name').value.trim();
            const target = row.querySelector('.metric-target').value.trim();
            const status = row.querySelector('.metric-status').value.trim();
            if (name || target || status) metrics.push({ name, target, status });
        });

        const entry = {
            date: dateKey,
            targetFocus: document.getElementById('targetFocus').value,
            priority1: document.getElementById('priority1').value,
            priority2: document.getElementById('priority2').value,
            priority3: document.getElementById('priority3').value,
            metrics: metrics,
            rating: document.getElementById('rating').value,
            reflection: document.getElementById('reflection').value
        };

        const res = await apiRequest('save', { entry });
        if (res && res.status === 'success') {
            currentStore = res.data;
            updateHistoryList();
            alert('Entry saved to server JSON for ' + dateKey);
        } else if (res) {
            alert(res.message);
        }
    }

    function populateFormForDate() {
        const dateKey = document.getElementById('entryDate').value;
        const data = currentStore[dateKey];
        const tbody = document.getElementById('metricsBody');
        tbody.innerHTML = '';

        if (data) {
            document.getElementById('targetFocus').value = data.targetFocus || '';
            document.getElementById('priority1').value = data.priority1 || '';
            document.getElementById('priority2').value = data.priority2 || '';
            document.getElementById('priority3').value = data.priority3 || '';
            document.getElementById('rating').value = data.rating || '4';
            document.getElementById('reflection').value = data.reflection || '';

            if (data.metrics && data.metrics.length > 0) {
                data.metrics.forEach(m => addMetricRow(m.name, m.target, m.status));
            } else {
                defaultMetrics.forEach(m => addMetricRow(m.name, m.target, m.status));
            }
        } else {
            document.querySelectorAll('#trackerForm input[type="text"], #trackerForm textarea').forEach(el => el.value = '');
            document.getElementById('rating').value = '4';
            defaultMetrics.forEach(m => addMetricRow(m.name, m.target, m.status));
        }
    }

    function updateHistoryList() {
        const dates = Object.keys(currentStore).sort((a, b) => new Date(b) - new Date(a));
        const container = document.getElementById('historyList');

        if (dates.length === 0) {
            container.innerHTML = 'No entries saved on server yet.';
            return;
        }

        container.innerHTML = dates.map(d => {
            const e = currentStore[d];
            return `• <strong>${e.date}</strong>: Focus: "${e.targetFocus || 'N/A'}" (Rating: ${e.rating}/5)`;
        }).join('<br>');
    }

    function exportToCSV() {
        const dates = Object.keys(currentStore).sort((a, b) => new Date(b) - new Date(a));
        if (dates.length === 0) {
            alert('No server data loaded to export!');
            return;
        }

        const headers = ["Date", "Target Focus", "Priority 1", "Priority 2", "Priority 3", "Metrics (Name | Target | Status)", "Rating", "Reflection"];
        let csvContent = "data:text/csv;charset=utf-8," + headers.join(",") + "\n";

        dates.forEach(d => {
            const e = currentStore[d];
            const formattedMetrics = (e.metrics || []).map(m => `${m.name} [Target: ${m.target}] = ${m.status}`).join(" ; ");

            const row = [
                `"${e.date}"`,
                `"${(e.targetFocus || '').replace(/"/g, '""')}"`,
                `"${(e.priority1 || '').replace(/"/g, '""')}"`,
                `"${(e.priority2 || '').replace(/"/g, '""')}"`,
                `"${(e.priority3 || '').replace(/"/g, '""')}"`,
                `"${formattedMetrics.replace(/"/g, '""')}"`,
                `"${e.rating}"`,
                `"${(e.reflection || '').replace(/"/g, '""')}"`
            ];
            csvContent += row.join(",") + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `Goal_Tracker_${document.getElementById('userEmail').value.split('@')[0]}_Past_Week.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportToPNG() {
        const card = document.getElementById('trackerCard');
        const dateKey = document.getElementById('entryDate').value || 'Entry';

        html2canvas(card, { scale: 2 }).then(canvas => {
            const link = document.createElement('a');
            link.download = `Goal_Tracker_${dateKey}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }

    function exportToPDF() {
        const card = document.getElementById('trackerCard');
        const dateKey = document.getElementById('entryDate').value || 'Entry';
        const { jsPDF } = window.jspdf;

        html2canvas(card, { scale: 2 }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            const imgWidth = 210;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;

            pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
            pdf.save(`Goal_Tracker_${dateKey}.pdf`);
        });
    }

    async function clearServerData() {
        if (confirm("Permanently delete this user's JSON log file on the server?")) {
            const res = await apiRequest('clear');
            if (res && res.status === 'success') {
                currentStore = {};
                populateFormForDate();
                updateHistoryList();
                alert('Server history cleared.');
            }
        }
    }

    defaultMetrics.forEach(m => addMetricRow(m.name, m.target, m.status));
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/goal-tracker",
    "Daily Goal Tracker Source Code"
);
include_footer();
close_database();
?>
