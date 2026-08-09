<?php
/**
 * CfCbazar System Configuration & Maintenance Helper Library
 * File: /includes/checkSystemFlags.php
 *
 * Queries system settings to enforce maintenance mode redirects 
 * and verify whether new user registration is enabled or disabled.
 */

declare(strict_types=1);

if (!function_exists('checkSystemFlags')) {
    /**
     * Evaluates system flags for maintenance mode and registration availability.
     *
     * @return bool True if normal execution can proceed, false if registration is disabled or in maintenance mode.
     */
    function checkSystemFlags(): bool
    {
        global $conn;

        if (!$conn) {
            die("Database connection missing.");
        }

        $result = $conn->query("SELECT maintenance, disable_registration FROM settings WHERE id = 1 LIMIT 1");

        if (!$result || $result->num_rows === 0) {
            die("System configuration error.");
        }

        $settings = $result->fetch_assoc();

        // Handle Maintenance Mode Redirect
        if ((int)($settings['maintenance'] ?? 0) === 1) {
            $currentUri = $_SERVER['REQUEST_URI'] ?? '';
            // Prevent redirect loop if already on the maintenance page
            if (strpos($currentUri, '/system/maintenance.php') === false) {
                header("Location: /system/maintenance.php");
                exit();
            }
            return false;
        }

        // Handle Disabled Registration Flag
        if ((int)($settings['disable_registration'] ?? 0) === 1) {
            echo "<p style='color:red;'>Registration is disabled by administrator.</p>";
            return false;
        }

        return true;
    }
}
