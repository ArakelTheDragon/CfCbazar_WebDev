<?php
// /diy/pic-assembler-light-a-led/index.php
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
//require_once __DIR__ . '/../../includes/settings_local.php'; // deleted
$NEED_SESSION = true;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN         = false;   // 1
// $NEED_MODERATOR     = false;   // 2
// $NEED_CONTRIBUTOR   = false;   // 3
// $NEED_VIP           = false;   // 4
// $NEED_STANDARD_USER = false;   // 5
// $IS_FRONTEND        = true;
// $IS_BACKEND         = false;

// ------------------------

// --- Page metadata -----------------------------------------------------------
$url        = "diy/pic-assembler-light-a-led/index.php";
$parent_url = "diy"; // NULL or path to parent folder, for diy/ebook/index.php the parent url is diy
$title      = "PIC Assembler: Light an LED (PIC10F200 & PIC12F508) — Gooligum Guide";
$template   = "Generic";
$meta_title = "PIC Assembler: Light an LED (PIC10F200 & PIC12F508) | Gooligum Tutorial & ZIP";
$meta_desc  = "Start baseline PIC assembly by lighting an LED on GP1 with PIC10F200 and PIC12F508. Gooligum study guide with Table of Contents, section summaries, and Baseline-140402.zip project files.";

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
custom_edge_protection($conn);


// Absolute URL for canonical / share links
$full_url   = build_full_url($url);

// --- Intercept Downloads -----------------------------------------------------
$downloadHandlerPath = __DIR__ . '/../../includes/download.php';
if (file_exists($downloadHandlerPath)) {
    require_once $downloadHandlerPath;

    // Check if a specific file is requested via the '?file=' parameter
    if (isset($_GET['file'])) {
        require_download($_GET['file']);
        exit;
    }
}

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);
// <head>, CSS/JS from /assets/, nav
render_header($title, $meta_desc);

$safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
$downloadUrl = htmlspecialchars($full_url . '?file=' . urlencode('/assets/files/Baseline-140402.zip'), ENT_QUOTES, 'UTF-8');

// --- page body (high-level functions only) ---
echo '<main class="content">';
echo '<div class="container">';

echo '<h1>' . $safeTitle . '</h1>';

if (function_exists('render_project_stats')) {
    render_project_stats([
        'status'     => 'Documented',
        'build_time' => '15-25 mins',
        'learn_time' => '37-page guide',
        'experience' => 'Beginner',
    ]);
}

echo <<<HTML
<div class="info-card">
    <p><strong>Author:</strong> Gooligum Electronics (David Meiklejohn, 2012)</p>
    <p>This initial exercise is the "Hello World!" of PIC programming. The apparently straightforward task of simply making an LED connected to one of the output pins of a PIC light up – never mind flashing or anything else – relies on:</p>
    <ul>
        <li>Having a functioning circuit in a workable prototyping environment</li>
        <li>Being able to use a development environment; to go from text to assembled PIC code</li>
        <li>Being able to correctly use a PIC programmer to load the code into the PIC chip</li>
        <li>Correctly configuring the PIC</li>
        <li>Writing code that will set the correct pin to output a high or low (depending on the circuit)</li>
    </ul>
    <p>If you can get an LED to light up, then you know that you have a development, programming and prototyping environment that works, and enough understanding of the PIC architecture and instructions to get started. It’s a firm base to build on.</p>
    <p><strong>Project Archive:</strong> <a href="{$downloadUrl}"><strong>Download Baseline-140402.zip</strong></a> (<code>/assets/files/Baseline-140402.zip</code> — PIC10F200 &amp; PIC12F508 project files; the 37-page PDF is the separate study guide).</p>
</div>

<div class="warning">
    <strong>⚠️ IMPORTANT CIRCUIT &amp; SAFETY NOTES:</strong> Keep the resistor in series with the LED (shown on the LED cathode to <code>VSS</code>/ground in the lesson circuit with a 100 nF bypass capacitor across <code>VDD</code>–<code>VSS</code>). Do not treat absolute maximum ratings as normal operating targets; follow datasheet limits, check PICkit 2/3 power limits, and avoid <code>GP0</code> on the LPC Demo Board where it connects to a trimpot.
</div>

