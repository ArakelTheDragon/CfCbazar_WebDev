<?php
session_start();

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
    if (function_exists('trackVisit')) trackVisit("games-number");
}

// ----- Load visit tracking only (no tokens, no login) -----
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

if (isset($conn)) {
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
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Equation Challenge Game</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Solve math equations under time pressure. Test your brain and speed in the Equation Challenge!" />

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

        document.getElementById('submit-btn').addEventListener('click', () => {
            clearInterval(timerInterval);
            checkAnswer();
        });

        document.getElementById('play-again-js').addEventListener('click', generateValidQuestion);

        document.getElementById('reset-game').addEventListener('click', () => {
            location.reload();
        });

        window.onload = generateValidQuestion;
    </script>
</body>
</html>

