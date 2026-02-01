<?php
session_start();

// Basic CSRF token for header meta
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// --- DB CONNECTION (adjust credentials) -- 
require_once __DIR__ . '/../includes/reusable.php';

// --- HELPERS ---
function generateTrackingNumber(): string {
    return 'CFC-' . rand(100000, 999999);
}

// Status: 0 = guest, 1 = admin, 2–5 = logged-in users
$status = getUserStatus($conn);

// Handle login redirect trigger
if (isset($_GET['need_login'])) {
    setReturnUrlCookie('/track/index.php');
    header('Location: /login.php');
    exit;
}

// --- DOWNLOAD HANDLER ---
if (isset($_GET['download'])) {
    $track = $_GET['download'];

    $stmt = $conn->prepare("SELECT id, download_link, status FROM tracking WHERE tracking_number = ? AND status <> 'pending' LIMIT 1");
    $stmt->bind_param('s', $track);
    $stmt->execute();
    $stmt->bind_result($id, $download_link, $current_status);
    $found = $stmt->fetch();
    $stmt->close();

    if ($found) {
        if ($current_status !== 'delivered') {
            $up = $conn->prepare("UPDATE tracking SET status = 'delivered' WHERE id = ?");
            $up->bind_param('i', $id);
            $up->execute();
            $up->close();
        }
        header("Location: " . $download_link);
        exit;
    }
}

// --- ADMIN APPROVAL (status 1 only) ---
if ($status === 1 && isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $conn->prepare("UPDATE tracking SET status = 'in_transit' WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header("Location: /track/index.php");
    exit;
}

// --- CREATE TRACKING (any logged-in user: status 1–5) ---
$created_tracking = null;
if ($status > 0 && isset($_POST['create_tracking'])) {
    $product_name  = trim($_POST['product_name'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $download_link = trim($_POST['download_link'] ?? '');

    if ($product_name !== '' && $download_link !== '') {
        $tracking = generateTrackingNumber();

        $stmt = $conn->prepare("
            INSERT INTO tracking (tracking_number, product_name, description, download_link, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param('ssss', $tracking, $product_name, $description, $download_link);
        $stmt->execute();
        $stmt->close();

        $created_tracking = $tracking;
    }
}

// --- SEO + HEADER ---
$title = "CfCbazar – Digital Product Tracking & Delivery";
include_header();
include_menu();
?>

<main class="container">
    <h2>Digital Product Tracking</h2>
    <p>Track and download your digital purchases from CfCbazar using your CFC tracking number.</p>

    <!-- PUBLIC SEARCH FORM -->
    <section>
        <h3>Track your order</h3>
        <form method="get" action="/track/index.php">
            <label for="track">Tracking number (format CFC-123456):</label>
            <input type="text" id="track" name="track" required>
            <button type="submit">Track</button>
        </form>

        <?php
        if (!empty($_GET['track'])) {
            $track = trim($_GET['track']);

            $stmt = $conn->prepare("SELECT tracking_number, product_name, description, status FROM tracking WHERE tracking_number = ? LIMIT 1");
            $stmt->bind_param('s', $track);
            $stmt->execute();
            $stmt->bind_result($t_num, $p_name, $desc, $t_status);
            $found = $stmt->fetch();
            $stmt->close();

            if (!$found) {
                echo "<p><strong>Status:</strong> Not found.</p>";
            } else {
                echo "<h4>" . htmlspecialchars($p_name) . "</h4>";
                if ($desc !== '') {
                    echo "<p>" . nl2br(htmlspecialchars($desc)) . "</p>";
                }

                if ($t_status === 'pending') {
                    echo "<p><strong>Status:</strong> Waiting for admin approval.</p>";
                } else {
                    $label = ($t_status === 'in_transit') ? 'In transit' : 'Delivered';
                    echo "<p><strong>Status:</strong> " . htmlspecialchars($label) . ".</p>";
                    echo "<p><a href=\"/track/index.php?download=" . urlencode($t_num) . "\">Download product</a></p>";
                }
            }
        }
        ?>
    </section>

    <hr>

    <!-- CREATION / ADMIN AREA -->
    <section>
        <h3>Creator & Admin area</h3>

        <?php if ($status === 0): ?>
            <p>You must be logged in to create tracking numbers.</p>
            <p><a href="/track/index.php?need_login=1">Login to your CfCbazar account</a></p>
        <?php else: ?>

            <h4>Create new tracking number</h4>
            <p>All new entries start as <strong>pending</strong> and must be approved by an admin before users can download.</p>

            <?php if ($created_tracking): ?>
                <p><strong>Tracking created:</strong> <?= htmlspecialchars($created_tracking) ?></p>
            <?php endif; ?>

            <form method="post" action="/track/index.php">
                <label for="product_name">Product name:</label>
                <input type="text" id="product_name" name="product_name" required>

                <label for="description">Short description (optional):</label>
                <textarea id="description" name="description" rows="3"></textarea>

                <label for="download_link">Download URL:</label>
                <input type="url" id="download_link" name="download_link" required>

                <button type="submit" name="create_tracking" value="1">Create tracking</button>
            </form>

            <?php if ($status === 1): ?>
                <hr>
                <h4>Pending approvals (Admin)</h4>
                <?php
                $res = $conn->query("SELECT id, tracking_number, product_name FROM tracking WHERE status = 'pending' ORDER BY id DESC");
                if ($res && $res->num_rows > 0):
                    while ($row = $res->fetch_assoc()):
                ?>
                        <div class="box">
                            <strong><?= htmlspecialchars($row['tracking_number']) ?></strong> –
                            <?= htmlspecialchars($row['product_name']) ?>
                            <a href="/track/index.php?approve=<?= (int)$row['id'] ?>">Approve</a>
                        </div>
                <?php
                    endwhile;
                else:
                    echo "<p>No pending submissions.</p>";
                endif;
                ?>

                <hr>
                <h4>All tracking entries</h4>
                <?php
                $resAll = $conn->query("SELECT tracking_number, product_name, status FROM tracking ORDER BY id DESC LIMIT 100");
                if ($resAll && $resAll->num_rows > 0):
                    while ($row = $resAll->fetch_assoc()):
                ?>
                        <div class="box">
                            <strong><?= htmlspecialchars($row['tracking_number']) ?></strong> –
                            <?= htmlspecialchars($row['product_name']) ?> –
                            Status: <?= htmlspecialchars($row['status']) ?>
                        </div>
                <?php
                    endwhile;
                else:
                    echo "<p>No tracking entries yet.</p>";
                endif;
                ?>
            <?php endif; ?>

        <?php endif; ?>
    </section>
</main>

<?php
include_footer();
$conn->close();
?>