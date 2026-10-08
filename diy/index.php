<?php
// CfCbazar DIY Tools & Dashboard (Public Access — no session required)
// diy/index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../includes/reusable.php';

// Set return URL cookie for login.php
setReturnUrl("/diy/index.php");

// --- Page metadata -----------------------------------------------------------
$url        = "diy/index.php";
$parent_url = "diy";
$title      = "Main Web Tools Page";
$template   = "Generic";   // optional: e.g. "tool", "article", "game"
$meta_title = "Free Open Source Web Tools";
$meta_desc  = "Budget calculators, goal trackers, ping test, internet download speed test, AI systems, ESP8266, PCBs and tutorials";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

// --- Render layout ---
include_menu();     // must be first
include_header();   // loads styles.css
render_top_userbar();
?>

<div class="container">

  <div class="header">
      <h1>🔍 Search Tools & Features</h1>
  </div>

  <h2 class="page-title">Explore CfCbazar Tools</h2>

  <div class="search-box">
      <input type="text" id="searchInput" placeholder="Search tools...">
  </div>

  <script>
      document.addEventListener("DOMContentLoaded", () => {
          const searchInput = document.getElementById("searchInput");
          const cards = document.querySelectorAll(".card");

          searchInput.addEventListener("input", () => {
              const query = searchInput.value.toLowerCase();

              cards.forEach(card => {
                  const text = card.innerText.toLowerCase();
                  card.style.display = text.includes(query) ? "block" : "none";
              });
          });
      });
  </script>

  <div class="balance-box">Explore our tools below.</div>
  <div><a href="/index.php">🏠 Go to Home</a></div>

  <!-- Admin / contributor link. Anyone can click it; form-diy.php handles auth. -->
  <div style="margin: 15px 0;">
    <a href="form-diy.php" style="display: inline-block; padding: 10px 16px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold;">➕ Add New Item</a>
  </div>

  <!-- NEW CARDS WILL BE APPENDED DIRECTLY ABOVE THIS LINE -->
  
    <div class="card">
    <h2>🎮 Games</h2>
    <p>Want to play instead?</p>
    <a href="/games/">Go to Games</a>
  </div>

    <div class="card">
    <h2>CfCbazar AI</h2>
    <p>Use our free AI agent, no signup, no image generation or file gen but it's open source.</p>
    <a href="/diy/ai-system/index.php">AI Agent</a>
  </div>
  
    <div class="card">
    <h2>Nutritions Calculator</h2>
    <p>Calculate the fats, carbs and etc in ingredients and meals.</p>
    <a href="/diy/nutritions/index.php">Nutritions Calculator</a>
  </div>

    <div class="card">
    <h2>Sky Stream Puck Connection and Troubleshooting Sim</h2>
    <p>Sky Stream Puck Connection and Troubleshooting Sim</p>
    <a href="/diy/sky-puck/index.php">Sky Stream Puck Connection and Troubleshooting Sim</a>
  </div>

    <div class="card">
    <h2>Free Goal Tracker</h2>
    <p>Track your daily goals, export as PNG, PDF or CSV, up to 7 days history</p>
    <a href="/diy/goal-tracker/index.php">Free Goal Tracker</a>
  </div>

  <div class="card">
    <h2>📚 Ebook Library</h2>
    <p>Browse and download free ebooks, guides, and resources. Upload available for admins and contributors.</p>
    <a href="/diy/library/index.php">Ebook Library</a>
  </div>

  <!-- INSERT_NEW_CARDS_HERE -->

  <div class="card">
    <h2>🔗 URL Shortener</h2>
    <p>Share links, pay with tokens based on traffic.</p>
    <a href="/r.php">URL Shortener</a>
  </div>

  <div class="card">
    <h2>🔌 Power Calculator</h2>
    <p>Check the power consumption of your devices & how much it costs you.</p>
    <a href="/diy/power/">Power Calculator</a>
  </div>

  <div class="card">
    <h2>💲 Survival Tool</h2>
    <p>Check your expenses for basic survival.</p>
    <a href="/diy/survival/">Survival Tool</a>
  </div>

  <div class="card">
    <h2>🚌 Tracking</h2>
    <p>Check or make a tracking number.</p>
    <a href="/diy/track/">Tracking</a>
  </div>

  <div class="card">
    <h2>💼 Work Value Table</h2>
    <p>Check the value of work in different regions.</p>
    <a href="/diy/work_value/">Work Value Table</a>
  </div>

  <div class="card">
    <h2>📞 Numbers Lookup</h2>
    <p>Check who called you.</p>
    <a href="/diy/numbers-lookup/">Numbers</a>
  </div>

  <div class="card">
    <h2>🛠️ UDS CAN Simulator</h2>
    <p>Learn UDS CAN diagnostics.</p>
    <a href="/diy/autodiag/">UDS CAN</a>
  </div>

  <div class="card">
    <h2>OPAMP Voltage Regulator</h2>
    <p>Check the circuit and project.</p>
    <a href="/diy/opamp-voltage-reg-zener/">Voltage Reg</a>
  </div>

  <div class="card">
    <h2>ESP8266 Flying Fish GLCD</h2>
    <p>A motion detection system with a display.</p>
    <a href="/diy/esp8266-flyingfish-glcd/">ESP8266 Flying Fish GLCD</a>
  </div>

  <div class="card">
    <h2>ESP8266 Repeater</h2>
    <p>Mesh repeater with ESP8266.</p>
    <a href="/diy/esp8266-repeater/">ESP8266 Repeater</a>
  </div>

  <div class="card">
    <h2>Python Alarm on WiFi Drop</h2>
    <p>Sound an alarm if your internet goes down.</p>
    <a href="/diy/python-alarm-wifi/">Python Alarm</a>
  </div>

  <div class="card">
    <h2>JS HTML Internet Speed Test</h2>
    <p>Test your download speed.</p>
    <a href="/diy/js-html-internetspeedtest/">JS HTML Internet Speed</a>
  </div>

  <div class="card">
    <h2>CfC Free TV Playlist</h2>
    <p>Watch public domain movies & classics.</p>
    <a href="/diy/cfc-free-tv/">CfC Free TV Playlist</a>
  </div>

  <div class="card">
    <h2>ESP8266 LCD</h2>
    <p>An ESP8266 with an I2C LCD 16x2.</p>
    <a href="/diy/esp8266-lcd/">ESP8266 LCD</a>
  </div>

  <div class="card">
    <h2>Ping and Latency Monitor</h2>
    <p>Check your ping and latency.</p>
    <a href="/diy/pinglatency/">Ping and Latency</a>
  </div>

  <div class="card">
    <h2>Monitor Internet Dropouts</h2>
    <p>Check if your internet drops.</p>
    <a href="/diy/internetdrop/">Internet Dropout</a>
  </div>
  
  <div class="card">
    <h2>Online Photo Converter</h2>
    <p>Convert images to different formats.</p>
    <a href="/diy/photo-converter/">Photo Converter</a>
  </div>
  
  <div class="card">
    <h2>Online Photo Upload Tool</h2>
    <p>Upload a photo that can viewed by someone else like a support agent.</p>
    <a href="/diy/photo/">Photo Tool</a>
  </div>
  
  <div class="card">
    <h2>Online Message Tool 32 chars max</h2>
    <p>Send a small message to someone you can't text.</p>
    <a href="/diy/mtool/">Mtool</a>
  </div>
  
  <div class="card">
    <h2>LM2576 adjustable switching power supply</h2>
    <p>1.2V to 55V adjustable switching power supply.</p>
    <a href="/diy/lm2576-adjustable/index.php">LM2576 power supply</a>
  </div>  
  
  <div class="card">
    <h2>LM317 adjustable linear power supply</h2>
    <p>1.5V to 37V adjustable switching power supply.</p>
    <a href="/diy/lm317-basic/index.php">LM317 power supply</a>
  </div>    
  
  <div class="card">
    <h2>BTC mining profit calc</h2>
    <p>Check how much you would make on the various platforms for mining and cloud mining.</p>
    <a href="/diy/btc-profit-calculator/index.php">BTC mining profit calc</a>
  </div>   
  
  <div class="card">
    <h2>Free MHR customer record tool</h2>
    <p>Free customer account management like MHR and IBM.</p>
    <a href="/diy/mhr/index.php">Free MHR Tool</a>
  </div>    
  
  <div class="card">
    <h2>Simple motor power</h2>
    <p>Simple motor power project.</p>
    <a href="/diy/bjt-motor-control-simple/index.php">Simple Motor Power</a>
  </div>   
  
  <div class="card">
    <h2>ESP8266 DS18B20 Temp Sensor</h2>
    <p>Circuit and code, easy and fast.</p>
    <a href="/diy/esp8266-ds18b20/index.php">ESP 8266 DS18B20</a>
  </div>    

  <div class="card">
    <h2>🧠 Quizzes & Tasks</h2>
    <p>Participate and get rewarded. (Coming soon!)</p>
    <a href="#">Coming Soon</a>
  </div>

</div>

<?php include_footer(); ?>
