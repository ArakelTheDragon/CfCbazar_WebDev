<?php
// esp-webserver.php
$title = "ESP8266 Web Server Manual with LittleFS | CfCbazar";
$description = "Step-by-step guide for setting up an ESP8266 Web Server with LittleFS. Host HTML, CSS, JS, and images directly on ESP8266. IoT hosting tutorial by CfCbazar.";
$keywords = "ESP8266, ESP8266 Web Server, Arduino, LittleFS, IoT, NodeMCU, Web Hosting, CfCbazar, ESP8266 Tutorial";
$url = "https://cfcbazar.ct.ws/esp-webserver.php";
$image = "https://cfcbazar.ct.ws/assets/esp8266_manual_preview.png";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?= htmlspecialchars($title) ?></title>
  <meta name="description" content="<?= htmlspecialchars($description) ?>">
  <meta name="keywords" content="<?= htmlspecialchars($keywords) ?>">
  <meta name="author" content="CfCbazar">

  <!-- Open Graph / Facebook -->
  <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
  <meta property="og:type" content="article">
  <meta property="og:url" content="<?= htmlspecialchars($url) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($image) ?>">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($image) ?>">

  <!-- Canonical -->
  <link rel="canonical" href="<?= htmlspecialchars($url) ?>">

  <!-- Schema.org Article -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "TechArticle",
    "headline": "ESP8266 Web Server Manual with LittleFS",
    "description": "Step-by-step guide for setting up an ESP8266 Web Server with LittleFS. Host HTML, CSS, JS, and images directly on ESP8266. IoT hosting tutorial by CfCbazar.",
    "author": {
      "@type": "Organization",
      "name": "CfCbazar"
    },
    "publisher": {
      "@type": "Organization",
      "name": "CfCbazar",
      "logo": {
        "@type": "ImageObject",
        "url": "https://cfcbazar.ct.ws/assets/logo.png"
      }
    },
    "datePublished": "2025-08-29",
    "dateModified": "<?= date('Y-m-d') ?>",
    "mainEntityOfPage": {
      "@type": "WebPage",
      "@id": "<?= htmlspecialchars($url) ?>"
    }
  }
  </script>

  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f8fafc;
      margin: 0;
      padding: 20px;
      line-height: 1.7;
      color: #2c3e50;
    }
    h1, h2, h3 {
      color: #1a202c;
    }
    h1 {
      text-align: center;
      margin-bottom: 30px;
    }
    pre, code {
      background: #edf2f7;
      padding: 6px 10px;
      border-radius: 6px;
      font-family: Consolas, monospace;
      font-size: 0.95em;
      display: block;
      overflow-x: auto;
    }
    .container {
      max-width: 900px;
      margin: auto;
      background: #fff;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    ul {
      margin: 10px 0 20px 20px;
    }
    .note {
      background: #ebf8ff;
      padding: 12px;
      border-left: 4px solid #3182ce;
      margin: 20px 0;
      border-radius: 6px;
    }
    .footer {
      text-align: center;
      margin-top: 40px;
      font-size: 0.9em;
      color: #718096;
    }
    .footer a {
      color: #3182ce;
      text-decoration: none;
    }
    a { color: #3182ce; text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="container">
    <h1>📖 ESP8266 Web Server with LittleFS</h1>

    <p>
      Welcome to the official <strong>ESP8266 Web Server Manual</strong> by <a href="https://cfcbazar.ct.ws/">CfCbazar</a>.  
      This guide teaches you how to host static websites (HTML, CSS, JS, images) on your <strong>ESP8266</strong> using <strong>LittleFS</strong>.
    </p>

    <h2>Requirements</h2>
    <ul>
      <li>ESP8266 Board (NodeMCU, Wemos D1 Mini, etc.)</li>
      <li>Arduino IDE installed</li>
      <li>ESP8266 Board package added in Arduino IDE</li>
      <li>USB cable for uploading code</li>
      <li>WiFi credentials (SSID and password)</li>
    </ul>

    <h2>Step 1: Install LittleFS</h2>
    <p>
      LittleFS allows storing your website files directly on the ESP8266 flash. Install the <strong>LittleFS</strong> library via Arduino IDE Library Manager.
    </p>

    <h2>Step 2: Prepare your website files</h2>
    <p>
      Place your <code>index.html</code>, <code>style.css</code>, <code>script.js</code> and images in the <code>data/</code> folder of your Arduino project.
    </p>

    <h2>Step 3: Upload LittleFS File System</h2>
    <p>
      Use the <strong>ESP8266 LittleFS Data Upload</strong> tool in Arduino IDE to flash the files to the board.
    </p>

    <h2>Step 4: Upload the Arduino Sketch</h2>
    <p>
      Copy and paste the following code into Arduino IDE and replace your WiFi credentials:
    </p>

    <pre><code>#include &lt;ESP8266WiFi.h&gt;
#include &lt;ESP8266WebServer.h&gt;
#include &lt;LittleFS.h&gt;

const char* ssid = "YOUR_SSID";
const char* password = "YOUR_PASSWORD";

ESP8266WebServer server(80);

String getContentType(String filename) {
  if(filename.endsWith(".html")) return "text/html";
  if(filename.endsWith(".css")) return "text/css";
  if(filename.endsWith(".js")) return "application/javascript";
  if(filename.endsWith(".png")) return "image/png";
  if(filename.endsWith(".jpg") || filename.endsWith(".jpeg")) return "image/jpeg";
  return "text/plain";
}

void handleNotFound() {
  String path = server.uri();
  if(path == "/") path = "/index.html";

  if(LittleFS.exists(path)) {
    File file = LittleFS.open(path, "r");
    server.streamFile(file, getContentType(path));
    file.close();
  } else {
    server.send(404, "text/plain", "404: File Not Found");
  }
}

void setup() {
  Serial.begin(115200);
  WiFi.begin(ssid, password);
  while(WiFi.status() != WL_CONNECTED) { delay(500); Serial.print("."); }
  Serial.println("\nConnected to WiFi: " + WiFi.localIP().toString());

  if(!LittleFS.begin()) { Serial.println("LittleFS mount failed"); return; }

  server.onNotFound(handleNotFound);
  server.begin();
  Serial.println("HTTP server started");
}

void loop() {
  server.handleClient();
}</code></pre>

    <h2>Step 5: Access Your ESP8266 Web Server</h2>
    <p>
      Open a browser and type the IP address printed in Serial Monitor. Your website should load immediately.
    </p>

    <h2>Step 6: Optional - Monitor System Info</h2>
    <p>
      You can add RAM, flash, and LittleFS usage info in Serial Monitor to optimize your setup.
    </p>

    <div class="note">
      ✅ With this setup, your ESP8266 becomes a fully functional mini web host for static websites and IoT dashboards.
    </div>

    <h2>Tips & Best Practices</h2>
    <ul>
      <li>Keep files under 1-2MB total to avoid memory issues.</li>
      <li>Use compressed images and minified CSS/JS to save space.</li>
      <li>Always check your LittleFS mount and WiFi connection on startup.</li>
      <li>Use separate HTML folders if you want multiple web pages.</li>
    </ul>

    <div class="footer">
      &copy; <a href="https://cfcbazar.ct.ws/">CfCbazar</a> — ESP8266 Projects & Tutorials
    </div>
  </div>
</body>
</html>
