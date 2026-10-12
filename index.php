<?php
// /index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/includes/reusable.php';

if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();

// --- Page metadata -----------------------------------------------------------
$url        = "/index.php";
$parent_url = NULL;
$title      = "Main home page";
$template   = "Generic";   // optional: e.g. "tool", "article", "game"
$meta_title = "Your Hub for Free DIY Smart Deals, Tools & Entertainment";
$meta_desc  = "Welcome to CfCbazar — we build our own free DIY smart deals, open source tools, web games, TV & entertainment and paid eGuides and eBooks. Discover practical digital products made for everyday people.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

// ------------------------
// No session, no login state — this page is fully public.
// ------------------------

// ------------------------
// Layout
// ------------------------
$title = "CfCbazar - A DIY producer of free smart deals, open source tools, web games, music & TV, eGuides and eBooks";
$additional = "We make all things by ourselves, check our work and see if it can help you";
include_header($title, $additional);
include_menu();

showAdvertPopup();
render_top_userbar();
?>

<main class="container home-container">

    <h1 class="page-title">✨ CfCbazar</h1>

    <section class="welcome-card">

        <h2>Your Hub for Free DIY Smart Deals, Tools & Entertainment</h2>

        <p>
            Welcome to <strong>CfCbazar</strong> — we build our own free DIY smart deals, open source tools, web games, TV & entertainment and paid eGuides and eBooks. Discover practical digital products made for everyday people.
        </p>

        <p>
            Join the platform and explore online tools, printable products, planners,
            guides, and helpful resources created to make life easier and more affordable.
        </p>

    </section>

    <section class="card">

        <h2>Latest News</h2>

        <p>
            WorkToken aims to represent the value of one hour of work.
        </p>

        <p>
            Read the latest updates on the News page.
        </p>

        <p>
            <a href="/system/news.php">
                View News
            </a>
        </p>

    </section>

    <section class="card">

        <h2>Explore CfCbazar</h2>

        <div class="links-grid">

            <a href="https://ebay.us/m/DM1tRs" target="_blank" class="link-card">
                🚚
                <span>Visit our eBay Store<br><small style="color:#b26a00;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Paid eBooks</small></span>
            </a>

            <a href="https://www.amazon.com/stores/CfCbazar-Group/author/B0HJF163J2?ref=ap_rdr&shoppingPortalEnabled=true&ccs_id=d10f1d56-3d37-42e0-a7e1-6a95400bfe72" target="_blank" class="link-card">
                🚚
                <span>Visit our KDP Store<br><small style="color:#b26a00;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Paid eBooks</small></span>
            </a>

            <a href="/diy/ai-system/index.php" class="link-card">
                🛠️
                <span>PHP Only AI System<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free open source tool</small></span>
            </a>

            <a href="/diy/speed/index.php" class="link-card">
                🛠️
                <span>Internet Speed Test<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free open source tool</small></span>
            </a>

            <a href="/diy/index.php" class="link-card">
                🛠️
                <span>DIY Tools<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free open source tools</small></span>
            </a>

            <a href="/games/index.php" class="link-card">
                🎮
                <span>Games<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free web games</small></span>
            </a>

            <a href="https://www.youtube.com/@cfcbazar/playlists" class="link-card">
                📺
                <span>Free TV and Entertainment<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free</small></span>
            </a>

            <a href="/diy/pinglatency/index.php" class="link-card">
                🛠️
                <span>Ping &amp; Latency Monitor<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free open source tool</small></span>
            </a>

            <a href="/diy/survival/index.php" class="link-card">
                🛠️
                <span>Individual &amp; Business Budget Calc<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free open source tool</small></span>
            </a>

            <a href="diy/photo-converter/index.php" class="link-card">
                🛠️
                <span>Photo Converter<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free open source tool</small></span>
            </a>

            <a href="/worktoken/index.php" class="link-card">
                💰
                <span>Worker Dashboard<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free to mine</small></span>
            </a>

            <a href="/pow/" class="link-card">
                💰
                <span>Proof of Work / Utility Center<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free System Tool</small></span>
            </a>

            <a href="/help/" class="link-card">
                ❓
                <span>Help Center<br><small style="color:#1b8a3a;font-size:.78rem;font-weight:600;letter-spacing:.3px;">Free System Tool</small></span>
            </a>

        </div>

    </section>

</main>

<?php

cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/index.php",
    "Main Index Source Code"
);
include_footer();
close_database();

?>
