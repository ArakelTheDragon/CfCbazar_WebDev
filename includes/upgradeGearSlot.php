<?php
/**
 * CfCbazar Worker & Equipment Helper Library
 * File: /includes/upgradeGearSlot.php
 *
 * Validates gear slots, parses current item boost levels, and updates 
 * the worker equipment attribute in the database.
 */

declare(strict_types=1);

if (!function_exists('upgradeGearSlot')) {
    /**
     * Upgrades a worker's equipment slot by incrementing its numerical boost value.
     *
     * @param string $email User's account email address
     * @param string $slot Valid gear slot name (e.g., 'helmet', 'weapon')
     * @param int $amount Numerical boost increment amount
     * @return bool True on successful upgrade, false on failure or invalid slot
     */
    function upgradeGearSlot(string $email, string $slot, int $amount): bool
    {
        global $conn;

        if (!$conn) {
            error_log("upgradeGearSlot: Database connection not available");
            return false;
        }

        $allowed = _valid_gear_slots();
        if (!in_array($slot, $allowed, true)) {
            return false;
        }

        $stmt = $conn->prepare("SELECT {$slot} FROM workers WHERE email = ? LIMIT 1");

        if (!$stmt) {
            error_log("upgradeGearSlot: Prepare failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $current = $row[$slot] ?? '';

        if (preg_match('/\+(\d+)/', $current, $m)) {
            $curBoost = (int)$m[1];
        } else {
            $curBoost = 0;
        }

        $newBoost = $curBoost + $amount;
        $pretty = ucwords(str_replace('_', ' ', $slot));
        $newGear = "{$pretty} +{$newBoost}";

        $upd = $conn->prepare("UPDATE workers SET {$slot} = ? WHERE email = ?");

        if (!$upd) {
            error_log("upgradeGearSlot: Prepare failed for update: " . $conn->error);
            return false;
        }

        $upd->bind_param('ss', $newGear, $email);
        $upd->execute();
        $upd->close();

        return true;
    }
}
