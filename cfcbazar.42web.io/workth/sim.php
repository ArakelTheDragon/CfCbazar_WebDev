<?php
// Constants for 2% daily growth
$initial_units = 100;
$growth_rate = 0.02; // 2% daily
?>

<!DOCTYPE html>
<html>
<head>
    <title> Minimal WorkToken Miner</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0f0f0f;
            color: #0f0;
            padding: 20px;
        }
        .console {
            background: #000;
            padding: 20px;
            border-radius: 8px;
            font-family: monospace;
        }
        .title { font-size: 1.8em; margin-bottom: 10px; }
        .row { margin-bottom: 10px; }
        .miner-animation {
            width: 100%;
            height: 20px;
            background: #222;
            overflow: hidden;
            position: relative;
            border: 1px solid #333;
            margin: 20px 0;
        }
        .pulse {
            width: 30px;
            height: 20px;
            background: lime;
            position: absolute;
            animation: move 2s linear infinite;
        }
        @keyframes move {
            from { left: -30px; }
            to { left: 100%; }
        }
        .timer { font-size: 1em; color: #0f0; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="console">
        <div class="title"> Simulated Hashrate Miner</div>
        <div class="row"> Hash Rate: <span id="hashrate">100.00</span> H/s</div>
        <div class="row"> Work Units: <span id="units">100.00</span></div>
        <div class="row"> Days Simulated: <span id="days">0</span></div>
        <div class="miner-animation"><div class="pulse"></div></div>
        <div class="row timer"> Next reward in: <span id="countdown">--:--:--</span></div>
    </div>

    <script>
        const STORAGE_KEY = 'minerSimPure';
        const msPerDay = 1000 * 60 * 60 * 24;
        const growthRate = <?= $growth_rate ?>;

        let state = {
            units: 100,
            hashrate: 100,
            days: 0,
            lastUpdate: Date.now()
        };

        function loadState() {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                state = JSON.parse(saved);
                const now = Date.now();
                const elapsedDays = Math.floor((now - state.lastUpdate) / msPerDay);
                if (elapsedDays > 0) applyGrowth(elapsedDays);
            }
        }

        function applyGrowth(days) {
            for (let i = 0; i < days; i++) {
                state.units *= (1 + growthRate);
                state.hashrate = state.units;
                state.days += 1;
                state.lastUpdate += msPerDay;
            }
            saveState();
        }

        function simulateDay() {
            state.units *= (1 + growthRate);
            state.hashrate = state.units;
            state.days += 1;
            state.lastUpdate = Date.now();
            saveState();
            renderState();
        }

        function renderState() {
            document.getElementById("units").innerText = state.units.toFixed(2);
            document.getElementById("hashrate").innerText = state.hashrate.toFixed(2);
            document.getElementById("days").innerText = state.days;
        }

        function updateCountdown() {
            const now = Date.now();
            const diff = state.lastUpdate + msPerDay - now;
            const remaining = diff > 0 ? diff : 0;

            const hrs = Math.floor(remaining / (1000 * 60 * 60)).toString().padStart(2, '0');
            const mins = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60)).toString().padStart(2, '0');
            const secs = Math.floor((remaining % (1000 * 60)) / 1000).toString().padStart(2, '0');

            document.getElementById("countdown").innerText = `${hrs}:${mins}:${secs}`;

            if (remaining === 0) {
                simulateDay();
            }
        }

        function saveState() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        }

        loadState();
        renderState();
        updateCountdown();
        setInterval(updateCountdown, 1000); // Tick every second
    </script>
</body>
</html>