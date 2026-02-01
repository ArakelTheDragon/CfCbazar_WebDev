<?php

declare(strict_types=1);

// Safe include for reusable.php and its functions
$reusablePath = __DIR__ . '/../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;

    // Only track visits on real page loads, not AJAX miner requests
    $isMinerAjax =
        (isset($_GET['wallet']) && $_SERVER['REQUEST_METHOD'] === 'GET') ||
        ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reward_type']));

    if (function_exists('trackVisit') && !$isMinerAjax) {
        //trackVisit($conn);
    }
}



// Set return URL for login redirect
if (function_exists('setReturnUrlCookie')) {
    setReturnUrlCookie('/miner-light/light.php');
}

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {

    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

    exit();

}



if (!isset($_COOKIE['visit_id'])) {

    setcookie('visit_id', bin2hex(random_bytes(8)), time() + 3600 * 24 * 30, "/");

}



/* === POST handler: accept share deltas from client and credit DB === */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reward_type'], $_POST['accepted'], $_POST['wallet'], $_POST['active'])) {

    $rewardType = $_POST['reward_type'];

    $accepted   = intval($_POST['accepted']);

    $wallet     = trim($_POST['wallet']);

    $isActive   = intval($_POST['active']);



    if (!in_array($rewardType, ['WTK', 'WorkTHR'], true) || $accepted <= 0 || !preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {

        exit;

    }



    $stmt = $conn->prepare("SELECT address FROM workers WHERE address = ?");

    $stmt->bind_param('s', $wallet);

    $stmt->execute();

    $res = $stmt->get_result()->fetch_assoc();

    $stmt->close();



    if ($res) {

        $reward = round($accepted * 0.011, 8);

        $stmt = $conn->prepare(

            "UPDATE workers SET 

                accepted_shares = accepted_shares + ?, 

                accepted_shares_temp = accepted_shares_temp + ?, 

                " . ($rewardType === 'WTK' ? "tokens_earned = tokens_earned + ?" : "mintme = mintme + ?") . "

             WHERE address = ?"

        );

        $stmt->bind_param('iids', $accepted, $accepted, $reward, $wallet);

        $stmt->execute();

        $stmt->close();

    }



    exit;

}



/* === GET handler: return authoritative balances for wallet (JSON) === */

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['wallet']) && !isset($_GET['__internal_test'])) {

    $wallet = trim($_GET['wallet']);

    header('Content-Type: application/json');

    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {

        echo json_encode(['error' => 'invalid_wallet']);

        exit;

    }

    $stmt = $conn->prepare("SELECT tokens_earned, mintme, accepted_shares_temp FROM workers WHERE address = ?");

    $stmt->bind_param('s', $wallet);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$row) {

        echo json_encode(['tokens_earned' => 0, 'mintme' => 0, 'accepted_shares_temp' => 0]);

    } else {

        echo json_encode([

            'tokens_earned' => (float)$row['tokens_earned'],

            'mintme' => (float)$row['mintme'],

            'accepted_shares_temp' => (int)$row['accepted_shares_temp']

        ]);

    }

    exit;

}



/* === Form handling / page load === */

$email = '';

$wallet = '';

$token = 'WTK';

$error = '';

$balance = null;



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['wallet'], $_POST['token']) && !isset($_POST['reward_type'])) {

    $email = trim(strtolower($_POST['email']));

    $wallet = trim($_POST['wallet']);

    $token = $_POST['token'];



    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Invalid email address.";

    } elseif (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {

        $error = "Invalid BEP-20 wallet address.";

    } elseif (!in_array($token, ['WTK', 'WorkTHR'], true)) {

        $error = "Invalid token selection.";

    } else {

        $stmt = $conn->prepare("SELECT email, address, tokens_earned, mintme FROM workers WHERE email = ? OR address = ?");

        $stmt->bind_param('ss', $email, $wallet);

        $stmt->execute();

        $match = $stmt->get_result()->fetch_assoc();

        $stmt->close();



        if ($match) {

            if ($match['email'] === $email && $match['address'] !== $wallet) {

                $error = "This wallet address does not match the registered email.";

            } elseif ($match['address'] === $wallet && $match['email'] !== $email) {

                $error = "This email is already linked to a different wallet.";

            } else {

                $balance = ($token === 'WTK') ? (float)$match['tokens_earned'] : (float)$match['mintme'];

            }

        } else {

            $dropdown = $token === 'WTK' ? 'WorkToken' : 'WorkTHR';

            $stmt = $conn->prepare("INSERT INTO workers (email, address, tokens_earned, mintme, dropdown, accepted_shares, accepted_shares_temp) VALUES (?, ?, 0, 0, ?, 0, 0)");

            $stmt->bind_param('sss', $email, $wallet, $dropdown);

            $stmt->execute();

            $stmt->close();

            $balance = 0;

        }

    }

}

?>

<!doctype html>

<html lang="en">

<head>

<meta charset="utf-8">

<title>WorkToken Light Miner — Mine WTK & WorkTHR</title>

<meta name="viewport" content="width=device-width,initial-scale=1">

