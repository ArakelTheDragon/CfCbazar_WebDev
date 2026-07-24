<?php
// index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$title = "CfCbazar - Smart Deals, DIY, Games, Music & the WorkToken";

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/includes/reusable.php';

if (file_exists($reusablePath)) {
    require_once $reusablePath;

    trackVisit("index-main");
}

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags($conn);

// ------------------------
// User
// ------------------------
$is_logged_in = is_logged_in($email);

// ------------------------
// Layout
// ------------------------
include_header();
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
                Explore Smart Deals, DIY, Games, Music and the WorkToken ecosystem.
            </p>

        <?php else: ?>

            <p>
                Welcome to <strong>CfCbazar</strong>, your marketplace for Smart Deals,
                DIY projects, games, music and the WorkToken ecosystem.
            </p>

        <?php endif; ?>

        <p>
            Join the platform and discover online tools, Proof of Work mining,
            printable products and blockchain utilities.
        </p>

    </section>

    <?php render_token_price_tracker(); ?>

    <section class="card">

        <h2>Trade WorkTokens</h2>

        <p>
            Trade WTK and WorkTHR using PancakeSwap.
        </p>

        <p>
            <a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D" target="_blank">
                Open PancakeSwap
            </a>
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
            <a href="/news.php">
                View News
            </a>
        </p>

    </section>

    <section class="card">

        <h2>Explore CfCbazar</h2>

        <div class="links-grid">

            <a href="/diy/speed/" class="link-card">
                📡
                <span>Internet Speed Test</span>
            </a>

            <a href="/d.php" class="link-card">
                💰
                <span>Worker Dashboard</span>
            </a>

            <a href="/pow/" class="link-card">
                ⛏️
                <span>Mine WorkTokens</span>
            </a>

            <a href="https://ebay.us/m/DM1tRs" target="_blank" class="link-card">
                🚚
                <span>Visit Store</span>
            </a>

        </div>

    </section>

</main>

<?php

cfc_footer(
    "https://github.com/YOUR_USERNAME/YOUR_REPO",
    "Main Index Source Code"
);
include_footer();
close_database();

?>
