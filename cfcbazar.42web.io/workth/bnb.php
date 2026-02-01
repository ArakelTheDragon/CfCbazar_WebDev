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

// 1) Fetch user ID
$userId = null;
$stmtUser = $conn->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1");
$stmtUser->bind_param("s", $email);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
if ($row = $resUser->fetch_assoc()) {
    $userId = (int)$row['id'];
}
$stmtUser->close();

// 2) Unique deposit amount
$unique_amount = ($userId !== null) ? number_format(0.0040 + ($userId / 1000000), 6, '.', '') : '0.004000';

// 3) Session control
if (!isset($_SESSION['expected_amount']) || (time() - ($_SESSION['amount_time'] ?? 0)) > $session_expiry_seconds) {
    $_SESSION['expected_amount'] = $unique_amount;
    $_SESSION['amount_time'] = time();
}

// 4) Check logic
if (isset($_GET['check']) && isset($_SESSION['expected_amount'])) {
    $expected = $_SESSION['expected_amount'];
    $start = $_SESSION['amount_time'];

    if (time() - $start > $session_expiry_seconds) {
        echo "❌ Session expired.";
        exit;
    }

    $url = "https://api.bscscan.com/api?module=account&action=txlist&address=$bnb_wallet&startblock=1&endblock=99999999&sort=desc&apikey=$api_key";
    $resp = file_get_contents($url);
    $data = json_decode($resp, true);

    if ($data && $data['status'] === '1') {
        foreach ($data['result'] as $tx) {
            $to = strtolower($tx['to']);
            $from = $tx['from'];
            $value = floatval($tx['value']) / 1e18;
            $hash = $tx['hash'];

            if ($to === strtolower($bnb_wallet) && abs($value - $expected) <= 0.00001) {
                $check = $conn->prepare("SELECT last_tx_hash FROM workers WHERE email=? LIMIT 1");
                $check->bind_param("s", $email);
                $check->execute();
                $result = $check->get_result();
                $prevHash = '';
                if ($row = $result->fetch_assoc()) {
                    $prevHash = $row['last_tx_hash'] ?? '';
                }
                $check->close();

                if ($prevHash === $hash) {
                    echo "⏳ Already credited for this TX.";
                    exit;
                }

                // Credit user
                $up = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + 100, last_tx_hash = ? WHERE email = ?");
                $up->bind_param("ss", $hash, $email);
                $up->execute();
                $up->close();

                unset($_SESSION['expected_amount'], $_SESSION['amount_time']);
                echo "✅ 100 WorkTokens credited!";
                exit;
            }
        }
    }

    echo "⏳ Waiting for deposit of exactly $expected BNB...";
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Buy WorkTokens with BNB</title>
  <style>
    body { font-family: sans-serif; background: #eef2ff; padding: 20px; text-align: center; }
    main { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 480px; margin: auto; }
    h2 { color: #1e40af; }
    .card { background: #f4f7ff; padding: 12px 16px; border-radius: 10px; font-weight: bold; word-break: break-word; position: relative; }
    button { margin-top: 20px; padding: 12px 18px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; }
    #countdown, #result { margin-top: 16px; font-weight: bold; }
    .copy-btn { position: absolute; right: 10px; top: 10px; background: #10b981; font-size: 13px; padding: 6px 10px; border-radius: 6px; }
    .note { margin-top: 15px; color: #555; font-size: 15px; }
    .whatsapp { margin-top: 20px; font-size: 15px; color: #065f46; background: #d1fae5; border: 1px solid #10b981; padding: 10px; border-radius: 10px; }
    #console { margin-top: 30px; background: #f9fafb; border-radius: 8px; padding: 10px; font-size: 13px; text-align: left; max-height: 200px; overflow-y: auto; }
    #console pre { margin: 0; white-space: pre-wrap; }
  </style>
</head>
<body>
<main>
  <h2>Deposit to Get 100 WorkTokens</h2>
  <p><strong>Logged in as:</strong><br><?= htmlspecialchars($email) ?></p>
  <p>Send exactly <strong><?= $unique_amount ?> BNB</strong> on BNB chain BEP20 to:</p>
  <div class="card">
    <span id="wallet-address"><?= $bnb_wallet ?></span>
    <button class="copy-btn" onclick="copyWallet()">Copy</button>
  </div>
  <button onclick="startTimer()" id="deposit-btn">I Deposited the Amount</button>

  <div class="note">
    You can mine BNB on <a href="https://unmineable.com/" target="_blank">unmineable.com</a> with low level hardware.
  </div>
  <div class="whatsapp">
    Deposited the wrong amount? Chat with us on <a href="https://wa.me/420723447398" target="_blank">WhatsApp +420 723 447 398</a>.
  </div>

  <div id="countdown" style="display:none">⏱️ Time left: <span id="timer">300</span>s</div>
  <div id="result" style="display:none"></div>

  <div id="console">
    <strong>🔍 Last BNB transactions:</strong>
    <pre id="log">Loading...</pre>
  </div>
</main>

<script>
let seconds = 300, interval, checker;

function startTimer() {
  document.getElementById('countdown').style.display = 'block';
  document.getElementById('result').style.display = 'none';
  document.getElementById('deposit-btn').disabled = true;

  interval = setInterval(() => {
    document.getElementById('timer').textContent = --seconds;
    if (seconds <= 0) {
      clearInterval(interval); clearInterval(checker);
      document.getElementById('result').textContent = '❌ Not found in 5 mins.';
      document.getElementById('result').style.display = 'block';
      document.getElementById('deposit-btn').disabled = false;
      setTimeout(() => location.reload(), 3000);
    }
  }, 1000);

  checker = setInterval(() => {
    fetch('?check=1')
      .then(r => r.text())
      .then(msg => {
        document.getElementById('log').textContent += "\n" + msg;
        if (msg.includes('✅')) {
          clearInterval(interval); clearInterval(checker);
          document.getElementById('result').textContent = msg;
          document.getElementById('result').style.display = 'block';
          document.getElementById('deposit-btn').style.display = 'none';
        }
      });
  }, 5000);
}

function copyWallet() {
  const walletText = document.getElementById('wallet-address').textContent.trim();
  navigator.clipboard.writeText(walletText).then(() => {
    alert("Wallet address copied!");
  });
}

// Load recent transactions
(async function loadBNBTransactions() {
  const log = document.getElementById('log');
  try {
    const res = await fetch(`https://api.bscscan.com/api?module=account&action=txlist&address=<?= $bnb_wallet ?>&startblock=1&endblock=99999999&sort=desc&apikey=<?= $api_key ?>`);
    const json = await res.json();
    if (json.status === '1') {
      log.textContent = '';
      let shown = 0;
      for (const tx of json.result) {
        if (tx.to.toLowerCase() === "<?= strtolower($bnb_wallet) ?>") {
          const bnb = (parseFloat(tx.value) / 1e18).toFixed(8);
          log.textContent += `→ From ${tx.from} sent ${bnb} BNB\n`;
          shown++;
          if (shown >= 5) break;
        }
      }
      if (shown === 0) log.textContent = 'No recent BNB deposits found.';
    } else {
      log.textContent = '❌ Failed to load transactions.';
    }
  } catch (e) {
    log.textContent = '⚠️ Error: ' + e;
  }
})();
</script>
</body>
</html>