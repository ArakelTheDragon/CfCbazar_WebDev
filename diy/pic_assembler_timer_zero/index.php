<?php
// /diy/pic_assembler_timer_zero/index.php
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
$url        = "diy/pic_assembler_timer_zero/index.php";
$parent_url = "diy"; // NULL or path to parent folder, for diy/ebook/index.php the parent url is diy
$title      = "PIC Assembler: Using Timer0 (PIC12F509) — Gooligum Guide";
$template   = "Generic";
$meta_title = "PIC Assembler: Using Timer0 (PIC12F509) | Gooligum Tutorial & ZIP";
$meta_desc  = "Use Timer0 in timer mode and counter mode on PIC12F509 in baseline assembly. Covers OPTION, prescalers, reaction timer, concurrent LED flash + switch scan, debouncing, and 32.768 kHz clock.";

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
        'build_time' => '30-45 mins',
        'learn_time' => '17-page guide',
        'experience' => 'Beginner',
    ]);
}

echo <<<HTML
<div class="info-card">
    <p><strong>Author:</strong> Gooligum Electronics (David Meiklejohn, 2013)</p>
    <p>Lesson 5 introduces the baseline PIC <strong>Timer0</strong> peripheral (historically called <code>RTCC</code>) on the <strong>PIC12F509</strong>, showing how to measure elapsed time, run concurrent tasks without blocking switch checks, debounce switch transitions with a hardware timer, and count external clock pulses on <code>T0CKI</code> (<code>GP2</code>).</p>
    <p>In doing this, you learn:</p>
    <ul>
        <li>How <code>TMR0</code> increments (8-bit counter rolling over from <code>255</code> to <code>0</code> with no overflow flag in baseline PICs) and how <code>OPTION</code> configures <code>T0CS</code>, <code>T0SE</code>, <code>PSA</code>, and <code>PS&lt;2:0&gt;</code></li>
        <li>Extending short timer intervals with a software counter and comparing values via <code>subwf</code> with <code>STATUS</code> <code>Z</code> and <code>C</code> ("not borrow") flags</li>
        <li>Polling a switch inside a Timer0 wait loop so LED timing and button response stay responsive</li>
        <li>Debouncing button press/release transitions by resetting <code>TMR0</code> whenever the target state is not yet present until 10 ms elapses</li>
        <li>Using counter mode (<code>T0CS=1</code>) with a 32.768 kHz watch-crystal CMOS inverter oscillator on <code>GP2/T0CKI</code>, prescaled by 1:128 so <code>TMR0&lt;7&gt;</code> toggles at 1 Hz</li>
    </ul>
    <p><strong>Project Archive:</strong> <a href="{$downloadUrl}"><strong>Download Baseline-140402.zip</strong></a> (<code>/assets/files/Baseline-140402.zip</code> — Baseline PIC project archive; the 17-page PDF is the separate study guide).</p>
</div>

<div class="warning">
    <strong>⚠️ IMPORTANT CIRCUIT &amp; CODING NOTES:</strong> Setting <code>T0CS=1</code> switches Timer0 to counter mode on <code>GP2/T0CKI</code> and overrides the <code>TRIS</code> output setting for <code>GP2</code>—always clear <code>T0CS</code> (<code>0</code>) when using <code>GP2</code> as a digital output. Remember all <code>OPTION</code> bits power up as <code>1</code>, baseline PICs have no Timer0 overflow flag, and you must always keep a 330 &Omega; current-limiting resistor in series with each LED and a 100 nF bypass capacitor across <code>VDD</code>–<code>VSS</code>.
</div>

