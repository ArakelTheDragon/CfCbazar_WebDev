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
     * Start and maintain a PHP session when required.
     *
     * @param int $required
     *        1 = session required (default)
     *        0 = session not required
     */
    function session_check(int $required = 1): void
    {
        // Session not required for this page.
        if ($required === 0) {
            return;
        }

        // Session already active.
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Start session when required.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}


if (!function_exists('is_logged_in')) {
    /**
     * Check whether an active session contains a logged-in user.
     *
     * This function does NOT create a session.
     *
     * @param string|null $email Receives the active user email address.
     * @return bool True if logged in, false otherwise.
     */
    function is_logged_in(?string &$email = null): bool
    {
        $email = null;

        // Do not create a session just to check login state.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $email = $_SESSION['email'] ?? null;

        return is_string($email) && trim($email) !== '';
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generates or retrieves the current active CSRF token.
     *
     * CSRF tokens require a session.
     *
     * @return string
     */
    function csrf_token(): string
    {
        session_check(1);

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('logout_user')) {
    /**
     * Log out the current user unconditionally.
     *
     * Safe to call from any page: if no session is active, it simply
     * redirects to /login.php.
     *
     * @return void
     */
    function logout_user(): void
    {
        // Nothing to destroy if no session is active.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            header('Location: /login.php');
            exit();
        }

        // Clear session data in memory…
        $_SESSION = [];

        // …then expire the session cookie in the browser.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        // Destroy server-side session storage.
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

//************ Cookie for redirect after login helpers ************//
if (!function_exists('isSafeReturnUrl')) {
    function isSafeReturnUrl(string $url): bool
    {
        if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
            return false;
        }
        if (str_contains($url, '\\')) {
            return false;
        }
        if (preg_match('/[\x00-\x1F\x7F\s]/', $url)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('setReturnUrl')) {
    /**
     * Remember where to send the user after login.
     * No session required. Safe to call on any page.
     */
    function setReturnUrl(string $url): void
    {
        if (!isSafeReturnUrl($url)) {
            return;
        }
        setcookie('return_url', $url, [
            'expires'  => time() + 600,   // 10 minutes
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // Make it available in this request as well.
        $_COOKIE['return_url'] = $url;
    }
}

if (!function_exists('getReturnUrl')) {
    /**
     * Resolve where to send the user after login.
     * Priority: GET ?return_url=…  →  cookie  →  $default.
     */
    function getReturnUrl(string $default = '/index.php'): string
    {
        if (isset($_GET['return_url']) && is_string($_GET['return_url'])
            && isSafeReturnUrl($_GET['return_url'])) {
            return $_GET['return_url'];
        }
        if (isset($_COOKIE['return_url']) && is_string($_COOKIE['return_url'])
            && isSafeReturnUrl($_COOKIE['return_url'])) {
            return $_COOKIE['return_url'];
        }
        return $default;
    }
}

if (!function_exists('clearReturnUrl')) {
    function clearReturnUrl(): void
    {
        setcookie('return_url', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['return_url']);
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
