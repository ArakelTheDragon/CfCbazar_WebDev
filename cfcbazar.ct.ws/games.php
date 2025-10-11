<?php
// dashboard.php — Public WorkToken Game Dashboard with Visit Tracking
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("config.php");

// Page visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
    $upd->bind_param('s', $path);
    $upd->execute();

    if ($upd->affected_rows === 0) {
        $slug  = ltrim($path, '/');
        $slug  = $slug === '' ? 'index' : $slug;
        $title = 'WorkToken Dashboard';

        $ins = $conn->prepare("
            INSERT INTO pages (title, slug, path, visits, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");
        if ($ins) {
            $ins->bind_param('sss', $title, $slug, $path);
            $ins->execute();
            $ins->close();
        }
    }
    $upd->close();
}

// Optional: get email if logged in
session_start();
$email = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>WorkToken Dashboard | Play Free Online Games & Earn Tokens</title>
  <meta name="description" content="WorkToken Dashboard - Play free online games like typing, basket, memory match, slots, wheel of fortune, maze, word guess, and more. Earn WorkTokens while having fun!" />
  <meta name="keywords" content="WorkToken, dashboard, earn tokens, free games, typing game, circle game, basket game, memory match, slot machine, wheel of fortune, maze, word guess, number puzzles, flop game, dino game, ORPG" />
  <meta name="robots" content="index, follow" />
  <meta property="og:title" content="WorkToken Dashboard | CfCbazar" />
  <meta property="og:description" content="Play free online games and earn WorkTokens. No login required!" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://yourdomain.com/dashboard.php" />
  <meta property="og:image" content="https://yourdomain.com/assets/dashboard-preview.png" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="WorkToken Dashboard | CfCbazar" />
  <meta name="twitter:description" content="Play free online games and earn WorkTokens. No login required!" />
  <meta name="twitter:image" content="https://yourdomain.com/assets/twitter-dashboard.png" />
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f7fa;
      margin: 0;
      padding: 2rem;
      max-width: 600px;
      margin: auto;
      color: #333;
    }
    h1 { color: #007acc; }
    .card {
      background: #fff;
      padding: 1rem 1.5rem;
      border-radius: 8px;
      margin-top: 1.5rem;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    a {
      display: inline-block;
      margin-top: 1rem;
      padding: 10px 20px;
      background: #28a745;
      color: white;
      border-radius: 6px;
      text-decoration: none;
    }
    .balance-box {
      margin-top: 1rem;
      font-size: 1.2em;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <header>
    <h1>Welcome<?= $email ? ', ' . htmlspecialchars($email) : '' ?>!</h1>
    <div class="balance-box">Explore our games below.</div>
  </header>

  <nav>
    <a href="/index.php">🏠 Go to Home</a>
  </nav>

  <main>
    <section class="card">
      <h2>Platform Features</h2>
      <p>See all the exciting features we offer.</p>
      <a href="features.php">Go to Features</a>
    </section>

    <section class="card"><h2>😁 Typing Speed Challenge</h2><p>Test your typing skills.</p><a href="type.php">Play Type</a></section>
    <section class="card"><h2>⭕ Circle Click Game</h2><p>Click the circle fast!</p><a href="circle.php">Play Circle</a></section>
    <section class="card"><h2>🧺 Basket Catch</h2><p>Catch items in your basket.</p><a href="basket.php">Play Basket</a></section>
    <section class="card"><h2>🎴 Memory Match</h2><p>Flip and match the cards.</p><a href="mm.php">Play Memory Match</a></section>
    <section class="card"><h2>🎰 Slot Machine</h2><p>Try your luck and earn tokens.</p><a href="slot.php">Play Slot Machine</a></section>
    <section class="card"><h2>🎡 Wheel of Fortune</h2><p>Spin to win or lose tokens.</p><a href="wheel.php">Spin the Wheel</a></section>
    <section class="card"><h2>☠️ Maze Escape</h2><p>Find your way out of the maze.</p><a href="maze.php">Play Maze</a></section>
    <section class="card"><h2>🆒 Word Guess</h2><p>Guess the word before time runs out.</p><a href="word.php">Play Word Guess</a></section>
    <section class="card"><h2>🔢 Math Puzzle</h2><p>Solve math challenges.</p><a href="number.php">Solve the Problem</a></section>
    <section class="card"><h2>🐥 Flop Game</h2><p>Flap through obstacles.</p><a href="flop.php">Play Flop</a></section>
    <section class="card"><h2>🦕 Dino Run</h2><p>Help the dinosaur survive.</p><a href="dino.php">Play Dino</a></section>
    <section class="card"><h2>WT ORPG</h2><p>Play our online RPG and collect rewards.</p><a href="orpg.php">Play ORPG</a></section>
    <section class="card"><h2>🧠 Coming Soon</h2><p>New games and challenges coming soon!</p><a href="#">Coming Soon</a></section>
  </main>
</body>
</html>