<h2>1. Overview &amp; PDF Table of Contents</h2>
<p>Based on Gooligum Electronics' 37-page <em>Introduction to PIC Programming — Baseline Architecture and Assembly Language, Lesson 1: Light an LED</em> (2012), covering <strong>PIC10F200</strong> and <strong>PIC12F508</strong> with <strong>MPLAB 8</strong>, <strong>MPLAB X</strong>, <strong>MPASM</strong>, and <strong>PICkit 2 / PICkit 3</strong>.</p>
<div class="info-card">
    <ol>
        <li><strong>Getting Started (p. 1–2):</strong> Introduces the "Hello World!" LED exercise, hardware/IDE prerequisites, and substituting the 8-pin PIC12F508 on the Microchip LPC Demo Board because it does not support 6-pin 10F PICs.</li>
        <li><strong>Introducing the PIC10F200 (p. 2–4):</strong> Compares 10F200/202/204/206 specs, 2.0 V–5.5 V supply with a 100 nF bypass capacitor, Harvard architecture, register file/<code>W</code> register, <code>GPIO</code>/<code>TRIS</code> pin states, and datasheet current limits.</li>
        <li><strong>Introducing the PIC12F508 (p. 5–6):</strong> Covers the PIC12F508 alongside 12F509 and 16F505, 512-word program memory (<code>000h–1FFh</code>), 25 general-purpose registers, <code>GPIO</code>/<code>TRIS</code> bits, and oscillator configuration notes.</li>
        <li><strong>Example Circuit &amp; Development Boards (p. 6–8):</strong> Calculates a 330 &Omega; series resistor from <code>R = (VDD - VLED) / I</code> (5 V supply, ~2 V LED drop, ~10 mA), drives <code>GP1</code>, and covers Gooligum training board and LPC Demo Board wiring (including linking <code>RA1</code> to <code>RC1</code> for <code>DS2</code> and header pins 9 and 12 for <code>DS3</code>).</li>
        <li><strong>Development Environment — MPLAB 8.xx &amp; MPLAB X (p. 8–18):</strong> Walks through installing MPLAB 8 or MPLAB X, creating a project, selecting relocatable code templates (<code>.ASM</code>) from MPASM, and managing relative or copied source files.</li>
        <li><strong>Template Code &amp; Turning on the LED (p. 19–27):</strong> Explains source comments, <code>list</code>, <code>#include</code>, <code>CONFIG</code> options (<code>_MCLRE_ON</code>, <code>_CP_OFF</code>, <code>_WDT_OFF</code>, and <code>_IntRC_OSC</code> on 12F508), reserving <code>OSCCAL</code> with <code>RES 1</code> at <code>0x0FF</code>/<code>0x1FF</code>, loading <code>OSCCAL</code>, setting <code>TRIS</code>/<code>GPIO</code> for <code>GP1</code>, and looping with <code>goto $</code>.</li>
        <li><strong>Building &amp; Programming — MPLAB 8.xx &amp; MPLAB X (p. 26–35):</strong> Covers assembling/linking into a <code>.hex</code> file, selecting PICkit 2 or PICkit 3, enabling 5 V target power when needed, and releasing PICkit 2 from reset after programming.</li>
        <li><strong>Summary &amp; Conclusion (p. 35–37):</strong> Reviews baseline PICs, boards, MPASM code structure, and IDE build/program steps before Lesson 2 (flashing an LED).</li>
    </ol>
</div>

<h2>2. System Block Diagram</h2>
<p>Visual architecture diagram styled via <code>/assets/css/styles.css</code> (no external image/JPG files required) showing 5 V power with a 100 nF bypass capacitor and ICSP/MCLR feeds into the baseline PIC core, driving <strong>GP1</strong> through an LED and 330 &Omega; series resistor to ground (<code>VSS</code>).</p>

<div class="info-card sys-diagram-wrap">
    <div class="sys-diagram" role="img" aria-label="System Block Diagram showing 5V power with 100nF bypass and MCLR/ICSP connected to PIC10F200 or PIC12F508 driving GP1 through an LED and 330 ohm resistor to ground">
        <div class="sys-col-left">
            <div class="sys-node sys-node-primary">
                <div class="sys-node-title">5V + 100nF Cap</div>
                <div class="sys-node-sub">(VDD / VSS Supply)</div>
            </div>
            <div class="sys-node sys-node-secondary">
                <div class="sys-node-title">MCLR / ICSP</div>
                <div class="sys-node-sub">(Reset &amp; Programmer)</div>
            </div>
        </div>

        <div class="sys-links-left">
            <div class="sys-arrow">
                <span class="sys-arrow-label sys-label-primary">Power</span>
                <div class="sys-arrow-line"></div>
            </div>
            <div class="sys-arrow">
                <span class="sys-arrow-label sys-label-secondary">PGD/PGC</span>
                <div class="sys-arrow-line"></div>
            </div>
        </div>

        <div class="sys-node sys-node-core">
            <div class="sys-node-title">PIC10F200 / 12F508</div>
            <div class="sys-node-sub">OSCCAL, TRIS, GPIO</div>
        </div>

        <div class="sys-arrow sys-arrow-bus">
            <span class="sys-arrow-label sys-label-bus">GP1 -&gt; LED/330&Omega;</span>
            <div class="sys-arrow-line"></div>
        </div>

        <div class="sys-node sys-node-output">
            <div class="sys-node-title">LED + 330&Omega; to GND</div>
            <div class="sys-node-sub">Series Path to VSS</div>
        </div>
    </div>
</div>

<h2>3. Typical Use Cases</h2>
<div class="info-card">
    <ul>
        <li><strong>Baseline PIC Assembly Study:</strong> Follow the 37-page Gooligum lesson structure and <code>Baseline-140402.zip</code> project files to practice <code>list</code>, <code>#include</code>, <code>CONFIG</code>, <code>RES</code>, <code>OSCCAL</code>, <code>TRIS</code>, <code>GPIO</code>, and <code>goto $</code> on PIC10F200 or PIC12F508.</li>
        <li><strong>Training &amp; Demo Board Setup:</strong> Wire the Gooligum training board or Microchip LPC Demo Board (patching <code>RA1</code> to <code>RC1</code> for LED <code>DS2</code>, or header pins 9 and 12 for <code>DS3</code>) and verify PICkit 2 / PICkit 3 programming and reset release.</li>
        <li><strong>Offline Project Reference:</strong> Download <code>Baseline-140402.zip</code> to keep the baseline assembly project archive handy alongside your study notes.</li>
    </ul>
</div>
HTML;

echo '</div>';
echo '</main>';

// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/pic-assembler-light-a-led/index.php",
    "Source Code"
);
include_footer();
close_database();
?>
