<?php
/**
 * CfCbazar Worker & Equipment Helper Library
 * File: /includes/upgradeAllGear.php
 *
 * Iterates through all valid gear slots and applies a specified boost level upgrade
 * to every equipment slot for a worker.
 */

declare(strict_types=1);

if (!function_exists('upgradeAllGear')) {
    /**
     * Upgrades every valid gear slot for a given worker by a specified boost amount.
     *
     * @param string $email User's account email address
     * @param int $amount Numerical boost increment amount
     * @return void
     */
    function upgradeAllGear(string $email, int $amount): void
    {
        foreach (_valid_gear_slots() as $s) {
            upgradeGearSlot($email, $s, $amount);
        }
    }
}
