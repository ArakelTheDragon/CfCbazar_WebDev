<?php
declare(strict_types=1);
/**
 * Consolidated Auth & Session Helpers
 */

// ===== from auth_session_helpers.php =====
/**
 * CfCbazar Auth & Session Management Helper Library
 * File: /includes/auth_session_helpers.php
 *
 * Core session initialization, CSRF token validation, and authentication verification.
 */


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

// ===== from logoutUser.php =====
/**
 * CfCbazar Auth & Session Helper Library
 * File: /includes/logoutUser.php
 *
 * Safely clears active session data, destroys the user session, 
 * and redirects the client to the login page.
 */


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

// ===== from getUserStatus.php =====
/**
 * CfCbazar Auth & User Role Helper Library
 * File: /includes/getUserStatus.php
 *
 * Resolves the numeric role status for a given user email address.
 * Includes safety patches for session fallback and mispassed parameters.
 *
 * Role Status Mapping:
 * 0 = Guest / Not Logged In
 * 1 = Admin
 * 2 = Moderator
 * 3 = Contributor
 * 4 = VIP
 * 5 = Standard User
 */


if (!function_exists('getUserStatus')) {
    /**
     * Query and return the numeric user status code.
     *
     * @param mixed $email User email address or auto-detect from session
     * @return int Numeric user status (0 through 5)
     */
    function getUserStatus($email = null): int
    {
        global $conn;

        // --- SAFETY & SESSION FALLBACK PATCH ---
        // If no email was passed OR if a mysqli object was accidentally passed -> pull from session
        if ($email === null || $email instanceof mysqli) {
            $email = $_SESSION['email'] ?? null;
        }

        // If email is missing or invalid -> user is not logged in
        if (!is_string($email) || trim($email) === '') {
            return 0;
        }

        $email = trim($email);

        // Query user status
        $stmt = $conn->prepare("SELECT status FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return 0; // fail-safe
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($status);
        $found = $stmt->fetch();
        $stmt->close();

        return $found ? (int)$status : 0;
    }
}

// ===== from setReturnUrlCookie.php =====
/**
 * CfCbazar Navigation & Cookie Helper Library
 * File: /includes/setReturnUrlCookie.php
 *
 * Sets a secure, HTTP-only return URL cookie for post-login or post-action
 * redirects. Validates input paths to ensure they strictly target local PHP scripts.
 */


if (!function_exists('getReturnUrl')) {
    /**
     * Get the validated return URL from GET or cookie.
     *
     * @param string $default
     * @return string
     */
    function getReturnUrl(string $default = '/index.php'): string
    {
        // Priority 1: GET parameter
        if (isset($_GET['return_url'])) {
            $url = urldecode($_GET['return_url']);
            if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $url)) {
                return $url;
            }
        }

        // Priority 2: Cookie
        if (isset($_COOKIE['return_url'])) {
            $url = urldecode($_COOKIE['return_url']);
            if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $url)) {
                return $url;
            }
        }

        return $default;
    }
}

// ===== from redirectToCfCbazar42web.php =====
/**
 * CfCbazar Navigation & Server Routing Helper Library
 * File: /includes/redirectToCfCbazar42web.php
 *
 * Redirects incoming HTTP requests directly to the main target server domain.
 */


if (!function_exists('redirectToCfCbazar42web')) {
    /**
     * Redirects the client immediately to the main CfCbazar host domain.
     *
     * @return void
     */
    function redirectToCfCbazar42web(): void
    {
        header("Location: https://cfcbazar.22web.io");
        exit();
    }
}
