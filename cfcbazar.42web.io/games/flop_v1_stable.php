<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: /login.php');
    exit();
}
$email = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Flappy Bird — WorkToken</title>
  <style>
    html, body {
      margin: 0; padding: 0; height: 100%;
      background: #222;
      display: flex; flex-direction: column; align-items: center;
      font-family: sans-serif; color: white;
    }
    #balance {
      margin: 10px; font-size: 18px;
    }
    #game-wrapper {
      position: relative;
      width: 100%; max-width: 360px;
      aspect-ratio: 9/16;
    }
    canvas {
      width: 100%; height: 100%;
      background: #70c5ce;
      border-radius: 8px;
      image-rendering: pixelated;
      touch-action: none;
    }
  </style>
</head>
<body>
  <div id="balance">
    Your WorkToken Balance:
    <strong><span id="balance-value">Loading...</span></strong> WT
  </div>
  <div id="game-wrapper">
    <canvas id="game" width="360" height="640"></canvas>
  </div>

  <script>
  const canvas = document.getElementById('game');
  const ctx    = canvas.getContext('2d');
  const W      = canvas.width, H = canvas.height;
  const email  = "<?= $email ?>";
  const workerUrl = '../worker.php';  // ← relative path from /games/flop.php

  // game state
  let balance       = 0;
  let tokenDeducted = false;
  const bird        = { x:80, y:H/2, r:16, vel:0, gravity:0.5, jump:-8 };
  const gap=140, wPipe=60, groundH=100, groundSpeed=2;
  let pipes=[], groundX=0, frame=0, score=0, best=0, over=false;

  // fetch & display balance
  async function fetchBalance() {
    try {
      const res  = await fetch(`${workerUrl}?email=${encodeURIComponent(email)}`);
      if (!res.ok) throw new Error(res.statusText);
      const data = await res.json();
      balance = parseFloat(data.tokens_earned);
      document.getElementById('balance-value')
              .textContent = balance.toFixed(5);
    } catch (err) {
      console.error('Balance load error:', err);
      document.getElementById('balance-value').textContent = 'Error';
    }
  }

  // send a token update
  async function updateBalance(delta) {
    try {
      await fetch(workerUrl, {
        method: 'POST',
        headers: { 'Content-Type':'application/x-www-form-urlencoded' },
        body: `email=${encodeURIComponent(email)}&tokens=${delta}`
      });
    } catch (err) {
      console.error('Balance update error:', err);
    }
  }

  function reset() {
    bird.y = H/2; bird.vel=0;
    pipes=[]; frame=score=0; over=false;
    tokenDeducted=false;
    loop();
  }

  function spawnPipes() {
    const topH = 50 + Math.random()*(H-groundH-gap-100);
    pipes.push({ x:W, y:0, h:topH, passed:false });
    pipes.push({
      x:W, y:topH+gap,
      h:H-groundH-topH-gap, passed:false
    });
  }

  function update() {
    // deduct play‐cost
    if (!tokenDeducted && balance >= 0.1) {
      updateBalance(-0.1);
      balance -= 0.1;
      document.getElementById('balance-value')
              .textContent = balance.toFixed(5);
      tokenDeducted = true;
    }

    bird.vel += bird.gravity;
    bird.y   += bird.vel;

    // check wall/ground collision
    if (bird.y+bird.r > H-groundH || bird.y-bird.r < 0) over=true;

    // pipes logic
    if (frame % 100 === 0) spawnPipes();
    pipes.forEach(p => {
      p.x -= groundSpeed;
      if (!p.passed && p.x + wPipe < bird.x - bird.r) {
        score += 0.5;
        p.passed = true;
      }
      if (
        bird.x+bird.r > p.x &&
        bird.x-bird.r < p.x + wPipe &&
        bird.y+bird.r > p.y &&
        bird.y-bird.r < p.y + p.h
      ) over = true;
    });
    pipes = pipes.filter(p => p.x + wPipe > 0);
    groundX = (groundX - groundSpeed) % W;

    // on game over
    if (over) {
      best = Math.max(best, score);
      const earned = (score >= 150) ? Math.floor(score) : 0;
      if (earned > 0) {
        updateBalance(earned);
        balance += earned;
        document.getElementById('balance-value')
                .textContent = balance.toFixed(5);
      }
      setTimeout(() => location.reload(), 2000);
    }
    frame++;
  }

  // draw routines
  function drawSky() {
    const g = ctx.createLinearGradient(0,0,0,H);
    g.addColorStop(0,'#70c5ce');
    g.addColorStop(1,'#b2ebf2');
    ctx.fillStyle = g;
    ctx.fillRect(0,0,W,H);
  }
  function drawGround() {
    ctx.fillStyle = '#ded895';
    ctx.fillRect(0,H-groundH,W,groundH);
    ctx.fillStyle = '#6bae3f';
    for (let i=-groundX; i<W; i+=40) {
      ctx.beginPath();
      ctx.moveTo(i, H-groundH);
      ctx.lineTo(i+20, H-groundH-15);
      ctx.lineTo(i+40, H-groundH);
      ctx.fill();
    }
  }
  function drawPipes() {
    pipes.forEach(p => {
      const grad = ctx.createLinearGradient(p.x,p.y,p.x+wPipe,p.y+p.h);
      grad.addColorStop(0,'#5aae45');
      grad.addColorStop(1,'#478f32');
      ctx.fillStyle = grad;
      ctx.fillRect(p.x,p.y,wPipe,p.h);
      ctx.fillStyle = '#3f7f20';
      ctx.fillRect(p.x-4, p.y-8, wPipe+8, 8);
      ctx.fillRect(p.x-4, p.y+p.h, wPipe+8, 8);
    });
  }
  function drawBird() {
    const angle = Math.min(Math.max(bird.vel/10, -1), 1);
    ctx.save();
    ctx.translate(bird.x, bird.y);
    ctx.rotate(angle);
    ctx.fillStyle = '#ffe24d';
    ctx.beginPath(); ctx.arc(0,0,bird.r,0,Math.PI*2); ctx.fill();
    ctx.fillStyle = '#e6d142';
    ctx.beginPath();
    ctx.ellipse(-4,0,8,4,-Math.PI/6,0,2*Math.PI);
    ctx.fill();
    ctx.fillStyle = '#fff';
    ctx.beginPath(); ctx.arc(6,-4,5,0,2*Math.PI); ctx.fill();
    ctx.fillStyle = '#333';
    ctx.beginPath(); ctx.arc(7,-4,2,0,2*Math.PI); ctx.fill();
    ctx.fillStyle = '#ff9933';
    ctx.beginPath();
    ctx.moveTo(bird.r,0);
    ctx.lineTo(bird.r+8,4);
    ctx.lineTo(bird.r+8,-4);
    ctx.fill();
    ctx.restore();
  }
  function drawUI() {
    ctx.fillStyle = '#fff';
    ctx.font = '28px sans-serif';
    ctx.fillText(`Score: ${Math.floor(score)}`, 20,50);
    ctx.fillText(`Best: ${best}`,20,90);
    if (over) {
      ctx.fillStyle = 'rgba(0,0,0,0.5)';
      ctx.fillRect(0,0,W,H);
      ctx.fillStyle = '#ffdddd';
      ctx.font = '48px sans-serif';
      ctx.fillText('Game Over',60,H/2-20);
      ctx.font = '24px sans-serif';
      ctx.fillText('Click or Space to Restart',30,H/2+30);
    }
  }
  function draw() {
    drawSky();
    drawPipes();
    drawGround();
    drawBird();
    drawUI();
  }

  function loop() {
    update();
    draw();
    if (!over) requestAnimationFrame(loop);
  }
  function flap() {
    if (over) reset();
    else bird.vel = bird.jump;
  }

  // input handlers
  canvas.addEventListener('mousedown', flap);
  canvas.addEventListener('touchstart', flap, {passive:true});
  document.addEventListener('keydown', e => {
    if (e.code === 'Space') flap();
  });

  // start!
  fetchBalance().then(loop);
  </script>
</body>
</html>
