<?php
/**
 * CfCbazar Worker & Equipment Helper Library
 * File: /includes/upgradeRandomGear.php
 *
 * Selects a random equipment slot from valid gear slots and applies a boost upgrade to it.
 */

declare(strict_types=1);

if (!function_exists('upgradeRandomGear')) {
    /**
     * Upgrades a randomly selected gear slot for a given worker.
     *
     * @param string $email User's account email address
     * @param int $amount Numerical boost increment amount
     * @return bool True on successful upgrade, false on failure
     */
    function upgradeRandomGear(string $email, int $amount): bool
    {
        $slots = _valid_gear_slots();
        $slot = $slots[array_rand($slots)];
        
        return upgradeGearSlot($email, $slot, $amount);
    }
}
