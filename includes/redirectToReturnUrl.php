<?php
/**
 * CfCbazar Navigation & Cookie Helper Library
 * File: /includes/redirectToReturnUrl.php
 *
 * Validates and executes post-action redirects based on the `return_url` cookie.
 * Safely clears the cookie upon consumption and falls back to a default path if invalid or unset.
 */

declare(strict_types=1);

if (!function_exists('redirectToReturnUrl')) {
    /**
     * Redirects the user to the destination stored in the `return_url` cookie, 
     * or to a designated fallback path.
     *
     * @param string $fallback Relative fallback path (default: '/index.php')
     * @return void
     */
    function redirectToReturnUrl(string $fallback = '/index.php'): void
    {
        if (isset($_COOKIE['return_url'])) {
            $returnPath = urldecode($_COOKIE['return_url']);

            // Validate again to ensure it's a safe local PHP path
            if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $returnPath)) {
                // Clear the cookie after use
                setcookie('return_url', '', time() - 3600, '/', '', false, true);

                // Redirect
                header("Location: " . $returnPath);
                exit();
            }
        }

        // Fallback redirect
        header("Location: " . $fallback);
        exit();
    }
}