<h2>1. Overview &amp; PDF Table of Contents</h2>
<p>Based on Gooligum Electronics' 17-page <em>Introduction to PIC Programming — Baseline Architecture and Assembly Language, Lesson 5: Using Timer0</em> (2013).</p>
<div class="info-card">
    <ol>
        <li><strong>Timer0 Module &amp; Prescaler (p. 1–3):</strong> Covers the 8-bit <code>TMR0</code> register, instruction-clock (<code>FOSC/4</code>) vs. external <code>T0CKI</code> (<code>GP2</code>) Schmitt-trigger input, <code>OPTION</code> control bits (<code>T0CS</code>, <code>T0SE</code>, <code>PSA</code>, <code>PS&lt;2:0&gt;</code>), and prescale ratios from 1:2 to 1:256 (up to 65.28 ms directly at 4 MHz clock / 1 MHz instruction cycle with 1:256).</li>
        <li><strong>Example 1 — Reaction Timer (p. 3–8):</strong> Adds a second LED on <code>GP2</code> (<code>JP3</code>, <code>JP12</code>, <code>JP13</code>), uses a 1:32 prescaler (32 &mu;s/tick) with 250 ticks (8 ms) and a 125-count outer loop (1 s), and tests <code>cnt_8ms &lt; 25</code> (200 ms) using <code>subwf</code> and the <code>C</code> bit before lighting the success LED on <code>GP1</code>.</li>
        <li><strong>Example 2 — Flash LED While Responding to Input (p. 8–11):</strong> Uses 1:32 prescaling, 125 ticks (4 ms) &times; 125 repetitions (500 ms) to flash <code>GP2</code> at 1 Hz using shadow register <code>SGPIO</code> while scanning <code>GP3</code> and updating <code>GP1</code> inside the inner wait loop.</li>
        <li><strong>Example 3 — Switch Debouncing with Timer0 (p. 11–13):</strong> Configures 1:64 prescaling (64 &mu;s/tick) and waits for 157 ticks (~10 ms) of continuous press (<code>GP3</code> low) or release (<code>GP3</code> high), resetting <code>TMR0</code> whenever the switch is not in the desired state.</li>
        <li><strong>Counter Mode — 32.768 kHz Crystal Clock (p. 13–17):</strong> Builds or connects a 32.768 kHz watch-crystal oscillator (<code>74HCU04N</code> inverter, 10 M&Omega; and 100 k&Omega; resistors, 22 pF load caps, 3.3 nF start cap; or <code>JP22</code> on Gooligum board) to <code>GP2/T0CKI</code> with 1:128 prescaling (256 Hz increment rate), driving <code>GP1</code> at 1 Hz from <code>TMR0&lt;7&gt;</code> via bit tests or <code>rlf</code> rotations.</li>
    </ol>
</div>

<h2>2. System Block Diagram</h2>
<p>Visual architecture diagram styled via <code>/assets/css/styles.css</code> (no external image/JPG files required) showing 5 V power with a 100 nF bypass capacitor, active-low switch with 10 k&Omega; pull-up on <code>GP3</code>, optional 32.768 kHz external oscillator on <code>GP2/T0CKI</code> in counter mode, and LED outputs on <code>GP1</code> and <code>GP2</code> with 330 &Omega; resistors on the <strong>PIC12F509</strong>.</p>

<div class="info-card sys-diagram-wrap">
    <div class="sys-diagram" role="img" aria-label="System Block Diagram showing 5V power with 100nF bypass, active-low pushbutton on GP3, 32.768 kHz external clock input on GP2/T0CKI, and LEDs with 330 ohm resistors on GP1 and GP2 for PIC12F509">
        <div class="sys-col-left">
            <div class="sys-node sys-node-primary">
                <div class="sys-node-title">5V + 100nF Cap</div>
                <div class="sys-node-sub">(VDD / VSS Supply)</div>
            </div>
            <div class="sys-node sys-node-secondary">
                <div class="sys-node-title">Switch (GP3) &amp; 32.768kHz (GP2)</div>
                <div class="sys-node-sub">(10k&Omega; Pull-Up / T0CKI Counter)</div>
            </div>
        </div>

        <div class="sys-links-left">
            <div class="sys-arrow">
                <span class="sys-arrow-label sys-label-primary">Power</span>
                <div class="sys-arrow-line"></div>
            </div>
            <div class="sys-arrow">
                <span class="sys-arrow-label sys-label-secondary">GP3 / T0CKI</span>
                <div class="sys-arrow-line"></div>
            </div>
        </div>

        <div class="sys-node sys-node-core">
            <div class="sys-node-title">PIC12F509 Timer0</div>
            <div class="sys-node-sub">OPTION, Prescaler, TMR0, SGPIO</div>
        </div>

        <div class="sys-arrow sys-arrow-bus">
            <span class="sys-arrow-label sys-label-bus">GP1 &amp; GP2 -&gt; LEDs</span>
            <div class="sys-arrow-line"></div>
        </div>

        <div class="sys-node sys-node-output">
            <div class="sys-node-title">LEDs + 330&Omega; to GND</div>
            <div class="sys-node-sub">JP3, JP12, JP13, JP22</div>
        </div>
    </div>
</div>

<h2>3. Typical Use Cases</h2>
<div class="info-card">
    <ul>
        <li><strong>Timed Games &amp; Reaction Measurement:</strong> Measure elapsed time across multi-millisecond windows using a prescaled <code>TMR0</code> loop and a counter register compared against a threshold via <code>subwf</code>.</li>
        <li><strong>Non-Blocking Input Polling &amp; Debouncing:</strong> Check switch state inside a timer wait loop or use <code>TMR0</code> to confirm a stable 10 ms press/release without dedicating extra software counter variables.</li>
        <li><strong>Accurate Timebase Generation:</strong> Drive <code>GP2/T0CKI</code> in counter mode from a 32.768 kHz crystal oscillator to derive low-drift periodic timing such as a 1 Hz toggle on <code>TMR0&lt;7&gt;</code>.</li>
    </ul>
</div>
HTML;

echo '</div>';
echo '</main>';

// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/pic_assembler_timer_zero/index.php",
    "Source Code"
);
include_footer();
close_database();
?>
