<?php
/**
 * CfCbazar UI & System Notice Helper Library
 * File: /includes/show_disabled_message.php
 *
 * Displays a styled page-disabled notice informing users of temporary access restrictions 
 * (e.g., maintenance or feature toggles) and links to the News Center.
 */

declare(strict_types=1);

if (!function_exists('show_disabled_message')) {
    /**
     * Render the disabled page notice and exit execution.
     *
     * @param string $reason Reason for disabling the page (default: 'maintenance')
     * @return void
     */
    function show_disabled_message(string $reason = 'maintenance'): void
    {
        if (function_exists('include_header')) {
            include_header();
        }
        if (function_exists('include_menu')) {
            include_menu();
        }

        $escapedReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

        echo '<div class="container">';
        echo '<div class="disabled-message card" style="padding:2em; background:#fff3f3; border:1px solid #f5c2c2; border-radius:8px; margin:2rem auto; max-width:600px; text-align:center;">';
        echo '<h2>🚫 This Page Is Disabled</h2>';
        echo '<p>We’ve temporarily disabled this page due to <strong>' . $escapedReason . '</strong>.</p>';
        echo '<p>For more updates, please visit our <a href="/system/news.php" target="_blank">News Center</a>.</p>';
        echo '</div>';
        echo '</div>';

        if (function_exists('include_footer')) {
            include_footer();
        }

        exit();
    }
}
