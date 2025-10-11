<?php
// numbers.php – Safe version with visit tracking

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("config.php");

// ==== Visit Tracking ====
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
    $upd->bind_param('s', $path);
    $upd->execute();

    if ($upd->affected_rows === 0) {
        $slug  = ltrim($path, '/');
        $slug  = $slug === '' ? 'index' : $slug;
        $title = 'Numbers Directory';

        $ins = $conn->prepare("
            INSERT INTO pages (title, slug, path, visits, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");
        if ($ins) {
            $ins->bind_param('sss', $title, $slug, $path);
            $ins->execute();
            $ins->close();
        }
    }
    $upd->close();
}

// ==== DB Connection Check ====
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    die("Database connection failed.");
}

// ==== Logged-in email ====
$userEmail = $_SESSION['email'] ?? null;

// ==== Check if admin ====
$isAdmin = false;
if ($userEmail) {
    $stmt = $conn->prepare("SELECT status FROM users WHERE email = ?");
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

// ==== JSON file handling ====
$jsonFile = __DIR__ . "/numbers.json";

// Create file if missing
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}

// Read JSON safely
$data = json_decode(file_get_contents($jsonFile), true);
if (!is_array($data)) {
    $data = [];
}

// ==== CSRF token generation ====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// ==== Add new number (Admin only) ====
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['number'], $_POST['associated'], $_POST['csrf_token'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $data[] = [
            "number" => trim($_POST['number']),
            "associated_with" => trim($_POST['associated'])
        ];
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    } else {
        die("Invalid CSRF token.");
    }
}

// ==== Search ====
$search = trim($_GET['search'] ?? '');
$results = array_filter($data, function ($entry) use ($search) {
    return $search === '' ||
        stripos($entry['number'], $search) !== false ||
        stripos($entry['associated_with'], $search) !== false;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Numbers Directory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Search and explore phone numbers and their associations. Add entries if you're an admin." />
    <meta name="keywords" content="phone number lookup, directory, caller ID, CfCbazar, admin tools" />
    <meta name="robots" content="index, follow" />
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        h1, h2, h3 { text-align: center; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        th { background: #4CAF50; color: white; }
        input[type="text"] { padding: 8px; width: 250px; }
        button { padding: 8px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        .notice { background: #fff3cd; padding: 10px; border: 1px solid #ffeeba; margin-top: 20px; }
        form { margin-top: 20px; text-align: center; }
    </style>
</head>
<body>

<h1>Numbers Directory</h1>

<div class="notice">
    <h2>Notice!</h2>
    <h3>We are not responsible for any harm, damage, or consequences from this page and the numbers on it. Numbers can change over time!</h3>
</div>

<form method="get">
    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Search</button>
</form>

<?php if (count($results) > 0): ?>
    <table>
        <tr><th>Number</th><th>Associated With</th></tr>
        <?php foreach ($results as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['number']) ?></td>
            <td><?= htmlspecialchars($row['associated_with']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <p style="text-align:center; margin-top:20px;">No results found.</p>
<?php endif; ?>

<?php if ($isAdmin): ?>
    <h2>Add Number</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="text" name="number" placeholder="Number" required>
        <input type="text" name="associated" placeholder="Associated With" required>
        <button type="submit">Add</button>
    </form>
<?php endif; ?>

</body>
</html>