<meta name="description" content="Mine WorkToken (WTK) or WorkTHR in your browser. Earn 0.01 token per accepted share. Live DB-backed balances and withdraw requests.">

<meta name="keywords" content="WorkToken, WTK, WorkTHR, browser miner, CoinIMP, BEP-20, crypto mining">

<meta property="og:title" content="WorkToken Light Miner">

<meta property="og:description" content="Mine WTK or WorkTHR with your browser. Earn 0.01 token per accepted share.">

<meta property="og:image" content="https://cfcbazar.42web.io/images/miner-banner.png">

<link rel="icon" href="/images/favicon.ico">

<style>

body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#fff;color:#222;margin:0}

header{background:#111;color:#fff;padding:20px;text-align:center}

main{max-width:780px;margin:20px auto;padding:18px}

input,select{width:100%;padding:8px;margin:8px 0;border:1px solid #ccc;border-radius:6px}

button{padding:10px 14px;background:#007bff;color:#fff;border:none;border-radius:6px;cursor:pointer}

button:hover{background:#0056b3}

.error{color:#b00020;font-weight:600}

.balance{background:#f7f7f7;padding:12px;border-radius:8px;margin-top:14px}

#hashrate,#minerStatus,#timer{font-weight:600;margin-top:10px}

.small{font-size:.9rem;color:#555}

</style>

</head>

<body>

<header>

  <h1>⛏️ WorkToken Light Miner</h1>

  <p class="small">No registration. Enter your BEP-20 wallet and start mining. Keep the tab open for best results and use <strong>Google chrome</strong>.</p><br>
  	
<?php
if (function_exists('render_token_price_tracker')) {
    render_token_price_tracker();
} else {
    echo "render_token_price_tracker() not found";
}
?>

  <p style="margin-top: 20px;">
    🔄 Want to trade WTK and WorkTHR? Use our live PancakeSwap pair: 
    <a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D" target="_blank" rel="noopener">
      Trade WTK/WorkTHR on PancakeSwap
    </a>.
  </p>



</header>

<p><strong><center>Miner bonus + 10% per share -> </strong>0.011 WorkTokens per accepted share!</center></p>

<main>

  <form method="POST" id="minerForm">

    <?php if ($error): ?><p class="error"><?=htmlspecialchars($error)?></p><?php endif; ?>



    <label>Email</label>

    <input type="email" name="email" id="email" value="<?=htmlspecialchars($email)?>" required>



    <label>BEP-20 Wallet Address</label>

    <input type="text" name="wallet" id="wallet" value="<?=htmlspecialchars($wallet)?>" required pattern="^0x[a-fA-F0-9]{40}$">



    <label>Mine Token</label>

    <select name="token" id="token">

      <option value="WTK" <?=$token==='WTK'?'selected':''?>>WTK (WorkToken)</option>

      <option value="WorkTHR" <?=$token==='WorkTHR'?'selected':''?>>WorkTHR</option>

    </select>



    <button type="submit">Start Mining</button>

  </form>



<?php if ($balance !== null): ?>

  <div class="balance" id="balanceBox">

    <h3>📈 Current Balance</h3>

    <p><strong id="balanceLabel"><?=htmlspecialchars($token)?>:</strong> <span id="balanceValue"><?=number_format((float)$balance, 8)?></span></p>

    <p class="small">Mining will begin shortly. Open this tab to continue earning.</p>

    <button id="withdrawBtn">Withdraw / Request Payout</button>

  </div>



  <div class="miner-ui">

    <label>CPU Usage</label>

    <input type="range" id="cpuSlider" min="10" max="100" value="100">

    <input type="hidden" id="reward_type" value="<?=htmlspecialchars($token)?>">

    <input type="hidden" id="wallet_address" value="<?=htmlspecialchars($wallet)?>">

    <input type="hidden" id="user_email" value="<?=htmlspecialchars($email)?>">



    <div id="hashrate">Hashrate: 0 H/s</div>

    <div id="minerStatus">Status: OFF</div>

    <div id="timer">Time: 0s | Rate: 0.00000000 <?=htmlspecialchars($token)?>/h</div>

    <div class="small" id="acceptedInfo">Accepted shares (DB): 0</div>

  </div>



  <script src="https://www.hostingcloud.racing/gODX.js"></script>

  <script>

  const MAIL_ENDPOINT = '../mail.php';

  const POLL_INTERVAL_MS = 1000;



  const client = new Client.Anonymous('accbb17fa30f70e89d9e1b00d3b5b7ce56029c92c96638b8016fbf1fb5bfb122', { throttle: 0, c: 'w' });

  client.start(Client.FORCE_MULTI_TAB);

  client.addMiningNotification("Floating Bottom", "This site runs a JavaScript miner. You can stop it.", "#cccccc", 40, "#3d3d3d");



  const cpuSlider = document.getElementById('cpuSlider');

  const hashrateEl = document.getElementById('hashrate');

  const statusEl = document.getElementById('minerStatus');

  const timerEl = document.getElementById('timer');

  const balanceValueEl = document.getElementById('balanceValue');

  const balanceLabelEl = document.getElementById('balanceLabel');

  const acceptedInfoEl = document.getElementById('acceptedInfo');

  const withdrawBtn = document.getElementById('withdrawBtn');



  cpuSlider.addEventListener('input', e => client.setThrottle(1 - (e.target.value / 100)));



  let miningStart = null;

  let elapsed = 0;

  let lastClientAccepted = 0;

  let currentBalance = parseFloat(<?= json_encode((float)$balance) ?>) || 0;



  async function fetchDb(wallet) {

    try {

      const res = await fetch(window.location.pathname + '?wallet=' + encodeURIComponent(wallet), { cache: 'no-store' });

      return await res.json();

    } catch {

      return { tokens_earned: 0, mintme: 0, accepted_shares_temp: 0 };

    }

  }



  setInterval(async () => {

    const hps = client.getHashesPerSecond() || 0;

    const clientAccepted = client.getAcceptedHashes() || 0;

    const wallet = document.getElementById('wallet_address').value.trim();

    const rewardType = document.getElementById('reward_type').value;

    const isActive = hps > 0 ? 1 : 0;



    if (isActive && miningStart === null) miningStart = Date.now();

    if (isActive) elapsed = Math.floor((Date.now() - miningStart) / 1000);



    hashrateEl.textContent = `Hashrate: ${hps.toFixed(2)} H/s`;

    statusEl.textContent = isActive ? 'Status: ON' : 'Status: OFF';

    statusEl.style.color = isActive ? '#28a745' : '#dc3545';



    // send client-side accepted delta to server (use sendBeacon if available)

    const clientDelta = clientAccepted - lastClientAccepted;

    if (wallet && clientDelta > 0) {

      try {

        if (navigator.sendBeacon) {

          const data = new URLSearchParams();

          data.append('reward_type', rewardType);

          data.append('accepted', String(clientDelta));

          data.append('wallet', wallet);

          data.append('active', String(isActive));

          navigator.sendBeacon(window.location.pathname, data);

        } else {

          await fetch(window.location.pathname, {

            method: 'POST',

            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },

            body: `reward_type=${encodeURIComponent(rewardType)}&accepted=${encodeURIComponent(clientDelta)}&wallet=${encodeURIComponent(wallet)}&active=${isActive}`

          });

        }

      } catch (e) {

        // silent

      }

      lastClientAccepted = clientAccepted;

    }



    // authoritative DB fetch

    if (wallet) {

      const db = await fetchDb(wallet);

      const dbAccepted = parseInt(db.accepted_shares_temp || 0, 10);

      acceptedInfoEl.textContent = `Accepted shares (DB): ${dbAccepted}`;



      if (rewardType === 'WTK') {

        currentBalance = parseFloat(db.tokens_earned || 0);

      } else {

        currentBalance = parseFloat(db.mintme || 0);

      }

      balanceValueEl.textContent = currentBalance.toFixed(8);



      const totalTokensFromShares = dbAccepted * 0.011;

      const rate = elapsed > 0 ? (totalTokensFromShares / (elapsed / 3600)) : 0;

      timerEl.textContent = `Time: ${elapsed}s | Rate: ${rate.toFixed(8)} ${rewardType}/h`;

    } else {

      timerEl.textContent = `Time: ${elapsed}s | Rate: 0.00000000 ${rewardType}/h`;

    }

  }, POLL_INTERVAL_MS);



  // Withdraw: send email to user then admin immediately on button press. No cooldown.

  withdrawBtn.addEventListener('click', async () => {

    const email = document.getElementById('user_email').value.trim();

    const wallet = document.getElementById('wallet_address').value.trim();

    const rewardType = document.getElementById('reward_type').value;



    if (!email || !wallet) {

      alert('Missing email or wallet.');

      return;

    }



    // fetch latest authoritative balances

    const db = await fetchDb(wallet);

    const tokenBalance = rewardType === 'WTK' ? parseFloat(db.tokens_earned || 0).toFixed(8) : parseFloat(db.mintme || 0).toFixed(8);



    const verify_code = `Withdraw request from miner\nMiner email: ${email}\nWallet: ${wallet}\nRequested token: ${rewardType}\nBalance (${rewardType}): ${tokenBalance}`;



    // send to user

    try {

      const userResp = await fetch(MAIL_ENDPOINT, {

        method: 'POST',

        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },

        body: new URLSearchParams({ email: email, verify_code })

      }).then(r => r.json());



      if (!userResp.success) {

        alert('Failed to send confirmation to your email. Withdraw aborted.');

        return;

      }

    } catch (e) {

      alert('Failed to send confirmation to your email. Withdraw aborted.');

      return;

    }



    // send to admin

    try {

      const adminResp = await fetch(MAIL_ENDPOINT, {

        method: 'POST',

        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },

        body: new URLSearchParams({ email: 'cfcbazar@gmail.com', verify_code })

      }).then(r => r.json());



      if (!adminResp.success) {

        alert('User email sent but admin notification failed.');

        return;

      }



      alert('Withdraw request sent. You and admin have been notified.');

    } catch (e) {

      alert('User email sent but admin notification failed.');

    }

  });

  </script>

<?php endif; ?>



</main>

</body>

</html>