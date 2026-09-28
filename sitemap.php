<?php
// Include config.php
require_once 'config.php';

// Configuration
$domain = "https://CfCbazar.ct.ws"; // Your website URL
$root_dir = $_SERVER['DOCUMENT_ROOT']; // Root directory of your website
$sitemap_file = $root_dir . '/sitemap.xml'; // Path to save sitemap
$robots_file = $root_dir . '/robots.txt'; // Path to save robots.txt
$excluded_pages = ['admin.php']; // Pages to exclude from sitemap
$disallow_paths = ['admin.php', 'includes/', 'private/', 'vendor/']; // Paths to disallow in robots.txt

// Function to fetch dynamic URLs from the pages table
function getDynamicUrls($conn, $domain, $excluded_pages) {
    $urls = [];
    // Query the pages table for published pages
    $query = "SELECT path, updated_at FROM pages WHERE status = 'published'";
    $result = $conn->query($query);

    if ($result) {
        $seen_urls = []; // Track unique URLs to avoid duplicates
        while ($row = $result->fetch_assoc()) {
            $path = $row['path'];
            // Skip excluded pages
            if (in_array(basename($path), $excluded_pages)) {
                continue;
            }
            // Normalize path to avoid duplicates (e.g., /numbers.php vs /numbers.php/)
            $normalized_path = rtrim($path, '/');
            if (isset($seen_urls[$normalized_path])) {
                continue; // Skip duplicates
            }
            $seen_urls[$normalized_path] = true;
            // Construct full URL
            $url = $domain . $path;
            $urls[] = [
                'loc' => $url,
                'lastmod' => date('Y-m-d', strtotime($row['updated_at'] ?: 'now')),
                'changefreq' => 'weekly', // Adjust as needed
                'priority' => ($path === '/index.php' ? '1.0' : '0.8') // Higher priority for homepage
            ];
        }
    } else {
        error_log("Database query error: " . $conn->error);
    }

    return $urls;
}

// Function to generate XML sitemap
function generateSitemap($urls, $sitemap_file) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    foreach ($urls as $url) {
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . PHP_EOL;
        $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . PHP_EOL;
        $xml .= '    <priority>' . $url['priority'] . '</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
    }
    $xml .= '</urlset>';

    // Save to file
    if (@file_put_contents($sitemap_file, $xml) === false) {
        die('Error writing sitemap.xml. Check directory permissions.');
    }
    return true;
}

// Function to generate robots.txt
function generateRobotsTxt($domain, $sitemap_file, $robots_file, $disallow_paths) {
    $robots = "User-agent: *" . PHP_EOL;
    $robots .= "Allow: /" . PHP_EOL;
    foreach ($disallow_paths as $path) {
        $robots .= "Disallow: /$path" . PHP_EOL;
    }
    $robots .= "Sitemap: " . $domain . "/sitemap.xml" . PHP_EOL;

    // Save to file
    if (@file_put_contents($robots_file, $robots) === false) {
        die('Error writing robots.txt. Check directory permissions.');
    }
    return true;
}

// Main execution
$urls = getDynamicUrls($conn, $domain, $excluded_pages);

// Generate files
if (generateSitemap($urls, $sitemap_file)) {
    echo "sitemap.xml generated successfully at $sitemap_file<br>";
}
if (generateRobotsTxt($domain, $sitemap_file, $robots_file, $disallow_paths)) {
    echo "robots.txt generated successfully at $robots_file<br>";
}

// Close database connection
$conn->close();
?>