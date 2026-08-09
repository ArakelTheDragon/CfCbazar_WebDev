<?php
declare(strict_types=1);

/**
 * Validates tracking POST requests against session CSRF token.
 */
function validateTrackingCSRF(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die("Invalid tracking CSRF token.");
        }
    }
}
