<?php
// d.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';
require 'includes/reusable.php';
require 'includes/reusable2.php'; // includes renderWorkTokenDashboard()

if (isset($_GET['logout'])) {
    session_destroy();
    unset($_SESSION['email']);
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit();
}

$email = $_SESSION['email'];

// Trigger token/device sync from remote API
require 'testapi.php';

// Wallet address logic
$current_address = "";
$res_addr = $conn->prepare("SELECT address FROM workers WHERE email = ? LIMIT 1");
if ($res_addr) {
    $res_addr->bind_param("s", $email);
    $res_addr->execute();
    $res_addr->bind_result($current_address);
    $res_addr->fetch();
    $res_addr->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["wallet_address"])) {
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

// Worker stats
$query = "SELECT id, worker_name, tokens_earned, mintme FROM workers WHERE email = ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    error_log("Worker stats prepare failed: " . $conn->error);
}

// Devices
$deviceGroups = fetch_local_devices($email);

$title = 'CfCbazar Worker Dashboard - WorkToken & Mining';
include_header();
?>
<meta name="description" content="Manage your WorkToken mining and wallet on the CfCbazar Worker Dashboard. Track worker stats, set wallet addresses, and explore platform credits.">
<?php include_menu(); ?>

<main class="container dashboard-container" style="padding-top: 80px;">
    <section class="hero-section">
        <h2>💼 Welcome to Your Worker Dashboard, <?php echo htmlspecialchars($email); ?>!</h2>
    </section>

    <section class="table-container">
        <h3>📊 Worker Stats</h3>
        <table class="worker-stats-table" role="grid" aria-label="Worker Statistics">
            <thead>
                <tr>
                    <th>Worker Name</th>
                    <th>dWorkTokens</th>
                    <th>WorkTHR</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr><td colspan="3">No workers found for this account. Visit <a href="/index.php">Home</a> to get started.</td></tr>
                <?php else: ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['worker_name']); ?></td>
                        <td><?php echo number_format((float)($row['tokens_earned'] ?? 0), 4); ?></td>
                        <td><?php echo number_format($row['mintme'], 4); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="wallet-form">
        <h3>💳 Set Wallet Address</h3>
        <?php if (isset($message)): ?>
            <div class="message <?php echo (strpos($message, 'successfully') !== false) ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="text" name="wallet_address" value="<?php echo htmlspecialchars($current_address); ?>" placeholder="Your wallet address..." required aria-label="Wallet Address">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <button type="submit">Save Wallet Address</button>
        </form>
    </section>

    <section class="links-grid">
        <a href="/w.php" target="_blank">💰 Withdraw Platform Credit as WTK</a>
        <a href="/buy.php" target="_blank">💰 Deposit WTK/BNB for Platform Credit</a>
        <a href="/pow" target="_blank">⛏️ Mine Platform Credit</a>
        <a href="https://github.com/ArakelTheDragon/CfCbazar-Tokens" target="_blank">📖 About WorkToken</a>
        <a href="/help/" target="_blank">🆘 Help Center</a>
        <a href="/index.php">🏠 Back to Home</a>
        <a href="/register.php">📝 Register for More Features</a>
    </section>

    <section class="market-dashboard">
        <h3>📈 WorkToken Dashboard</h3>
        <?php renderWorkTokenDashboard(); ?>
        <div class="cta">
            <a href="https://cc.free.bg/workth/">📊 View WTKS Detailed Stats</a>
        </div>
    </section>

    <section class="table-container">
        <h3>🖥️ Your Devices</h3>
        <?php
        if (empty($deviceGroups['active']) && empty($deviceGroups['inactive'])) {
            echo "<div class='error' style='text-align: center; padding: 15px;'>⚠️ Unable to fetch device data. Please try again later or visit <a href='/help/'>Help Center</a>.</div>";
        } else {
            render_device_table($deviceGroups['active'], 'active');
            render_device_table($deviceGroups['inactive'], 'inactive');
        }
        ?>
    </section>

    <section style="text-align: center; margin: 40px 0;">
        <a href="?logout=true" class="logout-btn">🚪 Logout</a>
    </section>

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "CfCbazar Worker Dashboard",
        "description": "Manage your WorkToken mining and wallet on the CfCbazar Worker Dashboard. Track worker stats, set wallet addresses, and explore platform credits.",
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
            "email": "<?php echo htmlspecialchars($email); ?>",
            "description": "Worker stats and wallet management for WorkToken mining on CfCbazar."
        }
    }
    </script>

    <script>
        const justPosted = <?php echo ($_SERVER["REQUEST_METHOD"] === "POST") ? 'true' : 'false'; ?>;
        if (!justPosted) {
            setInterval(() => window.location.reload(), 50000);
        }
    </script>
</main>

<?php
include_footer();
$conn->close();
?>