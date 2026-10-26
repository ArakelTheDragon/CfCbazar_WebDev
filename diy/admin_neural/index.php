<?php
// /diy/byte-embed/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';

enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

$url        = "/diy/admin_neural/index.php";
$parent_url = "/diy/byte-admin_neural";
$title      = "Byte Embedding Trainer (32 × 32)";
$template   = "Generic";
$meta_title = "Byte Embedding Trainer";
$meta_desc  = "Interactive training system: 16 input bytes, 16 output bytes, 32 embeddings per byte, learns from user corrections.";

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
.be {
    --bg:      #0f172a;
    --panel:   #1e293b;
    --border:  #334155;
    --text:    #f1f5f9;
    --muted:   #94a3b8;
    --accent:  #38bdf8;
    --in:      #a78bfa;
    --out:     #22c55e;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px 14px 40px;
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    line-height: 1.5;
}
.be * { box-sizing: border-box; }

.be h1 { margin: 0 0 4px 0; font-size: 1.4rem; color: var(--accent); }
.be .sub { margin: 0 0 18px 0; color: var(--muted); font-size: 0.85rem; }

.be-panel {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 12px;
}
.be-panel h2 {
    margin: 0 0 10px 0;
    font-size: 0.72rem;
    color: var(--accent);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.be-row {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 6px;
    margin-bottom: 8px;
}

.be-cell {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 5px;
    padding: 6px 4px;
    text-align: center;
    font-family: ui-monospace, "SF Mono", Consolas, monospace;
    font-size: 0.75rem;
}

.be-cell .lbl {
    display: block;
    font-size: 0.6rem;
    color: var(--muted);
    margin-bottom: 2px;
}

.be-cell .val {
    display: block;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.be-cell.input .val { color: var(--in); }
.be-cell.output .val { color: var(--out); }

.be-cell input {
    width: 100%;
    background: transparent;
    border: none;
    color: var(--out);
    text-align: center;
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 700;
    outline: none;
    padding: 0;
}

.be-cell input:focus { background: #0f172a; border-radius: 3px; }

.be-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.be-actions button {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.15s;
}

.be-btn-save    { background: var(--out); color: #0f172a; }
.be-btn-save:hover { background: #4ade80; }
.be-btn-new     { background: var(--accent); color: #0f172a; }
.be-btn-new:hover { background: #7dd3fc; }
.be-btn-accept  { background: #475569; color: var(--text); }
.be-btn-accept:hover { background: #64748b; }

.be-status {
    font-size: 0.8rem;
    color: var(--muted);
    margin-top: 8px;
    font-family: ui-monospace, monospace;
}

.be-status .ok  { color: var(--out); }
.be-status .err { color: #fca5a5; }

.be-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
.be-stat {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 8px 10px;
    text-align: center;
}
.be-stat .lbl {
    display: block;
    font-size: 0.62rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 3px;
}
.be-stat .val {
    display: block;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--accent);
    font-variant-numeric: tabular-nums;
}

@media (max-width: 600px) {
    .be-row { grid-template-columns: repeat(4, 1fr); }
    .be-stats { grid-template-columns: repeat(2, 1fr); }
}
</style>

<main class="content">
    <div class="be">

        <h1>Byte Embedding Trainer</h1>
        <p class="sub">
            16 input bytes (random probes) · 16 output bytes (learned) · 32 embeddings per byte
        </p>

        <div id="errorBox" class="be-status err" style="display:none;"></div>

        <section class="be-panel">
            <h2>Stats</h2>
            <div class="be-stats">
                <div class="be-stat"><span class="lbl">Vocab</span><span class="val">256</span></div>
                <div class="be-stat"><span class="lbl">Dim / Byte</span><span class="val">32</span></div>
                <div class="be-stat"><span class="lbl">Input Bytes</span><span class="val">16</span></div>
                <div class="be-stat"><span class="lbl">Output Bytes</span><span class="val">16</span></div>
            </div>
        </section>

        <section class="be-panel">
            <h2>Input bytes (locked)</h2>
            <div id="inputRow" class="be-row"></div>
        </section>

        <section class="be-panel">
            <h2>Output bytes (editable)</h2>
            <div id="outputRow" class="be-row"></div>

            <div class="be-actions">
                <button type="button" id="saveBtn" class="be-btn-save">Save correction</button>
                <button type="button" id="acceptBtn" class="be-btn-accept">Accept prediction</button>
                <button type="button" id="newBtn" class="be-btn-new">New input</button>
            </div>

            <div id="statusLine" class="be-status">Loading…</div>
        </section>

    </div>
</main>

<script>
let currentState = null;
let pollTimer = null;

const inputRow   = document.getElementById('inputRow');
const outputRow  = document.getElementById('outputRow');
const statusLine = document.getElementById('statusLine');
const errorBox   = document.getElementById('errorBox');

function render(state) {
    currentState = state;

    // Input row
    inputRow.innerHTML = '';
    for (let i = 0; i < 16; i++) {
        const div = document.createElement('div');
        div.className = 'be-cell input';
        div.innerHTML = `<span class="lbl">in${i}</span><span class="val">${state.input[i]}</span>`;
        inputRow.appendChild(div);
    }

    // Output row
    outputRow.innerHTML = '';
    for (let i = 0; i < 16; i++) {
        const div = document.createElement('div');
        div.className = 'be-cell output';
        div.innerHTML = `<span class="lbl">out${i}</span>`;
        const inp = document.createElement('input');
        inp.type = 'number';
        inp.min = '-128';
        inp.max = '127';
        inp.value = state.user_output[i];
        inp.name = `out${i}`;
        inp.dataset.index = String(i);
        div.appendChild(inp);
        outputRow.appendChild(div);
    }

    const ago = Math.max(0, Math.floor((Date.now() / 1000) - state.created_at));
    statusLine.innerHTML = `Prediction loaded · ${ago}s ago · ${state.corrected ? 'corrected' : 'not corrected'}`;
}

function showError(msg) {
    errorBox.style.display = 'block';
    errorBox.textContent = 'Error: ' + msg;
}

function clearError() {
    errorBox.style.display = 'none';
    errorBox.textContent = '';
}

async function loadState() {
    try {
        const res = await fetch('state.php?action=tick', { cache: 'no-store' });
        const j = await res.json();
        if (!j.ok) throw new Error('state.php returned not ok');
        render(j.state);
        clearError();
    } catch (e) {
        showError(e.message);
    }
}

async function saveCorrection() {
    const body = new URLSearchParams();
    body.set('action', 'correct');
    for (let i = 0; i < 16; i++) {
        const inp = document.querySelector(`input[data-index="${i}"]`);
        const v = parseInt(inp.value, 10);
        body.set(`out${i}`, String(isNaN(v) ? 0 : v));
    }

    statusLine.textContent = 'Saving and training…';

    try {
        const res = await fetch('state.php', { method: 'POST', body });
        const j = await res.json();
        if (!j.ok) throw new Error('save failed');
        render(j.state);
        const st = j.train_stats || {};
        statusLine.innerHTML = `<span class="ok">Saved · trained on ${st.pairs ?? '?'} pairs, ${st.epochs ?? '?'} epochs, loss=${st.loss ?? '?'}</span>`;
        clearError();
    } catch (e) {
        showError(e.message);
        statusLine.textContent = 'Save failed.';
    }
}

async function acceptPrediction() {
    statusLine.textContent = 'Accepted without training.';
    await newInput();
}

async function newInput() {
    try {
        const res = await fetch('state.php?action=force_new', { cache: 'no-store' });
        const j = await res.json();
        if (!j.ok) throw new Error('new input failed');
        render(j.state);
        clearError();
    } catch (e) {
        showError(e.message);
    }
}

document.getElementById('saveBtn').addEventListener('click', saveCorrection);
document.getElementById('acceptBtn').addEventListener('click', acceptPrediction);
document.getElementById('newBtn').addEventListener('click', newInput);

loadState();
pollTimer = setInterval(loadState, 30000);
</script>

<?php
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/byte-embed",
    "Byte Embedding Trainer Source Code"
);
include_footer();
close_database();
?>
