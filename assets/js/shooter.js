let game=document.getElementById("game");

let damage;
let speed;
let shooters;

let mobHp;
let mobSpeed;
let maxEscapes;

let playerX=window.innerWidth/2;

let mobs=[];
let bullets=[];

let kills=0;
let escaped=0;

let score=0;

let started=false;

document
.getElementById('startBtn')
.onclick=function(){

    damage=
    +document.getElementById('cfgDamage').value;

    speed=
    +document.getElementById('cfgSpeed').value;

    shooters=
    +document.getElementById('cfgShooters').value;

    mobHp=
    +document.getElementById('cfgMobHp').value;

    mobSpeed=
    +document.getElementById('cfgMobSpeed').value;

    maxEscapes=
    +document.getElementById('cfgEscapes').value;

    document
    .getElementById('setup')
    .style.display='none';

    started=true;

    drawShooters();
};

function difficultyMultiplier()
{
    let playerPower =
        damage +
        speed +
        shooters +
        maxEscapes;

    let difficulty =
        mobHp +
        mobSpeed;

    return difficulty/playerPower;
}

function updateScore()
{
    score=Math.floor(
        kills *
        difficultyMultiplier() *
        100
    );

    document.getElementById(
        'score'
    ).innerText=score;
}

function spawnMob()
{
    if(!started) return;

    let mob={

        hp:mobHp,

        x:
        Math.random()*
        (window.innerWidth*0.4),

        y:-40

    };

    mob.el=document.createElement('div');

    mob.el.className='mob';
    mob.el.innerHTML='👹';

    game.appendChild(mob.el);

    mobs.push(mob);
}

function drawShooters()
{
    document
    .querySelectorAll('.shooter')
    .forEach(x=>x.remove());

    for(let i=0;i<shooters;i++)
    {
        let gun=document.createElement('div');

        gun.className='shooter';
        gun.innerHTML='🔫';

        gun.style.left=
            (
                playerX
                -
                (shooters*15)
                +
                (i*30)
            )
            +'px';

        game.appendChild(gun);
    }
}

function shoot()
{
    if(!started) return;

    for(let i=0;i<shooters;i++)
    {
        let bullet={

            x:
            playerX
            -
            (shooters*15)
            +
            (i*30),

            y:
            window.innerHeight-100

        };

        bullet.el=document.createElement('div');

        bullet.el.className='bullet';

        game.appendChild(bullet.el);

        bullets.push(bullet);
    }
}

function update()
{
    if(!started) return;

    drawShooters();

    mobs.forEach((mob,mIndex)=>{

        mob.y += mobSpeed;

        mob.el.style.left=
            mob.x+'px';

        mob.el.style.top=
            mob.y+'px';

        if(mob.y>window.innerHeight)
        {
            mob.el.remove();

            mobs.splice(mIndex,1);

            escaped++;

            document
            .getElementById('escaped')
            .innerText=escaped;

            if(escaped>=maxEscapes)
            {
                gameOver();
            }
        }

    });

    bullets.forEach((bullet,bIndex)=>{

        bullet.y -=
            (6*speed);

        bullet.el.style.left=
            bullet.x+'px';

        bullet.el.style.top=
            bullet.y+'px';

        if(bullet.y < -20)
        {
            bullet.el.remove();
            bullets.splice(bIndex,1);
            return;
        }

        mobs.forEach((mob,mIndex)=>{

            if(
                Math.abs(
                    bullet.x-mob.x
                )<35
                &&
                Math.abs(
                    bullet.y-mob.y
                )<35
            )
            {
                mob.hp-=damage;

                bullet.el.remove();

                bullets.splice(
                    bIndex,
                    1
                );

                if(mob.hp<=0)
                {
                    mob.el.remove();

                    mobs.splice(
                        mIndex,
                        1
                    );

                    kills++;

                    document
                    .getElementById('kills')
                    .innerText=kills;

                    updateScore();
                }
            }

        });

    });

}

function gameOver()
{
    clearInterval(loop);
    clearInterval(mobLoop);
    clearInterval(shootLoop);

    document
    .getElementById(
        'finalscore'
    )
    .innerText=score;

    document
    .getElementById(
        'gameover'
    )
    .style.display='block';
}

document.addEventListener(
'mousemove',
e=>{
    playerX=e.clientX;
});

document.addEventListener(
'touchmove',
e=>{
    playerX=e.touches[0].clientX;
},
{passive:true}
);

let loop=
setInterval(update,20);

let mobLoop=
setInterval(function(){

    if(Math.random()<0.4)
    {
        spawnMob();
    }

},1000);

let shootLoop=
setInterval(function(){

    shoot();

},400);
