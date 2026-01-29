<?php
// d.php — CfCbazar Worker Dashboard
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'includes/reusable.php';

$is_logged_in = isset($_SESSION['email']);
$email = $is_logged_in ? $_SESSION['email'] : null;

// Logout logic
if ($is_logged_in && isset($_GET['logout'])) {
    session_destroy();
    unset($_SESSION['email']);
    header('Location: login.php');
    exit();
}

$title = 'CfCbazar Worker Dashboard - WorkToken & Mining';

// Trigger token/device sync
if ($is_logged_in) {
    require 'testapi.php';
}

$current_address = "";
$message = "";

if ($is_logged_in) {

    // -----------------------------------
    // Wallet address load and update
    // -----------------------------------
    $res_addr = $conn->prepare("SELECT address FROM workers WHERE email = ? LIMIT 1");
    if ($res_addr) {
        $res_addr->bind_param("s", $email);
        $res_addr->execute();
        $res_addr->bind_result($current_address);
        $res_addr->fetch();
        $res_addr->close();
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["wallet_address"])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $message = "Security error: Invalid CSRF token.";
        } else {
            $wallet_address = trim($_POST["wallet_address"]);
            if (!empty($wallet_address)) {
                $update_address = $conn->prepare("UPDATE workers SET address = ? WHERE email = ?");
                if ($update_address) {
                    $update_address->bind_param("ss", $wallet_address, $email);
                    if ($update_address->execute()) {
                        $message = "Wallet address saved successfully!";
                        $current_address = $wallet_address;
                    } else {
                        $message = "Error saving wallet address.";
                    }
                    $update_address->close();
                } else {
                    $message = "Error preparing wallet address update.";
                }
            } else {
                $message = "Please enter a valid wallet address.";
            }
        }
    }

    // -----------------------------------
    // Device deletion (secure)
    // -----------------------------------
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_mac'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $message = "Security error: Invalid CSRF token.";
        } else {
            $macToDelete = trim($_POST['delete_mac']);
            if (!empty($macToDelete)) {
                $stmt = $conn->prepare("DELETE FROM devices WHERE email = ? AND mac_address = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $email, $macToDelete);
                    if ($stmt->execute()) {
                        $message = "Device deleted successfully.";
                    } else {
                        $message = "Error deleting device.";
                    }
                    $stmt->close();
                } else {
                    $message = "Error preparing device deletion.";
                }
            }
        }
    }

    // -----------------------------------
    // Worker stats
    // -----------------------------------
    $stats = getWorkerStats($email);

    // -----------------------------------
    // Devices (not admin-only)
    // -----------------------------------
    $status = function_exists('getUserStatus') ? getUserStatus($conn) : 0;
    $deviceGroups = ['active' => [], 'inactive' => []];

    if ($status !== 0 && function_exists('getDevices')) {
        $deviceGroups = getDevices($email, 30, 'auto');
    }
}

include_header();
renderCaptchaIfNeeded();
?>

<meta name="description" content="Explore the WorkToken dashboard, trade tokens, and manage your wallet. Log in to track mining stats and platform credits.">
<?php include_menu(); ?>

