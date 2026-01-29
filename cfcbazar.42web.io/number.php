<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php'; // defines $conn

// Visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
    $upd->bind_param('s', $path);
    $upd->execute();

    if ($upd->affected_rows === 0) {
        $slug  = ltrim($path, '/');
        $slug  = $slug === '' ? 'index' : $slug;
        $title = 'Equation Challenge';

        $ins = $conn->prepare("
            INSERT INTO pages (title, slug, path, visits, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");
        if ($ins) {
            $ins->bind_param('sss', $title, $slug, $path);
            $ins->execute();
            $ins->close();
        }
    }
    $upd->close();
}

// Optional: get email if logged in
$email   = $_SESSION['email'] ?? '';
$balance = 0;

// Helpers
function getBalance($email, $conn) {
    $stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($tokens);
    $stmt->fetch();
    $stmt->close();
    return floatval($tokens);
}

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

// Handle AJAX token deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deduct_tokens']) && $email) {
    header('Content-Type: application/json');
    $current = getBalance($email, $conn);

    if ($current < 0.01) {
        echo json_encode(['success' => false, 'error' => 'Insufficient tokens']);
        exit;
    }

    updateBalance($email, -0.01, $conn);
    $newBalance = getBalance($email, $conn);

    echo json_encode(['success' => true, 'balance' => $newBalance]);
    exit;
}

// Fetch balance once for initial page render
if ($email) {
    $balance = getBalance($email, $conn);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Equation Challenge Game</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Solve math equations under time pressure. Test your brain and speed in the Equation Challenge!" />
    <meta name="keywords" content="math game, equation challenge, brain training, WorkToken, CfCbazar" />
    <meta name="robots" content="index, follow" />

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f0f4f8, #d3e8fa);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }
        .card:hover { transform: scale(1.02); }
        #balance { font-weight: bold; margin-bottom: 20px; color: #222; }
        h1 { font-size: 1.8em; margin-bottom: 15px; color: #333; }
        #question { font-size: 1.5em; font-weight: bold; margin: 15px 0; }
        input[type="number"] {
            padding: 10px;
            font-size: 1em;
            border-radius: 8px;
            border: 1px solid #aaa;
            width: 120px;
            text-align: center;
            margin-bottom: 15px;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin: 5px;
            transition: background 0.3s ease;
        }
        button:hover { background-color: #0056b3; }
        #timer { font-size: 1.2em; color: #d9534f; margin: 10px 0; }
        #result { margin-top: 15px; font-size: 1.2em; color: #333; }
        #reset-game { background-color: #28a745; }
        #reset-game:hover { background-color: #218838; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($email): ?>
            <div id="balance">
                Balance: <span id="balance-value"><?= number_format($balance, 5) ?></span> WT
            </div>
        <?php endif; ?>

        <h1>🧩 Equation Challenge</h1>
        <div id="question"></div>
        <input type="number" id="answer" placeholder="Your answer" />

        <div>
            <button id="submit-btn">Submit</button>
            <button id="play-again-js">Play Again</button>
            <button id="reset-game">Reset Game</button>
        </div>

        <p id="timer">⏱️ Time: 30</p>
        <p id="result"></p>
    </div>

    <script>
        let correctAnswer, timeLeft, timerInterval;
        const balanceEl   = document.getElementById('balance-value');
        const submitBtn   = document.getElementById('submit-btn');
        const playAgainJS = document.getElementById('play-again-js');
        const resetBtn    = document.getElementById('reset-game');

        function deductTokens() {
            <?php if ($email): ?>
            return fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'deduct_tokens=1'
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    balanceEl.textContent = parseFloat(json.balance).toFixed(5);
                } else {
                    alert('Error: ' + json.error);
                }
            });
            <?php else: ?>
            return Promise.resolve();
            <?php endif; ?>
        }

        function generateValidQuestion() {
            clearInterval(timerInterval);
            timeLeft = 30;
            document.getElementById("timer").textContent = "⏱️ Time: " + timeLeft;
            document.getElementById("result").textContent = "";
            document.getElementById("answer").value = "";

            const ops = ['+','-','*','/'];
            const nums = Array.from({ length: 4 }, () => Math.floor(Math.random() * 9 + 1));
            let opsArr = Array.from({ length: 3 }, () => ops[Math.floor(Math.random() * ops.length)]);

            opsArr.forEach((op,i) => {
                if (op === '/') {
                    nums[i] = nums[i+1] * Math.floor(Math.random() * 5 + 1);
                }
            });

            const expr = `${nums[0]} ${opsArr[0]} ${nums[1]} ${opsArr[1]} ${nums[2]} ${opsArr[2]} ${nums[3]}`;
            correctAnswer = eval(expr);

            if (!Number.isInteger(correctAnswer)) return generateValidQuestion();

            document.getElementById("question").textContent = "Solve: " + expr;

            timerInterval = setInterval(() => {
                timeLeft--;
                document.getElementById("timer").textContent = "⏱️ Time: " + timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    checkAnswer();
                }
            }, 1000);
        }

        function checkAnswer() {
            const userAns = parseInt(document.getElementById("answer").value, 10);
            if (userAns === correctAnswer) {
                document.getElementById("result").textContent = "✅ Correct!";
            } else {
                document.getElementById("result").textContent =
                    `❌ Wrong! Correct answer: ${correctAnswer}`;
            }
        }

        // On Submit: deduct tokens (if logged in), then evaluate
        submitBtn.addEventListener('click', () => {
            clearInterval(timerInterval);
            deductTokens().then(checkAnswer);
        });

        // JS–only new question
        playAgainJS.addEventListener('click', generateValidQuestion);

        // Full reset including balance re-fetch (simplest form)
        resetBtn.addEventListener('click', () => {
            location.reload();
        });

        // Start game on page load
        window.onload = generateValidQuestion;
    </script>
</body>
</html>
