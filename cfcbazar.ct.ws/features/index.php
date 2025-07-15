<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit();
}
$email = $_SESSION['username'];
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
    <h2>Games</h2>
    <p>Dis you want the games page instead?</p>
    <a href="/games/">Go to games!</a>
  </div>

  <div class="card">
    <h2>🔗 URL Shortener</h2>
    <p>Share links, pay with tokens based on traffic.
</p>
    <a href="/r.php">URL shortener</a>
  </div>    
  
  <div class="card">
 <h2>🔌 Power calc</h2>    
<p>Check the power consumption of your devices & how much it costs you.
</p>    
<a href="/features/power.php">Power calc</a>
  </div>  
  
  <div class="card">
<h2>💲Survival tool</h2>
<p>Check your expenses for basic survival.</p>   
 <a href="/features/survival.php">Survival tool</a>  
</div>

<div class="card">
    <h2>💼 Work vakue table</h2>
    <p>Check the value of work in different regions for different professions, products & services.</p>
    <a href="/work_value.php">Work value table</a>
  </div>    


  <div class="card">
    <h2>🧠 Quizzes & Tasks</h2>
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