<?php
declare(strict_types=1);
//session_start();
require_once __DIR__ . '/../../includes/reusable.php';

$url = '/games/battle.php';
// Track visit BEFORE any output
trackVisit($url);
renderCaptchaIfNeeded();

// Set return url cookie for after log in
$url = '/battle.php';
setReturnUrl($url);

enforce_https();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Get user status and redirect if not logged in
$userStatus = getUserStatus($conn);

$email = strtolower($_SESSION['email'] ?? '');
$shouldReveal = false;
$startGame = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        //http_response_code(403);
       echo "Invalid CSRF token";
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'play') {
        $startGame = true;
    } elseif ($action === 'reveal') {
        // Reveal requires an active game
        $shouldReveal = true;
        $startGame = true;
    }
}

include_header();
include_menu();
?>

<main class="game-container">
  <h1>Tactical Fleet Duel</h1>
  <p>Tap enemy grid to fire. Ships have attack, defense, and HP.</p>

  <form method="POST" id="gameActions">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <button type="submit" name="action" value="reveal" <?= !$startGame ? 'disabled' : '' ?>>Reveal Enemy Ships (10 turns)</button>
    <button type="submit" name="action" value="play">Play</button>
  </form>

  <div id="game">
    <div><strong>Your Grid</strong></div>
    <div id="player-grid" class="grid"></div>
    <div><strong>Enemy Grid</strong></div>
    <div id="enemy-grid" class="grid"></div>
  </div>

  <div id="counts">
    Your Ships Remaining: <span id="player-count">0</span> |
    Enemy Ships Remaining: <span id="enemy-count">0</span>
  </div>

  <div id="turn-indicator"><strong><?= $startGame ? 'Your Turn' : 'Waiting to Start' ?></strong></div>
  <div id="status"></div>
</main>

<?php include_footer(); ?>

<script>
const shouldReveal = <?= $shouldReveal ? 'true' : 'false' ?>;
const startGame = <?= $startGame ? 'true' : 'false' ?>;

const gridSize = 10;
const playerGrid = document.getElementById('player-grid');
const enemyGrid = document.getElementById('enemy-grid');
const status = document.getElementById('status');
const playerCount = document.getElementById('player-count');
const enemyCount = document.getElementById('enemy-count');
const turnIndicator = document.getElementById('turn-indicator');

const ships = [
  { name: "Destroyer", size: 2, attack: 3, defense: 5 },
  { name: "Cruiser", size: 3, attack: 4, defense: 6 },
  { name: "Battleship", size: 4, attack: 5, defense: 8 }
];

let playerShips = {};
let enemyShips = {};
let initialPlayerShips = {};
let initialEnemyShips = {};
let playerTurn = true;
let gameOver = false;
let revealTurnsLeft = 0;

function createGrid(grid, isEnemy) {
  grid.innerHTML = "";
  for (let i = 0; i < gridSize * gridSize; i++) {
    const cell = document.createElement('div');
    cell.dataset.index = i;
    if (isEnemy) {
      cell.addEventListener('click', () => {
        if (playerTurn && !gameOver) fireAtEnemy(i, cell);
      });
    }
    grid.appendChild(cell);
  }
}

function placeShips() {
  const shipMap = {};
  ships.forEach(ship => {
    let placed = false;
    while (!placed) {
      const start = Math.floor(Math.random() * (gridSize * gridSize));
      const direction = Math.random() < 0.5 ? 1 : gridSize;
      const positions = [];
      for (let i = 0; i < ship.size; i++) {
        const pos = start + i * direction;
        if (pos >= gridSize * gridSize || shipMap[pos]) break;
        positions.push(pos);
      }
      if (positions.length === ship.size) {
        positions.forEach(pos => {
          shipMap[pos] = { ...ship, hp: ship.defense };
        });
        placed = true;
      }
    }
  });
  return shipMap;
}

function updateCounts() {
  playerCount.textContent = Object.keys(playerShips).length;
  enemyCount.textContent = Object.keys(enemyShips).length;
}

function checkGameOver() {
  if (Object.keys(enemyShips).length === 0) {
    status.textContent = "You win!";
    turnIndicator.innerHTML = "<strong>Game Over</strong>";
    revealShips();
    gameOver = true;
  } else if (Object.keys(playerShips).length === 0) {
    status.textContent = "You lose!";
    turnIndicator.innerHTML = "<strong>Game Over</strong>";
    revealShips();
    gameOver = true;
  }
}

