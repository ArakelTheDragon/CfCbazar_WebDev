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

    function include_header(?string $title = null): void
    {
        // Updated default title (no WorkToken)
        $title = $title ?? 'CfCbazar – Smart Deals, DIY eGuides, Open‑Source Tools, Games & Free Entertainment';

        // Updated SEO description
        $description = 'CfCbazar offers DIY eGuides for cooking, budgeting, survival planning, electronics, PCB projects, open‑source tools, smart deals, free browser games, and free TV entertainment. Discover practical digital tools made for everyday people.';

        // Updated SEO keywords
        $keywords = implode(', ', [
            'CfCbazar',
            'DIY eGuides',
            'cooking guides',
            'budget planners',
            'survival calculators',
            'open source tools',
            'PCB projects',
            'electronics circuits',
            'smart deals',
            'printable products',
            'free browser games',
            'free TV',
            'entertainment',
            'digital downloads',
            'online tools'
        ]);

        $csrfToken  = $_SESSION['csrf_token'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<meta name="csrf_token" content="' . htmlspecialchars($csrfToken) . '">';

        // Title & SEO
        echo '<title>' . htmlspecialchars($title) . '</title>';
        echo '<meta name="description" content="' . htmlspecialchars($description) . '">';
        echo '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">';
        echo '<meta name="robots" content="index, follow">';
        echo '<meta name="author" content="CfCbazar">';

        // Open Graph
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
        echo '<header class="header"></header>';
    }
}

