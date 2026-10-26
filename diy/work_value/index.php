<?php
// /diy/work-value/index.php
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
// Flash message (PRG pattern)
// ------------------------
$message = '';
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

// ------------------------
// Handle form submission
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = trim($_POST['title']  ?? '');
    $type   = $_POST['type']        ?? '';
    $region = trim($_POST['region'] ?? '');
    $rate   = (float)($_POST['hourly_usd'] ?? 0);
    $hours  = (int)($_POST['hours']        ?? 0);

    if (
        $title !== '' &&
        $region !== '' &&
        in_array($type, ['profession', 'product', 'service'], true) &&
        $rate  > 0 &&
        $hours > 0
    ) {
        $total_usd = $rate * $hours;

        $ins = $conn->prepare("
            INSERT INTO work_value
              (title, type, region,
               hourly_usd, hours, total_usd,
               status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        if ($ins) {
            $ins->bind_param("sssdid", $title, $type, $region, $rate, $hours, $total_usd);
            if ($ins->execute()) {
                $_SESSION['form_message'] = "✅ Submission sent successfully and is awaiting admin approval.";
            } else {
                $_SESSION['form_message'] = "❌ Failed to submit. Try again.";
                error_log("work_value INSERT failed: " . $ins->error);
            }
            $ins->close();
        } else {
            $_SESSION['form_message'] = "❌ Database prepare failed.";
            error_log("work_value INSERT prepare failed: " . $conn->error);
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['form_message'] = "⚠️ Please complete all fields with valid values.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ------------------------
// Load approved entries
// ------------------------
$query = $conn->query("
    SELECT *
      FROM work_value
     WHERE status = 'approved'
  ORDER BY type, title
");
if (!$query) {
    die("Database query failed: " . $conn->error);
}

// ------------------------
// Page metadata
// ------------------------
$url        = "/diy/work-value/index.php";
$parent_url = "/diy/work-value";
$title      = "Work Value Directory | CfCbazar";
$template   = "Generic";
$meta_title = "💼 Work Value Directory | CfCbazar";
$meta_desc  = "Submit and explore real-world work values in USD. CfCbazar's Work Value Directory helps estimate fair compensation for professions, products, and services.";

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

        <h1>Work Value Table</h1>

        <table style="width:100%;border-collapse:collapse;max-width:1000px;margin:0 auto 0;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
            <caption style="margin-bottom:15px;font-size:1.1em;font-weight:500;color:#4a6587;">Approved Professions, Products &amp; Services NET $</caption>
            <thead>
                <tr>
                    <th style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;background:#2a405f;color:#fff;font-weight:600;">Title</th>
                    <th style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;background:#2a405f;color:#fff;font-weight:600;">Type</th>
                    <th style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;background:#2a405f;color:#fff;font-weight:600;">Region</th>
                    <th style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;background:#2a405f;color:#fff;font-weight:600;">Hourly Rate (USD)</th>
                    <th style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;background:#2a405f;color:#fff;font-weight:600;">Hours</th>
                    <th style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;background:#2a405f;color:#fff;font-weight:600;">Total Value (USD)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $query->fetch_assoc()): ?>
                    <tr>
                        <td style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;"><?= htmlspecialchars($row['title']) ?></td>
                        <td style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;"><?= ucfirst($row['type']) ?></td>
                        <td style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;"><?= htmlspecialchars($row['region']) ?></td>
                        <td style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;">$<?= number_format((float)$row['hourly_usd'], 2) ?></td>
                        <td style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;"><?= (int)$row['hours'] ?></td>
                        <td style="padding:14px 12px;border-bottom:1px solid #ddd;text-align:left;color:#008060;font-weight:bold;">$<?= number_format((float)$row['total_usd'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <h1>Submit a Work Value</h1>

        <div class="container" style="max-width:600px;margin:auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
            <form method="POST">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" required />

                <label for="type">Type</label>
                <select name="type" id="type" required>
                    <option value="profession">Profession</option>
                    <option value="product">Product</option>
                    <option value="service">Service</option>
                </select>

                <label for="region">Region</label>
                <input type="text" name="region" id="region" required />

                <label for="hourly_usd">Hourly Rate (USD)</label>
                <input type="number" step="0.01" name="hourly_usd" id="hourly_usd" required />

                <label for="hours">Total Estimated Hours</label>
                <input type="number" name="hours" id="hours" required />

                <button type="submit">Submit for Approval</button>
            </form>

            <?php if ($message !== ''): ?>
                <div class="message" style="margin-top:20px;font-size:14px;color:#555;text-align:center;"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
        </div>

        <div class="footer" style="text-align:center;margin-top:50px;font-size:13px;color:#666;">
            By using this site, you agree to our
            <a href="/t.php" style="color:#2a405f;text-decoration:underline;margin:0 4px;">Terms &amp; Conditions</a>,
            <a href="/t.php" style="color:#2a405f;text-decoration:underline;margin:0 4px;">Privacy Policy</a>, and
            <a href="/c.php" style="color:#2a405f;text-decoration:underline;margin:0 4px;">Cookie Policy</a>.
        </div>

    </div>
</main>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/work-value",
    "Work Value Directory Source Code"
);
include_footer();
close_database();
?>
