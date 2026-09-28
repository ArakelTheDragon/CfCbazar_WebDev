<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Shooter Game</title>
<style>
html, body {
    margin:0;
    padding:0;
    background:#111;
    overflow:hidden;
    touch-action:none;
}

#game {
    display:block;
    width:100%;
    height:90vh;
    background:#222;
}

.hud {
    position:fixed;
    top:10px;
    width:100%;
    text-align:center;
    color:white;
    font-size:18px;
}

.controls {
    position:fixed;
    bottom:10px;
    width:100%;
    display:flex;
    justify-content:center;
    gap:15px;
}

.btn {
    width:80px;
    height:80px;
    background:#333;
    color:white;
    text-align:center;
    line-height:80px;
    border-radius:10px;
}
</style>
</head>
<body>

<div class="hud">
Time: <span id="timer">60</span> |
Score: <span id="hits">0</span> |
Power: <span id="power">None</span>
</div>

<canvas id="game"></canvas>

<div class="controls">
<div class="btn" id="btnLeft">LEFT</div>
<div class="btn" id="btnShoot">SHOOT</div>
<div class="btn" id="btnRight">RIGHT</div>
</div>

<script>
const canvas = document.getElementById("game");
const ctx = canvas.getContext("2d");

function resize(){
    canvas.width = canvas.clientWidth;
    canvas.height = canvas.clientHeight;

    if(player){
        player.x = canvas.width/2 - player.w/2;
        player.y = canvas.height - 80;
    }
}
window.addEventListener("resize", resize);

function lerp(a,b,t){ return a+(b-a)*t; }

let player, bullets=[], enemies=[], powerups=[], particles=[];
let pointerX=null, shooting=false;
let moveLeft=false, moveRight=false;

let score=0, timeLeft=60;
let shootCooldown=0;

let currentPower="none";
let shield=false;

function reset(){
    resize();

    player={
        x:canvas.width/2 - 20,
        y:canvas.height - 80,
        w:40,
        h:40
    };

    bullets=[]; enemies=[]; powerups=[]; particles=[];
    score=0; timeLeft=60;
    currentPower="none";
    shield=false;
}
reset();

// Particles
function explode(x,y,color){
    for(let i=0;i<12;i++){
        particles.push({
            x,y,
            vx:(Math.random()-0.5)*5,
            vy:(Math.random()-0.5)*5,
            life:30,
            color
        });
    }
}

// Shooting
function shoot(){
    if(shootCooldown>0) return;

    if(currentPower==="triple"){
        bullets.push({x:player.x+20,y:player.y,w:5,h:10,vx:-2,vy:-10});
        bullets.push({x:player.x+20,y:player.y,w:5,h:10,vx:0,vy:-10});
        bullets.push({x:player.x+20,y:player.y,w:5,h:10,vx:2,vy:-10});
    } else {
        bullets.push({x:player.x+20,y:player.y,w:5,h:10,vx:0,vy:-10});
    }

    shootCooldown = currentPower==="rapid" ? 3 : 10;
}

// Spawn lanes
function spawnEnemy(){
    enemies.push({
        x: Math.random()*(canvas.width/2 - 40),
        y:0,
        w:40,h:40,
        vy:2+Math.random()*2,
        hp: Math.floor(Math.random()*3)+1 // HP 1–3
    });
}

function spawnPower(){
    const types=["rapid","shield","triple"];
    powerups.push({
        x: canvas.width/2 + Math.random()*(canvas.width/2 - 40),
        y:0,
        w:40,h:40,
        vy:2,
        type: types[Math.floor(Math.random()*types.length)]
    });
}

function hit(a,b){
    return a.x < b.x + b.w &&
           a.x + a.w > b.x &&
           a.y < b.y + b.h &&
           a.y + a.h > b.y;
}

let enemyTimer=0, powerTimer=0;

