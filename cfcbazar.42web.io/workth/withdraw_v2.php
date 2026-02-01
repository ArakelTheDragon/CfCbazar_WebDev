<?php
session_start();
require '../config.php';

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// API handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    header('Content-Type: application/json');
    $email = $_SESSION['email'] ?? '';
    if (!is_valid_email($email)) {
        echo json_encode(['success' => false, 'error' => 'Invalid session email']);
        exit;
    }

    if ($_POST['api_action'] === 'reward') {
        $tokens = isset($_POST['tokens']) ? floatval($_POST['tokens']) : 0;
        if ($tokens <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid tokens']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + ? WHERE LOWER(email) = LOWER(?)");
        $stmt->bind_param('ds', $tokens, $email);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['api_action'] === 'balance') {
        $stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE LOWER(email) = LOWER(?)");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($tokens);
        $stmt->fetch();
        $stmt->close();
        echo json_encode(['success' => true, 'tokens_earned' => $tokens]);
        exit;
    }

    if ($_POST['api_action'] === 'payout') {
        $address = trim($_POST['address'] ?? '');
        if (!$address) {
            echo json_encode(['success' => false, 'error' => 'Missing address']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE workers SET address = ?, payout_requested = 1 WHERE LOWER(email) = LOWER(?)");
        $stmt->bind_param('ss', $address, $email);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
        exit;
        
        if ($_POST['api_action'] === 'payout') {
    $address = trim($_POST['address'] ?? '');
    if (!$address) {
        echo json_encode(['success' => false, 'error' => 'Missing address']);
        exit;
    }

    // Update address + payout flag
    $stmt = $conn->prepare("UPDATE workers SET address = ?, payout_requested = 1 WHERE LOWER(email) = LOWER(?)");
    $stmt->bind_param('ss', $address, $email);
    $stmt->execute();
    $stmt->close();

    // Fetch worker ID
    $stmt = $conn->prepare("SELECT id FROM workers WHERE LOWER(email) = LOWER(?)");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($workerId);
    $stmt->fetch();
    $stmt->close();

    // Simulate mail.php request via cURL
    $mailData = [
        'email' => 'your@email.com', // <-- Admin destination
        'verify_code' => "💸 Payout requested!\nWorker ID: $workerId\nEmail: $email\nAddress: $address"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://yourdomain.com/mail.php'); // <-- Full path to mail.php
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($mailData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    echo json_encode(['success' => true]);
    exit;
}





    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

if (!isset($_SESSION['email'])) {
    header('Location: /login.php');
    exit();
}
$email = $_SESSION['email'];
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>WorkToken Miner</title>
  <style>
    /* Hide the main content initially */
    #mainContent {
      display: none;
    }

    #loadingScreen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-color: #000;
      color: white;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }

    .loader {
      border: 6px solid #f3f3f3;
      border-top: 6px solid #00ff00;
      border-radius: 50%;
      width: 60px;
      height: 60px;
      animation: spin 1s linear infinite;
      margin-bottom: 20px;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    #countdown {
      font-size: 20px;
    }  
    /* Hide the main content initially */
  
  
   body {
  font-family: 'Segoe UI', Roboto, sans-serif;
  background: linear-gradient(to bottom right, #e5edf7, #fdfdfd);
  margin: 0;
  padding-top: 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 100vh;
  color: #333;
}

.card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 6px 30px rgba(0, 0, 0, 0.06);
  padding: 35px 30px;
  width: 380px;
  text-align: center;
  transition: box-shadow 0.3s ease;
}

.card:hover {
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1);
}

h2, h3 {
  margin-top: 0;
  margin-bottom: 15px;
  color: #1d3557;
}

.status {
  font-size: 14px;
  margin-bottom: 15px;
  color: #555;
}

.balance {
  font-weight: bold;
  font-size: 16px;
  color: #008060;
  margin-top: 10px;
}

.warning {
  color: #b80000;
  font-size: 13px;
  margin: 10px 0;
}

input, select, button {
  font-size: 14px;
  padding: 10px 12px;
  margin-top: 8px;
  border-radius: 8px;
  border: 1px solid #ccc;
  width: 100%;
  box-sizing: border-box;
}

input:focus, select:focus {
  border-color: #008060;
  outline: none;
}

button {
  background-color: #008060;
  color: white;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.3s ease;
  border: none;
}

button:hover {
  background-color: #006d52;
}

.success {
  color: green;
  font-weight: 600;
  margin-top: 10px;
}

.login-info {
  margin-top: 20px;
  font-size: 13px;
  color: #666;
  text-align: center;
}  
</style>
</head>
<body>

<!-- Loading Screen -->
<div id="loadingScreen">
  <div class="loader"></div>
  <div id="countdown">Loading... s</div>
</div>
<!-- Loading Screen -->
<div id="mainContent">
  <div class="card">
    <h2>WorkToken Withdraw</h2>
    <p class="balance" id="balance">Checking balance...</p>
    <p id="statusMsg" class="warning"></p>

    <form id="payoutForm" onsubmit="submitPayout(event)">
      <h3>Payout Request</h3>
      <input type="text" name="address" id="walletAddress" placeholder="Wallet Address" required>
      <select id="walletPlatform" required>
        <option value="">Select Platform</option>
        <option value="Metamask">Metamask</option>
        <option value="TrustWallet">TrustWallet</option>
        <option value="Other">Other</option>
      </select>
      <div id="walletWarning" class="warning" style="display:none;">
        ⚠️ If your wallet is not compatible with the WorkToken (BNB Smart Chain)<br>
        or the token <code>0xecbD4E86EE8583c8681E2eE2644FC778848B237D</code> is not added,<br>
        we are not responsible for lost tokens.<br>
        For help, press <a href="#">this</a>.
      </div>
      <button type="submit">Request Payout</button>
      <p id="payoutConfirm" class="success" style="display:none;">✅ Requested within 48 hours or chat with us on WhatsApp +420 723 447 398. Min 1 WorkToken.</p>
    </form>
  </div>

  <div class="login-info">
    Logged in as: <strong><?php echo htmlspecialchars($email); ?></strong>
  </div>
</div>

<!-- JS -->  
<script>
  let minerRunning = true;
  let MinerWorking = true;
  const userEmail = "<?php echo htmlspecialchars($email); ?>";

  function sendApi(action, body = {}) {
    body.api_action = action;
    return fetch("", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(body)
    }).then(res => res.json());
  }

  function rewardUser() {
	if (MinerWorking){
    sendApi("reward", { tokens: 0.00001 });
	}
  }

  function updateBalance() {
    sendApi("balance").then(data => {
      if (data.success) {
        document.getElementById("balance").innerText = `WorkToken Balance: ${parseFloat(data.tokens_earned).toFixed(6)}`;
      }
    });
  }

  function submitPayout(e) {
    e.preventDefault();
    const address = document.getElementById("walletAddress").value.trim();
    if (!address) return;
    sendApi("payout", { address }).then(data => {
      if (data.success) {
        document.getElementById("payoutConfirm").style.display = "block";
        document.getElementById("payoutForm").querySelector("button").disabled = true;
      }
    });
  }
  
  
function checkConnectivity() {
  const msg = document.getElementById("statusMsg");
  if (!navigator.onLine) {
    msg.innerText = "⚠️ Internet connection lost. Mining paused.";
	MinerWorking = false;
  } else if (!minerRunning) {
    msg.innerText = "⚠️ Miner not running. Unblock JavaScript or check browser settings.";
	MinerWorking = false;
  } else if (typeof window.ethereum !== 'undefined' && window.ethereum.isMetaMask) {
    console.log('MetaMask is installed!');
    msg.innerText = "⚠️ MetaMask detected. Please disable MetaMask extension to continue mining.";
	MinerWorking = false;
  } else {
    msg.innerText = "";
	MinerWorking = true;
  }
}


  document.getElementById("walletPlatform").addEventListener("change", function() {
    const warning = document.getElementById("walletWarning");
    warning.style.display = this.value === "Other" ? "block" : "none";
  });

  setInterval(() => {
    updateBalance();
    checkConnectivity();
  }, 5000);

  document.addEventListener("visibilitychange", () => {
    const msg = document.getElementById("statusMsg");
    msg.innerText = document.hidden ? "⛔ Mining paused in background tab." : "";
  });
</script>


<!-- coinmp miner -->
<!-- Hashrate -->
<!-- Hashrate -->
<!-- coinmp miner -->

<!-- loading screen -->
<script>
  let seconds = 5;
  const countdown = document.getElementById("countdown");
  const loader = document.getElementById("loadingScreen");
  const main = document.getElementById("mainContent");

  const interval = setInterval(() => {
    seconds--;
    countdown.textContent = `Loading... ${seconds}s`;
    if (seconds <= 0) {
      clearInterval(interval);
      loader.style.display = "none";
      main.style.display = "block";
    }
  }, 1000);
</script>
<!-- loading screen -->
</body>
</html>
