<?php
session_start();
//require_once 'config.php'; // DB connection
require_once __DIR__ . '/includes/reusable.php';
setReturnUrlCookie('/squares.php');

// ------------- COMMON PRIZE SET (must match JS) -------------
$prizes = [0.001, 0.01, 0.1, 1, -0.1, -0.001, -1];
$playCost = 0.01;

// -------- AJAX PLAY REQUEST --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['email'])) {
    echo json_encode([
        'error' => 'Not logged in',
        'login_url' => 'login.php'
    ]);
    exit;
}

    $email = $_SESSION['email'];

    // Load balance
    $stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($tokens);
    $stmt->fetch();
    $stmt->close();

    if ($tokens < $playCost) {
        echo json_encode(['error'=>'Insufficient balance']);
        exit;
    }

    // -------- SELECT TARGET INDEX (0–24) ----------
    $targetIndex = random_int(0, 24);  // cryptographically secure
    $prize = $prizes[$targetIndex % count($prizes)];

    // -------- APPLY BALANCE --------
    $newBalance = $tokens - $playCost + $prize;
    if ($newBalance < 0) $newBalance = 0;

    // Single DB update
    $stmt = $conn->prepare("UPDATE workers SET tokens_earned=? WHERE email=?");
    $stmt->bind_param("ds", $newBalance, $email);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'played'        => $playCost,
        'won'           => $prize,
        'new_balance'   => $newBalance,
        'targetIndex'   => $targetIndex
    ]);
    exit;
}

// -------- AJAX BALANCE LOAD --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_balance'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['email'])) {
        echo json_encode(['error'=>'Not logged in']);
        exit;
    }

    $email = $_SESSION['email'];

    $stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($tokens);
    $stmt->fetch();
    $stmt->close();

    echo json_encode(['balance'=>$tokens]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>WorkToken Squares Game</title>
<style>
body { font-family: system-ui, sans-serif; background:#0b1220; color:#e7eefc; text-align:center; }
.grid { display:grid; grid-template-columns: repeat(5, 80px); gap:8px; justify-content:center; margin-top:20px; }
.cell { width:80px; height:80px; background:#1a2540; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#9fb3ff; position:relative; font-size:14px; }
.pawn { width:40px; height:40px; background:#ffd34e; border-radius:6px; position:absolute; transition:transform 250ms ease; }
.panel { margin-top:20px; }
button { background:#2a3a6e; color:#fff; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; }
button:hover { background:#3b4a8a; }
#loading { margin-top:15px; color:#52ffa8; font-size:16px; display:none; }
</style>
</head>
<body>
<h2>WorkToken Squares Game</h2>

<div class="panel">
  <p id="balance">Balance: loading after you press play…</p>
  <button id="playBtn">Play (Cost: 0.01 WorkTokens)</button>
  <p id="loading">randomizing…</p>
  <p id="result"></p>
</div>

<div class="grid" id="grid"></div>

<script>
// Static prize cycle (same as PHP)
const prizes = [0.001, 0.01, 0.1, 1, -0.1, -0.001, -1];

const gridEl = document.getElementById('grid');

// Build 5×5 grid
for (let i = 0; i < 25; i++) {
  const cell = document.createElement('div');
  cell.className = 'cell';
  cell.textContent = prizes[i % prizes.length];
  gridEl.appendChild(cell);
}

// Pawn element
const pawn = document.createElement('div');
pawn.className = 'pawn';
gridEl.children[0].appendChild(pawn);

// Move pawn to specific square
function movePawn(index) {
  const cell = gridEl.children[index];
  cell.appendChild(pawn);
}

// Load balance on page open
function loadBalance() {
  fetch('squares.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'load_balance=1'
  })
  .then(r=>r.json())
  .then(data=>{
    if (data.balance !== undefined) {
      document.getElementById('balance').textContent =
        "Balance: " + data.balance.toFixed(3) + " WT";
    }
  });
}
loadBalance();

document.getElementById('playBtn').addEventListener('click', () => {
  
  document.getElementById('loading').style.display = 'block';
  document.getElementById('result').textContent = "";

  fetch('squares.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'ajax=1'
  })
  .then(res => res.json())
  .then(data => {

    document.getElementById('loading').style.display = 'none';

    if (data.error) {
  if (data.login_url) {
      document.getElementById('result').innerHTML =
        data.error + ' – <a href="' + data.login_url + '">Login</a>';
  } else {
      document.getElementById('result').textContent = data.error;
  }
  return;
}

    document.getElementById('balance').textContent =
      "Balance: " + data.new_balance.toFixed(3) + " WT";

    document.getElementById('result').textContent =
      "Won: " + data.won.toFixed(3) + " WT";

    movePawn(data.targetIndex);
  });
});
</script>
</body>
</html>