<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP8266 + DS18B20 Digital Temperature Sensor Tutorial</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 900px; margin: 0 auto; padding: 20px; }
        h1, h2, h3 { color: #1a1a1a; }
        .hero { background: #f8f9fa; border-left: 4px solid #0066cc; padding: 15px 20px; margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        pre { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 14px; }
        code { font-family: "Courier New", Courier, monospace; }
        .note { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 15px; margin: 20px 0; }
        .bom-list { background: #f1f3f5; padding: 15px 30px; border-radius: 5px; }
    </style>
</head>
<body>

    <h1>Interfacing DS18B20 Temperature Sensor with ESP8266 NodeMCU</h1>
    
    <div class="hero">
        <p>Learn how to connect a <strong>DS18B20 1-Wire digital temperature sensor</strong> to an <strong>ESP8266 (NodeMCU)</strong> board and read temperature values via the Serial Monitor in Celsius.</p>
    </div>

    <h2>1. Overview & Specifications</h2>
    <p>The DS18B20 is a 1-Wire digital temperature sensor that measures temperatures from <strong>-55°C to +125°C</strong> with <strong>±0.5°C accuracy</strong> (between -10°C and +85°C). It operates on a supply voltage from <strong>3.0V to 5.5V</strong>, making it fully compatible with the 3.3V logic level of the ESP8266.</p>

    <h2>2. Bill of Materials (BOM)</h2>
    <div class="bom-list">
        <ul>
            <li><strong>1x</strong> ESP8266 Development Board (NodeMCU)</li>
            <li><strong>1x</strong> DS18B20 Temperature Sensor (TO-92 or Waterproof Probe)</li>
            <li><strong>1x</strong> 4.7 kΩ Resistor (1/4W)</li>
            <li><strong>1x</strong> Breadboard & Jumper Wires</li>
        </ul>
    </div>

    <h2>3. Hardware Connections & Pinout</h2>
    <p>The DS18B20 uses a single data line for communication (1-Wire protocol). A <strong>4.7 kΩ pull-up resistor</strong> is required between the <strong>DQ (Data)</strong> line and the <strong>3.3V</strong> supply line for stable communication.</p>

    <h3>DS18B20 Pinout Table</h3>
    <table>
        <thead>
            <tr>
                <th>DS18B20 Pin</th>
                <th>Function</th>
                <th>ESP8266 Connection</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Pin 1 (GND)</strong></td>
                <td>Ground</td>
                <td>GND</td>
            </tr>
            <tr>
                <td><strong>Pin 2 (DQ)</strong></td>
                <td>1-Wire Data Line</td>
                <td><strong>D4 (GPIO2)</strong> <em>(Requires 4.7 kΩ pull-up to 3.3V)</em></td>
            </tr>
            <tr>
                <td><strong>Pin 3 (VDD)</strong></td>
                <td>Power Supply (3.0V – 5.5V)</td>
                <td>3.3V</td>
            </tr>
        </tbody>
    </table>

    <div class="note">
        <strong>Note:</strong> Multiple DS18B20 sensors can be connected in parallel on the exact same DQ line because each sensor has a unique 64-bit factory-lasered ROM ID code.
    </div>

    <h2>4. Required Arduino Libraries</h2>
    <p>Before uploading the code, install the following libraries via the Arduino IDE Library Manager (<strong>Tools &gt; Manage Libraries</strong>):</p>
    <ul>
        <li><strong>OneWire</strong> by Paul Stoffregen</li>
        <li><strong>DallasTemperature</strong> by Miles Burton</li>
    </ul>

    <h2>5. Source Code</h2>
    <p>Upload the following low-level ANSI-compatible C++ Arduino sketch to your NodeMCU board:</p>

    <pre><code>#include &lt;OneWire.h&gt;
#include &lt;DallasTemperature.h&gt;

// Data wire is connected to NodeMCU Pin D4 (GPIO2)
#define ONE_WIRE_BUS D4

// Setup a oneWire instance to communicate with any OneWire devices
OneWire oneWire(ONE_WIRE_BUS);

// Pass our oneWire reference to Dallas Temperature sensor handler
DallasTemperature sensors(&amp;oneWire);

void setup() {
  // Initialize serial communication at 115200 baud
  Serial.begin(115200);
  
  // Start up the DallasTemperature library
  sensors.begin();
}

void loop() {
  // Send the command to request temperature from all devices on the bus
  sensors.requestTemperatures(); 
  
  // Fetch temperature in Celsius for the first sensor on the bus (index 0)
  float tempC = sensors.getTempCByIndex(0);
  
  // Output result to Serial Monitor
  Serial.print("Temperature: ");
  Serial.print(tempC);
  Serial.println(" °C");
  
  // Delay 1 second before the next reading
  delay(1000);
}</code></pre>

    <h2>6. Testing & Verification</h2>
    <ol>
        <li>Connect your NodeMCU board to your PC via Micro-USB.</li>
        <li>Select board <strong>NodeMCU 1.0 (ESP-12E Module)</strong> and correct COM port in Arduino IDE.</li>
        <li>Compile and upload the sketch.</li>
        <li>Open the <strong>Serial Monitor</strong> set to <strong>115200 baud</strong>.</li>
        <li>You should see real-time temperature updates printed every second.</li>
    </ol>

</body>
</html>

