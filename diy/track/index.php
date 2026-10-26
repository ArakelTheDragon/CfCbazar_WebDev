<?php
// /diy/track/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';
// settings_local.php sets:
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN   = false;

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// ------------------------
// Tracking bootstrap & request processing
// ------------------------
track_bootstrap();

process_download();
process_admin_approval();

$created_tracking = process_create_tracking();

updateTrackingJson();

// ------------------------
// Page metadata
// ------------------------
$url        = "/diy/track/index.php";
$parent_url = "/diy/track";
$title      = "CfCbazar – Digital Product Tracking & Delivery";
$template   = "Generic";
$meta_title = "CfCbazar – Digital Product Tracking & Delivery";
$meta_desc  = "Track your digital purchases using your tracking number.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<main class="content">
    <div class="container">

        <section class="card">
            <h1>Digital Product Tracking</h1>
            <p>Track your digital purchases using your tracking number.</p>
        </section>

        <section class="card">
            <h2>Track Your Order</h2>

            <form method="get">
                <label for="track">Tracking Number</label>
                <input
                    type="text"
                    id="track"
                    name="track"
                    placeholder="1234123456"
                    value="<?= e($_GET['track'] ?? '') ?>"
                    required
                >
                <button type="submit">Track Order</button>
            </form>

            <?php if (!empty($_GET['track'])): ?>
                <?php $tracking = findTracking(trim($_GET['track'])); ?>

                <?php if (!$tracking): ?>
                    <div class="notice error">
                        Tracking number not found.
                    </div>
                <?php else: ?>
                    <hr>
                    <h3><?= e($tracking['product_name']) ?></h3>

                    <?php if (!empty($tracking['description'])): ?>
                        <p><?= nl2br(e($tracking['description'])) ?></p>
                    <?php endif; ?>

                    <p>
                        <strong>Status:</strong>
                        <?= getTrackingStatusLabel($tracking['status']) ?>
                    </p>

                    <?php if (!empty($tracking['delivered_at'])): ?>
                        <p>
                            <strong>Delivered At:</strong>
                            <?= e(date('M d, Y - h:i A', strtotime($tracking['delivered_at']))) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($tracking['email_downloader'])): ?>
                        <p>
                            <strong>Delivered To:</strong>
                            <?= e($tracking['email_downloader']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (canDownload($tracking)): ?>
                        <p>
                            <a
                                class="button"
                                href="<?= trackingDownloadUrl($tracking['tracking_number']) ?>"
                            >
                                Download Product
                            </a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Create Tracking Number</h2>

            <?php if (($status ?? 0) <= 0): ?>
                <p>You must be logged in to generate tracking numbers.</p>
                <p>
                    <a class="button" href="/login.php">Login</a>
                </p>
            <?php else: ?>
                <?php if (!empty($created_tracking)): ?>
                    <div class="notice success">
                        <strong>Tracking Number Created</strong><br><br>
                        <?= e($created_tracking) ?>
                    </div>
                <?php endif; ?>

                <p>
                    New tracking numbers are created with a <strong>Pending</strong> status until an administrator approves them.
                </p>

                <form method="post">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token'] ?? '') ?>"
                    >

                    <label for="product_name">Product Name</label>
                    <input
                        type="text"
                        id="product_name"
                        name="product_name"
                        required
                    >

                    <label for="description">Description (optional)</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                    ></textarea>

                    <label for="download_link">Download URL</label>
                    <input
                        type="url"
                        id="download_link"
                        name="download_link"
                        required
                    >

                    <label for="creator_email">Creator</label>
                    <input
                        type="email"
                        id="creator_email"
                        value="<?= e($email ?? '') ?>"
                        readonly
                    >

                    <button
                        type="submit"
                        name="create_tracking"
                        value="1"
                    >
                        Generate Tracking Number
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <?php if (($status ?? 0) === 1): ?>
            <section class="card">
                <h2>Admin Tracking Management</h2>

                <h3>Pending Approvals</h3>
                <?php $pending = getPendingTracking(); ?>

                <?php if (empty($pending)): ?>
                    <p>No pending tracking submissions.</p>
                <?php else: ?>
                    <?php foreach ($pending as $item): ?>
                        <div class="box card">
                            <strong><?= e($item['tracking_number']) ?></strong><br>
                            <?= e($item['product_name']) ?><br><br>
                            <a
                                class="button"
                                href="<?= trackingApproveUrl((int)$item['id']) ?>"
                            >
                                Approve
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <hr>

                <h3>All Tracking Entries</h3>
                <?php $allTracking = getAllTracking(100); ?>

                <?php if (empty($allTracking)): ?>
                    <p>No tracking entries found.</p>
                <?php else: ?>
                    <?php foreach ($allTracking as $item): ?>
                        <div class="box card">
                            <strong><?= e($item['tracking_number']) ?></strong><br>
                            <?= e($item['product_name']) ?><br>
                            Status: <?= e($item['status']) ?>
                            <?php if (!empty($item['delivered_at'])): ?>
                                <br><small>Delivered: <?= e(date('M d, Y - h:i A', strtotime($item['delivered_at']))) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </div>
</main>

<?php
// ------------------------
// Tracking shutdown & footer
// ------------------------
track_shutdown();

cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/track",
    "Track Source Code"
);
include_footer();
close_database();
?>
