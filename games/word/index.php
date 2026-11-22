<?php
// /games/word.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

require_once __DIR__ . '/../../includes/settings_global.php';

$NEED_SESSION = true;
$NEED_LOGIN   = false;

enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// --- Page metadata -----------------------------------------------------------
$url        = "/games/word.php";
$parent_url = "/games/";
$title      = "Word Guess Game";
$template   = "Generic";
$meta_title = "Word Guess Game | CfCbazar";
$meta_desc  = "Guess the hidden word before the timer runs out.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);
setReturnUrl($url);

// ----- Load dictionary -----
// Note: Loading a massive JSON file on every request is very slow. 
// We recommend converting it to a line-by-line .txt file for production.
$dictPath = __DIR__ . '/words_dictionary.json';
$wordsDict = file_exists($dictPath) ? json_decode(file_get_contents($dictPath), true) : [];

// Fallback if file fails to load
if (empty($wordsDict)) {
    $wordsDict = ['apple' => 'A common fruit', 'banana' => 'A yellow fruit', 'cherry' => 'A red fruit'];
}

// ----- Pick a new word -----
if (!isset($_SESSION['word']) || empty($_SESSION['word']) || isset($_POST['new_word'])) {
    $keys = array_keys($wordsDict);
    $_SESSION['word'] = strtolower($keys[array_rand($keys)]);
    $_SESSION['start_time'] = time();
}

$word = $_SESSION['word'];
$hint = $wordsDict[$word] ?? "No hint available.";

// ----- Timer logic (Server-side calculation) -----
$gameTimer = 20.0;
$timeElapsed = time() - $_SESSION['start_time'];
$timeLeft = max(0.0, $gameTimer - $timeElapsed);

// If the time is already up on page load, reset it
if ($timeLeft <= 0) {
    $_SESSION['start_time'] = time();
    $timeLeft = $gameTimer;
}

render_header($title);
?>

<style>
/* Add color and a background so it's readable regardless of the site's theme */
.word-wrap { 
    max-width: 600px; 
    margin: 0 auto; 
    text-align: center; 
    padding: 20px 10px; 
    color: #333333; /* Dark gray text */
    background: #f8fafc; /* Light background */
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}
#word { font-size: 2.5rem; letter-spacing: 12px; text-align: center; margin: 20px 0; word-break: break-word; font-weight: bold; color: #0077cc; }
#hint { font-size: 1.1rem; color: #555; margin-bottom: 20px; font-style: italic; }
#keyboard { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-bottom: 25px; max-width: 500px; margin-left: auto; margin-right: auto; }
#keyboard button { 
    flex: 1 0 10%; 
    padding: 12px 0; 
    font-size: 1.1rem; 
    border-radius: 6px; 
    cursor: pointer; 
    min-width: 40px; 
    border: 1px solid #cbd5e1; 
    background: #fff; 
    color: #333333; /* <--- Add this line */
    font-weight: bold; 
    transition: background .15s; 
}
#keyboard button:hover:not(:disabled) { background: #eef6ff; border-color: #0077cc; }
#keyboard button:disabled { opacity: 0.4; cursor: not-allowed; background: #f1f5f9; }
#time-left { font-weight: bold; color: #dc2626; font-size: 1.2rem; }
#reset-game { display: inline-block; margin-top: 15px; padding: 10px 20px; font-size: 1rem; border-radius: 6px; cursor: pointer; background: #0077cc; color: #fff; border: none; font-weight: bold; }
#reset-game:hover { background: #005fa3; }
#result { font-size: 1.3rem; font-weight: bold; margin-top: 15px; min-height: 30px; }
.note { font-size: 1rem; color: #666; margin: 15px 0; }

@media(max-width:600px){
    #keyboard button { flex: 1 0 13%; font-size: 0.9rem; padding: 10px 0; }
    #word { font-size: 1.8rem; letter-spacing: 8px; }
}
</style>

<div class="word-wrap">
    <h1>Word Guess Game</h1>

    <div id="hint">💡 <?= htmlspecialchars($hint) ?></div>
    
    <div id="word">
        <?= implode(' ', str_split(str_repeat('_', strlen($word)))) ?>
    </div>

    <div class="note">Time left: <span id="time-left"><?= number_format($timeLeft, 2) ?></span>s</div>

    <div id="keyboard"></div>
    
    <div id="result"></div>

    <button id="reset-game" type="button">Reset Game</button>
</div>

<script>
// Safely pass the PHP word to JS
const word = <?= json_encode($word) ?>;
const initialTimeLeft = <?= json_encode($timeLeft) ?>;

let display = Array(word.length).fill("_");
let guessed = new Set(JSON.parse(localStorage.getItem("letters_" + word) || "[]"));
let timeLeft = initialTimeLeft;
let gameOver = false;
let timer;

const rows = ["qwertyuiop", "asdfghjkl", "zxcvbnm"];

// Initialize display with saved letters
guessed.forEach(letter => {
    for (let i = 0; i < word.length; i++) {
        if (word[i] === letter) display[i] = letter;
    }
});
document.getElementById("word").textContent = display.join(" ");

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
                localStorage.setItem("letters_" + word, JSON.stringify([...guessed]));
                btn.disabled = true;
                updateWord(l);
                
                if (display.join("") === word) finish(true);
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

    const res = document.getElementById("result");
    if (win) {
        res.textContent = "🎉 You guessed the word!";
        res.style.color = "#059669";
    } else {
        res.textContent = "⏱️ Time's up! The word was: " + word.toUpperCase();
        res.style.color = "#dc2626";
    }

    // Disable all buttons on finish
    document.querySelectorAll("#keyboard button").forEach(b => b.disabled = true);
    
    // Clear local storage for this word
    localStorage.removeItem("letters_" + word);

    // Wait a moment, then reload to get a new word
    setTimeout(() => {
        // We reload the page to let PHP pick a new word
        window.location.reload();
    }, 3000);
}

function loop() {
    timer = setInterval(() => {
        if (gameOver) return;
        timeLeft -= 0.01;
        
        if (timeLeft < 0) timeLeft = 0;
        document.getElementById("time-left").textContent = timeLeft.toFixed(2);
        
        if (timeLeft <= 0) finish(false);
    }, 10);
}

document.getElementById("reset-game").onclick = () => {
    // Send a POST to trigger a new word on the server
    const form = document.createElement("form");
    form.method = "POST";
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "new_word";
    input.value = "1";
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
};

buildKeyboard();
loop();
</script>

<?php
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/games/word.php",
    "Word Guess Game Source Code"
);
include_footer();
close_database();
?>
