<?php
/**
 * CfCbazar Staking & Worker Helper Library
 * File: /includes/getStakingStatus.php
 *
 * Queries and returns the active staking status and start timestamp 
 * for a given worker wallet address.
 */

declare(strict_types=1);

if (!function_exists('getStakingStatus')) {
    /**
     * Get human-readable staking status for a worker wallet.
     *
     * @param mysqli $conn Active MySQLi database connection object
     * @param string $wallet EVM wallet address (0x...)
     * @return string Human-readable staking status message
     */
    function getStakingStatus(mysqli $conn, string $wallet): string
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
            return "Invalid wallet address.";
        }

        $stmt = $conn->prepare("SELECT stake_active, stake_timestamp FROM workers WHERE address = ?");
        if (!$stmt) {
            return "Database query preparation failed.";
        }

        $stmt->bind_param("s", $wallet);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            $stmt->close();
            return "Wallet not found.";
        }

        $data = $result->fetch_assoc();
        $stmt->close();

        $isActive = ((int)($data['stake_active'] ?? 0)) === 1;
        $timestamp = $data['stake_timestamp'] ?? null;

        if ($isActive && $timestamp) {
            return "✅ Staking is active since " . htmlspecialchars((string)$timestamp, ENT_QUOTES, 'UTF-8');
        } elseif ($isActive) {
            return "✅ Staking is active (no timestamp recorded)";
        } else {
            return "❌ Staking is not active.";
        }
    }
}
