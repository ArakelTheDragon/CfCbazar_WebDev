<?php
// stake/test.php - Deposit MINTME for WorkTokens with 5-minute unique amount validity and nice mobile style
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include(__DIR__ . '/../config.php');

if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    unset($_SESSION['username']);
    header('Location: /login.php');
    exit();
}

$email = $_SESSION['username'];

$mintme_wallet = "0xe8911e98a00d36a1841945d6270610f1c7e88";
$api_id  = "f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3";
$api_key = "eafc42e3628bc072cb643e855d24b9206598000f91088bdb980f49810d2a1132";

$session_expiry_seconds = 300; // 5 minutes

if (isset($_GET['check']) && isset($_SESSION['expected_amount'])) {
    $expected_amount = $_SESSION['expected_amount'];
    $start_time = $_SESSION['amount_time'];

    if (time() - $start_time > $session_expiry_seconds) {
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
            $amount = floatval($tx['amount']);
            if (
                $tx['status']['statusCode'] === 'paid' &&
                $tx['type']['typeCode'] === 'deposit' &&
                $tx['address'] === $mintme_wallet &&
                (
                    $amount === $expected_amount ||
                    $amount === ($expected_amount - 1.0)
                )
            ) {
                $stmt = $conn->prepare("UPDATE workers SET tokens = tokens + 100 WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();

                unset($_SESSION['expected_amount'], $_SESSION['amount_time']);

                echo "✅ 100 WorkTokens credited!";
                exit;
            }
        }
    }

    echo "⏳ Waiting for deposit of exactly $expected_amount or " . ($expected_amount - 1) . " MINTME...";
    exit;
}

if (!isset($_SESSION['expected_amount']) || (time() - ($_SESSION['amount_time'] ?? 0)) > $session_expiry_seconds) {
    $_SESSION['expected_amount'] = round(10 + mt_rand(1, 9999) / 10000, 4);
    $_SESSION['amount_time'] = time();
}

$unique_amount = $_SESSION['expected_amount'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <title>Deposit MINTME → Get WorkTokens</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    /* Reset and base */
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen,
        Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
      background: #f5f7fa;
      color: #222;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding: 20px;
    }
    main {
      background: #fff;
      max-width: 480px;
      width: 100%;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgb(0 0 0 / 0.08);
      padding: 30px 30px 40px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    h2 {
      margin: 0 0 10px;
      font-weight: 600;
      font-size: 1.75rem;
      text-align: center;
      color: #1e3a8a;
      user-select: none;
    }
    .user-info {
      text-align: center;
      font-weight: 600;
      color: #555;
      user-select: none;
    }
    .card {
      background: #f9fafb;
      border: 1.5px solid #ddd;
      border-radius: 10px;
      padding: 15px 20px;
      font-size: 1rem;
      user-select: text;
      word-break: break-word;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .label {
      color: #555;
      font-weight: 500;
      margin-bottom: 6px;
      user-select: none;
    }
    .copy-container {
      display: flex;
      align-items: center;
      gap: 12px;
      width: 100%;
    }
    .copy-text {
      flex: 1;
      font-family: 'Courier New', Courier, monospace;
      font-weight: 600;
      font-size: 1.1rem;
      color: #1e293b;
      overflow-wrap: break-word;
      user-select: text;
    }
    button.copy-btn {
      background: #2563eb;
      color: white;
      border: none;
      padding: 10px 18px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: background-color 0.3s ease;
      user-select: none;
      flex-shrink: 0;
    }
    button.copy-btn:hover,
    button.copy-btn:focus {
      background: #1d4ed8;
      outline: none;
    }
    .copy-feedback {
      margin-left: 10px;
      color: #16a34a;
      font-weight: 700;
      user-select: none;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    .copy-feedback.visible {
      opacity: 1;
    }
    .instructions {
      font-size: 1rem;
      color: #374151;
      line-height: 1.4;
      user-select: none;
      text-align: center;
    }
    button#deposit-btn {
      background: #2563eb;
      color: white;
      border: none;
      padding: 16px 0;
      border-radius: 10px;
      font-size: 1.25rem;
      font-weight: 700;
      cursor: pointer;
      transition: background-color 0.3s ease;
      user-select: none;
      width: 100%;
      margin-top: 15px;
      box-shadow: 0 4px 12px rgb(37 99 235 / 0.4);
    }
    button#deposit-btn:hover,
    button#deposit-btn:focus {
      background: #1e40af;
      outline: none;
      box-shadow: 0 6px 18px rgb(30 64 175 / 0.6);
    }

    /* Home button style */
    button#home-btn {
      background: #6b7280; /* Tailwind gray-500 */
      color: white;
      border: none;
      padding: 14px 0;
      border-radius: 10px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.3s ease;
      user-select: none;
      width: 100%;
      margin-top: 10px;
      box-shadow: 0 3px 8px rgb(107 114 128 / 0.4);
    }
    button#home-btn:hover,
    button#home-btn:focus {
      background: #4b5563; /* Tailwind gray-700 */
      outline: none;
      box-shadow: 0 5px 14px rgb(75 85 99 / 0.6);
    }

    .countdown {
      text-align: center;
      font-weight: 700;
      font-size: 1.3rem;
      color: #dc2626;
      user-select: none;
      margin-top: 15px;
    }
    .result {
      margin-top: 20px;
      font-weight: 600;
      font-size: 1.2rem;
      text-align: center;
      user-select: none;
    }
    @media (max-width: 400px) {
      main {
        padding: 25px 20px 30px;
      }
      .copy-text {
        font-size: 1rem;
      }
      button.copy-btn {
        padding: 8px 14px;
        font-size: 0.9rem;
      }
      button#deposit-btn {
        font-size: 1.1rem;
        padding: 14px 0;
      }
      button#home-btn {
        font-size: 1rem;
        padding: 12px 0;
      }
    }
  </style>
