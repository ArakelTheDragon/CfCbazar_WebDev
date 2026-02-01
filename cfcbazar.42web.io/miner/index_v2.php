<?php
// miner/index.php
$title = "⛏️ CfCbazar Miner | Earn WorkToken & WorkTHR";
require_once __DIR__ . '/../includes/reusable.php';

// Track visit BEFORE any output
trackVisit($conn);

//$userEmail = $_SESSION['email'] ?? '';


include_header(); // ✅ This prints <head> and links styles.css, now called from reusable.php directly so this commented
renderCaptchaIfNeeded();

//require_once __DIR__ . '/../includes/reusable2.php'; // merged with reusable.php
//require_once __DIR__ . '/../includes/reusable3.php'; // merged with reusable.php

// Enforce HTTPS and track page visits
enforce_https(); // called directly from reusable.php now
//track_visits(); // removed, under development

$email = $_SESSION['email'] ?? null;
$logged_in = $email !== null;

// bonus WTK and WorkTHR from reusable3.php
$email = $_SESSION['email'] ?? null;
grant_mining_bonus($email);

// Handle auto-claim reward and device status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in && isset($_POST['reward_type'], $_POST['accepted'], $_POST['mac_address'], $_POST['active'])) {
    handleMinerReward(
        $email,
        $_POST['reward_type'],
        intval($_POST['accepted']),
        $_POST['mac_address'],
        intval($_POST['active'])
    );
    exit;
}

//$title = "⛏️ CfCbazar Miner | Earn WorkToken & WorkTHR"; // moved to top of index.php
//include_header(); // moved to top of index.php
include_menu();
?>

<main class="dashboard-container">
    <section class="hero-section">
        <h2>⛏️ Start Mining WorkToken & WorkTHR</h2>
        <p>Mine platform credits directly in your browser. Stay on this tab to earn rewards.</p>
    </section>

    <?php renderMinerInterface($logged_in); ?>
    <?php renderMinerScript(); ?>

    <?php
    if ($logged_in) {
        $stats = getWorkerStats($email);
        if (!empty($stats)) {
            echo '<section class="table-container">';
            echo '<h3>📈 Your Mining Totals</h3>';
            echo '<table class="worker-stats-table" role="grid" aria-label="Mining Totals">';
            echo '<thead><tr><th>WorkToken Earned</th><th>WorkTHR Earned</th></tr></thead>';
            echo '<tbody><tr>';
            echo '<td>' . number_format((float)($stats['tokens_earned'] ?? 0), 8) . '</td>';
            echo '<td>' . number_format((float)($stats['mintme'] ?? 0), 8) . '</td>';
            echo '</tr></tbody></table></section>';
			
			// Withdraw button section
            echo '<section class="links-grid" aria-label="Withdraw and Explore Tokens">';
render_withdraw_link();
render_workthr_teaser();
render_worktoken_teaser();
echo '</section>';
        }
    }
    ?>
</main>


<?php
include_footer();
?>
