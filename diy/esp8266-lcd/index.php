<?php
// /diy/esp8266-lcd/index.php
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
$url        = "/diy/esp8266-lcd/index.php";
$parent_url = "/diy/esp8266-lcd";
$title      = "ESP8266 I2C LCD Display";
$template   = "Generic";
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

render_header($title);
?>

<main class="content">
    <div class="container">

        <h1>ESP8266 I2C LCD Display</h1>

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
</main>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/esp8266-lcd",
    "ESP8266 I2C LCD Source Code"
);
include_footer();
close_database();
?>
