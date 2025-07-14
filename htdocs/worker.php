<?php
require 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle token and wallet update
    $email = $_POST['email'] ?? '';
    $tokens = $_POST['tokens'] ?? '';
    $wallet = $_POST['wallet'] ?? '';

    if (!$email || !is_numeric($tokens)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }

    // Only update wallet if it was provided (non-empty)
    if (!empty($wallet)) {
        $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + ?, address = ? WHERE email = ?");
        $stmt->bind_param('dss', $tokens, $wallet, $email);
    } else {
        $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + ? WHERE email = ?");
        $stmt->bind_param('ds', $tokens, $email);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed']);
    }
    $stmt->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle token read only
    $email = $_GET['email'] ?? '';

    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Email required']);
        exit;
    }

    $stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email = ?");
    $stmt->bind_param('s', $email);

    if ($stmt->execute()) {
        $stmt->bind_result($tokens);
        if ($stmt->fetch()) {
            echo json_encode(['tokens_earned' => (float) $tokens]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Worker not found']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Read failed']);
    }
    $stmt->close();

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>