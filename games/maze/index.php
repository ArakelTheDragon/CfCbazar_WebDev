<?php
// /games/maze.php — Public Maze Game with Visit Tracking & On-Page Controls
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../../includes/settings_global.php';

$NEED_SESSION = false;
$NEED_LOGIN   = false;

// Cron
require_once __DIR__ . '/../../cron_local.php';

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// --- Page metadata -----------------------------------------------------------
$url        = "/games/maze.php";
$parent_url = "/games/";
$title      = "Maze Game";
$template   = "Generic";
$meta_title = "Simple Opensource Maze Game";
$meta_desc  = "Get to the end, claim rewards, get the highest score.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);
setReturnUrl($url);

render_header($title);
?>

<style>
  /* Scoped to not break the main site */
  .maze-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #111;
    color: white;
    font-family: sans-serif;
    padding: 20px 0;
    min-height: calc(100dvh - 150px); /* Adjust based on your header/footer height */
    margin: 0 auto;
    max-width: 800px;
  }

  #header-maze {
    display: flex; justify-content: center; align-items: center;
    padding: 10px; font-size: 1.2rem;
    background: #000; width: 100%; box-sizing: border-box;
  }

  #maze {
    display: grid;
    grid-template-columns: repeat(11, 1fr);
    gap: 1px;
    width: 95vw;
    max-width: 400px; /* Prevents it from getting too large on desktop */
    aspect-ratio: 1 / 1;
    margin: 20px auto;
  }

  .cell {
    background: #333;
    width: 100%;
    height: 100%;
    display: flex; align-items: center; justify-content: center;
  }
  .wall { background: #555; }

  .controls {
    display: flex; flex-direction: column;
    justify-content: center; align-items: center;
    gap: 15px; margin-bottom: 20px;
  }

  .button-row {
    display: flex; justify-content: center;
    flex-wrap: wrap; gap: 10px;
  }

  .arrow-btn {
    font-size: 1.5rem;
    width: 60px; height: 60px;
    background: #222; color: white;
    border: 1px solid #444; border-radius: 10px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    cursor: pointer;
  }
  .arrow-btn:active { background: #0077cc; }

  .arrow-btn span.label { font-size: 0.8rem; margin-top: 4px; }

  .arrow-btn svg { width: 24px; height: 24px; flex-shrink: 0; }
</style>

<div class="maze-wrapper">
  <div id="header-maze">
    Moves: <span id="moves" style="margin-left: 8px;">0</span>
  </div>

  <div id="maze"></div>

  <div class="controls">
    <div class="button-row">
      <button class="arrow-btn" onclick="move(0,-1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 19V5M5 12l7-7 7 7"/>
        </svg>
        <span class="label">Up</span>
      </button>
      <button class="arrow-btn" onclick="move(-1,0)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        <span class="label">Left</span>
      </button>
      <button class="arrow-btn" onclick="move(1,0)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
        <span class="label">Right</span>
      </button>
      <button class="arrow-btn" onclick="move(0,1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 5v14M19 12l-7 7-7-7"/>
        </svg>
        <span class="label">Down</span>
      </button>
    </div>
  </div>
</div>

<script>
  const width = 11, height = 11;
  const maze = Array(width*height).fill(1);
  const playerPos = { x:0, y:0 };
  const goalPos   = { x:width-1, y:height-1 };
  let moves = 0;

  const mazeDiv     = document.getElementById('maze');
  const moveCounter = document.getElementById('moves');

  function index(x,y){ return y*width + x; }

  function generateMaze(){
    const visited = Array(width*height).fill(false);
    function shuffle(a){
      for(let i=a.length-1;i>0;i--){
        const j = Math.floor(Math.random()*(i+1));
        [a[i],a[j]]=[a[j],a[i]];
      }
      return a;
    }
    function carve(x,y){
      visited[index(x,y)] = true;
      maze[index(x,y)] = 0;
      for(const [dx,dy] of shuffle([[0,-2],[2,0],[0,2],[-2,0]])){
        const nx=x+dx, ny=y+dy;
        if(nx>=0&&ny>=0&&nx<width&&ny<height&&!visited[index(nx,ny)]){
          maze[index(x+dx/2,y+dy/2)] = 0;
          carve(nx,ny);
        }
      }
    }
    carve(0,0);
  }

  function drawMaze(){
    mazeDiv.innerHTML='';
    for(let y=0;y<height;y++){
      for(let x=0;x<width;x++){
        const div = document.createElement('div');
        div.className = 'cell';

        if(maze[index(x,y)]===1){
          div.classList.add('wall');
        } else if(x===playerPos.x && y===playerPos.y){
          div.classList.add('player');
          div.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="#32CD32" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="60%" height="60%">
                              <circle cx="12" cy="7" r="4"/>
                              <path d="M5.5 21a8.38 8.38 0 0113 0"/>
                           </svg>`;
        } else if(x===goalPos.x && y===goalPos.y){
          div.classList.add('goal');
          div.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="#FFD700" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="60%" height="60%">
                              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                              <path d="M8 21V7h8v14"/>
                              <circle cx="12" cy="15" r="1"/>
                           </svg>`;
        }
        mazeDiv.appendChild(div);
      }
    }
  }

  function move(dx,dy){
    const nx = playerPos.x+dx, ny = playerPos.y+dy;
    if(nx>=0&&ny>=0&&nx<width&& ny<height && maze[index(nx,ny)]===0){
      playerPos.x = nx;
      playerPos.y = ny;
      moves++;
      moveCounter.textContent = moves;
      drawMaze();

      if(nx === goalPos.x && ny === goalPos.y){
        setTimeout(() => {
          alert(`🎉 You escaped the maze in ${moves} moves!`);
        }, 150);
      }
    }
  }

  document.addEventListener('keydown', e => {
    // Prevent page scrolling when using arrow keys
    if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
        e.preventDefault(); 
    }
    if (e.key === 'ArrowUp')    move(0, -1);
    if (e.key === 'ArrowDown')  move(0, 1);
    if (e.key === 'ArrowLeft')  move(-1, 0);
    if (e.key === 'ArrowRight') move(1, 0);
  });

  generateMaze();
  drawMaze();
</script>

<?php
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/games/maze.php",
    "Maze Game Source Code"
);
include_footer();
close_database();
?>
