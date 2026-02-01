<?php  
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
  
$wallet = $mintme_wallet; // ✅ CORRECT – uses the variable from config.php
$api_id = $private_key;
$api_key = $api_key;
  
$session_expiry_seconds = 300;  
  
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
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: 'Inter', system-ui, sans-serif;
    background: #f5f7fa;
    color: #222;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
  }
  main {
    background: #fff;
    max-width: 480px;
    width: 100%;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgb(0 0 0 / 0.08);
    padding: 30px 30px 40px;
    margin-bottom: 20px;
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
    margin-bottom: 20px;
  }
  .label {
    color: #555;
    font-weight: 500;
    margin-bottom: 6px;
  }
  .card {
    background: #f9fafb;
    border: 1.5px solid #ddd;
    border-radius: 10px;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
    word-wrap: break-word;
    overflow-wrap: anywhere;
    flex-wrap: wrap;
  }
  .copy-container {
    gap: 12px;
    width: 100%;
  }
  .copy-text {
    flex: 1;
    font-family: monospace;
    font-weight: 600;
    font-size: 1.1rem;
    color: #1e293b;
    word-wrap: break-word;
    overflow-wrap: anywhere;
    white-space: normal;
  }
  button.copy-btn, button#deposit-btn, button#home-btn {
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    transition: 0.3s;
  }
  button.copy-btn {
    background: #2563eb;
    color: white;
    padding: 10px 18px;
  }
  button.copy-btn:hover { background: #1d4ed8; }
  button#deposit-btn {
    background: #2563eb;
    color: white;
    padding: 16px 0;
    font-size: 1.25rem;
    width: 100%;
    margin-top: 10px;
  }
  button#home-btn {
    background: #6b7280;
    color: white;
    padding: 14px 0;
    font-size: 1.1rem;
    width: 100%;
    margin-top: 10px;
  }
  .copy-feedback {
    margin-left: 10px;
    color: #16a34a;
    font-weight: 700;
    opacity: 0;
    transition: 0.3s;
  }
  .copy-feedback.visible { opacity: 1; }
  .instructions {
    font-size: 1rem;
    color: #374151;
    line-height: 1.4;
    text-align: center;
    margin: 10px 0 0;
  }
  .countdown, .result {
    text-align: center;
    font-weight: 700;
    font-size: 1.3rem;
    margin-top: 15px;
  }
  .countdown { color: #dc2626; }
  .result { font-size: 1.2rem; }
  ul {
    padding-left: 20px;
    font-size: 1rem;
    color: #374151;
  }
  ul li {
    margin-bottom: 10px;
    word-wrap: break-word;
    overflow-wrap: anywhere;
  }
  code, a {
    word-wrap: break-word;
    overflow-wrap: anywhere;
    white-space: normal;
  }
</style>
</head>  
<body>  

  <main>
    <h2>Deposit to Get 100 WorkTokens</h2>
    <div class="user-info">👤 Logged in as: <strong><?= htmlspecialchars($email) ?></strong></div>

    <div class="label">💰 Deposit exactly:</div>
    <div class="card copy-container">
      <div id="amountText" class="copy-text"><?= number_format($unique_amount, 4) ?> MINTME</div>
      <button class="copy-btn" onclick="copyToClipboard('amountText', this)">Copy</button>
      <span class="copy-feedback" id="amountCopiedMsg">Copied!</span>
    </div>

    <div class="label">🪙 To Wallet:</div>
    <div class="card copy-container">
      <div id="walletText" class="copy-text"><?= $mintme_wallet ?></div>
      <button class="copy-btn" onclick="copyToClipboard('walletText', this)">Copy</button>
      <span class="copy-feedback" id="walletCopiedMsg">Copied!</span>
    </div>

    <p class="instructions">⚠️ Make sure the amount is exact.<br>
      <small>This amount is valid for 5 minutes.</small>
    </p>

    <button id="deposit-btn" onclick="startTimer()">I Deposited the Amount</button>
    <button id="home-btn" onclick="window.location.href='https://cfcbazar.ct.ws'">🏠 Home</button>

    <div id="countdown" class="countdown" style="display:none">⏱️ Time left: <span id="timer">60</span> seconds</div>
    <div id="result" class="result" style="display:none;"></div>
  </main>

  <main>
    <h2>💻 Mine MINTME & Earn WorkTokens</h2>
    <p class="instructions">You can mine MINTME coins by:</p>
    <ul>
      <li>📌 <strong>Register for a wallet:</strong><br>
        <a href="https://www.mintme.com/token/WorkTH/invite" target="_blank">https://www.mintme.com/token/WorkTH/invite</a>
      </li>
      <li>💾 <strong>Download the miner:</strong><br>
        <a href="https://github.com/mintme-com/miner/releases/tag/v2.8.0" target="_blank">MintMe Miner v2.8.0</a>
      </li>
      <li>🚀 <strong>Run this command:</strong><br>
        <code>webchain-miner -o web-ko1.gonspool.com:3333 -u AddressWallet -p x</code>
      </li>
      <li>🌐 <strong>Easy pool:</strong><br>
        <a href="https://web.gonspool.com/#/" target="_blank">https://web.gonspool.com/#/</a>
      </li>
      <li>⚙️ <strong>Hard pool:</strong><br>
        <a href="https://www.mintme.com/pool/#/" target="_blank">https://www.mintme.com/pool/#/</a>
      </li>
    </ul>
    <p class="instructions">Earn MINTME by mining, then come back here to convert it into WorkTokens.</p>
  </main>

  <script>
    function copyToClipboard(id, btn) {
      const text = document.getElementById(id).textContent.trim();
      navigator.clipboard.writeText(text).then(() => {
        const msg = btn.nextElementSibling;
        msg.classList.add('visible');
        setTimeout(() => { msg.classList.remove('visible'); }, 1500);
      }).catch(() => {
        alert('Copy failed. Please copy manually.');
      });
    }

    let seconds = 60;
    let interval, checkInterval;

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