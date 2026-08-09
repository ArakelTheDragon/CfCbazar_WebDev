<?php
/**
 * CfCbazar Analytics & Page Tracking Helper Library
 * File: /includes/trackVisit.php
 *
 * Tracks page views in the `pages` database table. Normalizes page slugs,
 * generates default titles, and atomically increments visit counts using 
 * ON DUPLICATE KEY UPDATE for HTTP GET requests.
 */

declare(strict_types=1);

if (!function_exists('trackVisit')) {
    /**
     * Tracks a page visit in the `pages` table.
     *
     * @param string $slug Page slug or filename to track
     * @return void
     */
    function trackVisit(string $slug): void
    {
        global $conn;

        // Only track GET page loads
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }

        // Normalize slug
        $slug = trim($slug);
        $slug = mb_substr($slug, 0, 255);

        if ($slug === '') {
            $slug = 'index.php';
        }

        // Human-readable title
        $title = ucfirst(str_replace(['.php', '_', '-'], ['', ' ', ' '], $slug));

        // Path must match DB format
        $path = '/' . $slug;

        // Insert or update
        $stmt = $conn->prepare("
            INSERT INTO pages (title, slug, path, status, visits, created_at, updated_at)
            VALUES (?, ?, ?, 'published', 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE visits = visits + 1, updated_at = NOW()
        ");

        if ($stmt) {
            $stmt->bind_param('sss', $title, $slug, $path);
            $stmt->execute();
            $stmt->close();
        }
    }
}
