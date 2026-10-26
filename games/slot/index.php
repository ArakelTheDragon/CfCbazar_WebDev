<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../config.php';

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
        $title = 'Slot Machine';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>🎰 Slot Machine | CfCbazar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            background: #111;
            color: white;
            padding: 20px;
        }
        h1 { margin-bottom: 20px; }

        .slot-wrapper {
            position: relative;
            width: max-content;
            margin: auto;
        }

        .slot-grid {
            display: grid;
            grid-template-columns: repeat(5, 70px);
            grid-template-rows: repeat(3, 70px);
            gap: 10px;
            justify-content: center;
        }

        .slot-cell {
            font-size: 2.5em;
            background: #222;
            border: 2px solid #555;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #winCanvas {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        button {
            padding: 12px 24px;
            font-size: 18px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        button:hover { background: #218838; }

        #result {
            font-size: 20px;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <h1>🎰 Slot Machine</h1>

    <div class="slot-wrapper">
        <canvas id="winCanvas" width="450" height="250"></canvas>

        <div class="slot-grid" id="slotGrid">
            <?php for ($i = 0; $i < 15; $i++): ?>
                <div class="slot-cell">❓</div>
            <?php endfor; ?>
        </div>
    </div>

    <button onclick="spin()">Spin</button>
    <div id="result"></div>

    <script>
        const items = ['🍒', '🍋', '🍉', '🔔', '💎', '🍇', '⭐'];

        function getSymbols() {
            return Array.from({ length: 15 }, () => items[Math.floor(Math.random() * items.length)]);
        }

        function clearLines() {
            const canvas = document.getElementById("winCanvas");
            const ctx = canvas.getContext("2d");
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        // Draw a clean strike-through ONLY over the 3 matching symbols
        function drawSymbolStrike(row, colStart) {
            const canvas = document.getElementById("winCanvas");
            const ctx = canvas.getContext("2d");

            ctx.strokeStyle = "#00ff00";
            ctx.lineWidth = 5;
            ctx.lineCap = "round";

            const cellSize = 80; // 70px cell + 10px gap

            // Y position (center of the row)
            const y = row * cellSize + 35;

            // X positions for the 3 matching symbols
            const x1 = colStart * cellSize + 15;
            const x3 = (colStart + 3) * cellSize - 15;

            ctx.beginPath();
            ctx.moveTo(x1, y);
            ctx.lineTo(x3, y);
            ctx.stroke();
        }

        function spin() {
            const grid = document.querySelectorAll('.slot-cell');
            const resultEl = document.getElementById('result');
            const symbols = getSymbols();

            clearLines();

            symbols.forEach((sym, i) => grid[i].innerText = sym);

            let winCount = 0;

            for (let row = 0; row < 3; row++) {
                let start = row * 5;

                for (let col = 0; col < 3; col++) {
                    const a = symbols[start + col];
                    const b = symbols[start + col + 1];
                    const c = symbols[start + col + 2];

                    if (a === b && b === c) {
                        winCount++;
                        drawSymbolStrike(row, col); // draw only over the 3 symbols
                    }
                }
            }

            resultEl.innerText = winCount > 0
                ? `🎉 You got ${winCount} match${winCount > 1 ? 'es' : ''}!`
                : `😞 No matches this time. Try again!`;
        }
    </script>

</body>
</html>

