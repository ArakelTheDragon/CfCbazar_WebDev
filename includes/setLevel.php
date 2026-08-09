<?php
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/setLevel.php
 *
 * Updates the level attribute for a specific worker in the database.
 */

declare(strict_types=1);

if (!function_exists('setLevel')) {
    /**
     * Updates the level field for a given worker email address.
     *
     * @param string $email User's account email address
     * @param int $level New level to set for the worker
     * @return void
     */
    function setLevel(string $email, int $level): void
    {
        global $conn;

        if (!$conn) {
            error_log("setLevel: Database connection not available");
            return;
        }

        $stmt = $conn->prepare("UPDATE workers SET level = ? WHERE email = ?");

        if (!$stmt) {
            error_log("setLevel: Prepare failed: " . $conn->error);
            return;
        }

        $stmt->bind_param('is', $level, $email);
        $stmt->execute();
        $stmt->close();
    }
}
