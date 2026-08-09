<?php
/**
 * CfCbazar Tracking Helper Library
 * File: /includes/generateTrackingNumber.php
 *
 * Generates purely numeric tracking numbers.
 */

declare(strict_types=1);

if (!function_exists('generateTrackingNumber')) {
    /**
     * Generates a unique, purely numeric 10-digit tracking number.
     * Example output: 1234859204 or 8392019482
     *
     * @return string Purely numeric tracking number
     */
    function generateTrackingNumber(): string
    {
        // Generates a 10-digit random numeric string (between 1,000,000,000 and 9,999,999,999)
        return (string) random_int(1000000000, 9999999999);
    }
}
