<?php
// buy_bnb.php — Deposit BNB to earn WorkTokens
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include(__DIR__ . '/../config.php');

// 1) Ensure logged in
if (!isset($_SESSION['email'])) {
    header('Location: /login.php');
    exit;
}
$email = $_SESSION['email'];
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

// 2) Config
$bnb_wallet             = '0xFBd767f6454bCd07c959da2E48fD429531A1323A'; //'0xd05a0cf460bb91b49f9103228dd188024e68edea'; // Binance
$api_key                = $bscscan_api_key;
$session_expiry_seconds = 300; // 5 minutes

// 3) Get user ID
$userId = null;
$stmt = $conn->prepare("
    SELECT id 
      FROM users 
     WHERE LOWER(email)=LOWER(?) 
     LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $userId = (int)$row['id'];
}
$stmt->close();

// 4) Compute unique amount: 0.0004{ID}{ID}
if ($userId !== null) {
    $idStr         = str_pad($userId, 2, '0', STR_PAD_LEFT);
    $unique_amount = '0.0004' . $idStr . $idStr;
} else {
    $unique_amount = '0.000400000';
}

// 5) Persist expected amount in session
if (
    !isset($_SESSION['expected_amount']) ||
    (time() - ($_SESSION['amount_time'] ?? 0)) > $session_expiry_seconds
) {
    $_SESSION['expected_amount'] = $unique_amount;
    $_SESSION['amount_time']     = time();
}

// 6) Handle wallet-address submission
$address_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallet_address'])) {
    $addr = trim($_POST['wallet_address']);
    if (preg_match('/^0x[a-fA-F0-9]{40}$/', $addr)) {
        $upd = $conn->prepare("
            UPDATE workers 
               SET address = ? 
             WHERE email = ?
        ");
        $upd->bind_param("ss", $addr, $email);
        $upd->execute();
        $upd->close();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $address_error = 'Invalid BNB address format.';
    }
}

// 7) Fetch saved address
$existing_address = '';
$stmt = $conn->prepare("
    SELECT address 
      FROM workers 
     WHERE email = ? 
     LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $existing_address = trim((string)$row['address']);
}
$stmt->close();

