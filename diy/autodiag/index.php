<?php
// /diy/autodiag/index.php
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

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/autodiag/index.php";
$parent_url = "/diy/autodiag";
$title      = "Automotive UDS CAN diagnostic Simulator";
$template   = "Generic";
$meta_title = "Free Open Source UDS CAN diagnostic tool";
$meta_desc  = "Send AT commands and CAN bytes for UDS diagnostic under ISO to check how automotive testing is done.";

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
.autodiag {
    --bg:        #0f172a;
    --panel:     #1e293b;
    --border:    #334155;
    --text:      #f1f5f9;
    --muted:     #94a3b8;
    --accent:    #38bdf8;
    --success:   #22c55e;
    --danger:    #ef4444;
    --console-bg:#020617;
    --console-fg:#4ade80;

    max-width: 1100px;
    margin: 0 auto;
    padding: 24px 16px 48px;
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    line-height: 1.5;
}

.autodiag * {
    box-sizing: border-box;
}

/* --- Header --- */
.autodiag-header {
    text-align: center;
    margin-bottom: 24px;
}

.autodiag-header h1 {
    margin: 0 0 6px 0;
    font-size: 1.75rem;
    color: var(--accent);
    letter-spacing: 0.01em;
}

.autodiag-header p {
    margin: 0;
    color: var(--muted);
    font-size: 0.95rem;
}

/* --- Panel --- */
.autodiag-panel {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.autodiag-panel h2 {
    margin: 0 0 14px 0;
    font-size: 1.05rem;
    color: var(--accent);
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

/* --- Status badges --- */
.autodiag-status {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.autodiag-badge {
    flex: 0 1 auto;
    min-width: 140px;
    padding: 12px 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: 700;
    font-size: 0.95rem;
    border: 1px solid transparent;
    transition: background-color 0.2s, border-color 0.2s;
}

.autodiag-badge.off {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.4);
    color: #fca5a5;
}

.autodiag-badge.on {
    background: rgba(34, 197, 94, 0.15);
    border-color: rgba(34, 197, 94, 0.5);
    color: #86efac;
}

/* --- Command input row --- */
.autodiag-command-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.autodiag-command-row input {
    flex: 1 1 240px;
    min-width: 0;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text);
    font-size: 1rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s;
}

.autodiag-command-row input:focus {
    border-color: var(--accent);
}

.autodiag-command-row button {
    flex: 0 0 auto;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    background: var(--accent);
    color: #0f172a;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.15s;
}

.autodiag-command-row button:hover {
    background: #7dd3fc;
}

.autodiag-hint {
    margin: 12px 0 0 0;
    font-size: 0.85rem;
    color: var(--muted);
}

.autodiag-hint code {
    background: var(--bg);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--accent);
    font-size: 0.85rem;
}

/* --- Console --- */
.autodiag-console {
    background: var(--console-bg);
    color: var(--console-fg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px 16px;
    min-height: 200px;
    max-height: 320px;
    overflow-y: auto;
    font-family: ui-monospace, "SF Mono", Consolas, "Courier New", monospace;
    font-size: 0.85rem;
    line-height: 1.55;
    white-space: pre-wrap;
    word-break: break-word;
}

.autodiag-console-line {
    margin-bottom: 3px;
}

.autodiag-console-line:last-child {
    margin-bottom: 0;
}

.autodiag-console-clear {
    margin-top: 12px;
    padding: 8px 16px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--muted);
    font-size: 0.85rem;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}

.autodiag-console-clear:hover {
    border-color: var(--accent);
    color: var(--accent);
}

