<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Mob Defender Custom Edition</title>


<link rel="stylesheet" href="/assets/css/mobdefender.css">
<link rel="stylesheet" href="/assets/css/styles.css">

</head>
<body>

<div id="setup">

    <div class="container">

        <div class="card">

            <h2>Custom Game Setup</h2>

            <div class="setting">
                <label for="cfgDamage">Damage</label>
                <input id="cfgDamage" type="range" min="1" max="20" value="3">
            </div>

            <div class="setting">
                <label for="cfgSpeed">Fire Speed</label>
                <input id="cfgSpeed" type="range" min="1" max="10" value="2">
            </div>

            <div class="setting">
                <label for="cfgShooters">Starting Guns</label>
                <input id="cfgShooters" type="range" min="1" max="10" value="3">
            </div>

            <div class="setting">
                <label for="cfgMobHp">Mob HP</label>
                <input id="cfgMobHp" type="range" min="1" max="30" value="5">
            </div>

            <div class="setting">
                <label for="cfgMobSpeed">Mob Speed</label>
                <input id="cfgMobSpeed" type="range" min="1" max="10" value="2">
            </div>

            <div class="setting">
                <label for="cfgEscapes">Allowed Escapes</label>
                <input id="cfgEscapes" type="range" min="5" max="50" value="20">
            </div>

            <button id="startBtn">
                Start Game
            </button>

        </div>

    </div>

</div>

<div id="hud">

    <div>
        Kills:
        <span id="kills">0</span>
    </div>

    <div>
        Score:
        <span id="score">0</span>
    </div>

    <div>
        Escaped:
        <span id="escaped">0</span>
    </div>

</div>

<div id="game">

    <div id="gameover">

        <h2>GAME OVER</h2>

        <div>
            Score:
            <span id="finalscore">0</span>
        </div>

        <br>

        <button onclick="location.reload()">
            Play Again
        </button>

    </div>

</div>

<script src="/assets/js/shooter.js"></script>

</body>
</html>