// 8) Handle AJAX check
if ($existing_address && isset($_GET['check'])) {
    $expected = floatval($_SESSION['expected_amount']);
    $start    = $_SESSION['amount_time'];

    if (time() - $start > $session_expiry_seconds) {
        echo " Session expired.";
        exit;
    }

    $url  = "https://api.bscscan.com/api?module=account&action=txlist"
          . "&address={$bnb_wallet}&startblock=1&endblock=99999999&sort=desc"
          . "&apikey={$api_key}";
    $resp = file_get_contents($url);
    $data = json_decode($resp, true);

    if ($data['status'] === '1') {
        foreach ($data['result'] as $tx) {
            $to    = strtolower($tx['to']);
            $from  = strtolower($tx['from']);
            $value = floatval($tx['value']) / 1e18;
            $hash  = $tx['hash'];

            if (
                $to === strtolower($bnb_wallet) &&
                $from === strtolower($existing_address) &&
                abs($value - $expected) <= 0.00001
            ) {
                // Prevent double-credit
                $stmt2 = $conn->prepare("
                    SELECT last_tx_hash 
                      FROM workers 
                     WHERE email = ? 
                     LIMIT 1
                ");
                $stmt2->bind_param("s", $email);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                $prev = ($r2 = $res2->fetch_assoc()) ? $r2['last_tx_hash'] : '';
                $stmt2->close();

                if ($prev === $hash) {
                    echo " Already credited for this TX.";
                    exit;
                }

                // Credit tokens + save hash
                $up = $conn->prepare("
                    UPDATE workers 
                       SET tokens_earned = tokens_earned + 100,
                           last_tx_hash  = ?
                     WHERE email = ?
                ");
                $up->bind_param("ss", $hash, $email);
                $up->execute();
                $up->close();

                unset($_SESSION['expected_amount'], $_SESSION['amount_time']);
                echo " 100 WorkTokens credited!";
                exit;
            }
        }
    }

    echo " Waiting for deposit of exactly {$expected} BNB from {$existing_address}...";
    exit;
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
    main { background: #fff; padding: 20px; border-radius: 12px;
           box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 480px; margin: auto; }
    h2 { color: #1e40af; }
    .card { background: #f4f7ff; padding: 12px 16px; border-radius: 10px;
            font-weight: bold; word-break: break-word; position: relative; margin: 10px 0; }
    .copy-btn { position: absolute; right: 10px; top: 10px;
                background: #10b981; font-size: 13px; padding: 6px 10px;
                border-radius: 6px; color: white; cursor: pointer; }
    button { margin-top: 20px; padding: 12px 18px; background: #2563eb;
             color: white; border: none; border-radius: 8px;
             font-size: 16px; cursor: pointer; }
    input[type="text"] { width: 100%; padding: 10px; margin-top: 10px;
                         border: 1px solid #ccc; border-radius: 6px; }
    .error { color: #b91c1c; margin-top: 10px; }
    #countdown, #result { margin-top: 16px; font-weight: bold; }
    .note, .whatsapp { margin-top: 15px; font-size: 15px; }
    .whatsapp { background: #d1fae5; color: #065f46; border: 1px solid #10b981;
                padding: 10px; border-radius: 10px; }
    #console-log { text-align: left; margin-top: 20px; background: #f0f0f0;
                   padding: 10px; border-radius: 8px; font-size: 14px;
                   max-height: 200px; overflow-y: auto; }
  </style>
</head>
<body>
<main>
<?php if (!$existing_address): ?>
  <h2>Enter Your BNB Wallet Address</h2>
  <form method="POST">
    <input type="text" name="wallet_address"
           placeholder="0xYourBnbWalletAddress" required />
    <button type="submit">Save Address</button>
  </form>
  <?php if ($address_error): ?>
    <div class="error"><?= htmlspecialchars($address_error) ?></div>
  <?php endif; ?>
  <div class="note">We need your BNB address before you deposit.</div>

<?php else: ?>
  <h2>Deposit to Get 100 WorkTokens</h2>
  <p><strong>Logged in as:</strong><br><?= htmlspecialchars($email) ?></p>

  <p>Send exactly:</p>
  <div class="card">
    <span id="amountText"><?= htmlspecialchars($unique_amount) ?> BNB</span>
    <button class="copy-btn" onclick="copyToClipboard('amountText')">Copy</button>
  </div>

  <p>From your wallet:</p>
  <div class="card"><?= htmlspecialchars($existing_address) ?></div>

  <p>To our deposit address:</p>
  <div class="card">
    <span id="walletText"><?= htmlspecialchars($bnb_wallet) ?></span>
    <button class="copy-btn" onclick="copyToClipboard('walletText')">Copy</button>
  </div>

  <button onclick="startTimer()" id="deposit-btn">I Deposited the Amount</button>

  <div id="countdown" style="display:none">
     Time left: <span id="timer"><?= $session_expiry_seconds ?></span>s
  </div>
  <div id="result" style="display:none"></div>
  <div id="console-log"><strong>Console:</strong><br /></div>

  <div class="note">
    You can mine BNB on <a href="https://unmineable.com/" target="_blank">unmineable.com</a>.
  </div>
  <div class="whatsapp">
    Sent the wrong amount? Contact us on
    <a href="https://wa.me/420723447398" target="_blank">WhatsApp</a>.
  </div>
<?php endif; ?>
</main>

<script>
let seconds = <?= $session_expiry_seconds ?>, interval, checker;

function startTimer() {
  document.getElementById('countdown').style.display = 'block';
  document.getElementById('deposit-btn').disabled = true;
  document.getElementById('result').style.display = 'none';

  interval = setInterval(() => {
    document.getElementById('timer').textContent = --seconds;
    if (seconds <= 0) {
      clearInterval(interval);
      clearInterval(checker);
      showResult(' Time expired. Reload to try again.');
      setTimeout(() => location.reload(), 3000);
    }
  }, 1000);

  checker = setInterval(() => {
    fetch('?check=1')
      .then(r => r.text())
      .then(msg => {
        logConsole(msg);
        // stop only on success or alreadycredited
        if (
          msg.includes(' 100 WorkTokens credited') ||
          msg.includes(' Already credited')
        ) {
          clearInterval(interval);
          clearInterval(checker);
          showResult(msg);
        }
      })
      .catch(e => logConsole('Error: ' + e.message));
  }, 5000);
}

function copyToClipboard(id) {
  const txt = document.getElementById(id).textContent.trim();
  navigator.clipboard.writeText(txt).then(() => alert('Copied!'));
}

function showResult(msg) {
  const el = document.getElementById('result');
  el.textContent = msg;
  el.style.display = 'block';
}

function logConsole(msg) {
  const log = document.getElementById('console-log');
  log.innerHTML += msg + '<br />';
  log.scrollTop = log.scrollHeight;
}
</script>
</body>
</html>