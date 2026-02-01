<?php
header('Content-Type: application/json');

// Load DB connection + helpers
require_once __DIR__ . '/../includes/reusable.php';

// Ensure $conn exists
global $conn;
if (!$conn || $conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Validate input
if (!isset($_GET['go']) || trim($_GET['go']) === '') {
    echo json_encode(['error' => 'Missing tracking number']);
    exit;
}

$track = trim($_GET['go']);

// Query tracking
$stmt = $conn->prepare("
    SELECT tracking_number, product_name, description, status, download_link
    FROM tracking
    WHERE tracking_number = ?
    LIMIT 1
");
$stmt->bind_param('s', $track);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    echo json_encode([
        'tracking_number' => $track,
        'status' => 'not_found'
    ]);
    exit;
}

// Output JSON
echo json_encode([
    'tracking_number' => $data['tracking_number'],
    'product_name'    => $data['product_name'],
    'description'     => $data['description'],
    'status'          => $data['status'],        // pending, in_transit, delivered
    'download_link'   => $data['download_link']  // optional
]);