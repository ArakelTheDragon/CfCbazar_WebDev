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
    header('Location: /login.php');
    exit();
}

$email = $_SESSION['username'];
$bnb_wallet = '0xd05a0cf460bb91b49f9103228dd188024e68edea';
$api_key    = $bscscan_api_key;
$session_expiry  = 300; // seconds

// 1) Fetch user ID
$userId = null;
$stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1");
$stmt->bind_param("s",$email);
$stmt->execute();
$stmt->bind_result($userId);
$stmt->fetch();
$stmt->close();

// 2) Compute unique amount
$unique_amount = $userId
    ? number_format(0.0040 + ($userId/1e6), 6, '.', '')
    : '0.004000';

// 3) Load or collect user’s deposit address
$userAddress = null;
$stmt = $conn->prepare("SELECT address FROM workers WHERE email=? LIMIT 1");
$stmt->bind_param("s",$email);
$stmt->execute();
$stmt->bind_result($userAddress);
$stmt->fetch();
$stmt->close();

// If posted a new address
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['wallet_address'])) {
    $addr = strtolower(trim($_POST['wallet_address']));
    if (preg_match('/^0x[a-f0-9]{40}$/',$addr)) {
        $u = $conn->prepare("UPDATE workers SET address=? WHERE email=?");
        $u->bind_param("ss",$addr,$email);
        $u->execute();
        $u->close();
        $userAddress = $addr;
    } else {
        echo "<p style='color:red'>❌ Invalid BNB address format.</p>";
    }
}