/* --- Dashboard sliders --- */
.autodiag-dashboard {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.autodiag-control {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px;
    min-width: 0;
}

.autodiag-control label {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 10px;
    font-size: 0.85rem;
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    gap: 8px;
}

.autodiag-value {
    font-size: 1.15rem;
    color: var(--accent);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.autodiag-control input[type="range"] {
    width: 100%;
    margin: 0;
    accent-color: var(--accent);
}

/* --- Command reference --- */
.autodiag-reference {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.autodiag-ref-card {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px;
}

.autodiag-ref-card h3 {
    margin: 0 0 8px 0;
    font-size: 0.9rem;
    color: var(--accent);
    font-family: ui-monospace, "SF Mono", Consolas, monospace;
}

.autodiag-ref-card p {
    margin: 0 0 4px 0;
    font-size: 0.85rem;
    color: var(--muted);
}

.autodiag-ref-card code {
    background: var(--panel);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--text);
    font-size: 0.8rem;
    font-family: ui-monospace, "SF Mono", Consolas, monospace;
}

/* --- Responsive --- */
@media (max-width: 900px) {
    .autodiag-dashboard {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .autodiag-reference {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 520px) {
    .autodiag {
        padding: 16px 12px 32px;
    }
    .autodiag-header h1 {
        font-size: 1.35rem;
    }
    .autodiag-panel {
        padding: 16px;
    }
    .autodiag-dashboard {
        grid-template-columns: 1fr;
    }
    .autodiag-command-row input,
    .autodiag-command-row button {
        flex: 1 1 100%;
    }
    .autodiag-console {
        font-size: 0.8rem;
        min-height: 160px;
    }
}
</style>

<div class="autodiag">

    <header class="autodiag-header">
        <h1>CAN Diagnostic Emulator</h1>
        <p>UDS &amp; OBD-II simulator · Educational only — no real vehicle connection</p>
    </header>

    <section class="autodiag-panel">
        <h2>ECU &amp; Engine Status</h2>
        <div class="autodiag-status">
            <div id="ignitionStatus" class="autodiag-badge off">Ignition: OFF</div>
            <div id="engineStatus"   class="autodiag-badge off">Engine: OFF</div>
        </div>
    </section>

    <section class="autodiag-panel">
        <h2>Command Console</h2>

        <div id="consoleLog" class="autodiag-console" role="log" aria-live="polite"></div>

        <div class="autodiag-command-row" style="margin-top:14px;">
            <input
                type="text"
                id="commandInput"
                placeholder="e.g. AT+PWRON"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                aria-label="CAN diagnostic AT command"
            >
            <button type="button" id="sendCommandButton">Send</button>
        </div>

        <p class="autodiag-hint">
            Try <code>AT+PWRON</code> → <code>AT+ENGINEON</code> → <code>AT+RDI=0123</code>.
        </p>

        <button type="button" id="clearConsoleButton" class="autodiag-console-clear">Clear Console</button>
    </section>

    <section class="autodiag-panel">
        <h2>Vehicle Dashboard</h2>
        <div class="autodiag-dashboard">

            <div class="autodiag-control">
                <label for="speed">
                    Speed
                    <span class="autodiag-value"><span id="speedValue">0</span> km/h</span>
                </label>
                <input type="range" id="speed" min="0" max="200" value="0" step="1">
            </div>

            <div class="autodiag-control">
                <label for="rpm">
                    RPM
                    <span class="autodiag-value"><span id="rpmValue">0</span></span>
                </label>
                <input type="range" id="rpm" min="0" max="8000" value="0" step="100">
            </div>

            <div class="autodiag-control">
                <label for="brake">
                    Brake
                    <span class="autodiag-value"><span id="brakeValue">0</span>%</span>
                </label>
                <input type="range" id="brake" min="0" max="100" value="0" step="1">
            </div>

            <div class="autodiag-control">
                <label for="gas">
                    Gas
                    <span class="autodiag-value"><span id="gasValue">0</span>%</span>
                </label>
                <input type="range" id="gas" min="0" max="100" value="0" step="1">
            </div>

        </div>
    </section>

    <section class="autodiag-panel">
        <h2>Command Reference</h2>
        <div class="autodiag-reference">

            <div class="autodiag-ref-card">
                <h3>AT+PWRON</h3>
                <p>Powers on the ECU.</p>
                <p><code>0x10 01</code> → <code>0x50 01</code></p>
            </div>

            <div class="autodiag-ref-card">
                <h3>AT+ENGINEON</h3>
                <p>Starts the engine (requires ECU on).</p>
                <p><code>0x10 03</code> → <code>0x50 03</code></p>
            </div>

            <div class="autodiag-ref-card">
                <h3>AT+RDI=0123</h3>
                <p>Read speed and RPM.</p>
                <p><code>0x22 0123</code> → <code>0x62 0123 [Speed] [RPM]</code></p>
            </div>

            <div class="autodiag-ref-card">
                <h3>AT+RDI=0456</h3>
                <p>Read brake and gas pedal.</p>
                <p><code>0x22 0456</code> → <code>0x62 0456 [Brake] [Gas]</code></p>
            </div>

            <div class="autodiag-ref-card">
                <h3>AT+RDI=0789</h3>
                <p>Read ignition and engine state.</p>
                <p><code>0x22 0789</code> → <code>0x62 0789 [Ign] [Eng]</code></p>
            </div>

            <div class="autodiag-ref-card">
                <h3>Error responses</h3>
                <p>ECU unavailable: <code>0x7F 22 11</code></p>
                <p>Engine unavailable: <code>0x7F 22 33</code></p>
            </div>

        </div>
    </section>

</div>

<script>
(function () {
    'use strict';

    let ecuPoweredOn = false;
    let engineOn = false;

    const commandInput = document.getElementById('commandInput');
    const sendCommandButton = document.getElementById('sendCommandButton');
    const clearConsoleButton = document.getElementById('clearConsoleButton');
    const consoleLog = document.getElementById('consoleLog');
    const ignitionStatus = document.getElementById('ignitionStatus');
    const engineStatus = document.getElementById('engineStatus');

    function logMessage(message) {
        const line = document.createElement('div');
        line.className = 'autodiag-console-line';
        line.textContent = message;
        consoleLog.appendChild(line);
        consoleLog.scrollTop = consoleLog.scrollHeight;
    }

    function clearConsole() {
        consoleLog.replaceChildren();
    }

    function updateValue(id) {
        const input = document.getElementById(id);
        const output = document.getElementById(id + 'Value');
        if (!input || !output) {
            return;
        }
        output.textContent = input.value;
    }

    function updateAllValues() {
        updateValue('speed');
        updateValue('rpm');
        updateValue('brake');
        updateValue('gas');
    }

    function updateStatusDisplay() {
        ignitionStatus.textContent = 'Ignition: ' + (ecuPoweredOn ? 'ON' : 'OFF');
        ignitionStatus.classList.toggle('on', ecuPoweredOn);
        ignitionStatus.classList.toggle('off', !ecuPoweredOn);

        engineStatus.textContent = 'Engine: ' + (engineOn ? 'ON' : 'OFF');
        engineStatus.classList.toggle('on', engineOn);
        engineStatus.classList.toggle('off', !engineOn);
    }

    function byteHex(value) {
        value = Number(value);
        if (!Number.isFinite(value)) {
            value = 0;
        }
        value = Math.max(0, Math.min(255, Math.round(value)));
        return value.toString(16).padStart(2, '0').toUpperCase();
    }

    function rpmHex() {
        let value = Number(document.getElementById('rpm').value);
        if (!Number.isFinite(value)) {
            value = 0;
        }
        value = Math.max(0, Math.min(8000, Math.round(value)));
        return value.toString(16).padStart(4, '0').toUpperCase();
    }

    function sendCommand() {
        const rawInput = commandInput.value.trim();
        if (!rawInput) {
            return;
        }
        const command = rawInput.toUpperCase().replace(/\s+/g, '');
        logMessage('> ' + command);
        const response = processATCommand(command);
        logMessage(response);
        commandInput.value = '';
        commandInput.focus();
    }

    function processATCommand(command) {
        if (command === 'AT+PWRON') {
            ecuPoweredOn = true;
            updateStatusDisplay();
            return 'CAN [0x10 01] → Response: 0x50 01';
        }

        if (command === 'AT+ENGINEON') {
            if (!ecuPoweredOn) {
                return 'ERROR: ECU must be powered on first! Response: 0x7F 10 11';
            }
            engineOn = true;
            updateStatusDisplay();
            return 'CAN [0x10 03] → Response: 0x50 03';
        }

        if (command.startsWith('AT+RDI=')) {
            if (!ecuPoweredOn) {
                return 'ERROR: ECU is OFF! Response: 0x7F 22 11';
            }
            if (!engineOn) {
                return 'ERROR: Engine is OFF! Response: 0x7F 22 33';
            }

            const identifier = command.substring(7).trim();

            switch (identifier) {
                case '0123': {
                    const speed = document.getElementById('speed').value;
                    const rpm = rpmHex();
                    return 'CAN [0x22 0123] → Response: 0x62 0123 ' + byteHex(speed) + ' ' + rpm;
                }
                case '0456': {
                    const brake = document.getElementById('brake').value;
                    const gas = document.getElementById('gas').value;
                    return 'CAN [0x22 0456] → Response: 0x62 0456 ' + byteHex(brake) + ' ' + byteHex(gas);
                }
                case '0789':
                    return 'CAN [0x22 0789] → Response: 0x62 0789 ' + (ecuPoweredOn ? '01' : '00') + ' ' + (engineOn ? '01' : '00');
                default:
                    return 'ERROR: Unknown RDI Identifier! Response: 0x7F 22 12';
            }
        }

        return 'ERROR: Unknown command!';
    }

    sendCommandButton.addEventListener('click', sendCommand);
    clearConsoleButton.addEventListener('click', clearConsole);

    commandInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            sendCommand();
        }
    });

    ['speed', 'rpm', 'brake', 'gas'].forEach(function (id) {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', function () {
                updateValue(id);
            });
        }
    });

    updateAllValues();
    updateStatusDisplay();
    logMessage('CfCbazar CAN Diagnostic Emulator ready.');
    logMessage('Start with: AT+PWRON');
})();
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/autodiag",
    "Tool Source Code"
);
include_footer();
close_database();
?>