<main class="dashboard-container<?php echo ($_SERVER["REQUEST_METHOD"] === "POST") ? ' just-posted' : ''; ?>">
    <section class="hero-section">
        <h2>💼 Welcome to the WorkToken Dashboard<?php echo $is_logged_in ? ', ' . htmlspecialchars($email) : ''; ?>!</h2>
        <?php render_token_price_tracker(); ?>
        <p style="margin-top: 20px;">
            🔄 Want to trade WTK and WorkTHR? Use our live PancakeSwap pair:
            <a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D" target="_blank" rel="noopener">
                Trade WTK/WorkTHR on PancakeSwap
            </a>.
        </p>
    </section>

    <?php render_worktoken_dashboard(); ?>

    <?php if ($is_logged_in): ?>
        <section class="table-container">
            <h3>📊 Worker Stats</h3>
            <table class="worker-stats-table" role="grid" aria-label="Worker Statistics">
                <thead>
                    <tr>
                        <th>Worker Name</th>
                        <th>dWorkTokens</th>
                        <th>mWorkTokens</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats)): ?>
                        <tr><td colspan="3">No workers found for this account. Visit <a href="/index.php">Home</a> to get started.</td></tr>
                    <?php else: ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stats['worker_name']); ?></td>
                            <td><?php echo number_format((float)($stats['tokens_earned'] ?? 0), 8); ?></td>
                            <td><?php echo number_format((float)($stats['mintme'] ?? 0), 8); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <?php if ($status === 1): ?>
        <section class="table-container">
            <h3>🖥️ Your Mining Devices (Table View)</h3>
            <?php if (empty($deviceGroups['active']) && empty($deviceGroups['inactive'])): ?>
                <p>No devices found. Start mining to register your device.</p>
            <?php else: ?>
                <?php foreach (['active', 'inactive'] as $type): ?>
                    <?php if (!empty($deviceGroups[$type])): ?>
                        <h4><?php echo $type === 'active' ? '🟢 Active Devices' : '🔴 Inactive Devices'; ?></h4>
                        <table class="device-table <?php echo $type; ?>">
                            <thead>
                                <tr>
                                    <th>MAC Address</th>
                                    <th>Last Mine Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deviceGroups[$type] as $device): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($device['mac_address']); ?></td>
                                        <td><?php echo htmlspecialchars($device['last_mine_time'] ?? 'Never'); ?></td>
                                        <td><?php echo $device['active'] ? 'Active' : 'Inactive'; ?></td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this device?');" style="display:inline;">
                                                <input type="hidden" name="delete_mac" value="<?php echo htmlspecialchars($device['mac_address']); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <button type="submit" class="delete-btn">🗑️ Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="wallet-form">
            <h3>💳 Set Wallet Address</h3>
            <?php if (!empty($message)): ?>
                <div class="<?php echo (strpos($message, 'successfully') !== false) ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form method="post">
                <input type="text" name="wallet_address" value="<?php echo htmlspecialchars($current_address ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Your wallet address..." required aria-label="Wallet Address">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit">Save Wallet Address</button>
            </form>
        </section>

        <section class="links-grid">
            <a href="/w.php" target="_blank">💰 Withdraw WTK/WorkTHR from platform</a>
            <a href="/buy.php" target="_blank">💰 Deposit BNB on platform for WTK & WorkTHR WorkTokens</a>
            <a href="/miner/" target="_blank">⛏️ Mine WTK and WorkTHR WorkTokens Full Miner</a>
            <a href="https://github.com/ArakelTheDragon/CfCbazar-Tokens" target="_blank">📖 About the WorkToken</a>
            <a href="/pow/" target="_blank">⛏️ Proof of Work Mining Center</a>
            <a href="/help/" target="_blank">🆘 Help Center</a>
            <a href="/index.php">🏠 Back to Home</a>
            <a href="https://cfcbazar.42web.io">📝 Mine with our light miner</a>
        </section>

        <section class="logout-section">
            <a href="?logout=true" class="logout-btn">🚪 Logout</a>
        </section>
    <?php else: ?>
        <section class="login-prompt">
            <p>🔐 To manage your wallet and view worker stats, please <a href="/login.php">log in</a>.</p>
        </section>
    <?php endif; ?>

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "CfCbazar Worker Dashboard",
        "description": "Explore the WorkToken dashboard, trade tokens, and manage your wallet. Log in to track mining stats and platform credits.",
        "url": "https://cfcbazar.ct.ws/d.php",
        "publisher": {
            "@type": "Organization",
            "name": "CfCbazar",
            "logo": {
                "@type": "ImageObject",
                "url": "https://cfcbazar.ct.ws/images/cfcbazar-banner.jpg"
            }
        },
        "mainEntity": {
            "@type": "Person",
            "email": "<?php echo $is_logged_in ? htmlspecialchars($email) : 'guest'; ?>",
            "description": "Worker stats and wallet management for WorkToken mining on CfCbazar."
        }
    }
    </script>
</main>

<?php
include_footer();
if ($is_logged_in) {
    $conn->close();
}
?>