function revealShips() {
  for (let i = 0; i < gridSize * gridSize; i++) {
    const enemyCell = enemyGrid.children[i];
    const playerCell = playerGrid.children[i];
    if (initialEnemyShips[i]) enemyCell.classList.add('ship');
    if (initialPlayerShips[i]) playerCell.classList.add('ship');
  }
}

// 👁️ Reveal ships temporarily (for 10 turns)
function revealEnemyShips() {
  if (Object.keys(enemyShips).length === 0) return;
  revealTurnsLeft = 10;

  for (let i = 0; i < gridSize * gridSize; i++) {
    const cell = enemyGrid.children[i];
    // only reveal hidden cells, not already hit/sunk
    if (initialEnemyShips[i] && !cell.classList.contains('hit') && !cell.classList.contains('miss')) {
      cell.classList.add('temp-reveal');
      cell.textContent = "👀";
    }
  }

  status.textContent = "Enemy ships revealed for 10 turns!";
}

// ❌ Hide temporary reveals (keep hits and sunk ships)
function hideTemporaryReveal() {
  for (let i = 0; i < gridSize * gridSize; i++) {
    const cell = enemyGrid.children[i];
    if (cell.classList.contains('temp-reveal')) {
      cell.classList.remove('temp-reveal');
      if (!cell.classList.contains('hit') && !cell.classList.contains('miss')) {
        cell.textContent = "";
      }
    }
  }
}

function fireAtEnemy(index, cell) {
  const target = enemyShips[index];
  if (!target) {
    cell.classList.add('miss');
    status.textContent = "Miss!";
  } else {
    target.hp -= target.attack;
    cell.classList.add('hit');
    cell.textContent = target.hp > 0 ? "⚔️" : "💥";
    status.textContent = target.hp > 0 ? "Hit!" : "Sunk!";
    if (target.hp <= 0) delete enemyShips[index];
  }

  updateCounts();
  checkGameOver();

  playerTurn = false;
  turnIndicator.innerHTML = "<strong>Enemy Turn</strong>";

  if (revealTurnsLeft > 0) {
    revealTurnsLeft--;
    if (revealTurnsLeft === 0) hideTemporaryReveal();
  }

  if (!gameOver) setTimeout(aiTurn, 1000);
}

function aiTurn() {
  if (Math.random() < 0.4) {
    status.textContent = "Enemy missed!";
  } else {
    const indexes = Object.keys(playerShips);
    if (indexes.length === 0) return;
    const targetIndex = indexes[Math.floor(Math.random() * indexes.length)];
    const cell = playerGrid.children[targetIndex];
    const target = playerShips[targetIndex];
    target.hp -= target.attack;
    cell.classList.add('hit');
    cell.textContent = target.hp > 0 ? "⚔️" : "💥";
    status.textContent = target.hp > 0 ? "Enemy hit your ship!" : "Enemy sunk your ship!";
    if (target.hp <= 0) delete playerShips[targetIndex];
  }

  updateCounts();
  checkGameOver();

  playerTurn = true;
  turnIndicator.innerHTML = "<strong>Your Turn</strong>";
}

function restartGame() {
  createGrid(playerGrid, false);
  createGrid(enemyGrid, true);
  playerShips = placeShips();
  enemyShips = placeShips();
  initialPlayerShips = JSON.parse(JSON.stringify(playerShips));
  initialEnemyShips = JSON.parse(JSON.stringify(enemyShips));
  playerTurn = true;
  gameOver = false;
  revealTurnsLeft = 0;
  updateCounts();
  status.textContent = "";
  turnIndicator.innerHTML = "<strong>Your Turn</strong>";
}

document.addEventListener("DOMContentLoaded", () => {
  createGrid(playerGrid, false);
  createGrid(enemyGrid, true);
  if (startGame) restartGame();
  if (shouldReveal) {
    if (!startGame) restartGame();
    revealEnemyShips();
  }
});
</script>

<style>
.grid {
  display: grid;
  grid-template-columns: repeat(10, 1fr);
  gap: 2px;
  max-width: 300px;
  margin: 10px auto;
}
.grid div {
  aspect-ratio: 1 / 1;
  background: #ccc;
  border: 1px solid #999;
  font-size: 0.8em;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.hit { background: red; color: white; }
.miss { background: blue; color: white; }
.ship { background: gray; }
.temp-reveal {
  background: orange !important;
  border: 2px dashed black !important;
  color: black !important;
}
</style>
