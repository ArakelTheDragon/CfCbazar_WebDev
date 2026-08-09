<?php
/**
 * CfCbazar Staking & Worker Helper Library
 * File: /includes/toggleStaking.php
 *
 * Toggles active staking state for registered EVM worker wallet addresses.
 * Updates stake activation status and timestamps in the `workers` database table.
 */

declare(strict_types=1);

if (!function_exists('toggleStaking')) {
    /**
     * Toggles staking status ('start' or 'stop') for a given worker wallet address.
     *
     * @param mysqli $conn Active MySQLi database connection object
     * @param string $wallet EVM wallet address (0x...)
     * @param string $action Action to perform ('start' or 'stop')
     * @return string Human-readable result status message
     */
    function toggleStaking(mysqli $conn, string $wallet, string $action): string
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
            return "Invalid wallet address.";
        }

        // Verify wallet existence
        $stmt = $conn->prepare("SELECT address FROM workers WHERE address = ?");
        if (!$stmt) {
            return "Database error while verifying wallet.";
        }

        $stmt->bind_param("s", $wallet);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$res || $res->num_rows === 0) {
            $stmt->close();
            return "Wallet not found.";
        }
        $stmt->close();

        // Perform requested staking action
        if ($action === 'start') {
            $stmt = $conn->prepare("UPDATE workers SET stake_active = 1, stake_timestamp = NOW() WHERE address = ?");
        } elseif ($action === 'stop') {
            $stmt = $conn->prepare("UPDATE workers SET stake_active = 0 WHERE address = ?");
        } else {
            return "Invalid action.";
        }

        if (!$stmt) {
            return "Database error while updating staking status.";
        }

        $stmt->bind_param("s", $wallet);
        $stmt->execute();
        $stmt->close();

        return $action === 'start' ? "✅ Staking started." : "🛑 Staking stopped.";
    }
}
