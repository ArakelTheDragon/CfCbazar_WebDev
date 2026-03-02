<?php
header("Content-Type: application/json");

// Load DB connection + helpers
require_once __DIR__ . '/../includes/reusable.php';

// Ensure DB connection exists
global $conn;
if (!$conn || $conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

function clean($v) { return trim($v ?? ""); }
function respond($data) {
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/* =====================================================
   1) LOOKUP: json.php?go=TRACKNUMBER
   ===================================================== */
if (isset($_GET['go'])) {
    $track = clean($_GET['go']);

    $stmt = $conn->prepare("
        SELECT id, tracking_number, product_name, description, download_link,
               status, created_by, created_at, email_downloader
        FROM tracking
        WHERE tracking_number = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $track);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) {
        respond([
            "tracking_number" => $track,
            "status" => "not_found"
        ]);
    }

    respond($res);
}

/* =====================================================
   2) DOWNLOAD + EMAIL CAPTURE
      json.php?download=TRACK&email=EMAIL
   ===================================================== */
if (isset($_GET['download']) && isset($_GET['email'])) {
    $track = clean($_GET['download']);
    $email = clean($_GET['email']);

    $stmt = $conn->prepare("
        SELECT id, download_link, status
        FROM tracking
        WHERE tracking_number = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $track);
    $stmt->execute();
    $stmt->bind_result($id, $link, $status);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) respond(["error" => "Not found"]);
    if ($status === "pending") respond(["error" => "Not approved"]);

    // Save email + mark delivered
    $stmt = $conn->prepare("
        UPDATE tracking SET email_downloader = ?, status = 'delivered'
        WHERE id = ?
    ");
    $stmt->bind_param("si", $email, $id);
    $stmt->execute();
    $stmt->close();

    respond(["download_link" => $link]);
}

/* =====================================================
   3) CREATE TRACKING (POST)
   ===================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $product_name  = clean($_POST['product_name']);
    $description   = clean($_POST['description']);
    $download_link = clean($_POST['download_link']);
    $creator_email = clean($_POST['creator_email']);

    if ($product_name === "" || $download_link === "") {
        respond(["error" => "Missing fields"]);
    }

    // Generate tracking number
    $tracking = "CFC-" . rand(100000, 999999);

    $stmt = $conn->prepare("
        INSERT INTO tracking (tracking_number, product_name, description,
                              download_link, status, created_by)
        VALUES (?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->bind_param("sssss", $tracking, $product_name, $description, $download_link, $creator_email);
    $stmt->execute();
    $stmt->close();

    respond(["tracking_number" => $tracking]);
}

/* =====================================================
   4) ADMIN APPROVAL
      json.php?approve=ID
   ===================================================== */
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];

    $stmt = $conn->prepare("
        UPDATE tracking SET status='in_transit'
        WHERE id=? AND status='pending'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    respond(["approved" => $id]);
}

/* =====================================================
   5) LIST PENDING
      json.php?list=pending
   ===================================================== */
if (isset($_GET['list']) && $_GET['list'] === "pending") {
    $rows = [];
    $res = $conn->query("
        SELECT id, tracking_number, product_name, status
        FROM tracking
        WHERE status='pending'
        ORDER BY id DESC
    ");
    while ($row = $res->fetch_assoc()) $rows[] = $row;

    respond($rows);
}

/* =====================================================
   6) LIST ALL
      json.php?list=all
   ===================================================== */
if (isset($_GET['list']) && $_GET['list'] === "all") {
    $rows = [];
    $res = $conn->query("
        SELECT id, tracking_number, product_name, status
        FROM tracking
        ORDER BY id DESC
        LIMIT 200
    ");
    while ($row = $res->fetch_assoc()) $rows[] = $row;

    respond($rows);
}

/* =====================================================
   DEFAULT
   ===================================================== */
respond(["error" => "Invalid request"]);
?>

