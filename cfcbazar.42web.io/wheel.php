<?php
// wheel.php — Public Wheel of Fortune Game with Simulated Cash, Visit Tracking, and SEO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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
        $title = 'Wheel of Fortune';

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
    <title>🎡 Wheel of Fortune | CfCbazar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Spin the Wheel of Fortune and win simulated cash prizes. A free game by CfCbazar — no login required!" />
    <meta name="keywords" content="wheel of fortune, spin game, CfCbazar, free game, arcade, simulated cash, no login" />
    <meta name="author" content="CfCbazar" />
    <meta name="robots" content="index, follow" />

    <!-- Open Graph -->
    <meta property="og:title" content="🎡 Wheel of Fortune | CfCbazar" />
    <meta property="og:description" content="Spin the wheel and win simulated cash prizes in CfCbazar's free arcade game. No login required!" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://cfcbazar.ct.ws/wheel.php" />
    <meta property="og:image" content="https://cfcbazar.ct.ws/assets/wheel-preview.png" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="🎡 Wheel of Fortune | CfCbazar" />
    <meta name="twitter:description" content="Play CfCbazar's free Wheel of Fortune game. Spin and win simulated cash!" />
    <meta name="twitter:image" content="https://cfcbazar.ct.ws/assets/wheel-preview.png" />

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        #wheelContainer {
            position: relative;
            margin: 20px auto;
            width: 300px;
            height: 300px;
        }
        canvas {
            border: 5px solid #333;
            border-radius: 50%;
            background: #fff;
            width: 100%;
            height: 100%;
        }
        #pin {
            width: 20px;
            height: 40px;
            background-color: red;
            position: absolute;
            left: 50%;
            top: 10px;
            transform: translateX(-50%);
            border-radius: 5px;
            z-index: 2;
        }
        #spinButton {
            padding: 10px 20px;
            font-size: 1.1rem;
            background-color: #3498db;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }
        #spinButton:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <h1>🎡 Wheel of Fortune</h1>
    <h2 id="balanceDisplay">Cash: $1.000</h2>
    <h3>Spin Cost: $0.05</h3>

    <div id="wheelContainer">
        <div id="pin"></div>
        <canvas id="wheelCanvas" width="300" height="300"></canvas>
    </div>

    <button id="spinButton">Spin the Wheel!</button>

    <script>
        let totalBalance = 1.000;
        const spinCost = 0.05;
        const canvas = document.getElementById('wheelCanvas');
        const ctx = canvas.getContext('2d');

        const prizes = [
            { text: "+$0.01", value: 0.01, color: "#f39c12", weight: 40 },
            { text: "+$0.025", value: 0.025, color: "#e74c3c", weight: 30 },
            { text: "+$0.05", value: 0.05, color: "#2ecc71", weight: 15 },
            { text: "+$0.10", value: 0.10, color: "#9b59b6", weight: 10 },
            { text: "-$0.05", value: -0.05, color: "#3498db", weight: 50 },
            { text: "-$0.15", value: -0.15, color: "#1abc9c", weight: 30 },
            { text: "-$0.30", value: -0.30, color: "#e67e22", weight: 20 },
            { text: "Jackpot! +$5.00", value: 5.00, color: "#34495e", weight: 1 }
        ];

        const arcSize = (2 * Math.PI) / prizes.length;
        let rotationAngle = -Math.PI / 2 - arcSize / 2;
        let spinStartTime = null;
        let spinDuration = 3000;
        let startRotation = 0;
        let targetRotation = 0;
        let spinning = false;
        let targetPrizeIndex = 0;

        function drawWheel() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = canvas.width / 2 - 10;

            for (let i = 0; i < prizes.length; i++) {
                const startAngle = rotationAngle + i * arcSize;
                const endAngle = startAngle + arcSize;

                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, startAngle, endAngle, false);
                ctx.closePath();
                ctx.fillStyle = prizes[i].color;
                ctx.fill();
                ctx.strokeStyle = "#fff";
                ctx.lineWidth = 2;
                ctx.stroke();

                ctx.save();
                ctx.translate(centerX, centerY);
                ctx.rotate(startAngle + arcSize / 2);
                ctx.textAlign = "right";
                ctx.fillStyle = "#fff";
                ctx.font = "bold 12px Arial";
                ctx.fillText(prizes[i].text, radius - 10, 10);
                ctx.restore();
            }
        }

        function easeOutCubic(t) {
            return (--t) * t * t + 1;
        }

        function updateBalanceDisplay() {
            document.getElementById("balanceDisplay").textContent =
                "Cash: $" + totalBalance.toFixed(3);
        }

        function getWeightedPrizeIndex() {
            const totalWeight = prizes.reduce((sum, p) => sum + p.weight, 0);
            let rand = Math.random() * totalWeight;

            for (let i = 0; i < prizes.length; i++) {
                if (rand < prizes[i].weight) return i;
                rand -= prizes[i].weight;
            }
            return 0;
        }

        function animateSpin(timestamp) {
            if (!spinStartTime) spinStartTime = timestamp;
            const elapsed = timestamp - spinStartTime;
            const t = Math.min(elapsed / spinDuration, 1);
            const eased = easeOutCubic(t);

            rotationAngle = startRotation + (targetRotation - startRotation) * eased;
            drawWheel();

            if (t < 1) {
                requestAnimationFrame(animateSpin);
            } else {
                spinning = false;
                const prize = prizes[targetPrizeIndex];
                totalBalance += prize.value;
                updateBalanceDisplay();
                alert(`You won ${prize.text}!`);
            }
        }

        document.getElementById("spinButton").addEventListener("click", () => {
            if (spinning) return;
            if (totalBalance < spinCost) {
                alert("❌ Not enough cash to spin!");
                return;
            }

            spinning = true;
            totalBalance -= spinCost;
            targetPrizeIndex = getWeightedPrizeIndex();

            const pointer = -Math.PI / 2;
            const baseAngle = pointer - (targetPrizeIndex + 0.5) * arcSize;

            startRotation = rotationAngle;

            let ns = ((startRotation % (2 * Math.PI)) + 2 * Math.PI) % (2 * Math.PI);
            let nb = ((baseAngle % (2 * Math.PI)) + 2 * Math.PI) % (2 * Math.PI);

            let extra = nb - ns;
            if (extra <= 0) extra += 2 * Math.PI;

            const fullRounds = 5;
            targetRotation = startRotation + fullRounds * 2 * Math.PI + extra;

            spinStartTime = null;
            requestAnimationFrame(animateSpin);
        });

        window.onload = () => {
            drawWheel();
            updateBalanceDisplay();
        };
    </script>
</body>
</html>

