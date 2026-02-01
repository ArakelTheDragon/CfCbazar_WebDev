<?php
session_start();

// --- CONFIG ---
$BOARD_SIZE = 20;

// --- INIT ---
if (!isset($_SESSION['pos'])) $_SESSION['pos'] = 0;
if (!isset($_SESSION['roll'])) $_SESSION['roll'] = null;
if (!isset($_SESSION['laps'])) $_SESSION['laps'] = 0;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'roll') {
        $roll = random_int(1, 6);
        $_SESSION['roll'] = $roll;

        $old = $_SESSION['pos'];
        $new = $old + $roll;

        if ($new >= $BOARD_SIZE) {
            $new %= $BOARD_SIZE;
            $_SESSION['laps']++;
            $message = "You rolled $roll and completed a lap! Now on square " . ($new + 1) . ".";
        } else {
            $message = "You rolled $roll and moved to square " . ($new + 1) . ".";
        }

        $_SESSION['pos'] = $new;
    }

    if ($action === 'reset') {
        $_SESSION['pos'] = 0;
        $_SESSION['roll'] = null;
        $_SESSION['laps'] = 0;
        $message = "Game reset.";
    }
}

$pos  = $_SESSION['pos'];
$roll = $_SESSION['roll'];
$laps = $_SESSION['laps'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edge Rectangle Board Game</title>

<style>
    body {
        font-family: "Segoe UI", Arial, sans-serif;
        background: #1e1e2f;
        color: #eee;
        margin: 0;
        padding: 0;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* MAIN BOARD WRAPPER */
    .board-wrapper {
        width: 95vw;
        height: 95vh;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* CENTER UI AREA */
    .center-ui {
        width: 60%;
        max-width: 400px;
        padding: 20px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 12px;
        backdrop-filter: blur(4px);
        text-align: center;
        z-index: 10;
    }

    button {
        padding: 14px 28px;
        font-size: 18px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        margin: 8px;
        color: #fff;
        background: #3a7afe;
        box-shadow: 0 0 12px rgba(58,122,254,0.5);
        transition: 0.2s;
        width: 100%;
    }

    button:hover {
        background: #1f5eff;
    }

    .reset {
        background: #e53935;
    }

    .reset:hover {
        background: #c62828;
    }

    /* BOARD SQUARES AROUND EDGE */
    .square {
        width: 70px;
        height: 70px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 10px;
        position: absolute;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 11px;
        color: #aaa;
    }

    .pawn {
        width: 26px;
        height: 26px;
        background: #ff5722;
        border-radius: 50%;
        box-shadow: 0 0 12px rgba(255,87,34,0.8);
    }

    /* RESPONSIVE */
    @media (max-width: 600px) {
        .square {
            width: 50px;
            height: 50px;
        }
        .center-ui {
            width: 80%;
        }
    }
</style>
</head>
<body>

<div class="board-wrapper">

    <!-- TOP ROW -->
    <div class="row top-row">
        <?php for ($i = 0; $i < 10; $i++): ?>
            <div class="square">
                <?php if ($pos == $i): ?>
                    <div class="pawn"></div>
                <?php else: ?>
                    <?= $i + 1 ?>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="col right-col">
        <?php for ($i = 10; $i < 20; $i++): ?>
            <div class="square">
                <?php if ($pos == $i): ?>
                    <div class="pawn"></div>
                <?php else: ?>
                    <?= $i + 1 ?>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <!-- BOTTOM ROW -->
    <div class="row bottom-row">
        <?php for ($i = 20; $i < 30; $i++): ?>
            <div class="square">
                <?php if ($pos == $i): ?>
                    <div class="pawn"></div>
                <?php else: ?>
                    <?= $i + 1 ?>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <!-- LEFT COLUMN -->
    <div class="col left-col">
        <?php for ($i = 30; $i < 40; $i++): ?>
            <div class="square">
                <?php if ($pos == $i): ?>
                    <div class="pawn"></div>
                <?php else: ?>
                    <?= $i + 1 ?>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <!-- CENTER UI -->
    <div class="center-ui">
        <h2>🎲 Dice Game</h2>

        <form method="post">
            <input type="hidden" name="action" value="roll">
            <button type="submit">Roll Dice</button>
        </form>

        <form method="post">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="reset">Reset</button>
        </form>

        <p><strong>Message:</strong> <?= $message ?: "—" ?></p>
        <p><strong>Square:</strong> <?= $pos + 1 ?></p>
        <p><strong>Last roll:</strong> <?= $roll ?: "—" ?></p>
        <p><strong>Laps:</strong> <?= $laps ?></p>
    </div>

</div>

</body>
</html>