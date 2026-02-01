<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/../config.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit;
}

$email = $_SESSION['username'];

// Connect
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Load word list
$word_list_path = 'words_dictionary.json';
$words_data = json_decode(file_get_contents($word_list_path), true);

// Helper: fetch balance as float
function getBalance($email, $conn) {
    $stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($tokens);
    $stmt->fetch();
    $stmt->close();
    return floatval($tokens);
}

// Helper: update (add or subtract) tokens_earned
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

// ————————————————
// NEW GAME / DEDUCTION
// ————————————————
if (!isset($_SESSION['word'])) {
    // Deduct cost
    $balance = getBalance($email, $conn);
    if ($balance < 0.01) {
        header('Content-Type: application/json');
        echo json_encode([
            'display'  => '',
            'message'  => '❌ Not enough WorkToken to play!',
            'hint'     => '',
            'finished' => true
        ]);
        exit;
    }
    updateBalance($email, -0.01, $conn);

    // Pick a word & init session state
    $word = array_rand($words_data);
    $_SESSION['word']    = $word;
    $_SESSION['display'] = str_repeat("_", strlen($word));
    $_SESSION['guesses'] = 0;
}

// ————————————————
// SETUP CURRENT STATE
// ————————————————
$word    = $_SESSION['word'];
$hint    = $words_data[$word] ?? "Guess the word!";
$display = $_SESSION['display'];

// ————————————————
// AJAX: HANDLE A LETTER GUESS
// ————————————————
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess'])) {
    header('Content-Type: application/json');

    $guess       = strtolower(trim($_POST['guess']));
    $display_arr = str_split($display);
    $correct     = false;

    for ($i = 0; $i < strlen($word); $i++) {
        if ($word[$i] === $guess && $display_arr[$i] === '_') {
            $display_arr[$i] = $guess;
            $correct         = true;
        }
    }

    $_SESSION['display'] = implode("", $display_arr);
    $_SESSION['guesses']++;

    if ($_SESSION['display'] === $word) {
        $message  = "✅ You guessed it in {$_SESSION['guesses']} tries!";
        $finished = true;
        // ✅ Just unset the game-related session vars (do NOT destroy session)
        unset($_SESSION['word'], $_SESSION['display'], $_SESSION['guesses']);
    } else {
        $message  = $correct ? "✅ Good guess!" : "❌ Wrong letter. Try again.";
        $finished = false;
    }

    echo json_encode([
        'display'  => implode(" ", str_split($_SESSION['display'])),
        'message'  => $message,
        'hint'     => $hint,
        'finished' => $finished
    ]);
    exit;
}

// ————————————————
// RENDER HTML
// ————————————————
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0"/>
  <title>Guess the Word</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to bottom right, #f8f9fa, #cce3f2);
      display: flex; flex-direction: column;
      align-items: center; justify-content: flex-start;
      min-height: 100vh; margin: 0; padding: 20px;
    }
    .card {
      background: white; padding: 20px;
      border-radius: 15px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
      text-align: center; width: 100%;
      max-width: 400px;
    }
    h1 { font-size: 1.8em; margin-bottom: 15px; color: #333; }
    #word {
      font-size: 2em; letter-spacing: 8px;
      margin-bottom: 20px; word-wrap: break-word;
    }
    input[type="text"] {
      padding: 10px; font-size: 1.5em;
      width: 60px; text-align: center;
      border-radius: 8px; border: 1px solid #aaa;
      margin-right: 10px;
    }
    button {
      background-color: #007bff; color: white;
      border: none; padding: 10px 16px;
      border-radius: 8px; font-weight: bold;
      cursor: pointer; font-size: 1em;
    }
    button:hover { background-color: #0056b3; }
    #result, #hint {
      margin-top: 15px; font-size: 1.1em; color: #444;
    }
    #balance {
      font-weight: bold; margin-bottom: 10px; color: #222;
    }
  </style>
</head>
<body>
  <div class="card">
    <div id="balance">
      Balance: <span id="balance-value">Loading...</span> WT
    </div>
    <h1>🔤 Guess the Word</h1>
    <div id="word"><?php echo implode(" ", str_split($display)); ?></div>
    <div>
      <input type="text" id="guess" maxlength="1" placeholder="?" autofocus />
      <button onclick="makeGuess()">Guess</button>
    </div>
    <p id="hint"><?php echo htmlspecialchars($hint); ?></p>
    <p id="result">Start guessing!</p>
  </div>

  <script>
    const input = document.getElementById("guess");

    // Fetch fresh balance from your existing worker.php endpoint
    function fetchBalance() {
      fetch("../worker.php?email=<?= urlencode($email) ?>")
        .then(res => res.json())
        .then(data => {
          document.getElementById("balance-value").textContent =
            parseFloat(data.tokens_earned || 0).toFixed(5);
        })
        .catch(() => {
          document.getElementById("balance-value").textContent = "Error";
        });
    }

    function makeGuess() {
      const letter = input.value.toLowerCase().trim();
      input.value = "";
      if (!letter.match(/^[a-z]$/)) {
        input.focus();
        return;
      }

      fetch("word.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "guess=" + encodeURIComponent(letter)
      })
      .then(res => res.json())
      .then(data => {
        document.getElementById("word").textContent  = data.display;
        document.getElementById("result").textContent= data.message;
        document.getElementById("hint").textContent  = data.hint;
        if (data.finished) {
          input.disabled = true;
          setTimeout(() => location.reload(), 3000);
        } else {
          input.focus();
        }
      });
    }

    input.addEventListener("keyup", e => {
      if (e.key === "Enter") makeGuess();
    });

    window.addEventListener("load", fetchBalance);
  </script>
</body>
</html>