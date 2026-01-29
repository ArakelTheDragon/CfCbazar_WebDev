<?php
session_start();
require 'config.php';

// ----- User login check -----
if (!isset($_SESSION['user_id'])) {
    setcookie("last_page", basename($_SERVER['PHP_SELF']), time() + 3600, "/");
    header("Location: login.php");
    exit();
}

// ----- Get user WorkTHR balance -----
$email = $_SESSION['email'];
$balance = 0.0;
$stmt = $conn->prepare("SELECT mintme FROM workers WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($mintme);
if ($stmt->fetch()) $balance = floatval($mintme);
$stmt->close();

// ----- Load dictionary -----
$dictJson = file_get_contents(__DIR__ . '/words_dictionary.json');
$wordsDict = json_decode($dictJson, true);

// ----- Pick a new word if none exists or after previous game -----
if (!isset($_SESSION['word']) || empty($_SESSION['word']) || isset($_POST['new_word'])) {
    $keys = array_keys($wordsDict);
    $_SESSION['word'] = strtolower($keys[array_rand($keys)]);
    $_SESSION['start_time'] = time();
}

$word = $_SESSION['word'];
$display = str_repeat('_', strlen($word));
$hint = $wordsDict[$word] ?? "No hint available.";

// ----- Timer and reward/penalty -----
$gameTimer = 20.0;
$rewardAmount = 0.01;
$penaltyAmount = 0.01;

// ----- Handle AJAX balance update -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_balance'])) {
    $delta = floatval($_POST['delta']);
    $balance += $delta;
    $stmt = $conn->prepare("UPDATE workers SET mintme = ? WHERE email = ?");
    $stmt->bind_param("ds", $balance, $email);
    $stmt->execute();
    $stmt->close();

    // Reset word after reward/penalty
    $_SESSION['word'] = "";
    echo $balance;
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Word Guess Game</title>
<link rel="stylesheet" href="css/styles.css">
<style>
#word { font-size: 2rem; letter-spacing: 12px; text-align: center; margin: 15px 0; word-break: break-word; }
#hint { font-size: 1.1rem; color: #555; text-align: center; margin-bottom: 15px; }
#keyboard { display: flex; flex-wrap: wrap; justify-content: center; gap: 4px; margin-bottom: 20px; }
#keyboard button { flex: 1 0 10%; padding: 10px 0; font-size: 1rem; border-radius: 6px; cursor: pointer; min-width: 36px; }
#keyboard button:disabled { opacity: 0.5; cursor: not-allowed; }
#time-left { font-weight: bold; }
#reset-game { display: block; margin: 15px auto; padding: 10px 20px; font-size: 1rem; border-radius: 6px; cursor: pointer; background: #28a745; color: #fff; border: none; }
#reset-game:hover { background: #1e7e34; }
.balance { text-align: center; font-weight: bold; margin-bottom: 15px; font-size: 1.1rem; }
@media(max-width:600px){
    #keyboard button { flex: 1 0 13%; font-size: 0.85rem; padding: 8px 0; }
    #word { font-size: 1.5rem; letter-spacing: 8px; }
}
</style>
</head>
<body>
<div class="container">
    <h1>Word Guess Game</h1>
    <div class="balance">Current WorkTHR: <span id="balance"><?= number_format($balance, 2) ?></span></div>
    <div id="word"><?= implode(' ', str_split($display)) ?></div>
    <div id="hint"><?= $hint ?></div>
    <div id="keyboard"></div>
    <div class="note">Time left: <span id="time-left"><?= $gameTimer ?></span>s</div>
    <div id="result"></div>
    <button id="reset-game">Reset Game</button>
</div>

<script>
const word = "<?= $word ?>";
const gameTimer = <?= $gameTimer ?>;
const reward = <?= $rewardAmount ?>;
const penalty = <?= $penaltyAmount ?>;
let display = "_".repeat(word.length).split("");
let guessed = new Set(JSON.parse(localStorage.getItem("letters") || "[]"));
let timeLeft = gameTimer;
let gameOver = false;
const rows = ["qwertyuiop", "asdfghjkl", "zxcvbnm"];

function updateWord(letter) {
    let correct = false;
    for (let i = 0; i < word.length; i++) {
        if (word[i] === letter) {
            display[i] = letter;
            correct = true;
        }
    }
    document.getElementById("word").textContent = display.join(" ");
    return correct;
}

function buildKeyboard() {
    const kb = document.getElementById("keyboard");
    kb.innerHTML = "";
    rows.forEach(row => {
        row.split("").forEach(l => {
            const btn = document.createElement("button");
            btn.textContent = l.toUpperCase();
            btn.disabled = guessed.has(l);
            btn.onclick = () => {
                guessed.add(l);
                localStorage.setItem("letters", JSON.stringify([...guessed]));
                btn.disabled = true;
                updateWord(l);
            };
            kb.appendChild(btn);
        });
        const br = document.createElement("div");
        br.style.flexBasis = "100%";
        kb.appendChild(br);
    });
}

function finish(win) {
    if (gameOver) return;
    gameOver = true;
    clearInterval(timer);
    const delta = win ? reward : -penalty;
    fetch(window.location.href, {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "update_balance=1&delta=" + delta
    }).then(r => r.text()).then(newBal => {
        document.getElementById("balance").textContent = parseFloat(newBal).toFixed(2);
        document.getElementById("result").textContent = win ? "✅ +" + reward + " WorkTHR" : "⏱️ " + penalty + " WorkTHR lost";
        localStorage.removeItem("letters");
        setTimeout(() => location.reload(), 1000); 
    });
}

let timer;
function loop() {
    timer = setInterval(() => {
        if (gameOver) return;
        timeLeft -= 0.01;
        document.getElementById("time-left").textContent = timeLeft.toFixed(2);
        if (display.join("") === word) finish(true);
        if (timeLeft <= 0) finish(false);
    }, 10); // 0.01 sec
}

document.getElementById("reset-game").onclick = () => {
    const allGuessed = display.join("") === word;
    finish(allGuessed ? true : false);
};

window.addEventListener("beforeunload", function(e) {
    if (!gameOver) finish(false);
});

buildKeyboard();
guessed.forEach(l => updateWord(l));
loop();
</script>
</body>
</html>