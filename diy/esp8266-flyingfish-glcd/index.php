<?php
// CfCbazar Homepage — Public Access with Modular Layout & Visit Tracking
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$title = 'ESP8266 + Flying Fish Sensor + GLCD — CfCbazar DIY';

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
    
    // Track visit BEFORE any output
    if (function_exists('trackVisit')) {
        trackVisit("diy-esp8266-flying-fish");
    }
}

// Set return url cookie for after log in
setReturnUrlCookie('/diy/esp8266-flyingfish-glcd/index.php');

$userEmail = $_SESSION['email'] ?? '';

include_header(); // ✅ This prints <head> and links styles.css
// renderCaptchaIfNeeded();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ESP8266 + Flying Fish Sensor + GLCD — CfCbazar DIY</title>

    <meta name="description" content="ESP8266 project using a Flying Fish analog sensor and a 128x64 GLCD (U8g2). Includes wiring, code, and display output.">
    <meta name="keywords" content="ESP8266, Flying Fish sensor, GLCD, U8g2, Arduino, CfCbazar DIY">
    <meta name="author" content="CfCbazar">
    <link rel="canonical" href="https://CfCbazar.42web.io/diy/esp8266-flyingfish-glcd/">

    <meta property="og:title" content="ESP8266 + Flying Fish Sensor + GLCD — CfCbazar DIY">
    <meta property="og:description" content="Learn how to read a Flying Fish sensor and display values on a 128x64 GLCD using U8g2.">
    <meta property="og:image" content="/diy/esp8266-flyingfish-glcd/img.png">
    <meta property="og:url" content="https://CfCbazar.42web.io/diy/esp8266-flyingfish-glcd/">
    <meta property="og:type" content="article">

    <link rel="stylesheet" href="/css/styles.css">

    <style>
        .copy-btn {
            background: #28a745;
            color: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            float: right;
            margin-bottom: 6px;
        }
        .copy-btn:hover { opacity: 0.85; }

        pre.code-block {
            white-space: pre-wrap;
            font-size: 13px;
            background: #f4f6f9;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid rgba(148,163,184,0.3);
        }
    </style>

    <script>
        function copyCode(id) {
            const code = document.getElementById(id).innerText;
            navigator.clipboard.writeText(code).then(() => {
                alert("Code copied to clipboard!");
            });
        }
    </script>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>ESP8266 + Flying Fish Sensor + 128×64 GLCD</h1>
    </div>

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

    <!-- COMPONENTS -->
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

    <!-- WIRING -->
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

    <!-- CODE EXAMPLE -->
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

    <!-- OUTPUT -->
    <div class="card">
        <h2>Output</h2>
        <p class="muted">What you will see</p>

        <pre class="code-block">
Flying Fish Sensor
Value: 512
        </pre>
    </div>

</div>

<footer class="footer">
    © CfCbazar — DIY Electronics
</footer>

</body>
</html>
