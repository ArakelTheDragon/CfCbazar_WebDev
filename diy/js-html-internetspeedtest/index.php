<?php
// /diy/js_html_internetspeedtest/index.php
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
require_once __DIR__ . '/../../includes/settings_local.php';
// settings_local.php sets:
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN   = false;

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/js_html_internetspeedtest/index.php";
$parent_url = "/diy";
$title      = "DIY JavaScript & HTML Internet Speed Test | CfCbazar";
$template   = "Generic";
$meta_title = "DIY JavaScript & HTML Internet Speed Test | CfCbazar";
$meta_desc  = "Learn how to build a browser-based internet speed test using JavaScript and HTML. Explore the CfCbazar DIY platform and try the full demo.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<main class="content">
    <div class="container article">

        <h1>DIY JavaScript &amp; HTML Internet Speed Test</h1>
        <p class="subtitle">A CfCbazar DIY Project – Parallel Stream Speed Benchmarking in the Browser</p>

        <section>
            <p>
                Measuring your internet speed doesn’t require external services or heavy backend systems. Modern browsers support
                streaming downloads, parallel fetch requests, and real‑time charting — enough to build a surprisingly accurate
                speed‑test tool entirely in JavaScript.
            </p>
            <p>
                This CfCbazar DIY guide explains how the project works and why it’s a great example of what you can build using
                only HTML, CSS, and JavaScript. The full working demo is available on the CfCbazar DIY platform.
            </p>
        </section>

        <section>
            <h2>Why Build Your Own Speed Test?</h2>
            <p>Most online speed tests rely on proprietary servers and backend logic. But thanks to:</p>
            <ul>
                <li><strong>Fetch streaming</strong></li>
                <li><strong>AbortController timeouts</strong></li>
                <li><strong>Parallel HTTP requests</strong></li>
                <li><strong>Chart.js visualization</strong></li>
            </ul>
            <p>
                …you can replicate the core logic yourself and understand exactly how your browser measures throughput.
            </p>
        </section>

        <section>
            <h2>How the CfCbazar Speed Test Works</h2>
            <p>
                The CfCbazar speed test runs in <strong>parallel stream groups</strong> (2 → 8 connections). Each group downloads
                files of increasing size — from small images to multi‑hundred‑MB test files.
            </p>
            <ol>
                <li>Each group runs for a fixed 2‑second measurement window.</li>
                <li>Bytes received per stream are measured in real time.</li>
                <li>All streams are combined to calculate total Mbps.</li>
                <li>Outliers are removed for accuracy.</li>
                <li>Results are plotted on a live Chart.js graph.</li>
            </ol>
            <p>
                The full implementation is available in the CfCbazar DIY Speed Test demo.
            </p>
        </section>

        <section>
            <h2>Features of the CfCbazar Speed Test</h2>
            <ul>
                <li>Parallel stream testing (2 → 8 connections)</li>
                <li>Real‑time Chart.js graph</li>
                <li>Console‑style debug output</li>
                <li>Progress bar feedback</li>
                <li>Automatic outlier removal</li>
                <li>Fully client‑side — no backend required</li>
            </ul>
        </section>

        <section>
            <h2>Try the Full Demo</h2>
            <p>
                The full speed test is available on the CfCbazar DIY platform.
                Click the button below to launch the live version.
            </p>

            <div style="text-align:center; margin-top:20px;">
                <button id="startTest" class="btn-primary">Start Test</button>
            </div>

            <p style="margin-top:10px;">
                👉 Or open it directly:
                <a href="/diy/speed/" class="btn-link">cfcbazar.8bit.ca/diy/speed/</a>
            </p>
        </section>

        <section>
            <h2>Get the Full Source Code</h2>
            <p>
                If you want to study or modify the complete JavaScript speed‑test engine, you can find the full project in the
                CfCbazar DIY GitHub repository:
            </p>

            <a href="https://github.com/ArakelTheDragon/Library_Other" target="_blank" rel="noopener" class="btn-link">
                🔗 View the Source Code on GitHub
            </a>

            <p class="note">
                The repository includes the full parallel‑stream engine, charting logic, and standalone demo version.
            </p>
        </section>

        <section>
            <h2>What You Can Learn From This Project</h2>
            <ul>
                <li>Browser streaming APIs</li>
                <li>Network throughput measurement</li>
                <li>Handling large downloads efficiently</li>
                <li>Parallelism and concurrency in JavaScript</li>
                <li>Real‑time data visualization</li>
            </ul>
        </section>

        <section>
            <h2>Final Thoughts</h2>
            <p>
                This project demonstrates how powerful modern browsers have become. With just HTML, CSS, and JavaScript, you can
                build a fully functional speed‑test tool that rivals commercial services — and you control every part of it.
            </p>
            <p>
                Explore more DIY projects on the CfCbazar platform and start building your own tools today.
            </p>
        </section>

    </div>
</main>

<script>
    document.getElementById("startTest").addEventListener("click", () => {
        window.location.href = "/diy/speed/";
    });
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/js_html_internetspeedtest",
    "Speed Test Article Source Code"
);
include_footer();
close_database();
?>
