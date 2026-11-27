<?php
// /diy/generic-template-kicad-lm2596-5V-circuit-and-pcb/index.php
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
//require_once __DIR__ . '/../../includes/settings_local.php'; // deleted
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN         = false;   // 1
// $NEED_MODERATOR     = false;   // 2
// $NEED_CONTRIBUTOR   = false;   // 3
// $NEED_VIP           = false;   // 4
// $NEED_STANDARD_USER = false;   // 5
// $IS_FRONTEND        = true;
// $IS_BACKEND         = false;

// ------------------------

// --- Page metadata -----------------------------------------------------------
$url        = "generic-template-kicad-lm2596-5V-circuit-and-pcb";
$parent_url = "/diy/generic-template-kicad-lm2596-5V-circuit-and-pcb";
$title      = "KiCad LM2596 5V Power Supply Based on the Adjustable IC";
$template   = "Generic";
$meta_title = "Free Circuits";
$meta_desc  = "KiCad project for an LM2596S-ADJ based 5V power supply. High efficiency, 3A output, industrial grade switching regulator.";

// Absolute URL for canonical / share links
$full_url   = build_full_url($url);

setReturnUrl($url);

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}
// Ensure Session is active before download processing to prevent header warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Absolute URL for canonical / share links
$full_url   = build_full_url($parent_url);

// --- Intercept Downloads -----------------------------------------------------
$downloadHandlerPath = __DIR__ . '/../../includes/download.php';
if (file_exists($downloadHandlerPath)) {
    require_once $downloadHandlerPath;
    
    // Check if a specific file is requested via the '?file=' parameter
    if (isset($_GET['file'])) {
        require_download($_GET['file']);
        exit;
    }
}

// --- Page Tracking & Setup ---------------------------------------------------
trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

setReturnUrl($parent_url);

// <head>, CSS/JS from /assets/, nav
render_header($title, $meta_desc);

// --- page body (high-level functions only) ---
echo '<main class="content">';
echo '<div class="container">';

echo '<h1>KiCad LM2596 5V Power Supply Based on the Adjustable IC</h1>';

render_project_stats([
    'status'     => 'Documented',
    'build_time' => '20-30 mins',
    'learn_time' => '30 mins',
    'experience' => '1 year',
]);

// Derive the zip web path from the slug ($url)
$zipWebPath  = '/assets/files/' . $url . '.zip';
$downloadUrl = '?file=' . urlencode($zipWebPath);

echo <<<HTML
<style>
.sys-diagram-wrap {
    overflow-x: auto;
    padding: 18px 12px;
}
.sys-diagram {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    min-width: 660px;
    margin: 0 auto;
    font-family: inherit;
}
.sys-col-left {
    display: flex;
    flex-direction: column;
    gap: 24px;
}
.sys-node {
    border: 2px solid #1e293b;
    border-radius: 8px;
    padding: 14px 18px;
    text-align: center;
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}
.sys-node-5v {
    border-color: #dc2626;
    background: #fef2f2;
    min-width: 175px;
}
.sys-node-3v3 {
    border-color: #d97706;
    background: #fffbeb;
    min-width: 175px;
}
.sys-node-mcu {
    border-color: #2563eb;
    background: #eff6ff;
    min-width: 175px;
    padding: 36px 22px;
}
.sys-node-oled {
    border-color: #16a34a;
    background: #f0fdf4;
    min-width: 175px;
    padding: 22px 18px;
}
.sys-node-title {
    font-weight: 700;
    font-size: 1.05rem;
    line-height: 1.3;
}
.sys-node-sub {
    font-size: 0.85rem;
    color: #475569;
    margin-top: 4px;
}
.sys-links-left {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 48px;
}
.sys-arrow {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 95px;
    height: 28px;
}
.sys-arrow-line {
    width: 100%;
    height: 3px;
    background: #dc2626;
    position: relative;
}
.sys-arrow-line::after {
    content: "";
    position: absolute;
    right: -1px;
    top: 50%;
    transform: translateY(-50%);
    border-top: 6px solid transparent;
    border-bottom: 6px solid transparent;
    border-left: 10px solid #dc2626;
}
.sys-arrow-i2c {
    width: 120px;
}
.sys-arrow-i2c .sys-arrow-line {
    background: #2563eb;
}
.sys-arrow-i2c .sys-arrow-line::after {
    border-left-color: #2563eb;
}
.sys-arrow-label {
    position: absolute;
    top: -14px;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}
