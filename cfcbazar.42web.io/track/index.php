<?php
require_once __DIR__ . '/../includes/reusable.php';

// Backend API
$API = "http://cfcbazar.atwebpages.com/track/json.php";

// --- HELPERS ---
function api_get($url) {
    $json = @file_get_contents($url);
    return $json ? json_decode($json, true) : null;
}

function api_post($url, $data) {
    $opts = [
        "http" => [
            "method"  => "POST",
            "header"  => "Content-Type: application/x-www-form-urlencoded",
            "content" => http_build_query($data)
        ]
    ];
    $ctx = stream_context_create($opts);
    $json = @file_get_contents($url, false, $ctx);
    return $json ? json_decode($json, true) : null;
}

// Status: 0 = guest, 1 = admin, 2–5 = logged-in users
$status = getUserStatus($conn);

// LOGIN REDIRECT
if (isset($_GET['need_login'])) {
    setReturnUrlCookie('/track/index.php');
    header('Location: /login.php');
    exit;
}

/* =====================================================
   DOWNLOAD HANDLER
   ===================================================== */
if (isset($_GET['download'])) {
    $track = trim($_GET['download']);

    // STEP 1: Show email form
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        include_header();
        include_menu();
        ?>
        <main class="container">
            <h3>Enter your email to download</h3>
            <form method="post" action="/track/index.php?download=<?= htmlspecialchars($track) ?>">
                <label>Your email:</label>
                <input type="email" name="email_downloader" required>

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <button type="submit">Continue to download</button>
            </form>
        </main>
        <?php
        include_footer();
        exit;
    }

    // STEP 2: CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF token");
    }

    $email = trim($_POST['email_downloader'] ?? '');
    if ($email === '') die("Email required.");

    // STEP 3: Ask backend API
    $api = api_get("$API?download=$track&email=" . urlencode($email));

    if (!$api || empty($api['download_link'])) {
        die("Tracking not found or not approved yet.");
    }

    // STEP 4: Mirror update to local SQL
    $stmt = $conn->prepare("
        UPDATE tracking SET email_downloader=?, status='delivered'
        WHERE tracking_number=?
    ");
    $stmt->bind_param("ss", $email, $track);
    $stmt->execute();
    $stmt->close();

    // STEP 5: Redirect to file
    header("Location: " . $api['download_link']);
    exit;
}

/* =====================================================
   ADMIN APPROVAL
   ===================================================== */
if ($status === 1 && isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];

    // Update backend
    api_get("$API?approve=$id");

    // Mirror to local SQL
    $stmt = $conn->prepare("UPDATE tracking SET status='in_transit' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: /track/index.php");
    exit;
}

/* =====================================================
   CREATE TRACKING
   ===================================================== */
