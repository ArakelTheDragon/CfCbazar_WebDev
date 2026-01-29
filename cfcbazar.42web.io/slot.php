<?php
// slot.php — Public Slot Machine Game with Visit Tracking and SEO
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
    <meta name="description" content="Play the free Slot Machine game on CfCbazar. Spin the reels, match symbols, and win virtual rewards. No login or tokens required!" />
    <meta name="keywords" content="slot machine, free game, online slots, CfCbazar, spin game, arcade, no login" />
    <meta name="author" content="CfCbazar" />
    <meta name="robots" content="index, follow" />

    <!-- Open Graph -->
    <meta property="og:title" content="🎰 Slot Machine | CfCbazar" />
    <meta property="og:description" content="Spin the reels and win in CfCbazar's free Slot Machine game. No login or tokens required!" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://cfcbazar.ct.ws/slot.php" />
    <meta property="og:image" content="https://cfcbazar.ct.ws/assets/slot-preview.png" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="🎰 Slot Machine | CfCbazar" />
    <meta name="twitter:description" content="Play CfCbazar's free Slot Machine game. Spin the reels and match symbols. No login required!" />
    <meta name="twitter:image" content="https://cfcbazar.ct.ws/assets/slot-preview.png" />

    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            background: #111;
            color: white;
            padding: 20px;
        }
        h1 {
            margin-bottom: 20px;
        }
        .slot-grid {
            display: grid;
            grid-template-columns: repeat(5, 70px);
            grid-template-rows: repeat(3, 70px);
            gap: 10px;
            justify-content: center;
            margin: 20px auto;
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
        button {
            padding: 12px 24px;
            font-size: 18px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        button:hover {
            background: #218838;
        }
        #result {
            font-size: 20px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <h1>🎰 Slot Machine</h1>
    <div class="slot-grid" id="slotGrid">
        <?php for ($i = 0; $i < 15; $i++): ?>
            <div class="slot-cell">❓</div>
        <?php endfor; ?>
    </div>
    <button onclick="spin()">Spin</button>
    <div id="result"></div>

    <script>
        const items = ['🍒', '🍋', '🍉', '🔔', '💎', '🍇', '⭐'];

        function getSymbols() {
            return Array.from({ length: 15 }, () => items[Math.floor(Math.random() * items.length)]);
        }

        function spin() {
            const grid = document.querySelectorAll('.slot-cell');
            const resultEl = document.getElementById('result');
            const symbols = getSymbols();

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
