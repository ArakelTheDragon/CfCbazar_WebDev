<?php
// /diy/pic-assembler-modular-code/index.php
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
$url        = "diy/pic-assembler-modular-code/index.php";
$parent_url = "diy"; // NULL or path to parent folder, for diy/ebook/index.php the parent url is diy
$title      = "PIC Assembler: Modular Code & PIC12F509 — Gooligum Guide";
$template   = "Generic";
$meta_title = "PIC Assembler: Modular Code & PIC12F509 | Gooligum Tutorial & ZIP";
$meta_desc  = "Learn subroutines, W parameter passing, banking/paging, 256-word call limits, PAGESEL/BANKSEL, UDATA_SHR, relocatable modules (GLOBAL/EXTERN), 25-page PDF summary, and Baseline-140402.zip.";

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
        'learn_time' => '25-page guide',
        'experience' => 'Beginner',
    ]);
}

echo <<<HTML
<div class="info-card">
    <p><strong>Author:</strong> Gooligum Electronics (David Meiklejohn, 2012)</p>
    <p>Lesson 3 introduces reusable, modular assembly code alongside the <strong>PIC12F509</strong> (1024 words of program memory from <code>000h–3FFh</code>, 41 bytes of data memory across two banks, 8-pin package with 6 I/O pins, and up to 4 MHz clock).</p>
    <p>In doing this, you learn:</p>
    <ul>
        <li>The <strong>PIC12F509</strong> MCU and how it compares with the <strong>PIC12F508</strong> and <strong>PIC16F505</strong></li>
        <li>Subroutines (<code>call</code> and <code>retlw</code>), 2-level hardware stack limits, and passing parameters in <code>W</code> (such as <code>delay10</code> for <code>W &times; 10 ms</code>)</li>
        <li>Data memory banking (<code>FSR&lt;5&gt;</code>, shared registers <code>07h–0Fh</code>/<code>27h–2Fh</code>, banked registers, and <code>UDATA_SHR</code>) and program memory paging (<code>PA0</code> for 512-word pages)</li>
        <li>The 256-word subroutine <code>call</code> address window, jump/subroutine vectors, <code>PAGESEL</code>, and <code>BANKSEL</code></li>
        <li>Relocatable multi-file modules (<code>delay10.asm</code> and <code>BA_L3-Flash_LED-main.asm</code>) using <code>GLOBAL</code> and <code>EXTERN</code>, plus <code>.lst</code> and <code>.map</code> build outputs</li>
    </ul>
    <p><strong>Project Archive:</strong> <a href="{$downloadUrl}"><strong>Download Baseline-140402.zip</strong></a> (<code>/assets/files/Baseline-140402.zip</code> — Baseline PIC project archive; the 25-page PDF is the separate study guide).</p>
</div>

<div class="warning">
    <strong>⚠️ IMPORTANT HARDWARE &amp; ASSEMBLY NOTES:</strong> On the Gooligum training board, do not plug a <strong>PIC12F509</strong> into the 8-pin <code>10F</code> socket—install it in the top <code>12F</code> section of the 14-pin socket with jumper <code>JP12</code> on <code>GP1</code>, and keep the 330 &Omega; series resistor and 100 nF bypass capacitor within datasheet and PICkit 2/3 limits. In baseline PIC code, remember that <code>call</code> only targets the first 256 addresses of a page (use subroutine vectors and <code>PAGESEL</code> across code sections), baseline PICs have a 2-level stack and use <code>retlw</code> rather than <code>return</code>, and multi-file modules should avoid duplicate <code>list</code> processor directives across files.
</div>

