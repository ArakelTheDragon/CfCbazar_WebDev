<?php
/**
 * CfCbazar Auth & Session Helper Library
 * File: /includes/logoutUser.php
 *
 * Safely clears active session data, destroys the user session, 
 * and redirects the client to the login page.
 */

declare(strict_types=1);

if (!function_exists('logoutUser')) {
    /**
     * Terminates the current user session and redirects to login.
     *
     * @return void
     */
    function logoutUser(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_unset();
        session_destroy();

        header('Location: /login.php');
        exit();
    }
}