// If the user has no address yet, show the input form and exit
if (!$userAddress): ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Save Your BNB Address</title>
  <style>
    body{font-family:sans-serif;background:#eef2ff;padding:20px;text-align:center}
    .box{background:#fff;padding:20px;border-radius:12px;max-width:480px;margin:auto;box-shadow:0 4px 12px rgba(0,0,0,0.1)}
    input,button{width:100%;padding:12px;margin-top:10px;font-size:16px;border-radius:6px;border:1px solid #ccc}
    button{background:#2563eb;color:#fff;border:none;cursor:pointer}
  </style>
</head>
<body>
  <div class="box">
    <h2>Enter Your BNB Wallet Address</h2>
    <form method="post">
      <input type="text" name="wallet_address" placeholder="0x..." required />
      <button type="submit">Save Address</button>
    </form>
  </div>
</body>
</html>
<?php exit; endif; ?>

<!-- 4) Session-control for expected amount -->
<?php
if (!isset($_SESSION['expected_amount'])
 || time() - ($_SESSION['amount_time'] ?? 0) > $session_expiry)
{
    $_SESSION['expected_amount'] = $unique_amount;
    $_SESSION['amount_time']      = time();
}

// 5) Handle the AJAX “check” call
if (isset($_GET['check']) && isset($_SESSION['expected_amount'])) {
    $expected = $_SESSION['expected_amount'];
    if (time() - $_SESSION['amount_time'] > $session_expiry) {
        echo "⌛ Session expired."; exit;
    }

    // Fetch recent BNB transactions
    $url = "https://api.bscscan.com/api?module=account&action=txlist"
         . "&address={$bnb_wallet}&startblock=1&endblock=99999999&sort=desc"
         . "&apikey={$api_key}";
    $resp = file_get_contents($url);
    $data = json_decode($resp, true);

    if ($data['status']==='1') {
        foreach ($data['result'] as $tx) {
            $to    = strtolower($tx['to']);
            $from  = strtolower($tx['from']);
            $value = floatval($tx['value'])/1e18;
            $hash  = $tx['hash'];

            // match amount & sender address
            if ($to===strtolower($bnb_wallet)
             && abs($value - $expected) <= 0.00001
             && $from===$userAddress)
            {
                // has this TX already been used?
                $c = $conn->prepare(
                  "SELECT 1 FROM deposit_amounts WHERE tx_hash=? LIMIT 1"
                );
                $c->bind_param("s",$hash);
                $c->execute();
                $c->store_result();
                if ($c->num_rows) {
                    echo "ℹ️ Already credited this TX.";
                    exit;
                }
                $c->close();

                // credit 100 tokens
                $u = $conn->prepare(
                  "UPDATE workers SET tokens_earned=tokens_earned+100 WHERE email=?"
                );
                $u->bind_param("s",$email);
                $u->execute();
                $u->close();

                // record in deposit_amounts (uses your existing schema)
                $i = $conn->prepare(
                  "INSERT INTO deposit_amounts "
                 ." (user_mail, amount, token, address) "
                 ." VALUES (?, ?, ?, ?)"
                );
                $i->bind_param(
                  "sdss",
                  $email,
                  $value,
                  $unique_amount,    // or the number of tokens if you prefer
                  $userAddress
                );
                $i->execute();
                $i->close();

                // clear session and respond
                unset($_SESSION['expected_amount'], $_SESSION['amount_time']);
                echo "✅ 100 WorkTokens credited!";
                exit;
            }
        }
    }

    echo "⏳ Waiting for {$expected} BNB from your address…";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Buy WorkTokens with BNB</title>
  <style>
    body{font-family:sans-serif;background:#eef2ff;padding:20px;text-align:center}
    main{background:#fff;padding:20px;border-radius:12px;max-width:480px;margin:auto;box-shadow:0 4px 12px rgba(0,0,0,0.1)}
    h2{color:#1e40af} .card{background:#f4f7ff;padding:12px 16px;border-radius:10px;position:relative;word-break:break-word}
    .copy-btn{position:absolute;top:10px;right:10px;padding:6px 10px;border:none;border-radius:6px;background:#10b981;color:#fff}
    button{margin-top:20px;padding:12px 18px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:16px;cursor:pointer}
    #countdown,#result{margin-top:16px;font-weight:bold}
    .note{margin-top:15px;color:#555;font-size:15px}
    .whatsapp{margin-top:20px;font-size:15px;color:#065f46;background:#d1fae5;border:1px solid #10b981;padding:10px;border-radius:10px}
    #console{margin-top:30px;background:#f9fafb;border-radius:8px;padding:10px;font-size:13px;text-align:left;max-height:200px;overflow-y:auto}
    #console pre{margin:0;white-space:pre-wrap}
  </style>
</head>
<body>
<main>
  <h2>Deposit to Get 100 WorkTokens</h2>
  <p><strong>Logged in as:</strong><br><?=htmlspecialchars($email)?></p>
  <p>Send exactly <strong><?=$unique_amount?> BNB</strong> from <br><strong><?=$userAddress?></strong> →</p>
  <div class="card">
    <span id="wallet-address"><?=$bnb_wallet?></span>
    <button class="copy-btn" onclick="copyWallet()">Copy</button>
  </div>
  <button onclick="startTimer()" id="deposit-btn">I Deposited the Amount</button>

  <div class="note">
    You can mine BNB on <a href="https://unmineable.com/" target="_blank">unmineable.com</a>.
  </div>
  <div class="whatsapp">
    Wrong amount? Chat: <a href="https://wa.me/420723447398" target="_blank">+420 723 447 398</a>.
  </div>

  <div id="countdown" style="display:none">
    ⏱ Time left: <span id="timer"><?=$session_expiry?></span>s
  </div>
  <div id="result" style="display:none"></div>

  <div id="console">
    <strong>💡 Last BNB deposits:</strong>
    <pre id="log">Loading...</pre>
  </div>
</main>
<script>
let seconds = <?=$session_expiry?>, interval, checker;
function startTimer(){
  document.getElementById('countdown').style.display='block';
  document.getElementById('deposit-btn').disabled=true;
  interval = setInterval(()=>{
    document.getElementById('timer').textContent=--seconds;
    if(seconds<=0){
      clearInterval(interval); clearInterval(checker);
      showResult('⌛ Not found in 5 mins.');
    }
  },1000);
  checker=setInterval(async()=>{
    let msg=await fetch('?check=1').then(r=>r.text());
    const log=document.getElementById('log');
    log.textContent += "\n"+msg;
    log.scrollTop=log.scrollHeight;
    if(msg.includes('✅')) {
      clearInterval(interval); clearInterval(checker);
      showResult(msg);
    }
  },5000);
}
function showResult(txt){
  document.getElementById('result').textContent=txt;
  document.getElementById('result').style.display='block';
  document.getElementById('deposit-btn').disabled=false;
}
function copyWallet(){
  navigator.clipboard.writeText(
    document.getElementById('wallet-address').textContent.trim()
  ).then(_=>alert("Copied!"));
}

// Load recent deposits on page load
(async function(){
  const log=document.getElementById('log');
  try {
    let res=await fetch(`https://api.bscscan.com/api?module=account&action=txlist&address=<?=$bnb_wallet?>&startblock=1&endblock=99999999&sort=desc&apikey=<?=$api_key?>`);
    let json=await res.json();
    if(json.status==='1'){
      log.textContent='';
      let shown=0;
      for(let tx of json.result){
        if(tx.to.toLowerCase()==="<?=$bnb_wallet?>" ){
          let b=(parseFloat(tx.value)/1e18).toFixed(8);
          log.textContent+=`→ From ${tx.from} sent ${b} BNB\n`;
          if(++shown>=5) break;
        }
      }
      if(!shown) log.textContent='No recent deposits.';
    } else log.textContent='⚠️ Failed to load.';
  } catch(e){
    log.textContent='❗Error: '+e;
  }
})();
</script>
</body>
</html>