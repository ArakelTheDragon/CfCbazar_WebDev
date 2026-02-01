<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>2N7000 MOSFET Circuit Explained</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f4;
      padding: 20px;
      max-width: 800px;
      margin: auto;
    }
    h1 {
      color: #2c3e50;
    }
    h2 {
      color: #34495e;
      margin-top: 30px;
    }
    p {
      line-height: 1.6;
    }
    ul {
      background: #e8f0fe;
      padding: 10px 20px;
      border-left: 4px solid #3498db;
    }
    img {
      max-width: 100%;
      height: auto;
      margin-top: 20px;
      border: 1px solid #ccc;
    }
    .footer {
      margin-top: 40px;
      font-size: 0.9em;
      color: #777;
    }
  </style>
</head>
<body>
  <h1>Understanding the 2N7000 MOSFET Circuit</h1>

  <p>The <strong>2N7000</strong> is an N-channel enhancement-mode MOSFET commonly used for switching low-power loads like LEDs, sensors, or relays. It acts as a voltage-controlled switch: when the gate voltage exceeds a threshold (typically 2–3V), the MOSFET turns on and allows current to flow from drain to source.</p>

  <h2>🧪 Circuit Overview</h2>
  <p>This specific circuit uses a potentiometer to control the gate voltage, allowing manual adjustment of the switching point. Here's how it's wired:</p>

  <ul>
    <li><strong>3kΩ resistor</strong> connects VCC (5V) to the gate of the MOSFET.</li>
    <li><strong>Potentiometer</strong> connects between gate and ground — forming a voltage divider.</li>
    <li><strong>LED</strong> is placed between VCC and the drain of the MOSFET.</li>
    <li><strong>100Ω resistor</strong> connects the source to ground — limiting current through the MOSFET.</li>
  </ul>

  <h2>⚙️ How It Works</h2>
  <p>As you adjust the potentiometer, the voltage at the gate changes. When the gate voltage rises above the MOSFET's threshold voltage (V<sub>th</sub>), the transistor turns on. This allows current to flow through the LED and the 100Ω resistor to ground, lighting the LED.</p>

  <p>If the gate voltage is below the threshold, the MOSFET remains off and the LED stays dark. This setup is ideal for demonstrating voltage-controlled switching and can be adapted for sensor-based automation.</p>

  <h2>📷 Circuit Diagram</h2>
  <img src="2n7000.png" alt="2N7000 MOSFET Circuit Diagram">

  <h2>🔧 Applications</h2>
  <ul>
    <li>Manual or sensor-based switching</li>
    <li>Temperature or light-triggered indicators</li>
    <li>Low-voltage control circuits for microcontrollers</li>
  </ul>

  <div class="footer">
    Want to simulate this circuit or connect it to your ESP8266? Let me know and I’ll help you wire it up!
  </div>
</body>
</html>
