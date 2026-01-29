<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

  <title>Falling Object Catcher – Fun Reflex & Speed Game</title>
  <meta name="description" content="Play Falling Object Catcher – a free online reflex game. Move your basket to catch falling objects, increase your score, and test your speed and coordination.">
  <meta name="keywords" content="falling object game, basket catching game, reflex game, online speed game, free browser game, catch the falling object">
  <meta name="author" content="CfCbazar">

  <!-- Open Graph for Social Media -->
  <meta property="og:title" content="Falling Object Catcher – Test Your Reflexes">
  <meta property="og:description" content="Move the basket, catch the objects, and rack up points in this fun online reaction game!">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://yourwebsite.com/falling-object-catcher">
  <meta property="og:image" content="https://yourwebsite.com/images/falling-object-catcher-thumbnail.jpg">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Falling Object Catcher – Reflex Challenge">
  <meta name="twitter:description" content="Catch the falling objects before they hit the ground! Play this free reflex and coordination game now.">
  <meta name="twitter:image" content="https://yourwebsite.com/images/falling-object-catcher-thumbnail.jpg">

  <style>
    body {
      margin: 0;
      background: #f3f3f3;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      font-family: Arial, sans-serif;
      flex-direction: column;
    }
    canvas {
      background: #3498db;
      border: 2px solid #fff;
      display: block;
      width: 90%;
      height: 60%;
      margin-bottom: 5%;
    }
    .controls {
      display: flex;
      justify-content: center;
      gap: 2%;
      width: 90%;
      flex-wrap: wrap;
    }
    .btn {
      padding: 3% 5%;
      background: #2ecc71;
      border: none;
      color: white;
      font-size: 1.5em;
      margin: 1%;
      border-radius: 5px;
      cursor: pointer;
      width: 40%;
      box-sizing: border-box;
    }
    .btn:active {
      background: #27ae60;
    }
    .pause-btn {
      background: #e74c3c;
    }
    .pause-btn:active {
      background: #c0392b;
    }
    .speed-btn {
      background: #f39c12;
    }
    .speed-btn:active {
      background: #e67e22;
    }
    .info {
      font-size: 1.5em;
      margin-top: 5%;
      text-align: center;
    }
    section.game-info {
      max-width: 800px;
      padding: 20px;
      text-align: center;
    }
  </style>
</head>
<body>

  <header>
    <h1>Falling Object Catcher Game</h1>
    <p>Catch as many falling objects as you can before they hit the ground! Improve your reflexes and hand-eye coordination.</p>
  </header>

  <main>
    <canvas id="game" aria-label="Falling Object Catcher game area"></canvas>

    <div class="controls">
      <button class="btn" onclick="moveBasket('left')" aria-label="Move basket left">Move Left</button>
      <button class="btn" onclick="moveBasket('right')" aria-label="Move basket right">Move Right</button>
      <button class="btn pause-btn" onclick="togglePause()" aria-label="Pause game">Pause</button>
      <button class="btn speed-btn" onclick="adjustSpeed()" aria-label="Adjust game speed">Adjust Speed</button>
    </div>

    <div class="info">
      <p id="score">Score: 0</p>
      <p id="speed">Speed: 5</p>
    </div>

    <section class="game-info">
      <h2>How to Play Falling Object Catcher</h2>
      <ul>
        <li>Use the "Move Left" and "Move Right" buttons to control the basket.</li>
        <li>Catch the falling red squares before they hit the ground.</li>
        <li>Each catch increases your score by 1.</li>
        <li>Use the "Adjust Speed" button to change difficulty.</li>
      </ul>

      <h3>Why Play This Game?</h3>
      <p>
        This game is great for improving hand-eye coordination, reflex speed, and focus.
        Play it daily to sharpen your reaction skills while having fun.
      </p>
    </section>
  </main>

  <footer>
    <p>&copy; 2025 CfCbazar | Play Free Online Games</p>
  </footer>

  <script>
    const canvas = document.getElementById('game');
    const ctx = canvas.getContext('2d');

    // Set canvas size dynamically based on the screen
    canvas.width = window.innerWidth * 0.9;
    canvas.height = window.innerHeight * 0.6;

    const basketWidth = canvas.width * 0.2;
    const basketHeight = canvas.height * 0.05;
    let basketX = (canvas.width - basketWidth) / 2;
    const basketSpeed = canvas.width * 0.05;

    let score = 0;
    const fallingObjects = [];
    const objectSize = canvas.width * 0.1;
    let objectSpeed = canvas.height * 0.03;

    let isPaused = false;

    function createFallingObject() {
      if (isPaused) return;
      const x = Math.random() * (canvas.width - objectSize);
      fallingObjects.push({ x, y: 0, caught: false });
    }

    function drawBasket() {
      ctx.fillStyle = '#2ecc71';
      ctx.fillRect(basketX, canvas.height - basketHeight, basketWidth, basketHeight);
    }

    function drawFallingObjects() {
      ctx.fillStyle = '#e74c3c';
      fallingObjects.forEach((obj, index) => {
        if (!obj.caught) {
          ctx.fillRect(obj.x, obj.y, objectSize, objectSize);
          obj.y += objectSpeed;

          if (
            obj.y + objectSize >= canvas.height - basketHeight &&
            obj.x + objectSize >= basketX &&
            obj.x <= basketX + basketWidth
          ) {
            obj.caught = true;
            score++;
          }

          if (obj.y > canvas.height) {
            fallingObjects.splice(index, 1);
          }
        }
      });
    }

    function moveBasket(direction) {
      if (isPaused) return;
      if (direction === 'left' && basketX > 0) {
        basketX -= basketSpeed;
      } else if (direction === 'right' && basketX < canvas.width - basketWidth) {
        basketX += basketSpeed;
      }
    }

    function updateGame() {
      if (isPaused) return;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      drawBasket();
      drawFallingObjects();
      document.getElementById('score').textContent = 'Score: ' + score;
      document.getElementById('speed').textContent = 'Speed: ' + objectSpeed.toFixed(2);
      requestAnimationFrame(updateGame);
    }

    function togglePause() {
      isPaused = !isPaused;
      if (!isPaused) updateGame();
    }

    function adjustSpeed() {
      if (objectSpeed <= 0) {
        objectSpeed = canvas.height * 0.03;
      } else {
        objectSpeed -= 1.00;
      }
    }

    setInterval(createFallingObject, 1000);
    updateGame();
  </script>
</body>
</html>
