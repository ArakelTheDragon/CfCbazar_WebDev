<?php
// BTC Profit Calculator — CfCbazar Style (Noob Friendly)

ini_set('display_errors', 1);
error_reporting(E_ALL);

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
    if (function_exists('trackVisit')) trackVisit("btc-profit-calculator");
}

include_header();
include_menu();
render_top_userbar();

$usd_spent = $_POST['usd_spent'] ?? '';
$th = $_POST['th'] ?? '';
$days = $_POST['days'] ?? '';
$fee_usd = $_POST['fee_usd'] ?? '0.028';
$btc_price = $_POST['btc_price'] ?? '63000';

$btc_earned = '';
$btc_earned_usd = '';
$fee_usd_total = '';
$fee_btc = '';
$btc_left = '';
$btc_left_usd = '';
$profit_usd = '';
$profit_btc = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_numeric($usd_spent) && is_numeric($th) && is_numeric($days) &&
        is_numeric($fee_usd) && is_numeric($btc_price)) {

        // Mined BTC for full period
        $btc_earned = $th * $days * 0.00000048;
        $btc_earned_usd = $btc_earned * $btc_price;

        // Service fee for full period (USD)
        $fee_usd_total = $th * $days * $fee_usd;

        // Convert fee to BTC
        $fee_btc = $fee_usd_total / $btc_price;

        // Leftover BTC after fee
        $btc_left = $btc_earned - $fee_btc;
        $btc_left_usd = $btc_left * $btc_price;

        // Final profit (USD)
        $profit_usd = $btc_left_usd - $usd_spent;

        // Final profit (BTC)
        $profit_btc = $profit_usd / $btc_price;
    }
}
?>

<main class="container">

    <h1 class="page-title">BTC Mining Profit Calculator</h1>
    <p class="subtitle">Easy calculator showing mined BTC, fees, leftover BTC and final profit.</p>

    <div class="card">
        <form method="POST">

            <label>Upfront Cost (USD):</label>
            <input type="text" name="usd_spent" value="<?= htmlspecialchars($usd_spent) ?>" placeholder="99">

            <label>Total TH Purchased:</label>
            <input type="text" name="th" value="<?= htmlspecialchars($th) ?>" placeholder="2487.56">

            <label>Total Days:</label>
            <input type="text" name="days" value="<?= htmlspecialchars($days) ?>" placeholder="30">

            <label>Service Fee per TH/day (USD):</label>
            <input type="text" name="fee_usd" value="<?= htmlspecialchars($fee_usd) ?>" placeholder="0.028">

            <label>BTC Price (USD):</label>
            <input type="text" name="btc_price" value="<?= htmlspecialchars($btc_price) ?>" placeholder="63000">

            <button type="submit">Calculate</button>
        </form>
    </div>

    <?php if ($btc_earned !== ''): ?>
    <div class="card">
        <h2>Results (Full Period)</h2>

        <p><strong>Mined BTC:</strong> 
            <span style="color:#2563eb;font-weight:bold;"><?= $btc_earned ?></span>
        </p>

        <p><strong>Mined USD:</strong> 
            <?= number_format($btc_earned_usd, 2) ?>
        </p>

        <p><strong>Service Fee (USD):</strong> 
            <span style="color:#dc2626;font-weight:bold;"><?= number_format($fee_usd_total, 2) ?></span>
        </p>

        <p><strong>Service Fee (BTC):</strong> 
            <?= $fee_btc ?>
        </p>

        <p><strong>Leftover BTC After Fee:</strong> 
            <span style="color:#2563eb;font-weight:bold;"><?= $btc_left ?></span>
        </p>

        <p><strong>Leftover BTC After Fee (USD):</strong> 
            <?= number_format($btc_left_usd, 2) ?>
        </p>

        <p><strong>Final Profit net BTC - Upfront (USD):</strong> 
            <span style="color:#dc2626;font-weight:bold;"><?= number_format($profit_usd, 2) ?></span>
        </p>

        <p><strong>Final Profit (BTC):</strong> 
            <?= $profit_btc ?>
        </p>
    </div>
    <?php endif; ?>

</main>

/* cfc_footer(
    "https://github.com/YOUR_USERNAME/YOUR_REPO",
    "Message Tool Source Code"
);*/
?>
<?php include_footer(); ?>
