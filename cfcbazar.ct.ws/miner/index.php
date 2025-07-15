<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit();
}
$email = $_SESSION['username'];

require 'config.php'; // Adjust path as needed

// Optional: Fetch more user details if needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Duino Miner Dashboard</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #edf2f7;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-height: 100vh;
    }
    .topbar {
      width: 100%;
      background: #2a405f;
      color: white;
      padding: 12px 20px;
      text-align: right;
      font-size: 14px;
    }
    .card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      margin-top: 50px;
      padding: 30px;
      width: 340px;
      text-align: center;
    }
    h1 {
      margin-top: 0;
      color: #2a405f;
    }
    .status {
      color: #555;
    }
    .balance {
      font-weight: bold;
      color: #008060;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="topbar">
    Logged in as: <strong><?php echo htmlspecialchars($email); ?></strong>
  </div>

  <div class="card">
    <h1>Duino Miner</h1>
    <p class="status">Mining WorkTokens through Duino-Coin... earning 0.000001 WorkTokens every 5 seconds ⛏️</p>
    <p class="balance" id="balance">Checking balance...</p>
  </div>
  
<div style="margin-top: 40px; text-align: center; font-size: 13px; color: #666;">
  By using this site, you agree to our 
  <a href="terms.html" style="color: #2a405f; text-decoration: underline;">Terms & Conditions</a>, 
  <a href="privacy.html" style="color: #2a405f; text-decoration: underline;">Privacy Policy</a>, and 
  <a href="cookies.html" style="color: #2a405f; text-decoration: underline;">Cookie Policy</a>.
</div>

  <script>
    const userEmail = "<?php echo $email; ?>";
    const username = "PhoneMiner";
    const rigid = userEmail;
    const threads = 1;
    const miningkey = null;

    function startMiner(username, rigid, threads, miningkey) {
      const worker = new Worker("/miner/worker.js");
      worker.postMessage([username, rigid, 0, miningkey]);
    }

    function rewardUser(email) {
      fetch("/worker.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ email: email, tokens: 0.000001 })
      }).catch(err => console.error("Reward error:", err));
    }

    function checkBalance(email) {
      fetch(`/worker.php?email=${encodeURIComponent(email)}`)
        .then(res => res.json())
        .then(data => {
          const balance = data.tokens_earned ?? 0;
          document.getElementById("balance").innerText =
            `WorkToken Balance: ${balance.toFixed(6)}`;
        })
        .catch(() => {
          document.getElementById("balance").innerText = "Failed to load balance.";
        });
    }

    startMiner(username, rigid, threads, miningkey);
    checkBalance(userEmail);
    setInterval(() => {
      rewardUser(userEmail);
      checkBalance(userEmail);
    }, 5000);
  </script>
</body>
</html>