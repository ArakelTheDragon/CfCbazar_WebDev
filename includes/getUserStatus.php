<?php
/**
 * CfCbazar Auth & User Role Helper Library
 * File: /includes/getUserStatus.php
 *
 * Resolves the numeric role status for a given user email address.
 * Includes safety patches for session fallback and mispassed parameters.
 *
 * Role Status Mapping:
 * 0 = Guest / Not Logged In
 * 1 = Admin
 * 2 = Moderator
 * 3 = Contributor
 * 4 = VIP
 * 5 = Standard User
 */

declare(strict_types=1);

if (!function_exists('getUserStatus')) {
    /**
     * Query and return the numeric user status code.
     *
     * @param mixed $email User email address or auto-detect from session
     * @return int Numeric user status (0 through 5)
     */
    function getUserStatus($email = null): int
    {
        global $conn;

        // --- SAFETY PATCH ---
        // If a mysqli object was passed instead of an email → auto-fix using active session
        if ($email instanceof mysqli) {
            $email = $_SESSION['email'] ?? null;
        }

        // If email is missing or invalid → user is not logged in
        if (!is_string($email) || trim($email) === '') {
            return 0;
        }

        $email = trim($email);

        // Query user status
        $stmt = $conn->prepare("SELECT status FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return 0; // fail-safe
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($status);
        $found = $stmt->fetch();
        $stmt->close();

        return $found ? (int)$status : 0;
    }
}
