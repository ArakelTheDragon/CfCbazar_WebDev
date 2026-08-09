<?php
/**
 * CfCbazar Auth & Session Management Helper Library
 * File: /includes/auth_session_helpers.php
 *
 * Core session initialization, CSRF token validation, and authentication verification.
 */

declare(strict_types=1);

if (!function_exists('session_check')) {
    /**
     * Ensures an active PHP session exists.
     */
    function session_check(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('is_logged_in')) {
    /**
     * Checks if a user session is active and populates the session email reference.
     *
     * @param string|null $email Receives the active user email address
     * @return bool True if logged in, false otherwise
     */
    function is_logged_in(?string &$email = null): bool
    {
        session_check();
        $email = $_SESSION['email'] ?? null;
        return $email !== null;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generates or retrieves the current active CSRF token.
     *
     * @return string
     */
    function csrf_token(): string
    {
        session_check();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('logout_user')) {
    /**
     * Destroys active user session if 'logout' query parameter is set.
     */
    function logout_user(): void
    {
        session_check();

        if (!isset($_GET['logout'])) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        header('Location: login.php');
        exit();
    }
}
