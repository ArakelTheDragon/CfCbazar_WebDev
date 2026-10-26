<?php
// /games/index.php
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
require_once __DIR__ . '/../includes/settings_local.php';
// settings_local.php sets:
$NEED_SESSION = true;
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
$url        = "/games/index.php";
$parent_url = "/games";
$title      = "CfCbazar Games - Play Free Browser Games";
$template   = "Generic";
$meta_title = "WorkToken Dashboard | Play Free Online Games & Earn Tokens";
$meta_desc  = "Play free browser games on CfCbazar — Squares, Battle Fleet, Typing Speed, Memory Match, Slot Machine, Wheel of Fortune and more.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

// Optional login display
$email = $_SESSION['email'] ?? '';

render_header($title);
?>

<main class="content">
    <div class="container">

        <div class="header">
            <h1>🎮 WorkToken Game Dashboard<?= $email ? ' — ' . htmlspecialchars($email) : '' ?></h1>
        </div>

        <h2 class="page-title">Play Games &amp; Earn Tokens</h2>

        <div class="search-box" style="margin:20px 0;text-align:center;">
            <input type="text" id="searchInput" placeholder="Search games..."
                   style="width:100%;max-width:500px;padding:12px;border-radius:8px;border:1px solid #ccc;font-size:1.1rem;">
        </div>

        <div class="balance-box">Explore our games below.</div>
        <div><a href="/index.php">🏠 Go to Home</a></div>

        <section class="card">
            <h2>Platform Features</h2>
            <p>See all the exciting features we offer.</p>
            <a href="/diy/">Go to Features</a>
        </section>

        <section class="card"><h2>Squares</h2><p>Squares.</p><a href="/games/squares/">Play Squares</a></section>

        <section class="card"><h2>Battle Fleet</h2><p>Battle the enemy ships.</p><a href="/games/battle-fleet/">Play Battle Fleet</a></section>

        <section class="card"><h2>😁 Typing Speed Challenge</h2><p>Test your typing skills.</p><a href="/games/type/">Play Type</a></section>

        <section class="card"><h2>⭕ Circle Click Game</h2><p>Click the circle fast!</p><a href="/games/circle/">Play Circle</a></section>

        <section class="card"><h2>🧺 Basket Catch</h2><p>Catch items in your basket.</p><a href="/games/basket/">Play Basket</a></section>

        <section class="card"><h2>🎴 Memory Match</h2><p>Flip and match the cards.</p><a href="/games/memory-match/">Play Memory Match</a></section>

        <section class="card"><h2>🎰 Slot Machine</h2><p>Try your luck and earn tokens.</p><a href="/games/slot/index.php">Play Slot Machine</a></section>

        <section class="card"><h2>🎡 Wheel of Fortune</h2><p>Spin to win or lose tokens.</p><a href="/games/wheel/index.php">Spin the Wheel</a></section>

        <section class="card"><h2>☠️ Maze Escape</h2><p>Find your way out of the maze.</p><a href="/games/maze/">Play Maze</a></section>

        <section class="card"><h2>🆒 Word Guess</h2><p>Guess the word before time runs out.</p><a href="/games/word/">Play Word Guess</a></section>

        <section class="card"><h2>🔢 Math Puzzle</h2><p>Solve math challenges.</p><a href="/games/number/">Solve the Problem</a></section>

        <section class="card"><h2>🐥 Flop Game</h2><p>Flap through obstacles.</p><a href="/games/flop/">Play Flop</a></section>

        <section class="card"><h2>🦕 Dino Run</h2><p>Help the dinosaur survive.</p><a href="/games/dino/">Play Dino</a></section>

        <section class="card"><h2>🧠 Coming Soon</h2><p>New games and challenges coming soon!</p><a href="#">Coming Soon</a></section>

    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const cards = document.querySelectorAll(".card");

    searchInput.addEventListener("input", () => {
        const query = searchInput.value.toLowerCase();

        cards.forEach(card => {
            const text = card.innerText.toLowerCase();
            card.style.display = text.includes(query) ? "block" : "none";
        });
    });
});
</script>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/games",
    "Games Source Code"
);
include_footer();
close_database();
?>
