<?php
session_start();

// --- CONFIG ---
$BOARD_SIZE = 40;
$WIN_RATE = 0.20;     // 20% chance to win
$WIN_AMOUNT = 5;      // +5 WorkTHR
$LOSS_AMOUNT = -2;    // -2 WorkTHR

// --- INIT ---
if (!isset($_SESSION['pos'])) $_SESSION['pos'] = 0;
if (!isset($_SESSION['roll'])) $_SESSION['roll'] = null;
if (!isset($_SESSION['laps'])) $_SESSION['laps'] = 0;
if (!isset($_SESSION['workthr'])) $_SESSION['workthr'] = 10;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* --- ADD 10 WorkTHR --- */
    if ($action === 'add10') {
        $_SESSION['workthr'] += 10;
        $message = "Added +10 WorkTHR.";
    }

    /* --- ROLL DICE --- */
    if ($action === 'roll') {

        if ($_SESSION['workthr'] <= 0) {
            $message = "Not enough WorkTHR — press Add 10 WorkTHR.";
        } else {

            $roll = random_int(1, 6);
            $_SESSION['roll'] = $roll;

            $old = $_SESSION['pos'];
            $new = $old + $roll;

            if ($new >= $BOARD_SIZE) {
                $new %= $BOARD_SIZE;
                $_SESSION['laps']++;
            }

            $_SESSION['pos'] = $new;

            /* --- WorkTHR win/lose logic --- */
            $chance = mt_rand() / mt_getrandmax(); // 0–1 float

            if ($chance <= $WIN_RATE) {
                $_SESSION['workthr'] += $WIN_AMOUNT;
                $message = "You rolled $roll and WON +$WIN_AMOUNT WorkTHR!";
            } else {
                $_SESSION['workthr'] += $LOSS_AMOUNT;
                $message = "You rolled $roll and lost " . abs($LOSS_AMOUNT) . " WorkTHR.";
            }
        }
    }

    /* --- RESET GAME --- */
    if ($action === 'reset') {
        $_SESSION['pos'] = 0;
        $_SESSION['roll'] = null;
        $_SESSION['laps'] = 0;
        $_SESSION['workthr'] = 10;
        $message = "Game reset.";
    }
}

$pos  = $_SESSION['pos'];
$roll = $_SESSION['roll'];
$laps = $_SESSION['laps'];
$workthr = $_SESSION['workthr'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Dice Board Game</title>

<link rel="stylesheet" href="/css/styles.css">

<style>
/* ============================================================
   BOARD GAME SPECIFIC CSS
   ============================================================ */

.board-container {
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
    margin-top: 20px;
    padding: 20px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* 4 rows × 10 columns grid */
.board-grid {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 10px;
    margin-bottom: 25px;
}

.square {
    width: 100%;
    aspect-ratio: 1 / 1;
    background: #ffffff;
    border: 2px solid #28a745;
    border-radius: 10px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 14px;
    font-weight: bold;
    color: #28a745;
}

.pawn {
    width: 28px;
    height: 28px;
    background: #e53935;
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(229,57,53,0.6);
}

/* Center UI */
.center-ui {
    margin-top: 20px;
    padding: 25px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* BIGGER readable results */
.results-box {
    margin-top: 20px;
    padding: 20px;
    background: #f1f8f4;
    border-left: 6px solid #28a745;
    border-radius: 10px;
    font-size: 1.3rem;
    line-height: 1.8rem;
}

/* Warning when WorkTHR <= 0 */
.warning {
    background: #ffe6e6;
    border-left: 6px solid #e53935;
    color: #c62828;
}

/* Footer notice */
.footer-note {
    margin-top: 40px;
    text-align: center;
    font-size: 0.9rem;
    color: #777;
}
</style>
</head>

<body>

<div class="container">

<h1 class="page-title">🎲 Dice Board Game</h1>

<div class="board-container">

    <!-- BOARD GRID -->
    <div class="board-grid">
        <?php for ($i = 0; $i < 40; $i++): ?>
            <div class="square">
                <?= $pos == $i ? "<div class='pawn'></div>" : $i + 1 ?>
            </div>
        <?php endfor; ?>
    </div>

    <!-- CENTER UI -->
    <div class="center-ui">
        <h2>Game Controls</h2>

        <form method="post">
            <input type="hidden" name="action" value="roll">
            <button type="submit">Roll Dice</button>
        </form>

        <form method="post">
            <input type="hidden" name="action" value="add10">
            <button type="submit" style="background:#1e88e5;">Add +10 WorkTHR</button>
        </form>

        <form method="post">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="reset">Reset</button>
        </form>

        <!-- RESULTS BOX -->
        <div class="results-box <?= ($workthr <= 0 ? 'warning' : '') ?>">
            <strong><?= $message ?: "—" ?></strong><br>
            <strong>Square:</strong> <?= $pos + 1 ?><br>
            <strong>Last roll:</strong> <?= $roll ?: "—" ?><br>
            <strong>Laps:</strong> <?= $laps ?><br>
            <strong>WorkTHR:</strong> <?= $workthr ?>
        </div>

    </div>

</div>

<div class="footer-note">
    Simulated WorkTHR, can not be withdrawn!
</div>

</div>

</body>
</html>