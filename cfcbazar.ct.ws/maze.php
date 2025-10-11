<?php
// /games/maze.php — Public Maze Game with Visit Tracking & On-Page Controls
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';

// Visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
  $upd->bind_param('s', $path);
  $upd->execute();

  if ($upd->affected_rows === 0) {
    $slug  = ltrim($path, '/');
    $slug  = $slug === '' ? 'index' : $slug;
    $title = 'Maze Escape';

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
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Maze Escape</title>
  <style>
    html, body {
      margin: 0; padding: 0;
      background: #111; color: white;
      font-family: sans-serif;
      height: 100vh; width: 100vw;
      overflow: hidden;
      display: flex; flex-direction: column;
    }
    #header {
      display: flex; justify-content: center; align-items: center;
      padding: 1vh 4vw; font-size: 4vw;
      background: #000; height: 6vh; box-sizing: border-box;
    }
    #maze {
      display: grid;
      grid-template-columns: repeat(11, 1fr);
      gap: 1px;
      width: 95vw;
      margin: auto;
      max-height: 60vh;
    }
    .cell {
      background: #333;
      width: 100%;
      height: calc(60vh / 11);
      display: flex; align-items: center; justify-content: center;
    }
    .wall { background: #555; }
    .player svg path { fill: #32CD32; }
    .goal   svg path { fill: #FFD700; }

    #controls {
      height: 34vh;
      display: flex; flex-direction: column;
      justify-content: center; align-items: center;
      gap: 1.5vh;
      padding: 1vh 4vw; box-sizing: border-box;
    }
    .button-row {
      display: flex; justify-content: center;
      flex-wrap: wrap; gap: 2vw;
    }
    .arrow-btn {
      font-size: 6vw;
      width: 18vw; height: 6.5vh;
      background: #222; color: white;
      border: none; border-radius: 10px;
      display: flex; align-items: center;
      justify-content: center; gap: 0.5em;
    }
    .arrow-btn span.label { font-size: 3vw; }
    .arrow-btn:active { background: #444; }

    svg { width: 5vw; height: 5vw; flex-shrink: 0; }
  </style>
</head>
<body>
  <div id="header">
    Moves: <span id="moves">0</span>
  </div>

  <div id="maze"></div>

  <div id="controls">
    <div class="button-row">
      <button class="arrow-btn" onclick="move(0,-1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 19V5M5 12l7-7 7 7"/>
        </svg>
        <span class="label">Up</span>
      </button>
      <button class="arrow-btn" onclick="move(-1,0)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        <span class="label">Left</span>
      </button>
      <button class="arrow-btn" onclick="move(1,0)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
        <span class="label">Right</span>
      </button>
      <button class="arrow-btn" onclick="move(0,1)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 5v14M19 12l-7 7-7-7"/>
        </svg>
        <span class="label">Down</span>
      </button>
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
            div.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="#32CD32" stroke-width="2"
                                  stroke-linecap="round" stroke-linejoin="round" width="60%" height="60%">
                                <circle cx="12" cy="7" r="4"/>
                                <path d="M5.5 21a8.38 8.38 0 0113 0"/>
                             </svg>`;
          } else if(x===goalPos.x && y===goalPos.y){
            div.classList.add('goal');
            div.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="#FFD700" stroke-width="2"
                                  stroke-linecap="round" stroke-linejoin="round" width="60%" height="60%">
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
      if (e.key === 'ArrowUp')    move(0, -1);
      if (e.key === 'ArrowDown')  move(0, 1);
      if (e.key === 'ArrowLeft')  move(-1, 0);
      if (e.key === 'ArrowRight') move(1, 0);
    });

    generateMaze();
    drawMaze();
  </script>
</body>
</html>