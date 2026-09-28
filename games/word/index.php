<?php
session_start();

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
    if (function_exists('trackVisit')) trackVisit("games-word");
}

// ----- Load dictionary -----
$dictJson = file_get_contents(__DIR__ . '/words_dictionary.json');
$wordsDict = json_decode($dictJson, true);

// ----- Pick a new word -----
if (!isset($_SESSION['word']) || empty($_SESSION['word']) || isset($_POST['new_word'])) {
    $keys = array_keys($wordsDict);
    $_SESSION['word'] = strtolower($keys[array_rand($keys)]);
    $_SESSION['start_time'] = time();
}

$word = $_SESSION['word'];
$display = str_repeat('_', strlen($word));
$hint = $wordsDict[$word] ?? "No hint available.";

// ----- Timer (no rewards/penalties/login) -----
$gameTimer = 20.0;
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
@media(max-width:600px){
    #keyboard button { flex: 1 0 13%; font-size: 0.85rem; padding: 8px 0; }
    #word { font-size: 1.5rem; letter-spacing: 8px; }
}
</style>
</head>
<body>
<div class="container">
    <h1>Word Guess Game</h1>

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

    document.getElementById("result").textContent = win
        ? "🎉 You guessed the word!"
        : "⏱️ Time's up!";

    localStorage.removeItem("letters");

    setTimeout(() => location.reload(), 1500);
}

let timer;
function loop() {
    timer = setInterval(() => {
        if (gameOver) return;
        timeLeft -= 0.01;
        document.getElementById("time-left").textContent = timeLeft.toFixed(2);
        if (display.join("") === word) finish(true);
        if (timeLeft <= 0) finish(false);
    }, 10);
}

document.getElementById("reset-game").onclick = () => {
    const allGuessed = display.join("") === word;
    finish(allGuessed);
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