</head>
<body>
  <main>
    <h2>Deposit to Get 100 WorkTokens</h2>
    <div class="user-info">👤 Logged in as: <strong><?= htmlspecialchars($email) ?></strong></div>

    <div>
      <div class="label">💰 Deposit exactly:</div>
      <div class="card copy-container">
        <div id="amountText" class="copy-text"><?= number_format($unique_amount, 4) ?> MINTME</div>
        <button class="copy-btn" aria-label="Copy deposit amount" onclick="copyToClipboard('amountText', this)">Copy</button>
        <span class="copy-feedback" id="amountCopiedMsg">Copied!</span>
      </div>
    </div>

    <div>
      <div class="label">🪙 To Wallet:</div>
      <div class="card copy-container">
        <div id="walletText" class="copy-text"><?= $mintme_wallet ?></div>
        <button class="copy-btn" aria-label="Copy wallet address" onclick="copyToClipboard('walletText', this)">Copy</button>
        <span class="copy-feedback" id="walletCopiedMsg">Copied!</span>
      </div>
    </div>

    <p class="instructions">
      ⚠️ Make sure the amount is exact.<br />
      <small>This amount is valid for 5 minutes. After that, a new amount will be assigned.</small>
    </p>

    <button id="deposit-btn" onclick="startTimer()">I Deposited the Amount</button>
    <button id="home-btn" onclick="window.location.href='https://cfcbazar.ct.ws'">🏠 Home</button>

    <div id="countdown" class="countdown" style="display:none">
      ⏱️ Time left: <span id="timer">60</span> seconds
    </div>

    <div id="result" class="result" style="display:none;"></div>
  </main>

  <script>
    function copyToClipboard(id, btn) {
      const text = document.getElementById(id).textContent.trim();
      navigator.clipboard.writeText(text).then(() => {
        const msg = btn.nextElementSibling;
        msg.classList.add('visible');
        setTimeout(() => { msg.classList.remove('visible'); }, 1500);
      }).catch(() => {
        alert('Copy failed, please copy manually.');
      });
    }

    let seconds = 60;
    let interval;
    let checkInterval;

    function startTimer() {
      document.getElementById("countdown").style.display = "block";
      document.getElementById("result").style.display = "none";
      seconds = 60;
      document.getElementById("timer").textContent = seconds;

      interval = setInterval(() => {
        seconds--;
        document.getElementById("timer").textContent = seconds;
        if (seconds <= 0) {
          clearInterval(interval);
          clearInterval(checkInterval);
          document.getElementById("result").style.display = "block";
          document.getElementById("result").textContent = "❌ Transaction not found within 60 seconds.";
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
              document.getElementById("result").textContent = msg;
            }
          });
      }, 5000);
    }
  </script>
</body>
</html>