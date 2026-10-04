<?php
// index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
//require_once __DIR__ . '/system/sync.php';
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
require_database_connection();
checkSystemFlags();
trackVisit("index-main");

// ------------------------
// User
// ------------------------
session_check();
$email = null;
$is_logged_in = is_logged_in($email);
$csrf = csrf_token(); // Optional

// ------------------------
// Layout
// ------------------------
$title = "CfCbazar - Smart Deals, DIY, Games, Music & the WorkToken";
include_header($title);
include_menu();

showAdvertPopup();
render_top_userbar();
?>

<main class="container home-container">

    <h1 class="page-title">✨ CfCbazar</h1>

    <section class="welcome-card">

        <h2>Your Hub for Smart Deals and Tools</h2>

<?php if ($is_logged_in): ?>

    <p>
        Welcome back,
        <strong><?= htmlspecialchars($email) ?></strong>!
        Explore Smart Deals, DIY projects, games and music — all designed to help you save money, learn new skills and enjoy useful digital tools.
    </p>

<?php else: ?>

    <p>
        Welcome to <strong>CfCbazar</strong> — your marketplace for Smart Deals,
        DIY tools, games and music. Discover practical digital products made for everyday people.
    </p>

<?php endif; ?>

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
                <span>Visit eBay Store</span>
            </a>

            <a href="https://www.amazon.com/stores/CfCbazar-Group/author/B0HJF163J2?ref=ap_rdr&shoppingPortalEnabled=true&ccs_id=d10f1d56-3d37-42e0-a7e1-6a95400bfe72" target="_blank" class="link-card">
                🚚
                <span>Visit KDP Store</span>
            </a>

            <a href="/diy/ai-system/index.php" class="link-card">
                🛠️
                <span>PHP Only AI System</span>
            </a>
            
            <a href="/diy/speed/index.php" class="link-card">
                🛠️
                <span>Internet Speed Test</span>
            </a>
            
            <a href="/diy/index.php" class="link-card">
                🛠️
                <span>DIY Tools</span>
            </a>  
            
            <a href="/games/index.php" class="link-card">
                🎮
                <span>Games</span>
            </a>    
            
            <a href="https://www.youtube.com/@cfcbazar/playlists" class="link-card">
                📺
                <span>Free TV and Entertainment</span>
            </a>   
            
            <a href="/diy/pinglatency/index.php" class="link-card">
                🛠️
                <span>Ping & Latency Monitor</span>
            </a>   

            <a href="/diy/survival/index.php" class="link-card">
                🛠️
                <span>Individual & Business Budget Calc</span>
            </a>   

            <a href="diy/photo-converter/index.php" class="link-card">
                🛠️
                <span>Photo Converter</span>
            </a>                

            <a href="/worktoken/index.php" class="link-card">
                💰
                <span>Worker Dashboard</span>
            </a>

            <a href="/pow/" class="link-card">
                💰
                <span>Proof of Work/Utility Center</span>
            </a>
            
            <a href="/help/" class="link-card">
                ❓
                <span>Help Center</span>
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
