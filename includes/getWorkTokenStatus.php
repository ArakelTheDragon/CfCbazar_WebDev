<?php
/**
 * CfCbazar Staking & Token Status Helper Library
 * File: /includes/getWorkTokenStatus.php
 *
 * Retrieves active token balances (WTK or MintMe) for a worker wallet.
 * Automatically verifies active miner heartbeats (last_ping < 120s) and calculates 
 * minute-by-minute compound staking interest (1% APR) when staking conditions are met.
 */

declare(strict_types=1);

if (!function_exists('getWorkTokenStatus')) {
    /**
     * Fetch wallet status and process pending staking yields for WTK or MintMe tokens.
     *
     * @param mysqli $conn Active MySQLi database connection object
     * @param string $wallet EVM wallet address (0x...)
     * @param string $selectedToken Token symbol ('WTK' or 'MINTME')
     * @return string Formatted plain-text status response with balance details
     */
    function getWorkTokenStatus(mysqli $conn, string $wallet, string $selectedToken = 'WTK'): string
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
            return "Invalid wallet address.";
        }

        $stmt = $conn->prepare("SELECT tokens_earned, mintme, last_ping, stake_active, stake_timestamp FROM workers WHERE address = ?");
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

        $user = $result->fetch_assoc();
        $stmt->close();

        $now = time();

        // Validate last_ping (miner must have sent a ping within 120 seconds)
        $isMinerRunning = false;
        if (!empty($user['last_ping']) && is_string($user['last_ping'])) {
            $lastPing = strtotime($user['last_ping']);
            if ($lastPing !== false) {
                $isMinerRunning = ($now - $lastPing) < 120;
            }
        }

        // Determine target balance column
        $balanceField = ($selectedToken === 'WTK') ? 'tokens_earned' : 'mintme';
        $balance = floatval($user[$balanceField] ?? 0);
        $bonus = 0.0;

        // Calculate and apply yield if active staking requirements are met
        if (
            !empty($user['stake_active']) &&
            $isMinerRunning &&
            !empty($user['stake_timestamp']) &&
            is_string($user['stake_timestamp'])
        ) {
            $lastStake = strtotime($user['stake_timestamp']);
            if ($lastStake !== false) {
                $minutesStaked = ($now - $lastStake) / 60;
                if ($minutesStaked >= 1) {
                    $apr = 0.01; // 1% APR
                    $bonus = $balance * ($apr / 525600) * $minutesStaked;
                    $newBalance = $balance + $bonus;
                    $formattedNow = date("Y-m-d H:i:s", $now);

                    // Strictly whitelist column to prevent dynamic SQL injection
                    $targetColumn = ($balanceField === 'mintme') ? 'mintme' : 'tokens_earned';

                    $update = $conn->prepare("UPDATE workers SET {$targetColumn} = ?, stake_timestamp = ? WHERE address = ?");
                    if ($update) {
                        $update->bind_param("dss", $newBalance, $formattedNow, $wallet);
                        $update->execute();
                        $update->close();

                        $balance = $newBalance;
                    }
                }
            }
        }

        return sprintf(
            "Wallet: %s\nToken: %s\nBalance: %.6f%s",
            $wallet,
            $selectedToken,
            $balance,
            ($bonus > 0) ? " (includes staking bonus of +" . number_format($bonus, 6) . ")" : ""
        );
    }
}
