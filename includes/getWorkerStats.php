<?php
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/getWorkerStats.php
 *
 * Fetches complete worker stats, equipment levels, experience, and mining parameters
 * for a specific user email.
 */

declare(strict_types=1);

if (!function_exists('getWorkerStats')) {
    /**
     * Fetches worker attributes, gear slots, level/EXP, and mining stats from the database.
     *
     * @param string $email User's account email address
     * @return array Worker data array, or empty array if not found or DB error occurs
     */
    function getWorkerStats(string $email): array
    {
        global $conn;

        if (!$conn) {
            error_log("getWorkerStats: Database connection not available");
            return [];
        }

        $stmt = $conn->prepare("SELECT id, worker_name, email, hr2, mintme, tokens_earned, helmet, armour, weapon, second_weapon, pants, boots, gloves, base_location, exp, level, address, dHr, last_mine_time, last_tx_hash, payout_requested, last_submission FROM workers WHERE email = ? LIMIT 1");

        if (!$stmt) {
            error_log("getWorkerStats: Prepare failed: " . $conn->error);
            return [];
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: [];
    }
}
