<?php
require_once '../../includes/reusable.php';

enforce_https();
setReturnUrlCookie('/dashboard.php');

include_header();
include_menu();
?>

<style>
    #game-container {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        padding: 20px;
        text-align: center;
    }

    #pool-canvas {
        background: #0b5e2a;
        border: 4px solid #3b2a1a;
        border-radius: 8px;
        display: block;
        margin: 0 auto;
    }
</style>

<div id="game-container">
    <h1>Pool Billiard – Prototype</h1>
    <canvas id="pool-canvas" width="1000" height="500"></canvas>
</div>

<script src="js/game.js"></script>

<?php
include_footer();
?>