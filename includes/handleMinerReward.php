<?php
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/handleMinerReward.php
 *
 * Processes share submissions from mining hardware devices, calculates share deltas,
 * registers device activity, and awards corresponding token payouts (WorkToken or WorkTHR).
 */

declare(strict_types=1);

if (!function_exists('handleMinerReward')) {
    /**
     * Handle mining reward logic based on accepted shares.
     *
     * @param string $email User's email address
     * @param string $rewardType Either 'WorkToken' or 'WorkTHR'
     * @param int $acceptedFromMiner Total accepted shares count reported by miner
     * @param string $mac Device MAC address
     * @param int $active 1 if device is active, 0 if inactive
     * @return void
     */
    function handleMinerReward(string $email, string $rewardType, int $acceptedFromMiner, string $mac, int $active): void
    {
        global $conn;

        $mac = substr(trim($mac), 0, 20);
        $active = ($active === 1) ? 1 : 0;

        // Validate input
        if ($acceptedFromMiner < 0 || !in_array($rewardType, ['WorkToken', 'WorkTHR'], true)) {
            return;
        }

        // Register or update device mining activity
        if (!empty($mac)) {
            $stmt = $conn->prepare("
                INSERT INTO devices (email, mac_address, last_mine_time, active)
                VALUES (?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE last_mine_time = NOW(), active = VALUES(active)
            ");
            if ($stmt) {
                $stmt->bind_param("ssi", $email, $mac, $active);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Fetch wallet and last accepted_shares_temp
        $stmt = $conn->prepare("SELECT address, accepted_shares_temp FROM workers WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($wallet, $lastSeenMiner);
        $stmt->fetch();
        $stmt->close();

        if (empty($wallet)) {
            return;
        }

        $lastSeenMiner = $lastSeenMiner ?? 0;

        // Calculate delta
        $newShares = ($acceptedFromMiner >= $lastSeenMiner)
            ? $acceptedFromMiner - $lastSeenMiner
            : $acceptedFromMiner; // reset case

        if ($newShares <= 0) {
            return;
        }

        // Calculate reward
        $reward = round($newShares * 0.011, 8);
        $column = ($rewardType === 'WorkToken') ? 'tokens_earned' : 'mintme';

        // Update mining stats
        $stmt = $conn->prepare("
            UPDATE workers SET
                accepted_shares = accepted_shares + ?,
                accepted_shares_temp = ?,
                $column = $column + ?
            WHERE email = ?
        ");
        if ($stmt) {
            $stmt->bind_param("iids", $newShares, $acceptedFromMiner, $reward, $email);
            $stmt->execute();
            $stmt->close();
        }
    }
}
