<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/../config.php';

// redirect if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit;
}
$email = $_SESSION['username'];

// connect
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// load word list
$words_data = json_decode(file_get_contents(__DIR__ . '/words_dictionary.json'), true);

// helper: get current balance
function getBalance($email, $conn) {
    $stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($tokens);
    $stmt->fetch();
    $stmt->close();
    return floatval($tokens);
}

// helper: apply delta to balance
function updateBalance($email, $delta, $conn) {
    $stmt = $conn->prepare("
        UPDATE workers
        SET tokens_earned = GREATEST(tokens_earned + ?, 0)
        WHERE email = ?
    ");
    $stmt->bind_param("ds", $delta, $email);
    $stmt->execute();
    $stmt->close();
}

// --- START A NEW GAME ON GET, ONCE PER SESSION ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['word'])) {
    $balance = getBalance($email, $conn);
    if ($balance < 0.01) {
        die("❌ Not enough WorkToken to play!");
    }
    updateBalance($email, -0.01, $conn);

    $_SESSION['word']    = array_rand($words_data);
    $_SESSION['display'] = str_repeat("_", strlen($_SESSION['word']));
    $_SESSION['guesses'] = 0;
}

// --- HANDLE A GUESS VIA AJAX POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess'])) {
    header('Content-Type: application/json');

    $word        = $_SESSION['word'];
    $display     = $_SESSION['display'];
    $hint        = $words_data[$word] ?? "Guess the word!";
    $guess       = strtolower(trim($_POST['guess']));
    $arr         = str_split($display);
    $correct     = false;

    // reveal matches
    for ($i = 0; $i < strlen($word); $i++) {
        if ($word[$i] === $guess && $arr[$i] === '_') {
            $arr[$i] = $guess;
            $correct = true;
        }
    }

    $_SESSION['display'] = implode("", $arr);
    $_SESSION['guesses']++;

    // check for win
    if ($_SESSION['display'] === $word) {
        $message  = "✅ You guessed it in {$_SESSION['guesses']} tries!";
        $finished = true;
        // clear session so new game only on manual reset
        unset($_SESSION['word'], $_SESSION['display'], $_SESSION['guesses']);
    } else {
        $message  = $correct ? "✅ Good guess!" : "❌ Wrong letter.";
        $finished = false;
    }

    echo json_encode([
        'display'  => implode(" ", str_split($display = implode("", $arr))),
        'message'  => $message,
        'hint'     => $hint,
        'finished' => $finished
    ]);
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Guess the Word</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #f8f9fa, #cce3f2);
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; margin: 0;
    }
    .card {
      background: #fff; padding: 24px; border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
      text-align: center; max-width: 360px; width: 100%;
    }
    #balance {
      font-weight: bold; margin-bottom: 16px;
    }
    h1 {
      margin: 0 0 12px; font-size: 1.6em; color: #333;
    }
    #word {
      font-size: 2em; letter-spacing: 6px; margin: 16px 0;
    }
    #hint, #result {
      margin: 12px 0; color: #444;
    }
    .keyboard {
      display: flex; flex-wrap: wrap;
      justify-content: center; margin-top: 12px;
    }
    .keyboard button {
      width: 36px; height: 44px; margin: 4px;
      font-size: 1em; border: 1px solid #aaa;
      border-radius: 4px; background: #f0f0f0;
      cursor: pointer;
    }
    .keyboard button:disabled {
      background: #ccc; cursor: default;
    }
    #reset-game {
      margin-top: 20px;
      padding: 10px 20px; background: #007bff;
      color: #fff; border: none; border-radius: 6px;
      font-size: 1em; cursor: pointer;
    }
    #reset-game:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>
  <div class="card">
    <div id="balance">
      Balance: <span id="balance-value">…</span> WT
    </div>
    <h1>🔤 Guess the Word</h1>
    <div id="word"><?php echo implode(' ', str_split($_SESSION['display'])); ?></div>
    <div id="hint"><?php echo htmlspecialchars($words_data[$_SESSION['word']] ?? ''); ?></div>
    <p id="result">Start guessing!</p>
    <div id="keyboard" class="keyboard"></div>
    <button id="reset-game">Reset Game</button>
  </div>

  <script>
    let gameOver = false;
    const balanceEl = document.getElementById('balance-value');
    const wordEl    = document.getElementById('word');
    const hintEl    = document.getElementById('hint');
    const resultEl  = document.getElementById('result');
    const kb        = document.getElementById('keyboard');
    const resetBtn  = document.getElementById('reset-game');

    // fetch and display balance
    function fetchBalance() {
      fetch('../worker.php?email=<?= urlencode($email) ?>')
        .then(res => res.json())
        .then(data => {
          balanceEl.textContent = parseFloat(data.tokens_earned || 0).toFixed(5);
        })
        .catch(() => balanceEl.textContent = 'Error');
    }

    // build the on-page keyboard (always resets buttons)
    function buildKeyboard() {
      gameOver = false;
      kb.innerHTML = '';
      'abcdefghijklmnopqrstuvwxyz'.split('').forEach(letter => {
        const btn = document.createElement('button');
        btn.textContent    = letter.toUpperCase();
        btn.dataset.letter = letter;
        btn.addEventListener('click', () => onKey(letter, btn));
        kb.appendChild(btn);
      });
    }

    // handle letter click
    function onKey(letter, btn) {
      if (gameOver) return;
      btn.disabled = true;
      sendGuess(letter);
    }

    // POST guess to server
    function sendGuess(letter) {
      fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'guess=' + encodeURIComponent(letter)
      })
      .then(res => res.json())
      .then(data => {
        wordEl.textContent   = data.display;
        resultEl.textContent = data.message;
        hintEl.textContent   = data.hint;

        if (data.finished) {
          gameOver = true;
          // disable all remaining keys
          kb.querySelectorAll('button').forEach(b => b.disabled = true);
        }
      });
    }

    // manual reset: reload page and trigger new GET-game
    resetBtn.addEventListener('click', () => {
      location.reload();
    });

    window.addEventListener('load', () => {
      fetchBalance();
      buildKeyboard();
    });
  </script>
</body>
</html>