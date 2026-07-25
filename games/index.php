<?php
// /games/index.php

require_once __DIR__ . '/../includes/reusable.php';

// --- Core ---
enforce_https();
checkSystemFlags($conn);
trackVisit("games/index.php");

$title = "CfCbazar Games - Play Free Browser Games";

// Optional login
$email = $_SESSION['email'] ?? '';

// --- Layout ---
include_header();
include_menu();

showAdvertPopup();
render_top_userbar();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>WorkToken Dashboard | Play Free Online Games & Earn Tokens</title>

  <link rel="stylesheet" href="/css/styles.css">

  <style>
    .search-box {
        margin: 20px 0;
        text-align: center;
    }
    .search-box input {
        width: 100%;
        max-width: 500px;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 1.1rem;
    }
  </style>
</head>

<body>

<div class="container">

  <div class="header">
      <h1>🎮 WorkToken Game Dashboard<?= $email ? ' — ' . htmlspecialchars($email) : '' ?></h1>
  </div>

  <h2 class="page-title">Play Games & Earn Tokens</h2>

  <!-- SEARCH BAR -->
  <div class="search-box">
      <input type="text" id="searchInput" placeholder="Search games...">
  </div>

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

  <div class="balance-box">Explore our games below.</div>
  <div><a href="/index.php">🏠 Go to Home</a></div>

  <main>

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

  </main>

</div>

</body>
</html>

<?php
cfc_footer(
    "https://github.com/ArakelTheDragon/",
    "Games Source Code"
);

include_footer();
close_database();
?>
