<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['email'])) {
    header('Location: /login.php');
    exit();
}
$email = $_SESSION['email'];

// Fetch current balance
function getBalance($conn, $email) {
    $stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($tokens);
    if ($stmt->fetch()) {
        $stmt->close();
        return (float)$tokens;
    }
    $stmt->close();
    return 0;
}

// Update balance
function updateBalance($conn, $email, $delta) {
    $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + ? WHERE email = ?");
    $stmt->bind_param("ds", $delta, $email);
    $stmt->execute();
    $stmt->close();
}

$currentBalance = getBalance($conn, $email);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Flappy Bird — WorkToken</title>
  <style>
    html, body { margin: 0; padding: 0; height: 100%; background: #222; display: flex; flex-direction: column; align-items: center; font-family: sans-serif; color: white; }
    #balance { margin: 10px; font-size: 18px; }
    #game-wrapper { position: relative; width: 100%; max-width: 360px; aspect-ratio: 9/16; }
    canvas { width: 100%; height: 100%; background: #70c5ce; border-radius: 8px; image-rendering: pixelated; touch-action: none; }
  </style>
</head>
<body>
  <div id="balance">Your WorkToken Balance: <strong><span id="balance-value"><?= number_format($currentBalance, 5) ?></span></strong> WT</div>
  <div id="game-wrapper"><canvas id="game" width="360" height="640"></canvas></div>

  <script>
  const canvas = document.getElementById('game');
  const ctx = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;
  let balance = <?= $currentBalance ?>;
  let deducted = false;

  const gap = 160, wPipe = 60, groundH = 100, groundSpeed = 1.0, spawnRate = 120;
  const bird = { x: 80, y: H/2, r: 16, vel: 0, gravity: 0.4, jump: -7 };
  let pipes = [], groundX = 0, frame = 0, score = 0, best = 0, over = false;

  async function postToken(delta) {
    await fetch("", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `delta=${delta}`
    });
  }

  function update() {
    if (!deducted && balance >= 0.1) {
      postToken(-0.1);
      balance -= 0.1;
      document.getElementById('balance-value').textContent = balance.toFixed(5);
      deducted = true;
    }

    bird.vel += bird.gravity;
    bird.y += bird.vel;

    if (bird.y + bird.r > H - groundH || bird.y - bird.r < 0) over = true;
    if (frame % spawnRate === 0) spawnPipes();

    pipes.forEach(p => {
      p.x -= groundSpeed;
      if (!p.passed && p.x + wPipe < bird.x - bird.r) {
        score += 0.5;
        p.passed = true;
      }
      if (bird.x + bird.r > p.x && bird.x - bird.r < p.x + wPipe &&
          bird.y + bird.r > p.y && bird.y - bird.r < p.y + p.h) over = true;
    });

    pipes = pipes.filter(p => p.x + wPipe > 0);
    groundX = (groundX - groundSpeed) % W;

    if (over) {
      best = Math.max(best, score);
      const reward = score >= 150 ? Math.floor(score) : 0;
      if (reward > 0) {
        postToken(reward);
        balance += reward;
        document.getElementById('balance-value').textContent = balance.toFixed(5);
      }
      setTimeout(() => location.reload(), 2000);
    }

    frame++;
  }

  function draw() {
    const grad = ctx.createLinearGradient(0,0,0,H);
    grad.addColorStop(0,'#70c5ce'); grad.addColorStop(1,'#b2ebf2');
    ctx.fillStyle = grad; ctx.fillRect(0,0,W,H);

    pipes.forEach(p => {
      const grad = ctx.createLinearGradient(p.x,p.y,p.x+wPipe,p.y+p.h);
      grad.addColorStop(0,'#5aae45'); grad.addColorStop(1,'#478f32');
      ctx.fillStyle = grad; ctx.fillRect(p.x,p.y,wPipe,p.h);
      ctx.fillStyle = '#3f7f20';
      ctx.fillRect(p.x-4, p.y-8, wPipe+8, 8);
      ctx.fillRect(p.x-4, p.y+p.h, wPipe+8, 8);
    });

    ctx.fillStyle = '#ded895';
    ctx.fillRect(0, H-groundH, W, groundH);
    ctx.fillStyle = '#6bae3f';
    for (let i = -groundX; i < W; i += 40) {
      ctx.beginPath();
      ctx.moveTo(i, H-groundH);
      ctx.lineTo(i+20, H-groundH-15);
      ctx.lineTo(i+40, H-groundH);
      ctx.fill();
    }

    const angle = Math.min(Math.max(bird.vel/10, -1), 1);
    ctx.save();
    ctx.translate(bird.x, bird.y);
    ctx.rotate(angle);
    ctx.fillStyle = '#ffe24d';
    ctx.beginPath();
    ctx.arc(0,0,bird.r,0,Math.PI*2); ctx.fill();
    ctx.fillStyle = '#ff9933';
    ctx.beginPath(); ctx.moveTo(bird.r,0); ctx.lineTo(bird.r+8,4); ctx.lineTo(bird.r+8,-4); ctx.fill();
    ctx.restore();

    ctx.fillStyle = '#fff'; ctx.font = '28px sans-serif';
    ctx.fillText(`Score: ${Math.floor(score)}`, 20, 50);
    ctx.fillText(`Best: ${best}`, 20, 90);
    if (over) {
      ctx.fillStyle = 'rgba(0,0,0,0.5)'; ctx.fillRect(0,0,W,H);
      ctx.fillStyle = '#ffdddd'; ctx.font = '48px sans-serif';
      ctx.fillText('Game Over', 60, H/2 - 20);
      ctx.font = '24px sans-serif'; ctx.fillText('Click or Space to Restart', 30, H/2 + 30);
    }
  }

  function spawnPipes() {
    const topH = 50 + Math.random() * (H - groundH - gap - 100);
    pipes.push({ x: W, y: 0, h: topH, passed: false });
    pipes.push({ x: W, y: topH + gap, h: H - groundH - topH - gap, passed: false });
  }

  function flap() {
    if (over) location.reload();
    else bird.vel = bird.jump;
  }

  function loop() {
    update(); draw();
    if (!over) requestAnimationFrame(loop);
  }

  canvas.addEventListener('mousedown', flap);
  window.addEventListener('keydown', e => { if (e.code === 'Space') flap(); });

  loop();
  </script>
</body>
</html>

<?php
// Token adjustment via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delta'])) {
    $delta = floatval($_POST['delta']);
    if (abs($delta) > 0 && is_numeric($delta)) {
        updateBalance($conn, $email, $delta);
    }
    exit;
}
?>