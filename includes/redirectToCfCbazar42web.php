<?php
/**
 * CfCbazar Navigation & Server Routing Helper Library
 * File: /includes/redirectToCfCbazar42web.php
 *
 * Redirects incoming HTTP requests directly to the main target server domain.
 */

declare(strict_types=1);

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
