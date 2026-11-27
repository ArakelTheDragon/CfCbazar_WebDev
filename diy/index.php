<?php
// /diy/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../includes/settings_global.php';
// Public tools page — no session required
$NEED_SESSION = true;
$NEED_LOGIN   = false;
$NEED_CART    = false;
$NEED_ADMIN   = false;

// ------------------------

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/index.php";
$parent_url = "/diy";
$title      = "Main Web Tools Page";
$template   = "Generic";
$meta_title = "Open-source tools and circuits we built. DIY-made.";
$meta_desc  = "Budget calculators, goal trackers, ping test, internet download speed test, AI systems, ESP8266, PCBs and tutorials";

// Absolute URL for canonical / share links
$full_url   = build_full_url($url);

setReturnUrl($url);

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}
if (function_exists('custom_edge_protection')) {
    custom_edge_protection($conn);
}


trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

if (function_exists('setReturnUrl')) {
    setReturnUrl("/diy/index.php");
}

render_header($title);

// --- page body ---
echo '<main class="container">';
?>
  <div class="header">
      <h1>🔍 Search Tools & Features</h1>
  </div>

  <h2 class="page-title">Open-source tools and circuits we built. DIY-made.</h2>

<?php
if (function_exists('render_card_word_count_search')) {
    render_card_word_count_search('searchInput', '.card', 'Search tools...', 'search-box', 'block', true);
}
?>

  <script>
      document.addEventListener("DOMContentLoaded", () => {
          const firstCard = document.querySelector(".card");
          const cardsContainer = firstCard ? firstCard.parentElement : null;
          if (!cardsContainer) return;

          cardsContainer.addEventListener("searchResultsUpdated", (event) => {
              const { cards: relevantCards } = event.detail;
              relevantCards.forEach(card => {
                  card.style.display = "block";
                  cardsContainer.appendChild(card);
              });
          });
      });
  </script>

  
  <div><a href="/index.php">🏠 Go to Home</a></div>

  <div style="margin: 15px 0;">
    <a href="/diy/diy-form.php" style="display: inline-block; padding: 10px 16px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold;">➕ Add New Item</a>
  </div>

  <div class="card">
    <h2>🎮 Games</h2>
    <p>Want to play instead?</p>
    <a href="/games/">Go to Games</a>
  </div>

  <div class="card">
    <h2>Circuit Simulator</h2>
    <p>Wire ESP32, Arduino, and Pico circuits and run the firmware in the browser.</p>
    <a href="/diy/simulator/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>CfCbazar AI</h2>
    <p>Use our free AI agent, no signup, no image generation or file gen but it's open source, still just a RAG not a full LLM.</p>
    <a href="/diy/admin_ai/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>Nutritions Calculator</h2>
    <p>Calculate the fats, carbs and etc in ingredients and meals.</p>
    <a href="/diy/nutritions/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>Free Goal Tracker</h2>
    <p>Track your daily goals, export as PNG, PDF or CSV, up to 7 days history</p>
    <a href="/diy/goal-tracker/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>🔗 URL Shortener</h2>
    <p>Share links, pay with tokens based on traffic.</p>
    <a href="/diy/r/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>🔌 Power Calculator</h2>
    <p>Check the power consumption of your devices & how much it costs you.</p>
    <a href="/diy/power/">Read more</a>
  </div>

  <div class="card">
    <h2>💲 Survival Tool</h2>
    <p>Check your expenses for basic survival.</p>
    <a href="/diy/survival/">Read more</a>
  </div>

  <div class="card">
    <h2>💼 Work Value Table</h2>
    <p>Check the value of work in different regions.</p>
    <a href="/diy/work_value/">Read more</a>
  </div>

  <div class="card">
    <h2>📞 Numbers Lookup</h2>
    <p>Check who called you.</p>
    <a href="/diy/numbers-lookup/">Read more</a>
  </div>

  <div class="card">
    <h2>🛠️ UDS CAN Simulator</h2>
    <p>Learn UDS CAN diagnostics.</p>
    <a href="/diy/autodiag/">Read more</a>
  </div>

  <div class="card">
    <h2>OPAMP Voltage Regulator</h2>
    <p>Check the circuit and project.</p>
    <a href="/diy/opamp-voltage-reg-zener/">Read more</a>
  </div>

  <div class="card">
    <h2>ESP8266 Flying Fish GLCD</h2>
    <p>A motion detection system with a display.</p>
    <a href="/diy/esp8266-flyingfish-glcd/">Read more</a>
  </div>

  <div class="card">
    <h2>ESP8266 Repeater</h2>
    <p>Mesh repeater with ESP8266.</p>
    <a href="/diy/esp8266-repeater/">Read more</a>
  </div>

  <div class="card">
    <h2>Python Alarm on WiFi Drop</h2>
    <p>Sound an alarm if your internet goes down.</p>
    <a href="/diy/python-alarm-wifi/">Read more</a>
  </div>

  <div class="card">
    <h2>JS HTML Internet Speed Test</h2>
    <p>Test your download speed.</p>
    <a href="/diy/js-html-internetspeedtest/">Read more</a>
  </div>

  <div class="card">
    <h2>DIY Streams</h2>
    <p>DIY streams we curated. DIY-made.</p>
    <a href="/diy/cfc-free-tv/">Read more</a>
  </div>

  <div class="card">
    <h2>ESP8266 LCD</h2>
    <p>An ESP8266 with an I2C LCD 16x2.</p>
    <a href="/diy/esp8266-lcd/">Read more</a>
  </div>

  <div class="card">
    <h2>Ping and Latency Monitor</h2>
    <p>Check your ping and latency.</p>
    <a href="/diy/pinglatency/">Read more</a>
  </div>

  <div class="card">
    <h2>Monitor Internet Dropouts</h2>
    <p>Check if your internet drops.</p>
    <a href="/diy/internetdrop/">Read more</a>
  </div>

  <div class="card">
    <h2>Online Photo Converter</h2>
    <p>Convert images to different formats.</p>
    <a href="/diy/photo-converter/">Read more</a>
  </div>

  <div class="card">
    <h2>Online Photo Upload Tool</h2>
    <p>Upload a photo that can viewed by someone else like a support agent.</p>
    <a href="/diy/photo/">Read more</a>
  </div>

  <div class="card">
    <h2>Online Message Tool 32 chars max</h2>
    <p>Send a small message to someone you can't text.</p>
    <a href="/diy/mtool/">Read more</a>
  </div>

  <div class="card">
    <h2>LM2576 adjustable switching power supply</h2>
    <p>1.2V to 55V adjustable switching power supply.</p>
    <a href="/diy/lm2576-adjustable/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>LM317 adjustable linear power supply</h2>
    <p>1.5V to 37V adjustable linear power supply.</p>
    <a href="/diy/lm317-basic/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>BTC mining profit calc</h2>
    <p>Check how much you would make on the various platforms for mining and cloud mining.</p>
    <a href="/diy/btc-profit-calculator/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>Simple motor power</h2>
    <p>Simple motor power project.</p>
    <a href="/diy/bjt-motor-control-simple/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>ESP8266 DS18B20 Temp Sensor</h2>
    <p>Circuit and code, easy and fast.</p>
    <a href="/diy/esp8266-ds18b20/index.php">Read more</a>
  </div>

  <div class="card">
    <h2>Organizer</h2>
    <p>Create an issue, then add tasks under it. Double-click to edit. Drag tasks to reorder. Summary is editable before copy.</p>
    <a href="/diy/organizer/index.php">Read more</a>
  </div>  
  
  <div class="card">
    <h2>LM2596 5V Adjustable</h2>
    <p>Generic Template LM2596 5V circuit, PCB and KiCad files based on the adjustable IC.</p>
    <a href="/diy/generic-template-kicad-lm2596-5V-circuit-and-pcb/index.php">Read more</a>
  </div>  
  
  <div class="card">
    <h2>KiCad Generic Template for CfCbazar Projects</h2>
    <p> Has a 5V LM2596 power, 3.3V LM2596 power, ESP8266, Display: OLED; 0.91"; 128x32; 33x20mm.</p>
    <a href="/diy/generic-template-kicad-lm2596-5V-circuit-and-pcb/index.php">Read more</a>
  </div>      

  <div class="card">
    <h2>Pic Assembler Light a LED</h2>
    <p> Tutorial for PIC MCUs how to Light a LED in Assembler with souce code for download.</p>
    <a href="/diy/pic-assembler-light-a-led/index.php">Read more</a>
  </div>     

  <div class="card">
    <h2>Pic Assembler Flash a LED</h2>
    <p> Tutorial for PIC MCUs how to Flash a LED in Assembler with souce code for download.</p>
    <a href="/diy/pic-assembler-flash-a-led/index.php">Read more</a>
  </div>       

  <div class="card">
    <h2>Pic Assembler Modular Code</h2>
    <p> Tutorial for PIC MCUs how to build a modular code with souce code for download.</p>
    <a href="/diy/pic-assembler-modular-code/index.php">Read more</a>
  </div>  

  <div class="card">
    <h2>Pic Assembler Reading Switches & Debouncing</h2>
    <p> Tutorial for PIC MCUs how to read switches with souce code and circuit for download.</p>
    <a href="/diy/pic-assembler-reading-switches/index.php">Read more</a>
  </div>  


  <div class="card">
    <h2>Pic Assembler Timer 0</h2>
    <p> Tutorial for PIC MCUs how to use Timer 0 with souce code and circuit for download.</p>
    <a href="/diy/pic_assembler_timer_zero/index.php">Read more</a>
  </div>  
  

  <h2>Related reads</h2>
  <ul class="related-reads">
    <li><a href="/diy/sky-puck/index.php">Sky Stream Puck connection notes</a></li>
    <li><a href="/diy/sky-fix/index.php">Sky TV and broadband fix articles</a></li>
    <li><a href="/diy/ebook-glass-stream-ts/index.php">Sky Stream and Sky Glass troubleshooting notes</a></li>
    <li><a href="/diy/mhr/index.php">MHR-style customer record notes</a></li>
  </ul>

  <div class="card">
    <h2>🧠 Quizzes & Tasks</h2>
    <p>Participate and get rewarded. (Coming soon!)</p>
    <a href="#">Coming Soon</a>
  </div>
<?php
echo '</main>';

cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/index.php",
    "DIY Tools Source Code"
);
include_footer();
close_database();
?>
