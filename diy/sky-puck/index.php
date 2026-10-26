<?php
// /diy/sky-stream-datasheet/index.php
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
$url        = "/diy/sky-stream-datasheet/index.php";
$parent_url = "/diy/sky-stream-datasheet";
$title      = "Sky Stream Puck & TV - Technical Datasheet Simulator";
$template   = "Generic";
$meta_title = "Sky Stream Puck & TV - Technical Datasheet Simulator";
$meta_desc  = "Interactive Sky Stream Puck and Smart TV interconnect diagram with pinout, cable toggles, and troubleshooting guide.";

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
.sky-datasheet {
    --ic-bg: #1c1c1c;
    --ic-text: #ffffff;
    --pin-lead: #c0c0c0;
    --pin-pad: #d4af37;
    --sky-blue: #0288d1;
    --power-red: #d32f2f;
    --grid-color: #e8e8e8;
    font-family: 'Courier New', Courier, monospace;
    color: #111;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.sky-datasheet h1 {
    margin: 0 0 5px 0;
    font-family: Arial, sans-serif;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.sky-datasheet .subtitle {
    font-size: 0.85rem;
    color: #555;
    margin-bottom: 25px;
}

.sky-datasheet .diagram-container {
    position: relative;
    width: 1200px;
    max-width: 100%;
    height: 560px;
    background: #fff;
    background-image:
        linear-gradient(var(--grid-color) 1px, transparent 1px),
        linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
    background-size: 20px 20px;
    border: 2px solid #000;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    padding: 20px;
    box-sizing: border-box;
    overflow: hidden;
}

.sky-datasheet .chip-box {
    position: absolute;
    background: linear-gradient(145deg, #222, #111);
    color: var(--ic-text);
    border-radius: 4px;
    box-shadow: 3px 5px 12px rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.1rem;
    letter-spacing: 2px;
    z-index: 2;
}

.sky-datasheet .puck-chip {
    top: 140px;
    left: 360px;
    width: 150px;
    height: 250px;
    border: 1px solid #444;
}

.sky-datasheet .tv-chip {
    top: 70px;
    left: 880px;
    width: 200px;
    height: 400px;
    border: 1px solid #444;
}

.sky-datasheet .notch {
    width: 20px;
    height: 10px;
    background: #fff;
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 10px;
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
}

.sky-datasheet .pin1-dot {
    position: absolute;
    top: 15px;
    left: 15px;
    width: 6px;
    height: 6px;
    background: #666;
    border-radius: 50%;
}

.sky-datasheet .chip-label {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    font-family: Arial, sans-serif;
    text-shadow: 0 1px 2px #000;
}

.sky-datasheet .pin-wrapper {
    position: absolute;
    display: flex;
    align-items: center;
    z-index: 3;
}

.sky-datasheet .pin-lead {
    width: 18px;
    height: 8px;
    background: linear-gradient(to bottom, #e6e6e6, #999, #888);
    border: 1px solid #555;
    box-shadow: 0 2px 3px rgba(0,0,0,0.2);
    position: relative;
}

.sky-datasheet .pin-num {
    position: absolute;
    font-size: 9px;
    font-weight: bold;
    color: #222;
    background: var(--pin-pad);
    border: 1px solid #997a15;
    border-radius: 2px;
    width: 14px;
    height: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sky-datasheet .signal-label {
    position: absolute;
    font-size: 0.8rem;
    font-weight: bold;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
    z-index: 4;
    background: rgba(255, 255, 255, 0.92);
    padding: 2px 6px;
    border-radius: 3px;
}

.sky-datasheet .opt-tag {
    color: #666;
    font-size: 0.68rem;
    font-style: italic;
    font-weight: normal;
}

.sky-datasheet svg.wire-canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
    pointer-events: none;
}

.sky-datasheet .plug-box {
    position: absolute;
    top: 180px;
    left: 20px;
    width: 110px;
    height: 70px;
    border: 2px solid #333;
    background: #fff;
    box-shadow: 2px 2px 6px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-size: 0.75rem;
    font-weight: bold;
    z-index: 2;
}

.sky-datasheet .controls {
    margin-top: 20px;
    padding: 15px 25px;
    background: #fff;
    border: 2px solid #222;
    width: 1200px;
    max-width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-sizing: border-box;
    flex-wrap: wrap;
    gap: 10px;
}

.sky-datasheet .status-badge {
    padding: 5px 10px;
    border-radius: 3px;
    color: #fff;
    font-weight: bold;
    font-size: 0.85rem;
}

.sky-datasheet .status-on { background-color: #2e7d32; }
.sky-datasheet .status-off { background-color: #c62828; }

.sky-datasheet button {
    padding: 8px 14px;
    cursor: pointer;
    font-family: inherit;
    font-weight: bold;
    background: #222;
    color: #fff;
    border: none;
    border-radius: 3px;
}

.sky-datasheet button:hover { background: #444; }

.sky-datasheet .troubleshooting-panel {
    margin-top: 20px;
    width: 1200px;
    max-width: 100%;
    background: #fff;
    border: 2px solid #222;
    padding: 20px 25px;
    box-sizing: border-box;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
}

.sky-datasheet .troubleshooting-panel h2 {
    margin-top: 0;
    font-family: Arial, sans-serif;
    font-size: 1.1rem;
    color: #d32f2f;
    border-bottom: 2px solid #222;
    padding-bottom: 8px;
    letter-spacing: 1px;
}

.sky-datasheet .error-item { margin-bottom: 18px; }
.sky-datasheet .error-item:last-child { margin-bottom: 0; }
.sky-datasheet .error-title {
    font-weight: bold;
    font-size: 0.95rem;
    color: #111;
    margin-bottom: 6px;
}
.sky-datasheet .error-fix {
    font-size: 0.85rem;
    color: #333;
    line-height: 1.5;
    background: #f9f9f9;
    padding: 10px 14px;
    border-left: 4px solid var(--sky-blue);
}
</style>

<main class="content">
    <div class="sky-datasheet">

        <h1>SKY STREAM PUCK &amp; SMART TV</h1>
        <div class="subtitle">IC Datasheet Pinout &amp; System Interconnect Diagram</div>

        <div class="diagram-container">
            <svg class="wire-canvas">
                <path id="wire-power" d="M 130 215 L 210 215 L 210 200 L 342 200"
                      fill="none" stroke="#d32f2f" stroke-width="3" stroke-dasharray="6,3" />

                <path id="wire-hdmi" d="M 528 200 L 700 200 L 700 120 L 862 120"
                      fill="none" stroke="#0288d1" stroke-width="4" />
            </svg>

            <div class="plug-box">
                <span>230V AC</span>
                <span style="color:#d32f2f;">UK 3-PIN PLUG</span>
            </div>

            <div class="puck-chip chip-box">
                <div class="notch"></div>
                <div class="pin1-dot"></div>
                <div class="chip-label">SKY PUCK</div>
            </div>

            <div class="pin-wrapper" style="top: 196px; left: 342px;">
                <div class="pin-num" style="left: -16px;">1</div>
                <div class="pin-lead"></div>
            </div>
            <div class="signal-label" style="top: 172px; left: 200px;">
                Power <span style="color:#d32f2f;">&#x27F6;</span>
            </div>

            <div class="pin-wrapper" style="top: 266px; left: 342px;">
                <div class="pin-num" style="left: -16px;">2</div>
                <div class="pin-lead"></div>
            </div>
            <div class="signal-label" style="top: 262px; left: 160px;">
                Ethernet <span class="opt-tag">(OPTIONAL)</span> <span style="color:#333;">&#x27F7;</span>
            </div>

            <div class="pin-wrapper" style="top: 266px; left: 510px;">
                <div class="pin-lead"></div>
                <div class="pin-num" style="right: -16px;">3</div>
            </div>
            <div class="signal-label" style="top: 262px; left: 545px;">
                <span style="color:#888;">&#x27F6;</span> RF_AERIAL <span class="opt-tag">(UNUSED)</span>
            </div>

            <div class="pin-wrapper" style="top: 196px; left: 510px;">
                <div class="pin-lead"></div>
                <div class="pin-num" style="right: -16px;">4</div>
            </div>
            <div class="signal-label" style="top: 172px; left: 545px;">
                <span style="color:#0288d1;">&#x27F6;</span> HDMI
            </div>

            <div class="tv-chip chip-box">
                <div class="notch"></div>
                <div class="pin1-dot"></div>
                <div class="chip-label">SMART TV PANEL</div>
            </div>

            <div class="pin-wrapper" style="top: 116px; left: 862px;">
                <div class="pin-num" style="left: -16px;">1</div>
                <div class="pin-lead"></div>
            </div>
            <div class="signal-label" style="top: 112px; left: 765px;">
                HDMI1 <span style="color:#0288d1;">&#x27F6;</span>
            </div>

            <div class="pin-wrapper" style="top: 176px; left: 862px;">
                <div class="pin-num" style="left: -16px;">2</div>
                <div class="pin-lead"></div>
            </div>
            <div class="signal-label" style="top: 172px; left: 720px;">
                HDMI2_IN <span class="opt-tag">(AUX)</span> <span style="color:#333;">&#x27F6;</span>
            </div>

            <div class="pin-wrapper" style="top: 236px; left: 862px;">
                <div class="pin-num" style="left: -16px;">3</div>
                <div class="pin-lead"></div>
            </div>
            <div class="signal-label" style="top: 232px; left: 720px;">
                HDMI3_IN <span class="opt-tag">(AUX)</span> <span style="color:#333;">&#x27F6;</span>
            </div>

            <div class="pin-wrapper" style="top: 296px; left: 862px;">
                <div class="pin-num" style="left: -16px;">4</div>
                <div class="pin-lead"></div>
            </div>
            <div class="signal-label" style="top: 292px; left: 690px;">
                OPTICAL_OUT <span class="opt-tag">(SOUNDBAR)</span> <span style="color:#333;">&#x27F6;</span>
            </div>

            <div class="pin-wrapper" style="top: 356px; left: 862px;">
                <div class="pin-num" style="left: -16px;">5</div>
                <div class="pin-lead"></div>
            </div>
            <div class="signal-label" style="top: 352px; left: 710px;">
                RF_AERIAL <span class="opt-tag">(OPTIONAL)</span> <span style="color:#333;">&#x27F6;</span>
            </div>

            <div class="pin-wrapper" style="top: 416px; left: 862px;">
                <div class="pin-num" style="left: -16px;">6</div>
                <div class="pin-lead"></div>
            </div>
            <div class="signal-label" style="top: 412px; left: 760px;">
                AC_MAINS <span style="color:#d32f2f;">&#x27F6;</span>
            </div>
        </div>

        <div class="controls">
            <div>
                <strong>3-Pin Power Cable:</strong>
                <span id="plug-status" class="status-badge status-on">CONNECTED</span>
            </div>
            <div>
                <strong>HDMI Cable (Puck &#x27F6; TV HDMI 1):</strong>
                <span id="hdmi-status" class="status-badge status-on">CONNECTED</span>
            </div>
            <div>
                <button onclick="toggleCable('power')">Toggle Power Cable</button>
                <button onclick="toggleCable('hdmi')">Toggle HDMI Cable</button>
            </div>
        </div>

        <div class="troubleshooting-panel">
            <h2>ERRORS &amp; TROUBLESHOOTING GUIDE</h2>

            <div class="error-item">
                <div class="error-title">1. No signal on the TV</div>
                <div class="error-fix">
                    Switch the TV to <strong>HDMI1</strong> where Sky is connected by using the source/input button on the TV remote (not the Sky remote). Press the power button on the puck. Remove the paper from the Sky remote back. if it cuts open the remote and remove the batteries.
                </div>
            </div>

            <div class="error-item">
                <div class="error-title">2. The puck is stuck on software update</div>
                <div class="error-fix">
                    Connect an ethernet cable to the puck, unplug the power and plug it back in, wait 1 hour, switch on the puck and do the setup, go to <strong>Settings &#x2192; Network &#x2192; Status &#x2192; Reset</strong>, connect to Wi-Fi, then remove the ethernet cable.<br><br>
                    <em>If the router is far from the TV:</em> Unplug the HDMI cable from the puck, take the puck to the router, connect it with an ethernet cable, press the power button on the puck, plug in the power cable to the puck, hold the power button on the puck for 30 seconds, and leave it there with the power and ethernet plugged in for 1 hour.
                </div>
            </div>

            <div class="error-item">
                <div class="error-title">3. The puck gets stuck on enter your PIN or enter your new PIN</div>
                <div class="error-fix">
                    Take out the power cable, put it back in, and leave the puck like this for 15 minutes.
                </div>
            </div>
        </div>

    </div>
</main>

<script>
let powerConnected = true;
let hdmiConnected  = true;

function toggleCable(cable) {
    if (cable === 'power') {
        powerConnected = !powerConnected;
        const wire   = document.getElementById('wire-power');
        const status = document.getElementById('plug-status');

        wire.style.display = powerConnected ? 'block' : 'none';
        status.textContent = powerConnected ? 'CONNECTED' : 'DISCONNECTED';
        status.className = 'status-badge ' + (powerConnected ? 'status-on' : 'status-off');
    } else if (cable === 'hdmi') {
        hdmiConnected = !hdmiConnected;
        const wire   = document.getElementById('wire-hdmi');
        const status = document.getElementById('hdmi-status');

        wire.style.display = hdmiConnected ? 'block' : 'none';
        status.textContent = hdmiConnected ? 'CONNECTED' : 'DISCONNECTED';
        status.className = 'status-badge ' + (hdmiConnected ? 'status-on' : 'status-off');
    }
}
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/sky-stream-datasheet",
    "Sky Stream Datasheet Source Code"
);
include_footer();
close_database();
?>
