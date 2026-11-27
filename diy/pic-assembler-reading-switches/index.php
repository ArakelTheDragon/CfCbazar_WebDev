<?php
// /diy/pic_assembler_reading_switches/index.php
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
$url        = "diy/pic-assembler-reading-switches/index.php";
$parent_url = "diy"; // NULL or path to parent folder, for diy/ebook/index.php the parent url is diy
$title      = "PIC Assembler: Reading Switches & Debouncing (PIC12F509) — Gooligum Guide";
$template   = "Generic";
$meta_title = "PIC Assembler: Reading Switches & Debouncing (PIC12F509) | Gooligum Tutorial & ZIP";
$meta_desc  = "Read pushbuttons and debounce switches on PIC12F509 in baseline assembly. Covers pull-up resistors, weak pull-ups (OPTION), btfsc/btfss bit tests, state toggling, 21-page PDF summary, and Baseline-140402.zip.";

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
        'build_time' => '20-30 mins',
        'learn_time' => '21-page guide',
        'experience' => 'Beginner',
    ]);
}

echo <<<HTML
<div class="info-card">
    <p><strong>Author:</strong> Gooligum Electronics (David Meiklejohn, 2012)</p>
    <p>Lesson 4 introduces digital inputs on the <strong>PIC12F509</strong> by reading a pushbutton switch on <strong>GP3</strong> (configured as a digital input via <code>_MCLRE_OFF</code>) to light and toggle an LED on <strong>GP1</strong>.</p>
    <p>In doing this, you learn:</p>
    <ul>
        <li>Pull-up and pull-down resistors for reliable logic levels, plus internal weak pull-ups via the <code>OPTION</code> register</li>
        <li>Testing input pin bits with conditional skip instructions (<code>btfsc</code> and <code>btfss</code>)</li>
        <li>Contact bounce behavior in mechanical switches and how it triggers unintended multiple toggles</li>
        <li>Hardware RC debouncing vs. software debouncing algorithms (counting consecutive identical samples via <code>delay10</code>)</li>
    </ul>
    <p><strong>Project Archive:</strong> <a href="{$downloadUrl}"><strong>Download Baseline-140402.zip</strong></a> (<code>/assets/files/Baseline-140402.zip</code> — Baseline PIC project archive; the 21-page PDF is the separate study guide).</p>
</div>

<div class="warning">
    <strong>⚠️ IMPORTANT HARDWARE &amp; PIN CONFIGURATION NOTES:</strong> Because <code>GP3</code> is shared with external reset (<code>MCLR</code>), you must configure <code>_MCLRE_OFF</code> in <code>CONFIG</code> to use <code>GP3</code> as a digital input—and on the Gooligum training board, install the <strong>PIC12F509</strong> in the top <code>12F</code> section of the 14-pin socket with jumper <code>JP12</code> on <code>GP1</code> and <code>JP3</code> on <code>GP3</code> (or a 10 k&Omega; external pull-up on the LPC Demo Board, since <code>GP3</code> has no internal weak pull-up on PIC12F509). Always keep the 330 &Omega; resistor in series with the LED and the 100 nF bypass capacitor across <code>VDD</code>–<code>VSS</code>.
</div>

<h2>1. Overview &amp; PDF Table of Contents</h2>
<p>Based on Gooligum Electronics' 21-page <em>Introduction to PIC Programming — Baseline Architecture and Assembly Language, Lesson 4: Reading Switches</em> (2012), building directly on the <code>delay10</code> relocatable module from Lesson 3.</p>
<div class="info-card">
    <ol>
        <li><strong>Pull-Up &amp; Pull-Down Resistors and Example Circuit (p. 1–4):</strong> Explains why floating digital inputs are unreliable, wires an active-low pushbutton with a 10 k&Omega; pull-up resistor on <strong>GP3</strong> alongside the <strong>GP1</strong> LED + 330 &Omega; resistor on <strong>PIC12F509</strong>, and notes board jumper/header setups for the Gooligum board (<code>JP12</code>, <code>JP3</code>) and Microchip LPC Demo Board (<code>SW1</code> on <code>RA3</code>/<code>GP3</code> + external 10 k&Omega; pull-up).</li>
        <li><strong>Internal Weak Pull-Ups &amp; OPTION Register (p. 4–6):</strong> Introduces internal weak pull-ups on <code>GP0</code>, <code>GP1</code>, and <code>GP3</code> (note: on PIC12F509, internal weak pull-ups are available on <code>GP0</code>, <code>GP1</code>, and <code>GP3</code> when enabled via bit 6 <code>NOT_GPPU</code> of the <code>OPTION</code> register using the <code>option</code> instruction).</li>
        <li><strong>Reading a Switch &amp; Bit-Test Instructions (p. 6–10):</strong> Configures <code>_MCLRE_OFF</code> and <code>TRIS</code> (<code>b'111101'</code>), introduces <code>btfsc</code> ("bit test file register, skip if clear") and <code>btfss</code> ("bit test file register, skip if set"), and builds Example 1 to light the <strong>GP1</strong> LED while the <strong>GP3</strong> button is pressed.</li>
        <li><strong>Switch Bounce &amp; Toggle Example (p. 10–13):</strong> Demonstrates toggling <strong>GP1</strong> (using shadow register <code>SGPIO</code>) on each button press, and explains how mechanical contact bounce causes erratic/unreliable toggling without debouncing.</li>
        <li><strong>Debouncing Switches — Hardware &amp; Software (p. 13–21):</strong> Compares hardware RC filtering (resistor + capacitor + Schmitt trigger / hysteresis considerations) against two software debounce routines: a simple delay-after-transition approach and a robust counting algorithm that samples the switch every 1 ms (using <code>delay10</code> or a 1 ms counter) until 10 consecutive identical readings confirm a stable press or release.</li>
    </ol>
