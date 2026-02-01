<?php
require_once __DIR__ . '/../includes/reusable.php';

if (!function_exists('enforce_https')) {
    function enforce_https() {
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            if (!headers_sent()) {
                header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
                exit;
            }
        }
    }
}
enforce_https();
track_visits();
include_header("ESP8266 + I²C LCD Wiring Guide", "Beginner-friendly ESP8266 LCD tutorial using LiquidCrystal_I2C. Wiring, code, and troubleshooting tips.");
include_menu();
?>

<div class="container">
  <h1 class="page-title">📟 ESP8266 + I²C LCD Wiring Guide</h1>
  <p class="intro">Beginner-friendly setup for 16x2 LCD modules using LiquidCrystal_I2C</p>

  <div class="card">
    <h2>🔧 Overview</h2>
    <p>Connect a standard I²C 16x2 LCD display to your ESP8266 using minimal wiring and the LiquidCrystal_I2C library. Ideal for status messages, sensor readouts, or debugging output.</p>
  </div>

  <div class="card table-container">
    <h2>⚡ Wiring Instructions</h2>
    <table>
      <thead><tr><th>LCD Pin</th><th>ESP8266 Pin</th><th>Description</th></tr></thead>
      <tbody>
        <tr><td>VCC</td><td>5V / Vin</td><td>Power input (5V, 6W typical)</td></tr>
        <tr><td>GND</td><td>GND</td><td>Ground connection</td></tr>
        <tr><td>SDA</td><td>D4 (GPIO2)</td><td>I²C data line</td></tr>
        <tr><td>SCL</td><td>D3 (GPIO0)</td><td>I²C clock line</td></tr>
      </tbody>
    </table>
    <div class="success">✅ ESP8266 boards have built-in pull-up resistors on I²C pins. External 4.7kΩ resistors are optional but can improve stability.</div>
  </div>

  <div class="card">
    <h2>🧠 I²C Address Tips</h2>
    <ul>
      <li>0x27 (most popular)</li>
      <li>0x3F</li>
      <li>0x20</li>
    </ul>
    <p>Run an I²C scanner sketch to detect your module’s address. It will print all I²C devices to the Serial Monitor.</p>
  </div>

  <div class="card">
    <h2>📦 Required Libraries</h2>
    <pre><code>#include &lt;Wire.h&gt;
#include &lt;LiquidCrystal_I2C.h&gt;</code></pre>
  </div>

  <div class="card">
    <h2>🧪 Sample Code</h2>
    <pre><code>#include &lt;Wire.h&gt;
#include &lt;LiquidCrystal_I2C.h&gt;

LiquidCrystal_I2C lcd(0x27, 16, 2); // Address, columns, rows

void setup(){
  Wire.begin(2, 0);       // SDA = D4, SCL = D3
  lcd.init();             // Initialize LCD
  lcd.backlight();        // Turn on backlight
  lcd.print(" Hello World! ");
}

void loop(){
  // Nothing Absolutely Nothing!
}</code></pre>
    <div class="success">⚠️ Wire.begin(2, 0) maps SDA to D4 and SCL to D3. Confirm your board’s pinout — some ESP8266 variants differ.</div>
  </div>

  <div class="card">
    <h2>🛠️ Troubleshooting</h2>
    <ul>
      <li>No display? Check I²C address and wiring.</li>
      <li>Flickering or garbled text? Try external pull-ups.</li>
      <li>Contrast issues? Adjust the LCD module’s potentiometer.</li>
    </ul>
  </div>

  <div class="card">
    <h2>🔍 SEO Tags</h2>
    <p>ESP8266 LCD wiring, LiquidCrystal_I2C tutorial, I2C LCD 0x27 ESP8266, ESP8266 D4 D3 SDA SCL, Arduino LCD Hello World, ESP8266 LCD no pull-up, I2C scanner LCD address, ESP8266 LCD beginner guide</p>
  </div>
</div>

<?php include_footer(); ?>