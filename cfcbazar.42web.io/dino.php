<?php
// dino.php — Public Dino Runner Game with Visit Tracking & SEO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("config.php");

// Page visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
    $upd->bind_param('s', $path);
    $upd->execute();

    if ($upd->affected_rows === 0) {
        $slug  = ltrim($path, '/');
        $slug  = $slug === '' ? 'index' : $slug;
        $title = 'Dino Runner Game';

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
  <meta charset="UTF-8" />
  <meta name="viewport"
        content="width=device-width,
                 initial-scale=1.0,
                 maximum-scale=1.0,
                 user-scalable=no" />
  <title>Dino Runner — Free Browser Game</title>
  <meta name="description" content="Play Dino Runner, a fast-paced browser game. Jump over obstacles, dodge cacti, and challenge your reflexes. No login or tokens required.">
  <meta name="keywords" content="dino runner, browser game, free game, no login, arcade, jump game, obstacle game">
  <meta name="author" content="CFCBazar">
  <meta property="og:title" content="Dino Runner — Free Browser Game">
  <meta property="og:description" content="Jump, dodge, and survive in Dino Runner. No login or tokens required.">
  <meta property="og:image" content="https://yourdomain.com/assets/dino-preview.png">
  <meta property="og:url" content="https://yourdomain.com/dino.php">
  <meta name="twitter:card" content="summary_large_image">
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

  <div id="game-container">
    <canvas id="game" width="600" height="200"></canvas>
  </div>

  <script>
    if (screen.orientation && screen.orientation.lock) {
      screen.orientation.lock('landscape').catch(()=>{});
    }

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

    const canvas = document.getElementById('game');
    const ctx = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height;

    const dino = {
      x: 50, y: H - 50,
      w: 40, h: 40,
      vy: 0, gravity: 0.6, jumpForce: -12,
      grounded: false
    };

    let obstacles = [];
    const spawnRate = 90;
    const clouds = Array.from({length: 5}, () => ({
      x: Math.random() * W,
      y: Math.random() * 80,
      speed: 0.3 + Math.random() * 0.7
    }));

    let frame = 0, score = 0, gameOver = false;

    function reset() {
      obstacles = [];
      frame = 0; score = 0; gameOver = false;
      dino.y = H - dino.h - 10; dino.vy = 0; dino.grounded = false;
      loop();
    }

    function spawnObstacle() {
      const size = 20 + Math.random() * 20;
      obstacles.push({ x: W + 10, y: H - size - 10, w: size, h: size });
    }

    function update() {
      dino.vy += dino.gravity;
      dino.y += dino.vy;
      if (dino.y + dino.h >= H - 10) {
        dino.y = H - dino.h - 10;
        dino.vy = 0;
        dino.grounded = true;
      } else {
        dino.grounded = false;
      }

      if (frame % spawnRate === 0) spawnObstacle();
      obstacles.forEach(o => o.x -= 4);
      obstacles = obstacles.filter(o => {
        if (o.x + o.w < 0) { score++; return false; }
        return true;
      });

      obstacles.forEach(o => {
        if (
          dino.x < o.x + o.w && dino.x + dino.w > o.x &&
          dino.y < o.y + o.h && dino.y + dino.h > o.y
        ) gameOver = true;
      });

      clouds.forEach(c => {
        c.x -= c.speed;
        if (c.x < -80) c.x = W + 80;
      });

      frame++;
    }

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
      ctx.arc(dino.x + dino.w + 12, dino.y + 7, 5, 0,Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = 'black';
      ctx.beginPath();
      ctx.arc(dino.x + dino.w + 12, dino.y + 7, 2, 0, Math.PI * 2);
      ctx.fill();
    }

    function drawObstacles() {
      ctx.fillStyle = '#228B22';
      obstacles.forEach(o => {
        ctx.fillRect(o.x, o.y, o.w, o.h);
      });
    }

    function drawScore() {
      ctx.fillStyle = '#000';
      ctx.font = '20px sans-serif';
      ctx.fillText(`Score: ${score}`, 10, 30);
    }

    function drawGameOver() {
      ctx.fillStyle = 'rgba(0,0,0,0.6)';
      ctx.fillRect(0, 0, W, H);
      ctx.fillStyle = '#fff';
      ctx.font = '28px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('Game Over', W / 2, H / 2 - 20);
      ctx.font = '20px sans-serif';
      ctx.fillText(`Final Score: ${score}`, W / 2, H / 2 + 10);
      ctx.fillText('Tap or press space to restart', W / 2, H / 2 + 40);
    }

    function loop() {
      ctx.clearRect(0, 0, W, H);
      drawSky();
      drawClouds();
      drawGround();
      drawDino();
      drawObstacles();
      drawScore();

      if (gameOver) {
        drawGameOver();
        return;
      }

      update();
      requestAnimationFrame(loop);
    }

    canvas.addEventListener('click', () => {
      if (gameOver) return reset();
      if (dino.grounded) dino.vy = dino.jumpForce;
    });

    window.addEventListener('keydown', e => {
      if (e.code === 'Space') {
        if (gameOver) return reset();
        if (dino.grounded) dino.vy = dino.jumpForce;
      }
    });

    reset();
  </script>
</body>
</html>