</div>

<h2>2. System Block Diagram</h2>
<p>Visual architecture diagram styled via <code>/assets/css/styles.css</code> (no external image/JPG files required) showing 5 V power with a 100 nF bypass capacitor, ICSP programming lines, and an active-low pushbutton with a 10 k&Omega; pull-up resistor feeding <strong>GP3</strong> on the <strong>PIC12F509</strong> (<code>_MCLRE_OFF</code>), which debounces the input and drives <strong>GP1</strong> through an LED and 330 &Omega; resistor to <code>VSS</code>.</p>

<div class="info-card sys-diagram-wrap">
    <div class="sys-diagram" role="img" aria-label="System Block Diagram showing 5V power with 100nF bypass and an active-low pushbutton with 10k pull-up feeding GP3 on PIC12F509, driving GP1 through an LED and 330 ohm resistor to ground">
        <div class="sys-col-left">
            <div class="sys-node sys-node-primary">
                <div class="sys-node-title">5V + 100nF Cap</div>
                <div class="sys-node-sub">(VDD / VSS Supply)</div>
            </div>
            <div class="sys-node sys-node-secondary">
                <div class="sys-node-title">Switch + 10k&Omega; Pull-Up</div>
                <div class="sys-node-sub">(Active-Low Input on GP3)</div>
            </div>
        </div>

        <div class="sys-links-left">
            <div class="sys-arrow">
                <span class="sys-arrow-label sys-label-primary">Power</span>
                <div class="sys-arrow-line"></div>
            </div>
            <div class="sys-arrow">
                <span class="sys-arrow-label sys-label-secondary">GP3 Input</span>
                <div class="sys-arrow-line"></div>
            </div>
        </div>

        <div class="sys-node sys-node-core">
            <div class="sys-node-title">PIC12F509 Core</div>
            <div class="sys-node-sub">btfsc/btfss, Debounce, SGPIO</div>
        </div>

        <div class="sys-arrow sys-arrow-bus">
            <span class="sys-arrow-label sys-label-bus">GP1 -&gt; LED/330&Omega;</span>
            <div class="sys-arrow-line"></div>
        </div>

        <div class="sys-node sys-node-output">
            <div class="sys-node-title">LED + 330&Omega; to GND</div>
            <div class="sys-node-sub">12F Socket / JP12 &amp; JP3</div>
        </div>
    </div>
</div>

<h2>3. Typical Use Cases</h2>
<div class="info-card">
    <ul>
        <li><strong>Momentary &amp; Toggle User Controls:</strong> Read active-low pushbuttons on baseline PIC inputs using <code>btfsc</code>/<code>btfss</code> and <code>SGPIO</code> shadow registers to build reliable on/off toggles, mode selectors, or start/stop triggers.</li>
        <li><strong>Hardware vs. Software Debounce Selection:</strong> Choose between external RC debounce networks and zero-extra-component firmware debounce loops (requiring a stable consecutive sample count before accepting state transitions).</li>
        <li><strong>Offline Archive Reference:</strong> Download <code>Baseline-140402.zip</code> to keep the baseline assembly project archive handy alongside your study notes and prepare for Lesson 5 (Timer0).</li>
    </ul>
</div>
HTML;

echo '</div>';
echo '</main>';

// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/pic-assembler-reading-switches/index.php",
    "Source Code"
);
include_footer();
close_database();
?>
