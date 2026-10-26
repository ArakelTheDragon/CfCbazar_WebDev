<?php
// /diy/esp8266-repeater/index.php
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
$url        = "/diy/esp8266-repeater/index.php";
$parent_url = "/diy";
$title      = "ESP8266 WiFi Repeater — CfCbazar DIY";
$template   = "Generic";
$meta_title = "ESP8266 WiFi Repeater — CfCbazar DIY";
$meta_desc  = "Turn any ESP8266 NodeMCU into a WiFi repeater using the ESP Web Flash Tool or Arduino IDE. Step-by-step guide with firmware flashing, mesh setup, and NAPT repeater mode.";

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

        <h1>ESP8266 WiFi Repeater (Mesh + NAPT)</h1>

        <div class="card">
            <img src="/diy/esp8266-repeater/images/img.png" alt="ESP8266 WiFi Repeater" style="width:100%; border-radius:12px;">
        </div>

        <div class="card">
            <p class="muted">
                This guide shows how to turn an ESP8266 NodeMCU into a <strong>WiFi repeater / extender</strong> using two methods:
            </p>

            <ul>
                <li><strong>Method 1:</strong> Flash the open‑source <code>esp_wifi_repeater</code> firmware (Mesh mode available)</li>
                <li><strong>Method 2:</strong> Use the Arduino IDE example <code>RangeExtender-NAPT</code> (no mesh mode)</li>
            </ul>

            <p class="muted">
                Both methods require connecting to the repeater WiFi and visiting <strong>192.168.4.1</strong> to configure it.
            </p>
        </div>

        <div class="card">
            <h2>Step 1 — Download the Repeater Firmware</h2>
            <p class="muted">Open-source project</p>

            <button class="copy-btn" onclick="copyCode('github-url')">Copy</button>
            <pre id="github-url" class="code-block">https://github.com/martin-ger/esp_wifi_repeater/tree/master</pre>

            <p>Extract the ZIP and open the <strong>firmware</strong> folder. You will see:</p>

            <ul>
                <li><code>0x00000.bin</code></li>
                <li><code>0x02000.bin</code></li>
                <li><code>0x82000.bin</code></li>
            </ul>
        </div>

        <div class="card">
            <h2>Step 2 — Open the ESP Web Flash Tool</h2>
            <p class="muted">Works only in Chrome / Edge</p>

            <button class="copy-btn" onclick="copyCode('flash-url')">Copy</button>
            <pre id="flash-url" class="code-block">https://esp.huhn.me/</pre>

            <p>Connect your ESP8266 via USB and click <strong>Connect</strong>.</p>
        </div>

        <div class="card">
            <h2>Step 3 — Flash the Firmware</h2>
            <p class="muted">Upload all 3 files</p>

            <button class="copy-btn" onclick="copyCode('flash-map')">Copy</button>
            <pre id="flash-map" class="code-block">
0x00000.bin → 0x00000
0x02000.bin → 0x02000
0x82000.bin → 0x82000
            </pre>

            <p>Click <strong>Flash</strong> and wait until it completes.</p>
        </div>

        <div class="card">
            <h2>Step 4 — Reboot &amp; Connect</h2>
            <p class="muted">ESP8266 starts in AP mode</p>

            <p>It will create a WiFi network:</p>

            <pre class="code-block">MyAP</pre>

            <p>Then open:</p>

            <button class="copy-btn" onclick="copyCode('ip-url')">Copy</button>
            <pre id="ip-url" class="code-block">http://192.168.4.1</pre>
        </div>

        <div class="card">
            <h2>Step 5 — Configure the Repeater</h2>
            <p class="muted">Mesh mode available only in Method 1</p>

            <ul>
                <li>Enter the SSID you want to repeat</li>
                <li>Enter the WiFi password</li>
                <li><strong>Mesh Mode is available only in the esp_wifi_repeater firmware</strong></li>
                <li>Click <strong>Save</strong></li>
            </ul>

            <p>Wait about <strong>2 minutes</strong> for the mesh to initialize (Method 1 only).</p>
        </div>

        <div class="card">
            <h2>Step 6 — Check Mesh Status</h2>
            <p class="muted">Method 1 only</p>

            <pre class="code-block">
Mesh connected!
Repeating SSID: YourWiFi
Signal: -65 dBm
            </pre>
        </div>

        <div class="card">
            <h2>Alternative Method — Arduino IDE Repeater</h2>
            <p class="muted">No firmware flashing required</p>

            <ol>
                <li>Open the <strong>Arduino IDE</strong></li>
                <li>Go to <strong>File → Examples → ESP8266WiFi → RangeExtender-NAPT</strong></li>
                <li>Edit the SSID and password in the sketch</li>
                <li>Upload to your NodeMCU</li>
            </ol>

            <button class="copy-btn" onclick="copyCode('ip-url2')">Copy</button>
            <pre id="ip-url2" class="code-block">http://192.168.4.1</pre>

            <p>This method does <strong>not</strong> include mesh mode — it uses NAPT to extend your WiFi network.</p>
        </div>

    </div>
</main>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/esp8266-repeater",
    "ESP8266 WiFi Repeater Source Code"
);
include_footer();
close_database();
?>
