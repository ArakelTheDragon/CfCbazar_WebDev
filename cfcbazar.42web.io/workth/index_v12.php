<?php
// buy_bnb.php — Deposit BNB to earn WorkTokens
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
$bnb_wallet = '0xd05a0cf460bb91b49f9103228dd188024e68edea';
$api_key = $bscscan_api_key;
$session_expiry_seconds = 300; // 5 minutes

// Get user ID
$userId = null;
$stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $userId = (int)$row['id'];
}
$stmt->close();

// Unique amount: 0.004{ID}{ID}
if ($userId !== null) {
    $idStr = str_pad($userId, 2, '0', STR_PAD_LEFT);
    $unique_amount = '0.004' . $idStr . $idStr;
} else {
    $unique_amount = '0.00400000';
}

// Set session
if (!isset($_SESSION['expected_amount']) || (time() - ($_SESSION['amount_time'] ?? 0)) > $session_expiry_seconds) {
    $_SESSION['expected_amount'] = $unique_amount;
    $_SESSION['amount_time']    = time();
}

// Handle check
if (isset($_GET['check']) && isset($_SESSION['expected_amount'])) {
    $expected = floatval($_SESSION['expected_amount']);
    $start    = $_SESSION['amount_time'];

    if (time() - $start > $session_expiry_seconds) {
        echo "❌ Session expired."; exit;
    }

    $url = "https://api.bscscan.com/api?module=account&action=txlist&address=$bnb_wallet&startblock=1&endblock=99999999&sort=desc&apikey=$api_key";
    $resp = file_get_contents($url);
    $data = json_decode($resp, true);

    if ($data && $data['status'] === '1') {
        foreach ($data['result'] as $tx) {
            $to    = strtolower($tx['to']);
            $hash  = $tx['hash'];
            $value = floatval($tx['value']) / 1e18;

            if ($to === strtolower($bnb_wallet) && abs($value - $expected) <= 0.00001) {
                // Double check
                $stmt = $conn->prepare("SELECT last_tx_hash FROM workers WHERE email=? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $res = $stmt->get_result();
                $prevHash = ($row = $res->fetch_assoc()) ? $row['last_tx_hash'] : '';
                $stmt->close();

                if ($prevHash === $hash) {
                    echo "⏳ Already credited for this TX."; exit;
                }

                // Credit
                $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + 100, last_tx_hash = ? WHERE email = ?");
                $stmt->bind_param("ss", $hash, $email);
                $stmt->execute();
                $stmt->close();

                unset($_SESSION['expected_amount'], $_SESSION['amount_time']);
                echo "✅ 100 WorkTokens credited!";
                exit;
            }
        }
    }

    echo "⏳ Waiting for deposit of exactly $expected BNB..."; exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Buy WorkTokens with BNB</title>
  <style>
    body { font-family: sans-serif; background: #eef2ff; padding: 20px; text-align: center; }
    main { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 480px; margin: auto; }
    h2 { color: #1e40af; }
    .card { background: #f4f7ff; padding: 12px 16px; border-radius: 10px; font-weight: bold; word-break: break-word; position: relative; margin-top: 10px; }
    .copy-btn { position: absolute; right: 10px; top: 10px; background: #10b981; font-size: 13px; padding: 6px 10px; border-radius: 6px; color: white; cursor: pointer; }
    button { margin-top: 20px; padding: 12px 18px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; }
    #countdown, #result { margin-top: 16px; font-weight: bold; }
    .note, .whatsapp { margin-top: 15px; font-size: 15px; }
    .whatsapp { background: #d1fae5; color: #065f46; border: 1px solid #10b981; padding: 10px; border-radius: 10px; }
    #console-log { text-align: left; margin-top: 20px; background: #f0f0f0; padding: 10px; border-radius: 8px; font-size: 14px; max-height: 200px; overflow-y: auto; }
  </style>
</head>
<body>
<main>
  <h2>Deposit to Get 100 WorkTokens</h2>
  <p><strong>Logged in as:</strong><br><?= htmlspecialchars($email) ?></p>

  <p>Send exactly:</p>
  <div class="card">
    <span id="amountText"><?= $unique_amount ?> BNB</span>
    <button class="copy-btn" onclick="copyToClipboard('amountText')">Copy</button>
  </div>

  <p>To wallet:</p>
  <div class="card">
    <span id="walletText"><?= $bnb_wallet ?></span>
    <button class="copy-btn" onclick="copyToClipboard('walletText')">Copy</button>
  </div>

  <button onclick="startTimer()" id="deposit-btn">I Deposited the Amount</button>

  <div class="note">
    You can mine BNB on <a href="https://unmineable.com/" target="_blank">unmineable.com</a>.
  </div>
  <div class="whatsapp">
    Sent the wrong amount? Contact us on <a href="https://wa.me/420723447398" target="_blank">WhatsApp</a>.
  </div>

  <div id="countdown" style="display:none">⏱️ Time left: <span id="timer">360</span> seconds</div>
  <div id="result" style="display:none"></div>
  <div id="console-log"><strong>Console:</strong><br /></div>
</main>
<script>
  let seconds = 360, interval, checker;

  function startTimer() {
    document.getElementById('countdown').style.display = 'block';
    document.getElementById('result').style.display = 'none';
    document.getElementById('deposit-btn').disabled = true;

    interval = setInterval(() => {
      document.getElementById('timer').textContent = --seconds;
      if (seconds <= 0) {
        clearInterval(interval); clearInterval(checker);
        document.getElementById('result').textContent = '❌ Not found in 360 seconds. Reload page, press button again.';
        document.getElementById('result').style.display = 'block';
        document.getElementById('deposit-btn').disabled = false;
        setTimeout(() => location.reload(), 3000);
      }
    }, 1000);

    checker = setInterval(() => {
      fetch('?check=1').then(r => r.text()).then(msg => {
        logToConsole("Check: " + msg);
        if (msg.includes('✅')) {
          clearInterval(interval); clearInterval(checker);
          document.getElementById('result').textContent = msg;
          document.getElementById('result').style.display = 'block';
          document.getElementById('deposit-btn').style.display = 'none';
          setTimeout(() => location.reload(), 3000);
        }
      }).catch(e => {
        logToConsole("Error: " + e.message);
      });
    }, 5000);
  }

  function copyToClipboard(id) {
    const text = document.getElementById(id).textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
      alert("Copied!");
    });
  }

  function logToConsole(msg) {
    const log = document.getElementById("console-log");
    log.innerHTML += msg + "<br />";
    log.scrollTop = log.scrollHeight;
  }
</script>
</body>
</html>