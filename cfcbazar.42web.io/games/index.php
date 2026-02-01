<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: /login.php');
    exit();
}
$email = $_SESSION['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>WorkToken Dashboard</title>
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
  <h1>Welcome, <?php echo htmlspecialchars($email); ?>!</h1>
  <div class="balance-box">Current Balance: <span id="balance">Loading...</span> WorkTokens</div>

  <div class="card">
  <h2> Features </h2>
  <p>Did you want the features page instead?</p>
  <a href="/features/">Go to features!</a>
</div>

  <div class="card">
    <h2>🎰 Slot Game </h2>
    <p>Play and try your luck to earn even more WorkTokens.</p>
    <a href="slot.php">Play Slot Machine</a>
  </div>
  
<div class="card">
  <h2>🎲 Wheel of Fortune </h2>
  <p>Spin to win or lose WorkTokens�are you feeling lucky?</p>
  <a href="wheel.php">Spin the Wheel</a>
</div>

<div class="card">
  <h2>☠️ Maze </h2>
  <p>Get out of the maze�are you feeling lucky?</p>
  <a href="maze.php">Maze</a>
</div>

<div class="card">
  <h2>🆒 Word guess </h2>
  <p>Guess the word!</p>
  <a href="word.php">Word guess</a>
</div>

<div class="card">  
<h2>🔢 Solve the problem</h2>  
<p>Can you aolve this 1*5/4+3?</p>  
<a href="number.php">Solve the problem</a></div>

<div class="card">  
<h2>🐥 Flop </h2>  
<p>Flop flop and go through the obstacles?</p>  
<a href="flop.php">Flop play</a></div>

<div class="card">  
<h2>🦕 Dino </h2>  
<p>Get the dino through?</p>  
<a href="dino.php">Dino play</a></div>

  <div class="card">
    <h2>🧠 Other games</h2>
    <p>Participate and get rewarded. (Coming soon!)</p>
    <a href="#">Coming Soon</a>
  </div>

  <script>
    const userEmail = "<?php echo $email; ?>";

    fetch('/worker.php?email=' + encodeURIComponent(userEmail))
      .then(res => res.json())
      .then(data => {
        document.getElementById('balance').textContent = parseFloat(data.tokens_earned).toFixed(5);
      })
      .catch(() => {
        document.getElementById('balance').textContent = "Error loading balance";
      });
  </script>
</body>
</html>