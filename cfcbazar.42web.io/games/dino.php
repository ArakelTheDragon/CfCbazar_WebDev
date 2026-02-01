<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: /login.php');
    exit();
}
$email = $_SESSION['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <!-- force landscape, disable zoom on mobile -->
  <meta name="viewport"
        content="width=device-width,
                 initial-scale=1.0,
                 maximum-scale=1.0,
                 user-scalable=no" />
  <title>Dino Runner — WorkToken Edition</title>
  <style>
    * { margin: 0; padding: 0; }
    html, body {
      width: 100%; height: 100%;
      display: flex; flex-direction: column;
      justify-content: center; align-items: center;
      background: #333; color: #fff;
      font-family: sans-serif;
      user-select: none; overflow: hidden;
      -webkit-tap-highlight-color: transparent;
    }
    #balance {
      position: absolute; top: 10px; left: 10px;
      font-size: 1.2rem;
    }
    #game-container {
      position: relative;
      width: 600px; height: 200px;
      transition: transform 0.3s ease;
    }
    canvas {
      border: 2px solid #555;
      background: #fff;
      display: block;
      touch-action: none;
    }
    #rotate-overlay {
      position: absolute; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.85);
      color: #fff;
      display: none;
      align-items: center; justify-content: center;
      text-align: center;
      padding: 1rem;
      font-size: 1.2rem;
      z-index: 999;
      user-select: none;
    }
    @media screen and (orientation: portrait) {
      #rotate-overlay { display: flex; }
      #game-container {
        transform: rotate(90deg);
        transform-origin: center center;
        width: 200px; height: 600px;
      }
    }
  </style>
