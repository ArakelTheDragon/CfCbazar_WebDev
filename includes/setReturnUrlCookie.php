<?php
/**
 * CfCbazar Navigation & Cookie Helper Library
 * File: /includes/setReturnUrlCookie.php
 *
 * Sets a secure, HTTP-only return URL cookie for post-login or post-action
 * redirects. Validates input paths to ensure they strictly target local PHP scripts.
 */

declare(strict_types=1);

if (!function_exists('setReturnUrlCookie')) {
    /**
     * Set a validated return URL cookie.
     *
     * @param string $path Relative script path (e.g., '/dashboard.php')
     * @param int $expireSeconds Expiration time in seconds (default: 300 / 5 minutes)
     * @return void
     */
    function setReturnUrlCookie(string $path, int $expireSeconds = 300): void
    {
        // Validate path: must be a local PHP file
        if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $path)) {
            setcookie(
                'return_url',
                urlencode($path),
                time() + $expireSeconds,
                '/',
                '',
                false,
                true // HttpOnly flag for security
            );
        }
    }
}
