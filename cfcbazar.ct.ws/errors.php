<?php
// errors.php — Display PHP errors and log JavaScript errors
if (session_status() === PHP_SESSION_NONE) session_start();

// Handle JavaScript error logging via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    // Log error
    $input = file_get_contents('php://input');
    if ($input) {
        $error_data = json_decode($input, true);
        $error_message = $error_data['error'] ?? 'Unknown error';
        $user_email = $_SESSION['email'] ?? 'anonymous';
        $log_entry = date('Y-m-d H:i:s') . " [$user_email] " . $error_message . "\n";
        file_put_contents(__DIR__ . '/js_errors.log', $log_entry, FILE_APPEND);
    }
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    exit;
}

// Display PHP errors from $errors array
if (!empty($errors) && is_array($errors)) : ?>
    <div class="error" role="alert">
        <?php foreach ($errors as $error) : ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>