$created_tracking = null;
if ($status > 0 && isset($_POST['create_tracking'])) {
    $data = [
        "product_name"  => trim($_POST['product_name']),
        "description"   => trim($_POST['description']),
        "download_link" => trim($_POST['download_link']),
        "creator_email" => trim($_POST['creator_email'])
    ];

    // Create on backend
    $api = api_post($API, $data);

    if ($api && !empty($api['tracking_number'])) {
        $created_tracking = $api['tracking_number'];

        // Mirror to local SQL
        $stmt = $conn->prepare("
            INSERT INTO tracking (tracking_number, product_name, description, download_link, status, created_by)
            VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->bind_param("sssss", $created_tracking, $data['product_name'], $data['description'], $data['download_link'], $data['creator_email']);
        $stmt->execute();
        $stmt->close();
    }
}

/* =====================================================
   PAGE HEADER
   ===================================================== */
$title = "CfCbazar – Digital Product Tracking & Delivery";
include_header();
include_menu();
?>

<main class="container">
    <h2>Digital Product Tracking</h2>
    <p>Track and download your digital purchases using your tracking number.</p>

    <!-- PUBLIC SEARCH FORM -->
    <section>
        <h3>Track your order</h3>
        <form method="get" action="/track/index.php">
            <label for="track">Tracking number:</label>
            <input type="text" id="track" name="track" required>
            <button type="submit">Track</button>
        </form>

        <?php
        if (!empty($_GET['track'])) {
            $track = trim($_GET['track']);
            $api = api_get("$API?go=$track");

            if (!$api || ($api['status'] ?? '') === 'not_found') {
                echo "<p><strong>Status:</strong> Not found.</p>";
            } else {
                echo "<h4>" . htmlspecialchars($api['product_name']) . "</h4>";

                if (!empty($api['description'])) {
                    echo "<p>" . nl2br(htmlspecialchars($api['description'])) . "</p>";
                }

                // Mirror backend data to local SQL
                $stmt = $conn->prepare("
                    INSERT INTO tracking (id, tracking_number, product_name, description, download_link, status, created_by, created_at, email_downloader)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        product_name=VALUES(product_name),
                        description=VALUES(description),
                        download_link=VALUES(download_link),
                        status=VALUES(status),
                        created_by=VALUES(created_by),
                        created_at=VALUES(created_at),
                        email_downloader=VALUES(email_downloader)
                ");
                $stmt->bind_param(
                    "issssssss",
                    $api['id'],
                    $api['tracking_number'],
                    $api['product_name'],
                    $api['description'],
                    $api['download_link'],
                    $api['status'],
                    $api['created_by'],
                    $api['created_at'],
                    $api['email_downloader']
                );
                $stmt->execute();
                $stmt->close();

                if ($api['status'] === 'pending') {
                    echo "<p><strong>Status:</strong> Waiting for admin approval.</p>";
                } else {
                    $label = ($api['status'] === 'in_transit') ? 'In transit' : 'Delivered';
                    echo "<p><strong>Status:</strong> " . htmlspecialchars($label) . ".</p>";
                    echo '<p><a href="/track/index.php?download=' . htmlspecialchars($api['tracking_number']) . '">Download product</a></p>';
                }
            }
        }
        ?>
    </section>

    <hr>

    <!-- CREATION / ADMIN AREA -->
    <br>
    <section>
        <i><h3>Gen a new number & Admin area</h3></i>

        <?php if ($status === 0): ?>
            <p>You must be logged in to create tracking numbers.</p>
            <p><a href="/track/index.php?need_login=1">Login to your CfCbazar account</a></p>

        <?php else: ?>

            <h4>Make a new tracking number</h4>

            <?php if ($created_tracking): ?>
                <p><strong>Tracking created:</strong> <?= htmlspecialchars($created_tracking) ?></p>
            <?php endif; ?>

            <form method="post" action="/track/index.php">
                <label>Product name:</label>
                <input type="text" name="product_name" required>

                <label>Description (optional):</label>
                <textarea name="description" rows="3"></textarea><br>

                <label>Download URL:</label>
                <input type="url" name="download_link" required>

                <label>Your email:</label>
                <input type="email" name="creator_email"
                       value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>"
                       required readonly>

                <button type="submit" name="create_tracking" value="1">Generate tracking</button>
            </form>

            <br>

            <?php if ($status === 1): ?>
                <hr>
                <h4>Pending approvals (Admin)</h4>
                <?php
                $pending = api_get("$API?list=pending");
                if ($pending && count($pending) > 0):
                    foreach ($pending as $row):
                ?>
                        <div class="box">
                            <strong><?= htmlspecialchars($row['tracking_number']) ?></strong> –
                            <?= htmlspecialchars($row['product_name']) ?>
                            <a href="/track/index.php?approve=<?= (int)$row['id'] ?>">Approve</a>
                        </div>
                <?php
                    endforeach;
                else:
                    echo "<p>No pending submissions.</p>";
                endif;
                ?>

                <hr>
                <h4>All tracking entries</h4>
                <?php
                $all = api_get("$API?list=all");
                if ($all && count($all) > 0):
                    foreach ($all as $row):
                ?>
                        <div class="box">
                            <strong><?= htmlspecialchars($row['tracking_number']) ?></strong> –
                            <?= htmlspecialchars($row['product_name']) ?> –
                            Status: <?= htmlspecialchars($row['status']) ?>
                        </div>
                <?php
                    endforeach;
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

