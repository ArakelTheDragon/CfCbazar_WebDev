<?php
session_start(); // Always first

require 'config.php';
header('Content-Type: application/json');

// Validate email format
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $email = $_POST['email'] ?? ($_SESSION['email'] ?? '');
    $tokens = $_POST['tokens'] ?? $_POST['tokens_earned'] ?? null;
    $wallet = $_POST['wallet'] ?? '';

    if (!is_valid_email($email) || !is_numeric($tokens)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email or token value']);
        exit;
    }

    $tokens = floatval($tokens);

    if (!empty($wallet)) {
        $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + ?, address = ? WHERE email = ?");
        $stmt->bind_param('dss', $tokens, $wallet, $email);
    } else {
        $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + ? WHERE email = ?");
        $stmt->bind_param('ds', $tokens, $email);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'email' => $email,
            'tokens_delta' => $tokens,
            'wallet_updated' => !empty($wallet),
            'rows_affected' => $stmt->affected_rows,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database update failed']);
    }

    $stmt->close();

} elseif ($method === 'GET') {
    $email = $_GET['email'] ?? ($_SESSION['email'] ?? '');

    if (!is_valid_email($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing email']);
        exit;
    }

    $stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($tokens);

    if ($stmt->fetch()) {
        echo json_encode([
            'success' => true,
            'email' => $email,
            'tokens_earned' => (float) $tokens
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Worker not found']);
    }

    $stmt->close();

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>