<?php
session_start();
include('../config.php');

// Word list and initialization
$words = ["planet", "bubble", "dragon", "coffee", "wizard"];
if (!isset($_SESSION['word'])) {
    $_SESSION['word'] = $words[array_rand($words)];
    $_SESSION['display'] = str_repeat("_", strlen($_SESSION['word']));
    $_SESSION['guesses'] = 0;
}

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess'])) {
    header('Content-Type: application/json');

    $guess = strtolower(trim($_POST['guess']));
    $word = $_SESSION['word'];
    $display = str_split($_SESSION['display']);
    $correct = false;

    for ($i = 0; $i < strlen($word); $i++) {
        if ($word[$i] === $guess && $display[$i] === '_') {
            $display[$i] = $guess;
            $correct = true;
        }
    }

    $_SESSION['display'] = implode("", $display);
    $_SESSION['guesses']++;

    $finished = false;
    if ($_SESSION['display'] === $word) {
        $message = "✅ You guessed it in {$_SESSION['guesses']} tries!";
        $finished = true;
        session_destroy();
    } else {
        $message = $correct ? "✅ Good guess!" : "❌ Wrong letter. Try again.";
    }

    echo json_encode([
        'display' => implode(" ", str_split($_SESSION['display'])),
        'message' => $message,
        'finished' => $finished
    ]);
    exit;
}
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
      background: linear-gradient(to bottom right, #f8f9fa, #cce3f2);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }
    .card {
      background: white;
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      text-align: center;
      width: 100%;
      max-width: 400px;
    }
    h1 {
      font-size: 1.8em;
      margin-bottom: 15px;
      color: #333;
    }
    #word {
      font-size: 2em;
      letter-spacing: 8px;
      margin-bottom: 20px;
      word-wrap: break-word;
    }
    input[type="text"] {
      padding: 10px;
      font-size: 1.5em;
      width: 60px;
      text-align: center;
      border-radius: 8px;
      border: 1px solid #aaa;
      margin-right: 10px;
    }
    button {
      background-color: #007bff;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      font-size: 1em;
    }
    button:hover {
      background-color: #0056b3;
    }
    #result {
      margin-top: 20px;
      font-size: 1.1em;
      color: #444;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>🔤 Guess the Word</h1>
    <div id="word"><?php echo implode(" ", str_split($_SESSION['display'])); ?></div>
    <div>
      <input type="text" id="guess" maxlength="1" placeholder="?" autofocus />
      <button onclick="makeGuess()">Guess</button>
    </div>
    <p id="result">Start guessing!</p>
  </div>

  <script>
    function makeGuess() {
      const input = document.getElementById("guess");
      const letter = input.value.toLowerCase().trim();
      input.value = "";

      if (!letter.match(/^[a-z]$/)) return;

      fetch("word.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "guess=" + encodeURIComponent(letter)
      })
      .then(res => res.json())
      .then(data => {
        document.getElementById("word").textContent = data.display;
        document.getElementById("result").textContent = data.message;
        if (data.finished) {
          setTimeout(() => location.reload(), 3000);
        }
      });
    }

    document.getElementById("guess").addEventListener("keyup", function(e) {
      if (e.key === "Enter") makeGuess();
    });
  </script>
</body>
</html>