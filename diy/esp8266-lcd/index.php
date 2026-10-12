<?php
// /diy/esp8266-lcd/index.php

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;

    if (function_exists('trackVisit')) {
        // --- Page metadata -----------------------------------------------------------
	$url        = "diy/esp8266-lcd/index.php";
	$parent_url = "diy/esp8266-lcd";
	$title      = "ESP8266 I2C LCD Display";
	$template   = "Generic";   // optional: e.g. "tool", "article", "game"
	$meta_title = "ESP8266 I2C LCD Display";
	$meta_desc  = "This simple DIY project demonstrates how to connect an ESP8266 microcontroller to a 16×2 I2C LCD display and print a message. It uses the Wire and LiquidCrystal_I2C libraries to initialize and control the LCD.";

	trackVisit(
	    slug:      $url,
	    parentUrl: $parent_url,
	    title:     $title,
	    template:  $template,
	    metaTitle: $meta_title,
	    metaDesc:  $meta_desc
	);
    }
}

// --- Core logic ---
enforce_https();
checkSystemFlags($conn);
$return_url = '/diy/esp8266-lcd';

// --- Render layout ---
$title = "ESP8266 I2C LCD — CfCbazar DIY";
include_header();
include_menu();
showAdvertPopup();
render_top_userbar();
?>

<link rel="stylesheet" href="/css/styles.css">

<style>
    /* Correct spacing so content sits BELOW menu + top bar */
    body { padding-top: 320px !important; }

    /* Mobile-safe wiring table */
    .compact-table {
        min-width: 100% !important;
        width: 100% !important;
        white-space: normal !important;
        font-size: 0.95rem;
    }
    .compact-table td {
        white-space: normal !important;
        padding: 8px;
    }

    /* Code wrapper + copy button */
    .code-wrapper {
        position: relative;
        margin-bottom: 20px;
    }
    .copy-btn {
        position: absolute;
        top: -32px;
        right: 0;
        background: #28a745;
        color: #fff;
        border: none;
        padding: 6px 12px;
        font-size: 0.8rem;
        border-radius: 6px;
        cursor: pointer;
        z-index: 10;
    }
    .copy-btn:hover {
        background: #1e7e34;
    }
</style>

<div class="container">

    <div class="header">
        <h1>ESP8266 I2C LCD Display</h1>
    </div>

    <div class="card">
        <img src="/diy/esp8266-lcd/img.webp" alt="ESP8266 LCD Project" style="width:100%; border-radius:12px; margin-bottom:15px;">
        <p>
            This simple DIY project demonstrates how to connect an ESP8266 microcontroller to a 16×2 I2C LCD display and print a message.
            It uses the <code>Wire</code> and <code>LiquidCrystal_I2C</code> libraries to initialize and control the LCD.
        </p>
    </div>

    <div class="card">
        <h2>Components Required</h2>
        <ul>
            <li>ESP8266 (NodeMCU or ESP‑01)</li>
            <li>16×2 I2C LCD display</li>
            <li>Jumper wires</li>
            <li>Breadboard (optional)</li>
        </ul>
    </div>

    <div class="card">
        <h2>Wiring (ESP8266 → LCD)</h2>
        <table class="compact-table">
            <tr><td><strong>LCD SDA</strong></td><td>GPIO2 (D4)</td></tr>
            <tr><td><strong>LCD SCL</strong></td><td>GPIO0 (D3)</td></tr>
            <tr><td><strong>VCC</strong></td><td>3.3V – 5V (LCD supports both)</td></tr>
            <tr><td><strong>GND</strong></td><td>GND</td></tr>
        </table>
    </div>

    <div class="card">
        <h2>Code Example</h2>
        <p>Hello World on LCD</p>

        <div class="code-wrapper">
            <button class="copy-btn" onclick="copyCode()">Copy</button>

            <pre class="code-block" id="codeBlock">
#include &lt;Wire.h&gt;
#include &lt;LiquidCrystal_I2C.h&gt;

LiquidCrystal_I2C lcd(0x27, 16, 2); // LCD address and size

void setup() {
  Wire.begin(2, 0);        // SDA = GPIO2, SCL = GPIO0
  lcd.init();              // Initialize LCD
  lcd.backlight();         // Turn on backlight
  lcd.print(" Hello World! ");
}

void loop() {
  // Nothing here
}
            </pre>
        </div>

        <script>
            function copyCode() {
                const code = document.getElementById("codeBlock").innerText;
                navigator.clipboard.writeText(code).then(() => {
                    const btn = document.querySelector(".copy-btn");
                    btn.textContent = "Copied!";
                    setTimeout(() => btn.textContent = "Copy", 1500);
                });
            }
        </script>

        <p class="note">
            The LCD address <code>0x27</code> is common, but some displays use <code>0x3F</code>.
        </p>
    </div>

    <div class="card">
        <h2>Output</h2>
        <pre class="code-block">
 Hello World!
        </pre>
    </div>

</div>

<?php include_footer(); ?>
