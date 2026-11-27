<?php
// /diy/sky-stream-glass-guide/index.php
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
// $NEED_SESSION       = false;
// $NEED_LOGIN         = false;
// $NEED_CART          = false;
// $NEED_ADMIN         = false;   // 1
// $NEED_MODERATOR     = false;   // 2
// $NEED_CONTRIBUTOR   = false;   // 3
// $NEED_VIP           = false;   // 4
// $NEED_STANDARD_USER = false;   // 5
// $IS_FRONTEND        = true;
// $IS_BACKEND         = false;

// ------------------------

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/ebook-glass-stream-ts/index.php";
$parent_url = "/diy/";
$title      = "Fix Common Sky Stream and Sky Glass Problems — CfCbazar DIY";
$template   = "Generic";
$meta_title = "Fix Common Sky Stream and Sky Glass Problems";
$meta_desc  = "Practical troubleshooting guide for Sky Stream and Sky Glass users by CfCbazar Group, covering setup, network, playback, recovery, and diagnostics.";

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

// --- page body (high-level functions only) ---
?>
<main class="content">
    <div class="container">
        <h1 class="page-title">Fix Common Sky Stream and Sky Glass Problems</h1>
        <p class="subtitle">A practical troubleshooting guide for Sky Stream and Sky Glass users by CfCbazar Group.</p>

        <div class="card">
            <h2>Guide Overview</h2>
            <p>
                Sky Stream uses a separate streaming puck connected to a television through HDMI, while Sky Glass has the streaming hardware built into the television, with both relying on broadband rather than a satellite dish or aerial for live Sky channels.
                This guide organizes troubleshooting around a six-step diagnostic loop (restart, check connections, check picture settings, check sound settings, isolate the problem, and escalate).
            </p>
            <p>
                <strong>Amazon Listing (ASIN B0HSJDYGW4):</strong>
                <a href="https://www.amazon.com/dp/B0HSJDYGW4" target="_blank" rel="noopener noreferrer">https://www.amazon.com/dp/B0HSJDYGW4</a>
            </p>
            <p>
                <strong>Online Help Center:</strong>
                <a href="https://cfcbazar.cloud-ip.cc/diy/sky-fix/" target="_blank" rel="noopener noreferrer">https://cfcbazar.cloud-ip.cc/diy/sky-fix/</a>
            </p>
        </div>

        <div class="card">
            <h2>What&apos;s Inside</h2>
            <ul>
                <li><strong>Foundations &amp; Setup:</strong> Broadband speed allowances (30 Mbps base for one HD puck, +5 Mbps per UHD stream, additional puck/Glass TV, Sky Sports, or Sky Cinema, with a suggested 75+ Mbps household target), remote pairing/reset sequence (4+6, 7+9, 1+3), and Wi-Fi/Ethernet setup.</li>
                <li><strong>Common Failure Modes &amp; Tuning:</strong> Boot and logo stalls, playback/app issues, HD vs. Auto streaming format guidance, PCM stereo testing for soundbars, ventilation (~10 cm clearance), and performance tuning.</li>
                <li><strong>Recovery, Escalation &amp; Appendices:</strong> Differences between normal restart, mains power cycle, network reset, Standby-button recovery procedure (up to 15 minutes), and factory reset, plus a glossary, pre-support checklist, diagnostic flowchart, and FAQ.</li>
            </ul>
        </div>

        <div class="card">
            <h2>License &amp; Disclaimer</h2>
            <p>
                Issued under Master Resell Rights (MRR) by CfCbazar Group with terms permitting personal/commercial use, reselling as-is, passing on resell rights, and bundling, while prohibiting modification/rebranding, unbundled free distribution, or claiming Sky affiliation.
                Independently produced by CfCbazar Group; not affiliated with or endorsed by Sky UK Limited.
            </p>
        </div>
    </div>
</main>
<?php

// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/sky-stream-glass-guide/index.php",
    "Source Code"
);
include_footer();
close_database();
?>