<h2>1. Overview &amp; PDF Table of Contents</h2>
<p>Based on Gooligum Electronics' 25-page <em>Introduction to PIC Programming — Baseline Architecture and Assembly Language, Lesson 3: Introducing Modular Code</em> (2012), assuming a nominal 4 MHz clock (1 &mu;s instruction cycle).</p>
<div class="info-card">
    <ol>
        <li><strong>Introduction &amp; Introducing the PIC12F509 (p. 1–2):</strong> Explains why modular code matters as programs grow, introduces the <strong>PIC12F509</strong> (1024 words program memory, 41 bytes data memory, 8-pin package, 6 I/O pins, 4 MHz max clock), and compares it with <strong>PIC12F508</strong> and <strong>PIC16F505</strong>.</li>
        <li><strong>Banking &amp; Paging (p. 2–4):</strong> Examines 12-bit baseline opcodes, 5-bit register addressing (32-register banks, <code>FSR&lt;5&gt;</code> bank select, shared vs. banked registers on PIC12F509), and 9-bit <code>goto</code> address fields (512-word pages and <code>STATUS</code> bit <code>PA0</code>).</li>
        <li><strong>Subroutines &amp; Parameter Passing with W (p. 5–10):</strong> Covers <code>call</code>, the 2-level return stack, <code>retlw</code>, passing a count in <code>W</code> for <code>delay10</code> (<code>W &times; 10 ms</code>, 10 ms to 2.55 s), wiring the <strong>PIC12F509</strong> on <code>GP1</code> for a 20% duty cycle flash (200 ms on / 800 ms off), and why direct <code>movwf GPIO</code> / <code>clrf GPIO</code> avoids read-modify-write here.</li>
        <li><strong>CALL Address Limit, PAGESEL, BANKSEL &amp; UDATA_SHR (p. 10–15):</strong> Explains the 8-bit address limit (0–255) in <code>call</code>, using subroutine vectors and <code>PAGESEL</code> across <code>CODE</code> sections, using <code>BANKSEL</code> for <code>UDATA</code> variables, and using <code>UDATA_SHR</code> for shared variables without bank switching.</li>
        <li><strong>Relocatable Modules, Build Process &amp; Conclusion (p. 16–25):</strong> Walks through multi-file project setup in MPLAB 8 and MPLAB X (<code>delay10.asm</code> and <code>BA_L3-Flash_LED-main.asm</code>), exporting/importing symbols with <code>GLOBAL</code> and <code>EXTERN</code>, inspecting <code>.lst</code> and <code>.map</code> linker outputs, and preparing for Lesson 4 (switches and debouncing).</li>
    </ol>
</div>

<h2>2. System Block Diagram</h2>
<p>Visual architecture diagram styled via <code>/assets/css/styles.css</code> (no external image/JPG files required) showing 5 V power with a 100 nF bypass capacitor and ICSP/MCLR feeds into the <strong>PIC12F509</strong> core, passing a <code>W</code> parameter through a subroutine vector (<code>PAGESEL</code>) to the relocatable <code>delay10.asm</code> module (<code>GLOBAL</code>/<code>EXTERN</code>, <code>BANKSEL</code>, <code>UDATA_SHR</code>) while driving <strong>GP1</strong> through an LED and 330 &Omega; resistor to <code>VSS</code>.</p>

<div class="info-card sys-diagram-wrap">
    <div class="sys-diagram" role="img" aria-label="System Block Diagram showing 5V power with 100nF bypass and MCLR/ICSP connected to PIC12F509 calling a modular delay10 subroutine via PAGESEL and BANKSEL while driving GP1 through an LED and 330 ohm resistor to ground">
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
            <div class="sys-node-title">PIC12F509 Core</div>
            <div class="sys-node-sub">PAGESEL, BANKSEL, delay10_R</div>
        </div>

        <div class="sys-arrow sys-arrow-bus">
            <span class="sys-arrow-label sys-label-bus">GP1 -&gt; LED/330&Omega;</span>
            <div class="sys-arrow-line"></div>
        </div>

        <div class="sys-node sys-node-output">
            <div class="sys-node-title">LED + 330&Omega; to GND</div>
            <div class="sys-node-sub">12F Socket / JP12</div>
        </div>
    </div>
</div>

<h2>3. Typical Use Cases</h2>
<div class="info-card">
    <ul>
        <li><strong>Reusable Delay Module &amp; Multi-File Projects:</strong> Package <code>delay10_R</code> in <code>delay10.asm</code> with <code>GLOBAL</code>/<code>EXTERN</code> so multiple programs or timing intervals (such as 200 ms on / 800 ms off or 500 ms toggle) reuse one tested routine.</li>
        <li><strong>Scaling to 1024-Word Baseline PICs:</strong> Move from 512-word <strong>PIC12F508</strong> projects to the <strong>PIC12F509</strong> (<code>0x3FF</code> <code>OSCCAL</code> calibration address) while managing bank selection (<code>BANKSEL</code> / <code>UDATA_SHR</code>) and page selection (<code>PAGESEL</code>).</li>
        <li><strong>Offline Archive Reference:</strong> Download <code>Baseline-140402.zip</code> to keep the baseline assembly project archive handy alongside your study notes and prepare for Lesson 4.</li>
    </ul>
</div>
HTML;

echo '</div>';
echo '</main>';

// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/pic-assembler-modular-code/index.php",
    "Source Code"
);
include_footer();
close_database();
?>
