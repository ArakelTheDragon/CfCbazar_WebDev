<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Token Tower Time Trial</title>
  <style>
    html, body {
      margin: 0; padding: 0;
      width: 100vw; height: 100vh;
      background: linear-gradient(to bottom, #4b79a1, #283e51);
      font-family: sans-serif;
      overflow: hidden;
      display: flex; flex-direction: column;
      align-items: center;
    }

    #hud {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 90vw;
      font-size: 5vw;
      color: #fff;
      margin-top: 1vh;
    }

    #game {
      position: relative;
      width: 90vw;
      height: 80vh;
      background: #111;
      overflow: hidden;
      border: 3px solid #222;
      margin-top: 1vh;
    }

    .block {
      position: absolute;
      height: 5vh;
      background: #32CD32;
      left: 0;
    }

    #message {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      color: #fff;
      font-size: 6vw;
      display: none;
      text-align: center;
      z-index: 10;
    }

    button {
      margin-top: 1vh;
      padding: 1vh 2vw;
      font-size: 4vw;
      background: #222;
      color: #fff;
      border: none;
      border-radius: 10px;
    }

    button:active {
      background: #444;
    }
  </style>
</head>
<body>
  <div id="hud">
    <div>Score: <span id="score">0</span></div>
    <div>Time: <span id="timer">30</span>s</div>
  </div>

  <div id="game">
    <div id="message">Game Over<br/>Tap to Restart</div>
  </div>

  <button onclick="dropBlock()">DROP</button>

  <script>
    const game = document.getElementById('game');
    const scoreEl = document.getElementById('score');
    const timerEl = document.getElementById('timer');
    const msgEl = document.getElementById('message');

    let tower = [];
    let current, direction, speed, level, gameOver;
    let timeLeft = 30;
    let gameInterval, timerInterval;

    function init() {
      tower = [];
      level = 0;
      timeLeft = 30;
      gameOver = false;
      msgEl.style.display = 'none';
      game.innerHTML = '';  // Clear all blocks
      game.appendChild(msgEl);
      scoreEl.textContent = level;
      timerEl.textContent = timeLeft;

      spawnBlock();
      gameInterval = requestAnimationFrame(animate);
      timerInterval = setInterval(() => {
        timeLeft--;
        timerEl.textContent = timeLeft;
        if (timeLeft <= 0) endGame();
      }, 1000);
    }

    function spawnBlock() {
      const width = level === 0 ? game.clientWidth : tower[level - 1].offsetWidth;
      const y = game.clientHeight - (level + 1) * (5 * game.clientHeight / 100);
      current = document.createElement('div');
      current.className = 'block';
      current.style.width = width + 'px';
      current.style.top = y + 'px';
      current.style.left = '0px';
      game.appendChild(current);
      direction = 1;
      speed = 2 + Math.floor(level / 5);
    }

    function animate() {
      if (gameOver) return;
      const maxX = game.clientWidth - current.offsetWidth;
      let x = parseFloat(current.style.left) || 0;
      x += direction * speed;
      if (x < 0) { x = 0; direction = 1; }
      if (x > maxX) { x = maxX; direction = -1; }
      current.style.left = x + 'px';

      // Scroll tower up
      for (const b of tower) {
        const ty = parseFloat(b.style.top);
        b.style.top = (ty + speed * 0.15) + 'px';
      }
      current.style.top = (parseFloat(current.style.top) + speed * 0.15) + 'px';

      gameInterval = requestAnimationFrame(animate);
    }

    function dropBlock() {
      if (gameOver) {
        init();
        return;
      }

      const prev = tower[level - 1];
      const curr = current;
      const cx = curr.offsetLeft, cw = curr.offsetWidth;

      if (level === 0) {
        tower.push(curr);
      } else {
        const px = prev.offsetLeft, pw = prev.offsetWidth;
        const overlapStart = Math.max(px, cx);
        const overlapEnd   = Math.min(px + pw, cx + cw);
        const overlapWidth = overlapEnd - overlapStart;

        if (overlapWidth <= 0) {
          endGame();
          return;
        }

        curr.style.left = overlapStart + 'px';
        curr.style.width = overlapWidth + 'px';
        tower.push(curr);
      }

      level++;
      scoreEl.textContent = level;
      spawnBlock();
    }

    function endGame() {
      gameOver = true;
      cancelAnimationFrame(gameInterval);
      clearInterval(timerInterval);
      msgEl.style.display = 'block';
      msgEl.innerHTML = ` Time's Up!<br/>Score: ${level}<br/>Tap DROP to Restart`;
    }

    // Start game
    init();
  </script>
</body>
</html>