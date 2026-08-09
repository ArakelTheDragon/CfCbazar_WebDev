<?php
/**
 * CfCbazar Tracking Helper Library
 * File: /includes/track_bootstrap.php
 *
 * Bootstraps security enforcement, database connection, session tracking,
 * and user status initialization for the tracking module.
 */

declare(strict_types=1);

if (!function_exists('track_bootstrap')) {
    /**
     * Initializes security, system checks, database connection, visit logging,
     * and populates global session user state.
     *
     * @return void
     */
    function track_bootstrap(): void
    {
        global $conn;
        global $email;
        global $status;
        global $is_logged_in;

        enforce_https();

        checkSystemFlags();

        require_database_connection();

        trackVisit('track-main');

        session_check();

        $email = $_SESSION['email'] ?? null;

        $is_logged_in = is_logged_in($email, true);

        $status = getUserStatus($email);
    }
}
