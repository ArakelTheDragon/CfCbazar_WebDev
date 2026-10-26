<?php
// /diy/esp8266-flyingfish-glcd/index.php
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
$url        = "/diy/esp8266-flyingfish-glcd/index.php";
$parent_url = "/diy/esp8266-flyingfish-glcd";
$title      = "ESP8266 + Flying Fish Sensor + 128×64 GLCD";
$template   = "Generic";
$meta_title = "ESP8266 + Flying Fish Sensor + 128×64 GLCD";
$meta_desc  = "This project shows how to read an analog Flying Fish sensor (A0) using an ESP8266 and display the values on a 128×64 GLCD using the U8g2 graphics library. The display updates every 500ms and shows both the sensor reading and lock status.";

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

        <h1>ESP8266 + Flying Fish Sensor + 128×64 GLCD</h1>

        <div class="card">
            <img src="images/img.png" alt="ESP8266 Flying Fish GLCD Project" style="width:100%; border-radius:12px;">
        </div>

        <div class="card">
            <p class="muted">
                This project shows how to read an analog Flying Fish sensor (A0) using an ESP8266 and display the values
                on a 128×64 GLCD using the <code>U8g2</code> graphics library.
                The display updates every 500ms and shows both the sensor reading and lock status.
            </p>
        </div>

        <div class="card">
            <h2>Components Required</h2>
            <p class="muted">Everything you need</p>

            <ul>
                <li>ESP8266 (NodeMCU or ESP‑01)</li>
                <li>Flying Fish analog sensor (A0)</li>
                <li>128×64 GLCD (ST7567 / U8g2 compatible)</li>
                <li>Jumper wires</li>
            </ul>
        </div>

        <div class="card">
            <h2>Wiring</h2>
            <p class="muted">ESP8266 → Sensor + GLCD</p>

            <div style="overflow-x:auto; width:100%;">
                <table style="width:100%; font-size:13px; min-width:300px;">
                    <tr><td><strong>Flying Fish OUT</strong></td><td>A0</td></tr>
                    <tr><td><strong>Flying Fish VCC</strong></td><td>3.3V</td></tr>
                    <tr><td><strong>Flying Fish GND</strong></td><td>GND</td></tr>

                    <tr><td><strong>GLCD SDA</strong></td><td>SDA D4</td></tr>
                    <tr><td><strong>GLCD SCL</strong></td><td>SCL D3</td></tr>
                    <tr><td><strong>GLCD VCC</strong></td><td>3.3V–5V</td></tr>
                    <tr><td><strong>GLCD GND</strong></td><td>GND</td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Code Example</h2>
            <p class="muted">ESP8266 + Flying Fish + GLCD</p>

            <button class="copy-btn" onclick="copyCode('glcd-code')">Copy Code</button>

            <pre id="glcd-code" class="code-block">
#include &lt;U8g2lib.h&gt;
#include &lt;Wire.h&gt;

U8G2_ST7567_ENH_DG128064I_F_SW_I2C u8g2(U8G2_R0, SCL, SDA, U8X8_PIN_NONE);

const int sensorPin = A0;

bool isLocked = true;
unsigned long unlockTime = 0;
unsigned long lockDelay = 5000;
int scanCount = 0;

void setup() {
	Wire.begin(2, 0); // SDA, SCL initialization
  Serial.begin(115200);

  u8g2.setI2CAddress(0x3F * 2);
  u8g2.begin();
  u8g2.clearBuffer();
  displayStatus();
}

void loop() {
  int sensorValue = analogRead(sensorPin);

  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_ncenB08_tr);
  u8g2.drawStr(0, 10, "Flying Fish Sensor");

  u8g2.setCursor(0, 30);
  u8g2.print("Value: ");
  u8g2.print(sensorValue);

  u8g2.sendBuffer();
  delay(500);
}

void displayStatus() {
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_ncenB08_tr);
  u8g2.drawStr(0, 10, isLocked ? "Locked" : "Unlocked");

  u8g2.setCursor(0, 30);
  u8g2.print("Scans: ");
  u8g2.print(scanCount);

  u8g2.sendBuffer();
}
            </pre>

            <p class="muted" style="margin-top:10px;">
                If your GLCD does not respond, try changing the I2C address from <code>0x3F</code> to <code>0x3C</code> or <code>0x27</code>.
            </p>
        </div>

        <div class="card">
            <h2>Output</h2>
            <p class="muted">What you will see</p>

            <pre class="code-block">
Flying Fish Sensor
Value: 512
            </pre>
        </div>

    </div>
</main>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/esp8266-flyingfish-glcd",
    "ESP8266 + Flying Fish + GLCD Source Code"
);
include_footer();
close_database();
?>
