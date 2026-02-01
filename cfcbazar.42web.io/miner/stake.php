<?php
// index.php — WorkTHR Staking Dashboard
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/reusable.php';

$title = 'WorkTHR Staking Dashboard';
$wallet = $_GET['wallet'] ?? '';
$token = 'WorkTHR'; // Only allow WorkTHR
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallet'], $_POST['action'])) {
    $wallet = trim($_POST['wallet']);
    $action = $_POST['action'];
    $message = toggleStaking($conn, $wallet, $action);
}

$statusOutput = '';
if ($wallet) {
    $stakingStatus = getStakingStatus($conn, $wallet);
    $tokenStatus = getWorkTokenStatus($conn, $wallet, $token);
    $statusOutput = nl2br(htmlspecialchars($stakingStatus . "\n\n" . $tokenStatus));
}

include_header();
?>

<main class="staking-container">
    <section class="hero-section">
        <h2>💠 WorkTHR Staking</h2>
        <p>Stake your <strong>WorkTHR</strong> while mining to earn additional rewards.<br>
        You must have an active miner session (pinged within 2 minutes) for staking to accumulate.</p>
    </section>

    <section class="staking-form">
        <form method="POST" id="stakingForm">
            <label for="wallet">Wallet Address</label>
            <input type="text" id="wallet" name="wallet" value="<?php echo htmlspecialchars($wallet); ?>" placeholder="0x..." required pattern="^0x[a-fA-F0-9]{40}$">

            <div class="stake-buttons">
                <button type="submit" name="action" value="start" class="start-btn">Start Staking WorkTHR</button>
                <button type="submit" name="action" value="stop" class="stop-btn">Stop Staking WorkTHR</button>
            </div>
        </form>

        <?php if ($message): ?>
            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div id="stakingStatusBox" class="status-box">
            <h3>📊 Current Staking Status</h3>
            <pre id="stakingStatus"><?php echo $statusOutput ?: 'Enter a wallet to view status.'; ?></pre>
        </div>
    </section>
</main>

<script>
const form = document.getElementById('stakingForm');
const statusBox = document.getElementById('stakingStatus');
const walletInput = document.getElementById('wallet');
const token = 'WorkTHR';

async function fetchStatus() {
    const wallet = walletInput.value.trim();
    if (!wallet.match(/^0x[a-fA-F0-9]{40}$/)) return;
    try {
        const res = await fetch(`index.php?wallet=${encodeURIComponent(wallet)}&token=${encodeURIComponent(token)}`);
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newStatus = doc.getElementById('stakingStatus')?.innerText || 'No status.';
        statusBox.textContent = newStatus;
    } catch {
        statusBox.textContent = 'Error fetching status.';
    }
}

form.addEventListener('submit', () => setTimeout(fetchStatus, 1000));
setInterval(fetchStatus, 30000);
</script>

<style>
main.staking-container {
    max-width: 720px;
    margin: 40px auto;
    padding: 20px;
    font-family: system-ui, sans-serif;
}
.hero-section {
    margin-bottom: 20px;
}
.staking-form form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
input {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 6px;
}
.stake-buttons {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}
.start-btn {
    background: #007bff;
    color: #fff;
    border: none;
    padding: 10px;
    border-radius: 6px;
    cursor: pointer;
}
.stop-btn {
    background: #dc3545;
    color: #fff;
    border: none;
    padding: 10px;
    border-radius: 6px;
    cursor: pointer;
}
.start-btn:hover {
    background: #0056b3;
}
.stop-btn:hover {
    background: #a71d2a;
}
.message {
    margin-top: 15px;
    font-weight: bold;
}
.status-box {
    margin-top: 20px;
    background: #f7f7f7;
    padding: 15px;
    border-radius: 8px;
    white-space: pre-wrap;
}
</style>

<?php include_footer(); ?>