</head>
<body>

  <div id="rotate-overlay">
    Please rotate your device<br>into landscape to play.
  </div>

  <div id="balance">
    Your WorkToken Balance:
    <strong><span id="balance-value">Loading...</span></strong> WT
  </div>

  <div id="game-container">
    <canvas id="game" width="600" height="200"></canvas>
  </div>

  <script>
    // attempt lock
    if (screen.orientation && screen.orientation.lock) {
      screen.orientation.lock('landscape').catch(()=>{});
    }

    // overlay logic
    function checkOrient() {
      const ov = document.getElementById('rotate-overlay');
      if (window.innerHeight > window.innerWidth) {
        ov.style.display = 'flex';
      } else {
        ov.style.display = 'none';
      }
    }
    window.addEventListener('load', checkOrient);
    window.addEventListener('resize', checkOrient);

    // core vars
    const canvas    = document.getElementById('game');
    const ctx       = canvas.getContext('2d');
    const W         = canvas.width, H = canvas.height;
    const email     = "<?= $email ?>";
    const workerUrl = '../worker.php';

    // token & balance
    let balance = 0;
    let tokenDeducted = false;

    // Dino
    const dino = {
      x: 50, y: H - 50,
      w: 40, h: 40,
      vy: 0, gravity: 0.6, jumpForce: -12,
      grounded: false
    };

    // obstacles & clouds
    let obstacles = [];
    const spawnRate = 90;
    const clouds = [];
    for (let i = 0; i < 5; i++) {
      clouds.push({
        x: Math.random() * W,
        y: Math.random() * 80,
        speed: 0.3 + Math.random() * 0.7
      });
    }

    // game state
    let frame = 0, score = 0, gameOver = false;

    // fetch balance
    async function fetchBalance() {
      try {
        const res  = await fetch(`${workerUrl}?email=${encodeURIComponent(email)}`);
        if (!res.ok) throw new Error(res.statusText);
        const data = await res.json();
        balance = parseFloat(data.tokens_earned) || 0;
        document.getElementById('balance-value')
                .textContent = balance.toFixed(5);
      } catch (err) {
        console.error('Fetch balance error:', err);
        document.getElementById('balance-value').textContent = 'Error';
      }
    }

    // update balance
    async function updateBalance(delta) {
      try {
        await fetch(workerUrl, {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: `email=${encodeURIComponent(email)}&tokens=${delta}`
        });
      } catch (err) {
        console.error('Update balance error:', err);
      }
    }

    // reset
    function reset() {
      obstacles = [];
      frame = 0; score = 0; gameOver = false;
      dino.y = H - dino.h - 10; dino.vy = 0; dino.grounded = false;
      tokenDeducted = false;
      loop();
    }

    // spawn cactus
    function spawnObstacle() {
      const size = 20 + Math.random() * 20;
      obstacles.push({ x: W + 10, y: H - size - 10, w: size, h: size });
    }

    // update logic
    function update() {
      // deduct play cost
      if (!tokenDeducted && balance >= 0.01) {
        updateBalance(-0.01);
        balance -= 0.01;
        document.getElementById('balance-value')
                .textContent = balance.toFixed(5);
        tokenDeducted = true;
      }

      // dino physics
      dino.vy += dino.gravity;
      dino.y  += dino.vy;
      if (dino.y + dino.h >= H - 10) {
        dino.y = H - dino.h - 10;
        dino.vy = 0;
        dino.grounded = true;
      } else {
        dino.grounded = false;
      }

      // obstacles
      if (frame % spawnRate === 0) spawnObstacle();
      obstacles.forEach(o => o.x -= 4);
      obstacles = obstacles.filter(o => {
        if (o.x + o.w < 0) { score++; return false; }
        return true;
      });

      // collision
      obstacles.forEach(o => {
        if (
          dino.x < o.x + o.w && dino.x + dino.w > o.x &&
          dino.y < o.y + o.h && dino.y + dino.h > o.y
        ) gameOver = true;
      });

      // clouds
      clouds.forEach(c => {
        c.x -= c.speed;
        if (c.x < -80) c.x = W + 80;
      });

      frame++;
    }

    // draw routines
    function drawSky() {
      const sky = ctx.createLinearGradient(0, 0, 0, H);
      sky.addColorStop(0, '#87CEEB');
      sky.addColorStop(1, '#E0FFFF');
      ctx.fillStyle = sky;
      ctx.fillRect(0, 0, W, H);
    }

    function drawClouds() {
      ctx.fillStyle = 'white';
      clouds.forEach(c => {
        ctx.beginPath();
        ctx.arc(c.x, c.y, 20, Math.PI * .5, Math.PI * 1.5);
        ctx.arc(c.x + 30, c.y - 10, 25, Math.PI, Math.PI * 1.85);
        ctx.arc(c.x + 60, c.y, 20, Math.PI * 1.5, Math.PI * .5);
        ctx.closePath();
        ctx.fill();
      });
    }

    function drawGround() {
      ctx.fillStyle = '#A0522D';
      ctx.fillRect(0, H - 10, W, 10);
      ctx.fillStyle = '#8B4513';
      for (let i = 0; i < W; i += 15) {
        ctx.fillRect(i, H - 14, 7, 4);
      }
    }

    function drawDino() {
      ctx.fillStyle = '#555';
      ctx.fillRect(dino.x, dino.y, dino.w, dino.h);
      ctx.fillStyle = '#666';
      ctx.beginPath();
      ctx.arc(dino.x + dino.w + 5, dino.y + 10, 15, 0, Math.PI*2);
      ctx.fill();
      ctx.fillStyle = 'white';
      ctx.beginPath();
      ctx.arc(dino.x + dino.w + 12, dino.y + 7, 5, 0, Math.PI*2);
      ctx.fill();
      ctx.fillStyle = 'black';
      ctx.beginPath();
      ctx.arc(dino.x + dino.w + 13, dino.y + 7, 2, 0, Math.PI*2);
      ctx.fill();
      ctx.fillStyle = '#444';
      ctx.beginPath();
      ctx.moveTo(dino.x, dino.y + 10);
      ctx.lineTo(dino.x - 20, dino.y + 5);
      ctx.lineTo(dino.x, dino.y + 20);
      ctx.fill();
    }

    function drawObstacles() {
      obstacles.forEach(o => {
        const grad = ctx.createLinearGradient(o.x, o.y, o.x, o.y + o.h);
        grad.addColorStop(0, '#228B22');
        grad.addColorStop(1, '#006400');
        ctx.fillStyle = grad;
        ctx.fillRect(o.x, o.y, o.w, o.h);
        ctx.fillStyle = '#006400';
        if (o.h > 30) {
          ctx.fillRect(o.x - 5, o.y + 10, 10, o.h * .3);
          ctx.fillRect(o.x + o.w - 5, o.y + 5, 10, o.h * .25);
        }
      });
    }

    function drawScore() {
      ctx.fillStyle = '#333';
      ctx.font = '20px sans-serif';
      ctx.fillText(`Score: ${score}`, 10, 25);
    }

    function drawGameOver() {
      if (!gameOver) return;
      ctx.fillStyle = 'rgba(0,0,0,0.5)';
      ctx.fillRect(0, 0, W, H);
      ctx.fillStyle = '#FFF';
      ctx.font = '36px sans-serif';
      ctx.fillText('Game Over', W/2 - 120, H/2 - 10);
      ctx.font = '18px sans-serif';
      ctx.fillText('Press Space/Click to Restart', W/2 - 150, H/2 + 20);
    }

    function draw() {
      drawSky();
      drawClouds();
      drawGround();
      drawDino();
      drawObstacles();
      drawScore();
      drawGameOver();
    }

    // main loop
    function loop() {
      update();
      draw();
      if (!gameOver) requestAnimationFrame(loop);
    }

    // controls
    function jump() {
      if (gameOver) return reset();
      if (dino.grounded) dino.vy = dino.jumpForce;
    }
  