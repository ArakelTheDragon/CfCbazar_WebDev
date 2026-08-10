<?php
// diy/track/update.php — Receiver on atwebpages.com

// 1. Define your Secret Key (Change this to a strong random string)
define('API_SECRET_KEY', 'CfCbazar_Secure_Track_Key_2026_X9z');

header('Content-Type: application/json');

// 2. Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 405, 'error' => 'Method Not Allowed']);
    exit;
}

// 3. Verify API Secret Key from HTTP Header
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (!hash_equals(API_SECRET_KEY, $providedKey)) {
    http_response_code(403);
    echo json_encode(['status' => 403, 'error' => 'Unauthorized: Invalid API Key']);
    exit;
}

// 4. Retrieve Raw JSON Body
$jsonPayload = file_get_contents('php://input');

if (empty($jsonPayload)) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'error' => 'Empty payload received']);
    exit;
}

// Ensure the payload is valid JSON before saving
$decoded = json_decode($jsonPayload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'error' => 'Invalid JSON payload']);
    exit;
}

// 5. Write to local index.json
$targetFile = __DIR__ . '/index.json';

if (file_put_contents($targetFile, $jsonPayload, LOCK_EX) !== false) {
    echo json_encode([
        'status'    => 200,
        'message'   => 'index.json updated successfully on atwebpages.com',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 500, 'error' => 'Failed to write index.json']);
}
