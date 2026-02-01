<?php
// stake/test.php - Deposit MINTME for WorkTokens
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include(__DIR__ . '/../config.php');

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit();
}

// Optional logout from this page
if (isset($_GET['logout'])) {
    session_destroy();
    unset($_SESSION['username']);
    header('Location: /login.php');
    exit();
}

$email = $_SESSION['username'];

$mintme_wallet = "0xe8911e98a00d36a1841945d6270611510f1c7e88";
$api_id  = "f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3";
$api_key = "eafc42e3628bc072cb643e855d24b9206598000f91088bdb980f49810d2a1132";

// Handle AJAX check for deposit
if (isset($_GET['check']) && isset($_SESSION['expected_amount'])) {
    $expected_amount = $_SESSION['expected_amount'];
    $start_time = $_SESSION['amount_time'];

    // Expire after 90 seconds
    if (time() - $start_time > 90) {
        echo "❌ Session expired.";
        exit;
    }

    $url = "https://www.mintme.com/dev/api/v2/auth/user/wallet/history?offset=0&limit=30";
    $headers = [
        "accept: application/json",
        "X-API-ID: $api_id",
        "X-API-KEY: $api_key"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    $transactions = json_decode($response, true);

    if (is_array($transactions)) {
        foreach ($transactions as $tx) {
            if (
                $tx['status']['statusCode'] === 'paid' &&
                $tx['type']['typeCode'] === 'deposit' &&
                $tx['address'] === $mintme_wallet &&
                floatval($tx['amount']) == floatval($expected_amount)
            ) {
                // Credit 100 WorkTokens to user
                $stmt = $conn->prepare("UPDATE workers SET tokens = tokens + 100 WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();

                // Prevent duplicate crediting
                unset($_SESSION['expected_amount'], $_SESSION['amount_time']);

                echo "✅ 100 WorkTokens credited!";
                exit;
            }
        }
    }

    echo "⏳ Waiting for exact deposit of $expected_amount MINTME...";
    exit;
}

// Initial page load — generate unique amount if not set or expired
if (!isset($_SESSION['expected_amount']) || (time() - ($_SESSION['amount_time'] ?? 0)) > 90) {
    $_SESSION['expected_amount'] = round(10 + mt_rand(1, 9999) / 10000, 4);
    $_SESSION['amount_time'] = time();
}

$unique_amount = $_SESSION['expected_amount'];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Deposit MINTME ➜ Get WorkTokens</title>
  <style>
    body { font-family: Arial; max-width: 500px; margin: auto; padding: 20px; }
    .info, .result { background: #f9f9f9; padding: 15px; margin-top: 20px; border-left: 4px solid #ccc; }
    button { padding: 12px; font-size: 16px; width: 100%; margin-top: 15px; cursor: pointer; }
    .timer { font-weight: bold; color: red; }
  </style>
</head>
<body>
  <h2>Deposit to Get 100 WorkTokens</h2>

  <div class="info">
    👤 Logged in as: <strong><?= htmlspecialchars($email) ?></strong><br>
    💰 Deposit exactly: <strong><?= number_format($unique_amount, 4) ?> MINTME</strong><br>
    🪙 To Wallet: <code><?= $mintme_wallet ?></code><br><br>
    ⚠️ Make sure the amount is exact or the transaction will fail! Then click the button below.
  </div>

  <button onclick="startTimer()">I Deposited the Amount</button>

  <div id="countdown" style="display:none" class="info">
    ⏱️ Time left: <span id="timer" class="timer">60</span> seconds
  </div>

  <div id="result" class="result" style="display:none;"></div>

  <script>
    let seconds = 60;
    let interval;
    let checkInterval;

    function startTimer() {
      document.getElementById("countdown").style.display = "block";
      document.getElementById("result").style.display = "none";

      interval = setInterval(() => {
        seconds--;
        document.getElementById("timer").textContent = seconds;
        if (seconds <= 0) {
          clearInterval(interval);
          clearInterval(checkInterval);
          document.getElementById("result").style.display = "block";
          document.getElementById("result").innerHTML = "❌ Transaction not found within 60 seconds.";
        }
      }, 1000);

      checkInterval = setInterval(() => {
        fetch("test.php?check=1")
          .then(res => res.text())
          .then(msg => {
            if (msg.includes("✅")) {
              clearInterval(interval);
              clearInterval(checkInterval);
              document.getElementById("result").style.display = "block";
              document.getElementById("result").innerHTML = msg;
            }
          });
      }, 5000);
    }
  </script>
</body>
</html>