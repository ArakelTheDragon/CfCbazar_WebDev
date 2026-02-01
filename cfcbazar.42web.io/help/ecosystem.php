<?php
// /help/ecosystem.php
require_once '../includes/reusable.php';

$title = 'CfCbazar Blockchain WorkToken Ecosystem & Credit Flow';
include_header();
include_menu();

// Render the ecosystem content (flow chart + text)
?>
<main class="ecosystem-main">
  <h1>💠 CfCbazar Blockchain Token Ecosystem & Credit Flow</h1>

  <div class="chart-container">
    <canvas id="tokenChart"></canvas>
  </div>

  <section class="ecosystem-flow">
    <h2>🔁 How CfCbazar Converts Blockchain Tokens into Platform Credits</h2>
    <ul>
      <li><strong>Blockchain Reserve:</strong> Backs all platform credits</li>
      <li><strong>Platform Credits:</strong> Used for games, features, and withdrawals</li>
      <li><strong>To Get Credits:</strong> Send WorkTokens or BNB to <code>0xFBd767f6454bCd07c959da2E48fD429531A1323A</code></li>
      <li><strong>On Withdraw:</strong> You receive WorkTokens from <code>0xFBd767f6454bCd07c959da2E48fD429531A1323A</code></li>
    </ul>
    <p>Learn more about <a href="/d.php">WorkToken mechanics</a> or explore <a href="/games.php">CfCbazar games</a>.</p>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const canvas = document.getElementById('tokenChart');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');

  function resizeCanvas() {
    canvas.width = Math.min(window.innerWidth - 20, 1000);
    canvas.height = 400;
    drawChart();
  }

  function drawBlock(text, x, y, w = 180, h = 60) {
    ctx.fillStyle = '#f0f8ff';
    ctx.strokeStyle = '#0055aa';
    ctx.lineWidth = 2;
    ctx.fillRect(x, y, w, h);
    ctx.strokeRect(x, y, w, h);
    ctx.fillStyle = '#333';
    ctx.font = '13px Arial';
    const lines = text.split('\n');
    lines.forEach((line, i) => {
      ctx.fillText(line, x + 10, y + 22 + i * 16);
    });
  }

  function drawArrow(fromX, fromY, toX, toY) {
    ctx.strokeStyle = '#0055aa';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(fromX, fromY);
    ctx.lineTo(toX, toY);
    ctx.stroke();

    const angle = Math.atan2(toY - fromY, toX - fromX);
    const headlen = 10;
    ctx.beginPath();
    ctx.moveTo(toX, toY);
    ctx.lineTo(toX - headlen * Math.cos(angle - Math.PI / 6), toY - headlen * Math.sin(angle - Math.PI / 6));
    ctx.lineTo(toX - headlen * Math.cos(angle + Math.PI / 6), toY - headlen * Math.sin(angle + Math.PI / 6));
    ctx.lineTo(toX, toY);
    ctx.fillStyle = '#0055aa';
    ctx.fill();
  }

  function drawChart() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const scale = canvas.width / 1000;

    drawBlock('Blockchain Reserve', 50 * scale, 50, 180 * scale, 60);
    drawBlock('Send WorkTokens/BNB\n→ 0xFBd767...', 300 * scale, 50, 180 * scale, 60);
    drawBlock('Platform Credits', 550 * scale, 50, 180 * scale, 60);
    drawBlock('Use Credits:\nGames, Features, Withdraw', 300 * scale, 150, 180 * scale, 60);
    drawBlock('Withdraw Request', 550 * scale, 150, 180 * scale, 60);
    drawBlock('Receive WorkTokens\n← 0xFBd767...', 800 * scale, 150, 180 * scale, 60);

    drawArrow(230 * scale, 80, 300 * scale, 80);
    drawArrow(480 * scale, 80, 550 * scale, 80);
    drawArrow(550 * scale, 80, 550 * scale, 150);
    drawArrow(480 * scale, 80, 300 * scale, 150);
    drawArrow(480 * scale, 180, 800 * scale, 180);
  }

  window.addEventListener('resize', resizeCanvas);
  resizeCanvas();
});
</script>

<?php include_footer(); ?>