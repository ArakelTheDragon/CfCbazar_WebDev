const canvas = document.getElementById("pool-canvas");
const ctx = canvas.getContext("2d");

const table = {
    width: canvas.width,
    height: canvas.height
};

const ball = {
    x: 200,
    y: 200,
    radius: 12,
    vx: 3,
    vy: 2,
    color: "#ffffff"
};

function update() {
    // Move ball
    ball.x += ball.vx;
    ball.y += ball.vy;

    // Bounce on left/right cushions
    if (ball.x - ball.radius < 0 || ball.x + ball.radius > table.width) {
        ball.vx *= -1;
    }

    // Bounce on top/bottom cushions
    if (ball.y - ball.radius < 0 || ball.y + ball.radius > table.height) {
        ball.vy *= -1;
    }
}

function draw() {
    ctx.clearRect(0, 0, table.width, table.height);

    // Draw ball
    ctx.beginPath();
    ctx.arc(ball.x, ball.y, ball.radius, 0, Math.PI * 2);
    ctx.fillStyle = ball.color;
    ctx.fill();
}

function loop() {
    update();
    draw();
    requestAnimationFrame(loop);
}

loop();