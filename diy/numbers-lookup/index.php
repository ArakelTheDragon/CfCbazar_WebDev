<?php
// /diy/numbers/index.php
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

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/numbers/index.php";
$parent_url = "/diy/numbers";
$title      = "Numbers Directory";
$template   = "Generic";
$meta_title = "Numbers Directory";
$meta_desc  = "Search and explore phone numbers and their associations.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

// --- Logged-in user & admin check -------------------------------------------
$userEmail = $_SESSION['email'] ?? null;
$isAdmin   = false;

if ($userEmail && isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("SELECT status FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ((int)$row['status'] === 1) {
                $isAdmin = true;
            }
        }
        $stmt->close();
    }
}

// --- JSON data --------------------------------------------------------------
$jsonFile = __DIR__ . '/numbers.json';

if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}

$data = json_decode(file_get_contents($jsonFile), true);
if (!is_array($data)) {
    $data = [];
}

// --- CSRF -------------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// --- Add new number (Admin only) --------------------------------------------
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['number'], $_POST['associated'], $_POST['csrf_token'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $data[] = [
            'number'          => trim($_POST['number']),
            'associated_with' => trim($_POST['associated']),
        ];
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    die("Invalid CSRF token.");
}

// --- Search -----------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$results = array_filter($data, function ($entry) use ($search) {
    return $search === ''
        || stripos($entry['number'], $search) !== false
        || stripos($entry['associated_with'], $search) !== false;
});

render_header($title);
?>

<main class="content">
    <div class="container">

        <h1>Numbers Directory</h1>

        <div class="card" style="background:#fff3cd;border:1px solid #ffeeba;">
            <h2>Notice!</h2>
            <h3>We are not responsible for any harm, damage, or consequences from this page and the numbers on it. Numbers can change over time!</h3>
        </div>

        <div class="card">
            <form method="get" style="text-align:center;">
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="card">
            <?php if (count($results) > 0): ?>
                <table style="width:100%;border-collapse:collapse;background:#fff;">
                    <tr>
                        <th style="padding:10px;border:1px solid #ccc;text-align:left;background:#4CAF50;color:#fff;">Number</th>
                        <th style="padding:10px;border:1px solid #ccc;text-align:left;background:#4CAF50;color:#fff;">Associated With</th>
                    </tr>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td style="padding:10px;border:1px solid #ccc;text-align:left;"><?= htmlspecialchars($row['number']) ?></td>
                            <td style="padding:10px;border:1px solid #ccc;text-align:left;"><?= htmlspecialchars($row['associated_with']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p style="text-align:center; margin-top:20px;">No results found.</p>
            <?php endif; ?>
        </div>

        <?php if ($isAdmin): ?>
            <div class="card">
                <h2>Add Number</h2>
                <form method="post" style="text-align:center;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="text" name="number" placeholder="Number" required>
                    <input type="text" name="associated" placeholder="Associated With" required>
                    <button type="submit">Add</button>
                </form>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/numbers",
    "Numbers Directory Source Code"
);
include_footer();
close_database();
?>