.sys-label-pwr {
    color: #dc2626;
}
.sys-label-3v3 {
    color: #b45309;
}
.sys-label-i2c {
    color: #1d4ed8;
}
</style>

<div class="info-card">
    <p><strong>Author:</strong> CfCbazar Group</p>
    <p>A switching power supply based on the LM2596S-ADJ. The circuit has higher efficiency than the classic LM317 and can supply up to <strong>3A</strong>. This is an industrial power supply design that can work reliably in noisy environments.</p>
    <p><strong>Project Files:</strong> <a href="{$downloadUrl}">Download KiCad Files from our Library</a></p>
</div>

<div class="warning">
    <strong>⚠️ CRITICAL WARNING:</strong> There is <strong>NO current limitation</strong> on this circuit. LEDs connected without appropriate current-limiting resistors <strong>will burn out immediately</strong>. Always verify your load and use proper fusing.
</div>

<h2>1. Overview &amp; Specifications</h2>
<p>The LM2596 is a step-down (buck) switching regulator capable of driving a 3A load with excellent line and load regulation. These devices are available in fixed-output and adjustable versions. This specific project utilizes the <strong>LM2596S-ADJ</strong> to provide a stable 5V output.</p>
<ul>
    <li><strong>Input Voltage:</strong> 4V to 37V (High voltage version up to 57V)</li>
    <li><strong>Output Voltage:</strong> 5V (Adjustable via feedback divider)</li>
    <li><strong>Max Output Current:</strong> 3A</li>
    <li><strong>Switching Frequency:</strong> 150kHz fixed frequency internal oscillator</li>
    <li><strong>Voltage Drop:</strong> 1.25V</li>
</ul>

<h2>2. System Block Diagram</h2>
<p>The block diagram below shows how the <strong>5V (LM2596S-ADJ)</strong> and <strong>3.3V</strong> power rails supply the <strong>ESP8266</strong>, which communicates with the <strong>0.91" OLED display</strong> over <strong>I2C (SDA / SCL)</strong>.</p>

<div class="info-card sys-diagram-wrap">
    <div class="sys-diagram" role="img" aria-label="System Block Diagram: 5V (LM2596S-ADJ) and 3.3V connected to ESP8266, ESP8266 connected to 0.91 inch OLED Display via I2C (SDA / SCL)">
        <div class="sys-col-left">
            <div class="sys-node sys-node-5v">
                <div class="sys-node-title">5V</div>
                <div class="sys-node-sub">(LM2596S-ADJ)</div>
            </div>
            <div class="sys-node sys-node-3v3">
                <div class="sys-node-title">3.3V</div>
                <div class="sys-node-sub">Logic Supply</div>
            </div>
        </div>

        <div class="sys-links-left">
            <div class="sys-arrow">
                <span class="sys-arrow-label sys-label-pwr">5V VIN</span>
                <div class="sys-arrow-line"></div>
            </div>
            <div class="sys-arrow">
                <span class="sys-arrow-label sys-label-3v3">3.3V VCC</span>
                <div class="sys-arrow-line"></div>
            </div>
        </div>

        <div class="sys-node sys-node-mcu">
            <div class="sys-node-title">ESP8266</div>
            <div class="sys-node-sub">Controller</div>
        </div>

        <div class="sys-arrow sys-arrow-i2c">
            <span class="sys-arrow-label sys-label-i2c">I2C (SDA / SCL)</span>
            <div class="sys-arrow-line"></div>
        </div>

        <div class="sys-node sys-node-oled">
            <div class="sys-node-title">0.91" OLED Display</div>
            <div class="sys-node-sub">128×32 I2C Screen</div>
        </div>
    </div>
