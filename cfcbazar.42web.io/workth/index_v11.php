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
  
// config.php vars  
$mintme_wallet = $mintme_wallet;  
$api_id        = $private_key;  
$api_key       = $api_key;  
  
$session_expiry_seconds = 300; // 5 minutes  
  
// 1) Fetch user ID (case-insensitive)  
$userId = null;  
$stmtUser = $conn->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1");  
$stmtUser->bind_param("s", $email);  
$stmtUser->execute();  
$resUser = $stmtUser->get_result();  
if ($row = $resUser->fetch_assoc()) {  
    $userId = (int)$row['id'];  
}  
$stmtUser->close();  
  
// 2) Compute stable deposit amount  
if ($userId !== null) {  
    $unique_amount = number_format(10 + ($userId / 10000), 4, '.', '');  
} else {  
    $unique_amount = '10.0000';  
}  
  
// 3) Session expiry logic  
if (!isset($_SESSION['expected_amount']) ||  
    (time() - ($_SESSION['amount_time'] ?? 0)) > $session_expiry_seconds) {  
    $_SESSION['expected_amount'] = $unique_amount;  
    $_SESSION['amount_time']    = time();  
}  
  
// 4) Deposit check via MintMe API  
if (isset($_GET['check']) && isset($_SESSION['expected_amount'])) {  
    $expected = $_SESSION['expected_amount'];  
    $start    = $_SESSION['amount_time'];  
  
    if (time() - $start > $session_expiry_seconds) {  
        echo "❌ Session expired.";  exit;  
    }  
  
    $url = "https://www.mintme.com/dev/api/v2/auth/user/wallet/history?offset=0&limit=30";  
    $headers = [  
        "accept: application/json",  
        "X-API-ID: $api_id",  
        "X-API-KEY: $api_key"  
    ];  
    $ch = curl_init($url);  
    curl_setopt_array($ch, [  
        CURLOPT_RETURNTRANSFER => true,  
        CURLOPT_HTTPHEADER     => $headers,  
    ]);  
    $resp = curl_exec($ch);  
    curl_close($ch);  
  
    $txs = json_decode($resp, true) ?: [];  
    foreach ($txs as $tx) {  
        $amt = floatval($tx['amount']);  
        if (  
            $tx['status']['statusCode']==='paid' &&  
            $tx['type']['typeCode']==='deposit' &&  
            $tx['address']===$mintme_wallet &&  
            ($amt === $expected || $amt === ($expected - 1.0))  
        ) {  
            $up = $conn->prepare("UPDATE workers SET tokens = tokens + 100 WHERE email = ?");  
            $up->bind_param("s", $email);  
            $up->execute();  
            $up->close();  
  
            unset($_SESSION['expected_amount'], $_SESSION['amount_time']);  
            echo "✅ 100 WorkTokens credited!";  
            exit;  
        }  
    }  
  
    echo "⏳ Waiting for deposit of exactly $expected MINTME...";  
    exit;  
}  
?>  
<!DOCTYPE html>  
<html lang="en">  
<head>  
  <meta charset="UTF-8"/>  
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>  
  <title>Deposit MINTME → Get WorkTokens</title>  
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet"/>  
  <style>  
    /* Reset and basics */  
    *, *::before, *::after { box-sizing: border-box; }  
    body {  
      margin: 0;  
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;  
      background: linear-gradient(135deg, #f0f4ff, #d9e4ff);  
      color: #222;  
      display: flex;  
      flex-direction: column;  
      align-items: center;  
      min-height: 100vh;  
      padding: 20px 15px 40px;  
    }  
  
    main {  
      background: white;  
      border-radius: 16px;  
      box-shadow: 0 8px 20px rgb(0 0 0 / 0.1);  
      max-width: 480px;  
      width: 100%;  
      padding: 32px 28px;  
      margin-bottom: 30px;  
      display: flex;  
      flex-direction: column;  
      gap: 24px;  
    }  
  
    h2 {  
      font-weight: 700;  
      font-size: 1.9rem;  
      color: #1e40af;  
      text-align: center;  
      user-select: none;  
      margin: 0;  
    }  
  
    .user-info {  
      font-weight: 600;  
      color: #555;  
      text-align: center;  
      font-size: 1rem;  
      word-break: break-word;  
    }  
  
    .label {  
      font-weight: 600;  
      color: #555;  
      margin-bottom: 10px;  
      font-size: 1.1rem;  
    }  
  
    .card {  
      background: #f4f7ff;  
      border: 1.8px solid #b3c0ff;  
      border-radius: 14px;  
      padding: 18px 22px;  
      display: flex;  
      align-items: center;  
      justify-content: space-between;  
      flex-wrap: wrap;  
      gap: 12px;  
      word-break: break-word;  
      font-family: 'Courier New', Courier, monospace;  
      font-weight: 700;  
      font-size: 1.2rem;  
      color: #1e3a8a;  
      user-select: all;  
    }  
  
    .copy-container {  
      display: flex;  
      align-items: center;  
      gap: 14px;  
      width: 100%;  
    }  
  
    button {  
      background: #2563eb;  
      color: white;  
      border: none;  
      border-radius: 12px;  
      padding: 14px 22px;  
      font-weight: 700;  
      font-size: 1.15rem;  
      cursor: pointer;  
      user-select: none;  
      transition: background-color 0.25s ease;  
      flex-shrink: 0;  
      min-width: 130px;  
      box-shadow: 0 4px 10px rgb(37 99 235 / 0.3);  
    }  
  
    button:hover:not(:disabled) {  
      background: #1e40af;  
      box-shadow: 0 6px 15px rgb(30 64 175 / 0.5);  
    }  
  
    button:disabled {  
      opacity: 0.6;  
      cursor: not-allowed;  
      box-shadow: none;  
    }  
  
    button#deposit-btn {  
      width: 100%;  
      font-size: 1.3rem;  
      padding: 16px 0;  
      min-width: auto;  
      box-shadow: 0 5px 15px rgb(37 99 235 / 0.4);  
    }  
  
    button#home-btn {  
      background: #6b7280;  
      width: 100%;  
      font-size: 1.15rem;  
      padding: 14px 0;  
      box-shadow: 0 4px 10px rgb(107 114 128 / 0.3);  
    }  
  
    button#home-btn:hover:not(:disabled) {  
      background: #4b5563;  
      box-shadow: 0 6px 14px rgb(75 85 99 / 0.45);  
    }  
  
    .copy-feedback {  
      font-weight: 700;  
      color: #22c55e;  
      opacity: 0;  
      user-select: none;  
      transition: opacity 0.3s ease;  
      font-size: 1rem;  
    }  
  
    .copy-feedback.visible {  
      opacity: 1;  
    }  
  
    .instructions {  
      font-size: 1rem;  
      line-height: 1.5;  
      color: #444;  
      text-align: center;  
      margin-top: -6px;  
      user-select: none;  
    }  
  
    .countdown, .result {  
      font-weight: 700;  
      font-size: 1.25rem;  
      text-align: center;  
      margin-top: 10px;  
      user-select: none;  
    }  
  
    .countdown {  
      color: #dc2626;  
    }  
  
    .whatsapp-support {  
      margin-top: 18px;  
      padding: 14px 20px;  
      background-color: #d1fae5;  
      border-radius: 14px;  
      border: 1.5px solid #10b981;  
      text-align: center;  
      font-weight: 600;  
      color: #065f46;  
      font-size: 1rem;  
      user-select: none;  
      word-break: break-word;  
    }  
  
    .whatsapp-support a {  
      color: #047857;  
      font-weight: 700;  
      text-decoration: none;  
      transition: text-decoration 0.25s ease;  
    }  
  
    .whatsapp-support a:hover, .whatsapp-support a:focus {  
      text-decoration: underline;  
    }  
  
    ul.instructions {  
      padding-left: 24px;  
      color: #444;  
      font-size: 1rem;  
      line-height: 1.5;  
      user-select: none;  
    }  
  
    ul.instructions li {  
      margin-bottom: 12px;  
      word-break: break-word;  
    }  
  
    code {  
      background: #e0e7ff;  
      border-radius: 6px;  
      padding: 3px 6px;  
      font-family: monospace;  
      font-weight: 600;  
      font-size: 1rem;  
      user-select: all;  
    }  
  
    /* Responsive adjustments */  
    @media (max-width: 520px) {  
      main {  
        padding: 24px 20px;  
      }  
      h2 {  
        font-size: 1.6rem;  
      }  
      button.copy-btn {  
        padding: 12px 16px;  
        font-size: 1.1rem;  
        min-width: 100px;  
      }  
      .copy-container {  
        flex-direction: column;  
        align-items: flex-start;  
        gap: 10px;  
      }  
      button.copy-btn {  
        width: 100%;  
      }  
      button#deposit-btn, button#home-btn {  
        font-size: 1.25rem;  
        padding: 16px 0;  
        border-radius: 14px;  
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
        <div id="amountText"><?= $unique_amount ?> MINTME</div>  
        <button class="copy-btn" onclick="copyToClipboard('amountText', this)">Copy</button>  
        <span class="copy-feedback" id="amountCopiedMsg">Copied!</span>  
      </div>  
    </div>  

    <div>  
      <div class="label">🪙 To Wallet:</div>  
      <div class="card copy-container">  
        <div id="walletText"><?= htmlspecialchars($mintme_wallet) ?></div>  
        <button class="copy-btn" onclick="copyToClipboard('walletText', this)">Copy</button>  
        <span class="copy-feedback" id="walletCopiedMsg">Copied!</span>  
      </div>  
    </div>  

    <p class="instructions">⚠️ Make sure the amount is exact.<br>  
      <small>This amount is valid for <?= htmlspecialchars($email) ?>!</small>  
    </p>  

    <button id="deposit-btn" onclick="startTimer()">I Deposited the Amount</button>  
    <button id="home-btn" onclick="window.location.href='https://cfcbazar.ct.ws'">🏠 Home</button>  

    <div class="whatsapp-support">  
      Deposited the wrong amount? Chat with us on <a href="https://wa.me/420723447398" target="_blank" rel="noopener noreferrer">WhatsApp +420 723 447 398</a>!  
    </div>  

    <div id="countdown" class="countdown" style="display:none">  
      ⏱️ Time left: <span id="timer">300</span>s  
    </div>  
    <div id="result" class="result" style="display:none;"></div>  
  </main>  

  <main>  
    <h2>💻 Mine MINTME & Earn WorkTokens</h2>  
    <p class="instructions">You can mine MINTME coins by:</p>  
    <ul class="instructions">  
      <li>📌 <strong>Register for a wallet:</strong><br>  
        <a href="https://www.mintme.com/token/WorkTH/invite" target="_blank" rel="noopener noreferrer">MintMe Wallet</a>  
      </li>  
      <li>💾 <strong>Download the miner:</strong><br>  
        <a href="https://github.com/mintme-com/miner/releases/tag/v2.8.0" target="_blank" rel="noopener noreferrer">MintMe Miner v2.8.0</a>  
      </li>  
      <li>🚀 <strong>Run this command:</strong><br>  
        <code>./webchain-miner -o web-ko1.gonspool.com:3333 -u <?= htmlspecialchars($mintme_wallet) ?> -p x</code>  
      </li>  
      <li>🌐 <strong>Easy pool:</strong><br>  
        <a href="https://web.gonspool.com/#/" target="_blank" rel="noopener noreferrer">web.gonspool.com</a>  
      </li>  
      <li>⚙️ <strong>Hard pool:</strong><br>  
        <a href="https://www.mintme.com/pool/#/" target="_blank" rel="noopener noreferrer">mintme.com/pool</a>  
      </li>  
    </ul>  
    <p class="instructions">Earn MINTME by mining, then return to deposit here to get WorkTokens.</p>  
  </main>  

  <script>  
    function copyToClipboard(id, btn) {  
      const t = document.getElementById(id).textContent.trim();  
      navigator.clipboard.writeText(t).then(() => {  
        btn.nextElementSibling.classList.add('visible');  
        setTimeout(() => btn.nextElementSibling.classList.remove('visible'), 1500);  
      }).catch(() => alert('Copy failed, please copy manually.'));  
    }  
  
    let seconds = 300, interval, chk;  
  
    function startTimer() {  
      document.getElementById("timer").textContent = seconds;  
      document.getElementById("countdown").style.display = "block";  
      document.getElementById("result").style.display = "none";  
      const d = document.getElementById("deposit-btn"), h = document.getElementById("home-btn");  
      d.disabled = true;  
      h.disabled = true;  
      d.innerText = "⏳ Waiting...";  
  
      interval = setInterval(() => {  
        seconds--;  
        document.getElementById("timer").textContent = seconds;  
        if (seconds <= 0) {  
          clearInterval(interval);  
          clearInterval(chk);  
          document.getElementById("result").style.display = "block";  
          document.getElementById("result").textContent = "❌ Not found in 5 mins.";  
          d.disabled = false;  
          h.disabled = false;  
          d.innerText = "I Deposited the Amount";  
          setTimeout(() => location.reload(), 3000);  
        }  
      }, 1000);  
  
      chk = setInterval(() => {  
        fetch("?check=1").then(r => r.text()).then(m => {  
          if (m.includes("✅")) {  
            clearInterval(interval);  
            clearInterval(chk);  
            document.getElementById("result").style.display = "block";  
            document.getElementById("result").textContent = m;  
            d.style.display = "none";  
            h.disabled = false;  
          }  
        });  
      }, 5000);  
    }  
  </script>  
</body