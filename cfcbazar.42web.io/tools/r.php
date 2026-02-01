<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- DB connection ---
$servername = "sql313.infinityfree.com";
$username   = "if0_39103611";
$password   = "53098516";
$dbname     = "if0_39103611_db1";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

// --- Handle redirect ---
$path = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path !== 'index.php' && ctype_digit($path)) {
    $stmt = $conn->prepare("SELECT `long` FROM short_links WHERE id = ?");
    $stmt->bind_param("i", $path);
    $stmt->execute();
    $stmt->bind_result($longUrl);
    if ($stmt->fetch()) {
        header("Location: $longUrl");
        exit;
    } else {
        http_response_code(404);
        echo "Short link not found.";
        exit;
    }
}

// --- Handle POST to shorten URL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['url'] ?? '');
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo "Invalid URL.";
        exit;
    }

    // Check if already exists
    $stmt = $conn->prepare("SELECT id FROM short_links WHERE `long` = ?");
    $stmt->bind_param("s", $url);
    $stmt->execute();
    $stmt->bind_result($existingId);
    if ($stmt->fetch()) {
        echo "Short link: https://{$_SERVER['HTTP_HOST']}/tools/$existingId";
        exit;
    }
    $stmt->close();

    // Insert new
    $stmt = $conn->prepare("INSERT INTO short_links (`long`) VALUES (?)");
    $stmt->bind_param("s", $url);
    $stmt->execute();
    $newId = $stmt->insert_id;

    echo "Short link: https://{$_SERVER['HTTP_HOST']}/tools/$newId";
    exit;
}
?>

<!-- Minimal HTML UI -->
<form method="POST">
    <input type="text" name="url" placeholder="Paste a long URL" required>
    <button type="submit">Shorten</button>
</form>