</div>

<h2>3. Bill of Materials (BOM)</h2>
<div class="info-card">
    <ul>
        <li><strong>1x</strong> LM2596S-ADJ (U3)</li>
        <li><strong>2x</strong> 1N5824 or SK56 Schottky Diodes (D17, D18)</li>
        <li><strong>1x</strong> 33µH Inductor (L1)</li>
        <li><strong>2x</strong> 680µF, 470µF Electrolytic Capacitors (C2, C5)</li>
        <li><strong>1x</strong> 220µF Capacitor (C4)</li>
        <li><strong>2x</strong> 470nF / 100V Capacitors (C3, C6)</li>
        <li><strong>1x</strong> 3kΩ Resistor (R14) - Feedback Divider</li>
        <li><strong>1x</strong> 1kΩ Resistor (R15) - Feedback Divider</li>
        <li><strong>1x</strong> 2-Pin Power Connector (J5)</li>
    </ul>
</div>

<h2>4. Circuit Design &amp; Schematic Notes</h2>
<p>The design is captured in KiCad. The feedback network (R14 and R15) sets the output voltage using the internal 1.25V reference. The output voltage is calculated as: <code>Vout = 1.25V * (1 + R14/R15)</code>. With the values shown (3k and 1k), the output is configured for 5V.</p>
<p>Schottky diodes (D17, D18) are used for reverse polarity protection on the input and as the freewheeling diode in the buck converter, minimizing power loss.</p>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Component</th>
                <th>Value / Part</th>
                <th>Function</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>D17, D18</strong></td>
                <td>1N5824 or SK56</td>
                <td>Input protection &amp; freewheeling diode</td>
            </tr>
            <tr>
                <td><strong>L1</strong></td>
                <td>33 µH</td>
                <td>Main energy storage inductor</td>
            </tr>
            <tr>
                <td><strong>R14 / R15</strong></td>
                <td>3kΩ / 1kΩ</td>
                <td>Output voltage feedback divider</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>5. Utilization in Noisy Environments</h2>
<p>Because switching regulators operate at high frequencies (150kHz), the layout and filtering are critical. The input (C2, C3) and output (C4, C5, C6) filtering networks are designed to suppress voltage ripple. This makes the supply robust enough for industrial applications, motor drivers, and noisy digital logic environments.</p>

<h2>6. Typical Use Cases (ESP8266 + Power Supply + 0.91" OLED)</h2>
<div class="info-card">
    <ul>
        <li><strong>Compact Local Status &amp; Telemetry Display:</strong> Pair the <strong>ESP8266</strong> with the <strong>0.91" I2C OLED (128×32)</strong> to show Wi-Fi status, IP address, sensor readings, or local alerts directly at the device without needing a phone or serial monitor.</li>
        <li><strong>12V–24V / Off-Grid &amp; Workbench Power Nodes:</strong> Step down higher DC input rails (such as 12V–24V supplies or battery/solar rails within the LM2596S-ADJ input range) efficiently while providing a steady rail for 3.3V logic and peripherals.</li>
        <li><strong>Benchtop &amp; Enclosure Diagnostics:</strong> Use the OLED screen to display live status messages, counters, or fault states inside project enclosures or noisy workshop/equipment environments where USB serial isn't convenient.</li>
        <li><strong>DIY Smart &amp; Automation Prototypes:</strong> Build standalone Wi-Fi nodes for home/workshop monitoring or relay/controller front-ends where compact size and low-heat switching regulation matter.</li>
    </ul>
</div>
HTML;

echo '</div>';
echo '</main>';

// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/generic-template-kicad-lm2596-5V-circuit-and-pcb",
    "KiCad LM2596 5V Power Supply Source Files"
);
include_footer();
close_database();
?>
