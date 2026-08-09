<?php
/**
 * CfCbazar Layout Helper Library
 * File: /includes/include_header.php
 *
 * Generates and outputs the base HTML5 structure, meta tags, Open Graph / Twitter cards,
 * stylesheet references, CDN scripts, and initial body layout header.
 */

declare(strict_types=1);

if (!function_exists('include_header')) {
    /**
     * Outputs HTML document head, metadata, scripts, styles, and opening layout header.
     *
     * @param string|null $title Custom page title (falls back to default marketplace title if null)
     * @return void
     */
    function include_header(?string $title = null): void
    {
        // Local fallback if no title was passed
        $title = $title ?? 'CfCbazar - Your marketplace for Smart Deals, DIY, games, music & the WorkToken';

        $description = 'CfCbazar offers URL shortening, power usage calc, survivor tool calc, value of work per hour for different professions, products and services and the WorkToken ecosystem for mining and spending WTK. Join now!';
        $csrfToken   = $_SESSION['csrf_token'] ?? '';
        $requestUri  = $_SERVER['REQUEST_URI'] ?? '';

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<meta name="trustpilot-one-time-domain-verification-id" content="4c6c3303-6308-414f-b3f4-9735179a3877"/>';
        echo '<meta name="csrf_token" content="' . htmlspecialchars($csrfToken) . '">';
        echo '<title>' . htmlspecialchars($title) . '</title>';
        echo '<meta name="description" content="' . htmlspecialchars($description) . '">';
        echo '<meta name="keywords" content="CfCbazar, smart deals, DIY tools, games, music, WorkToken, platform credits, online tools, power usage calc, survival budget calc, value of 1h of work table">';
        echo '<meta name="robots" content="index, follow">';
        echo '<meta name="author" content="CfCbazar">';

        // Open Graph for social media
        echo '<meta property="og:title" content="' . htmlspecialchars($title) . '">';
        echo '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
        echo '<meta property="og:type" content="website">';
        echo '<meta property="og:url" content="https://cfcbazar.42web.io' . htmlspecialchars($requestUri) . '">';
        echo '<meta property="og:image" content="https://cfcbazar.42web.io/images/cfcbazar-banner.jpg">';

        // Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image">';
        echo '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">';
        echo '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';
        echo '<meta name="twitter:image" content="https://cfcbazar.42web.io/images/cfcbazar-banner.jpg">';

        // Core CSS & JS
        echo '<link rel="stylesheet" href="/css/styles.css">';
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        echo '<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>';
        echo '</head><body>';
        echo '<header class="header">';
        echo '</header>';
    }
}
