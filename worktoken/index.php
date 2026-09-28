<?php
// /worktoken/index.php — CfCbazar Worker Dashboard

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$reusablePath = __DIR__ . '/../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;

    if (function_exists('trackVisit')) {
        trackVisit("d-worktoken");
    }
}

// require_once __DIR__ . '/../includes/miner.php';

// --- Session ---
$is_logged_in = isset($_SESSION['email']);
$email = $is_logged_in ? $_SESSION['email'] : null;

// --- CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Logout ---
if ($is_logged_in && isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit();
}

$title = 'CfCbazar Worker Dashboard';

// --- Prevent API spam ---
if ($is_logged_in) {
    if (!isset($_SESSION['last_bonus_run']) || time() - $_SESSION['last_bonus_run'] > 60) { 
        $ApiPath = __DIR__ . '/../api/testapi.php';
        if (file_exists($ApiPath)) {
            //require_once $ApiPath;
            trackVisit("d-worktoken");
        }
        grant_mining_bonus($email);
        $_SESSION['last_bonus_run'] = time();
    }
}

$current_address = "";
$message = "";
$vip_message = "";
$user_status = 5;
$mintme_balance = 0;

if ($is_logged_in) {

    // --- Get user status ---
    $stmt = $conn->prepare("SELECT status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($user_status);
    $stmt->fetch();
    $stmt->close();

    // --- Load wallet + balance ---
    $stmt = $conn->prepare("SELECT address, mintme FROM workers WHERE email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($current_address, $mintme_balance);
        $stmt->fetch();
        $stmt->close();
    }

    // --- Save wallet ---
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["wallet_address"])) {

        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            $message = "Security error.";
        } else {

            $wallet_address = trim($_POST["wallet_address"]);

            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
                $message = "Invalid wallet address.";
            } else {

                $update = $conn->prepare("UPDATE workers SET address = ? WHERE email = ?");
                $update->bind_param("ss", $wallet_address, $email);

                if ($update->execute()) {
                    $message = "Wallet saved!";
                    $current_address = $wallet_address;
                } else {
                    $message = "Database error.";
                }

                $update->close();
            }
        }
    }

    // ============================
    // ⭐ VIP PURCHASE
    // ============================
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["vip_buy"])) {

        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            $vip_message = "Security error.";
        } else {

            $conn->begin_transaction();

            try {

                // Lock user
                $stmt = $conn->prepare("SELECT status FROM users WHERE email = ? FOR UPDATE");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->bind_result($status);
                $stmt->fetch();
                $stmt->close();

                if ($status != 5) {
                    throw new Exception("Already VIP or not eligible.");
                }

                // Lock WorkTHR (mintme)
                $stmt = $conn->prepare("SELECT mintme FROM workers WHERE email = ? FOR UPDATE");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->bind_result($balance);
                $stmt->fetch();
                $stmt->close();

                if ($balance === null) {
                    throw new Exception("Worker account not found.");
                }

                if ($balance < 10) {
                    throw new Exception("Not enough WorkTHR.");
                }

                // Deduct
                $stmt = $conn->prepare("UPDATE workers SET mintme = mintme - 10 WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->close();

                // Upgrade to VIP
                $stmt = $conn->prepare("UPDATE users SET status = 4 WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $vip_message = "VIP upgrade successful!";
                $user_status = 4;
                $mintme_balance -= 10;

            } catch (Exception $e) {
                $conn->rollback();
                $vip_message = $e->getMessage();
            }
        }
    }
}

// --- Layout ---
include_menu();
render_top_userbar();
include_header();
?>
<script src="/js/miner.js"></script>

<main class="container">

    <div class="welcome-card">
        <h2>💼 Welcome<?= $is_logged_in ? ', ' . htmlspecialchars($email) : '' ?>!</h2>
        <?php render_token_price_tracker(); ?>
    </div>

<?php if ($is_logged_in): ?>

    <!-- WALLET -->
    <div class="card">
        <h3>💳 Wallet Address</h3>

        <?php if (!empty($message)): ?>
            <div class="<?= str_contains($message, 'saved') ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="text" name="wallet_address"
                   value="<?= htmlspecialchars($current_address ?? '') ?>"
                   placeholder="0x..." required>

            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit">Save Wallet</button>
        </form>
    </div>

<!-- MINER under development
<div class="card">
    <h3>⛏️ WorkTHR Browser Miner</h3>

    <p>
        Start mining WorkTHR using your browser.
    </p>

    <p>
        Accepted Shares:
        <strong id="accepted-shares">0</strong>
    </p>

    <button onclick="startMiner(); this.disabled=true;">
        🚀 Start Mining
    </button>

</div>
-->
<script>
let minerDisplay = setInterval(function() {

    if (typeof getAcceptedShares === "function") {

        document.getElementById("accepted-shares").innerHTML =
            getAcceptedShares();

    }

}, 1000);
</script>

    <!-- VIP -->
    <div class="card">
        <h3>⭐ VIP Upgrade</h3>

        <p><strong>Balance:</strong> <?= number_format($mintme_balance, 2) ?> WorkTHR</p>

        <?php if (!empty($vip_message)): ?>
            <div class="<?= str_contains($vip_message, 'successful') ? 'success' : 'error' ?>">
                <?= htmlspecialchars($vip_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($user_status == 5): ?>
            <form method="post">
                <input type="hidden" name="vip_buy" value="1">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button onclick="this.disabled=true; this.form.submit();">
                    Buy VIP (10 WorkTHR)
                </button>
            </form>
        <?php else: ?>
            <p>✅ You are VIP</p>
        <?php endif; ?>
    </div>

    <!-- LINKS -->
    <div class="card">
        <h3>🔗 Quick Links</h3>

        <div class="links-grid">
            <a class="link-card" href="/w.php" target="_blank">💰 Withdraw</a>
            <a class="link-card" href="/worktoken/buy.php" target="_blank">💰 Deposit</a>
            <a class="link-card" href="/miner/" target="_blank">⛏️ Full Miner</a>
            <a class="link-card" href="/pow/" target="_blank">⛏️ PoW Mining</a>
            <a class="link-card" href="/help/" target="_blank">🆘 Help</a>
            <a class="link-card" href="/index.php">🏠 Home</a>
        </div>
    </div>

    <!-- REUSABLE TASKS -->
    <?php
    if (function_exists('render_task_grid')) {
        render_task_grid($conn, $email, '⚙️ Available WorkToken Tasks');
    }
    ?>

    <div class="card">
        <a href="?logout=true" class="logout-btn" style="
            display:block;
            padding:12px;
            background:#e3342f;
            color:#fff;
            border-radius:8px;
            text-align:center;
            font-weight:bold;
            text-decoration:none;
        ">🚪 Logout</a>
    </div>

<?php else: ?>

    <div class="card">
        <p>🔐 <a href="/login.php">Log in</a> to continue.</p>
    </div>

<?php endif; ?>

</main>

<?php
include_footer();

if ($is_logged_in) {
    $conn->close();
}
?>
