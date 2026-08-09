<?php
/**
 * CfCbazar Worker & Equipment Helper Library
 * File: /includes/_valid_gear_slots.php
 *
 * Returns an array of valid gear slot keys supported for worker equipment.
 */

declare(strict_types=1);

if (!function_exists('_valid_gear_slots')) {
    /**
     * Returns an array of valid equipment slot identifiers.
     *
     * @return array List of valid gear slot names
     */
    function _valid_gear_slots(): array
    {
        return ['helmet', 'armour', 'weapon', 'second_weapon', 'pants', 'boots', 'gloves'];
    }
}
