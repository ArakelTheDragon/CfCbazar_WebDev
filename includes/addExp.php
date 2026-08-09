<?php
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/addExp.php
 *
 * Increments the total experience points (EXP) for a specific worker in the database.
 */

declare(strict_types=1);

if (!function_exists('addExp')) {
    /**
     * Increments the exp field for a given worker email address.
     *
     * @param string $email User's account email address
     * @param int $xp Amount of experience points to add
     * @return void
     */
    function addExp(string $email, int $xp): void
    {
        global $conn;

        if (!$conn) {
            error_log("addExp: Database connection not available");
            return;
        }

        $stmt = $conn->prepare("UPDATE workers SET exp = COALESCE(exp,0) + ? WHERE email = ?");

        if (!$stmt) {
            error_log("addExp: Prepare failed: " . $conn->error);
            return;
        }

        $stmt->bind_param('is', $xp, $email);
        $stmt->execute();
        $stmt->close();
    }
}