function update(){
    requestAnimationFrame(update);

    if(timeLeft<=0) return;

    // Movement
    if(pointerX!==null){
        let targetX = pointerX - player.w/2;
        player.x = lerp(player.x, targetX, 0.25);
    }

    if(moveLeft) player.x -= 6;
    if(moveRight) player.x += 6;

    player.x = Math.max(0, Math.min(canvas.width-player.w, player.x));

    if(shooting) shoot();
    if(shootCooldown>0) shootCooldown--;

    // Spawning
    enemyTimer++;
    if(enemyTimer>40){ spawnEnemy(); enemyTimer=0; }

    powerTimer++;
    if(powerTimer>120){ spawnPower(); powerTimer=0; }

    // Movement updates
    bullets.forEach(b=>{
        b.x += b.vx;
        b.y += b.vy;
    });

    enemies.forEach(e=>e.y += e.vy);
    powerups.forEach(p=>p.y += p.vy);

    // Particles
    particles.forEach(p=>{
        p.x+=p.vx;
        p.y+=p.vy;
        p.life--;
    });
    particles = particles.filter(p=>p.life>0);

    // Bullet collisions (ENEMIES + POWERUPS)
    for(let i=bullets.length-1;i>=0;i--){
        let b = bullets[i];

        // Enemies
        for(let j=enemies.length-1;j>=0;j--){
            let e = enemies[j];
            if(hit(b,e)){
                e.hp--; // reduce HP
                bullets.splice(i,1);

                if(e.hp <= 0){
                    explode(e.x,e.y,"red");
                    enemies.splice(j,1);
                    score++;
                }
                break;
            }
        }

        // Power-ups (shoot to collect)
        for(let j=powerups.length-1;j>=0;j--){
            if(hit(b,powerups[j])){
                let p = powerups[j];

                currentPower = p.type;
                if(p.type==="shield") shield=true;

                explode(p.x,p.y,"cyan");

                powerups.splice(j,1);
                bullets.splice(i,1);
                break;
            }
        }
    }

    // Player collision with enemies
    for(let i=enemies.length-1;i>=0;i--){
        if(hit(player,enemies[i])){
            if(shield){
                shield=false;
                enemies.splice(i,1);
            } else {
                reset();
                return;
            }
        }
    }

    draw();
}

function draw(){
    ctx.clearRect(0,0,canvas.width,canvas.height);

    // Lane divider
    ctx.strokeStyle="#555";
    ctx.beginPath();
    ctx.moveTo(canvas.width/2,0);
    ctx.lineTo(canvas.width/2,canvas.height);
    ctx.stroke();

    // Player
    ctx.fillStyle = shield ? "#0ff" : "#0f0";
    ctx.fillRect(player.x,player.y,player.w,player.h);

    // Bullets
    ctx.fillStyle="#fff";
    bullets.forEach(b=>ctx.fillRect(b.x,b.y,b.w,b.h));

    // Enemies
    enemies.forEach(e=>{
        ctx.fillStyle="#f00";
        ctx.fillRect(e.x,e.y,e.w,e.h);

        // Draw HP text
        ctx.fillStyle="white";
        ctx.font="16px Arial";
        ctx.textAlign="center";
        ctx.fillText(e.hp, e.x + e.w/2, e.y + e.h/2 + 6);
    });

    // Power-ups
    powerups.forEach(p=>{
        ctx.fillStyle =
            p.type==="rapid" ? "yellow" :
            p.type==="shield" ? "cyan" : "lime";
        ctx.fillRect(p.x,p.y,p.w,p.h);
    });

    // Particles
    particles.forEach(p=>{
        ctx.fillStyle=p.color;
        ctx.fillRect(p.x,p.y,3,3);
    });

    hits.textContent = score;
    power.textContent = currentPower;
}

update();

// Controls
canvas.addEventListener("pointerdown",e=>{
    pointerX = e.offsetX;
    shooting = true;
});
canvas.addEventListener("pointermove",e=>{
    pointerX = e.offsetX;
});
canvas.addEventListener("pointerup",()=>{
    pointerX = null;
    shooting = false;
});

btnLeft.ontouchstart=()=>moveLeft=true;
btnLeft.ontouchend=()=>moveLeft=false;
btnRight.ontouchstart=()=>moveRight=true;
btnRight.ontouchend=()=>moveRight=false;
btnShoot.ontouchstart=()=>shooting=true;
btnShoot.ontouchend=()=>shooting=false;

setInterval(()=>{
    timeLeft--;
    timer.textContent=timeLeft;
},1000);
</script>

</body>
</html>