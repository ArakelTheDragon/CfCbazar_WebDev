<?php
// /sitemap.php
declare(strict_types=1);

// ------------------------------------------------------------------ config
require_once __DIR__ . '/config.php';

// FIX: fail loudly if config.php didn't give us a DB connection.
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    die('config.php did not provide a $conn mysqli instance.');
}

const DOMAIN            = 'https://cfcbazar.8bit.ca';
const CHANGEFREQ        = 'weekly';
const DEFAULT_PRIORITY  = '0.8';
const HOMEPAGE_PRIORITY = '1.0';

// FIX: centralised config. Anything matching these never appears in the
// sitemap AND is also written to robots.txt, so the two can never drift.
const EXCLUDED_BASENAMES = [
    'admin.php',
    'test.php',
    'tmp.php',
    'debug.php',
    'scratch.php',
];

const DISALLOW_PATHS = [
    'admin.php',
    '/diy/*/admin.php',   // FIX: covers /diy/sky-fix/admin.php etc.
    'includes/',
    'private/',
    'vendor/',
    'system/',
    'PHPMailer/',
    'logs/',
    'docker/',
    'memory/',
];

$rootDir     = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
$sitemapFile = $rootDir . '/sitemap.xml';
$robotsFile  = $rootDir . '/robots.txt';

// ------------------------------------------------------------------ helpers

/**
 * FIX: placeholder detector. Matches __FOO__, {foo}, %s, {{foo}}, TODO,
 * FIXME, and similar template leftovers that should never be public.
 */
function is_placeholder(string $path): bool
{
    return (bool)preg_match(
        '/(__[A-Z0-9_]+__|\{[^}]+\}|\{\{[^}]+\}\}|%[sd]|TODO|FIXME)/i',
        $path
    );
}

/**
 * FIX: canonicalize a path for sitemap purposes.
 *   /index.php          -> /
 *   /foo/index.php      -> /foo/
 *   /foo/index.php?x=1  -> /foo/
 *   /foo//bar           -> /foo/bar
 * Query strings and fragments are stripped — the sitemap should only
 * contain the canonical URL, not every query variation.
 */
function canonical_path(string $path): string
{
    // Strip query + fragment
    $path = preg_replace('/[?#].*$/', '', $path) ?? $path;

    // Collapse duplicate slashes (but preserve a leading one)
    $path = '/' . ltrim(preg_replace('#/+#', '/', $path) ?? $path, '/');

    // /index.php -> /
    if ($path === '/index.php') {
        return '/';
    }
    // /foo/index.php -> /foo/
    if (preg_match('#^(/.*)/index\.php$#', $path, $m)) {
        return rtrim($m[1], '/') . '/';
    }
    return $path;
}

/**
 * FIX: does this path match any robots.txt disallow rule?
 * Handles the "/diy/*/admin.php" wildcard form because robots.txt supports
 * "*" and we want the sitemap to respect the same rules.
 */
function is_disallowed(string $path, array $rules): bool
{
    foreach ($rules as $rule) {
        $rule = '/' . ltrim($rule, '/');
        // Convert robots wildcard to a regex.
        $regex = '#^' . str_replace('\*', '.*', preg_quote($rule, '#')) . '#';
        // robots.txt matching is prefix-based by default.
        if (preg_match($regex, $path)) {
            return true;
        }
        if (str_ends_with($rule, '/') && str_starts_with($path, $rule)) {
            return true;
        }
    }
    return false;
}

/**
 * FIX: atomic write so a crash can't leave a half-written file.
 */
function atomic_write(string $file, string $contents): void
{
    $tmp = tempnam(dirname($file), '.sitemap_');
    if ($tmp === false) {
        throw new RuntimeException("tempnam failed for $file");
    }
    if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException("write failed for $file");
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException("rename failed for $file");
    }
}

// ------------------------------------------------------------------ fetch

/**
 * Pull published rows from `pages`, canonicalize, filter, dedupe.
 * Returns a map of canonical-path => ['lastmod' => Y-m-d].
 */
function getDynamicUrls(mysqli $conn): array
{
    $out = [];
    $sql = "SELECT path, updated_at FROM pages WHERE status = 'published' ORDER BY path";
    $result = $conn->query($sql);
    if ($result === false) {
        throw new RuntimeException('DB query failed: ' . $conn->error);
    }

    $skipped = 0;
    while ($row = $result->fetch_assoc()) {
        $raw = (string)$row['path'];

        // FIX: skip placeholders like __URL__ before doing anything else.
        if (is_placeholder($raw)) {
            error_log("[sitemap] skipped placeholder path: $raw");
            $skipped++;
            continue;
        }

        $canonical = canonical_path($raw);

        // FIX: skip scratch/test files and anything robots.txt forbids.
        if (in_array(basename($canonical), EXCLUDED_BASENAMES, true)) {
            $skipped++;
            continue;
        }
        if (is_disallowed($canonical, DISALLOW_PATHS)) {
            $skipped++;
            continue;
        }

        // FIX: dedupe on the canonical form, not the raw path.
        if (isset($out[$canonical])) {
            // If a duplicate has a newer lastmod, keep the newer one.
            $newDate = date('Y-m-d', strtotime((string)($row['updated_at'] ?: 'now')));
            if ($newDate > $out[$canonical]['lastmod']) {
                $out[$canonical]['lastmod'] = $newDate;
            }
            $skipped++;
            continue;
        }

        $out[$canonical] = [
            'lastmod' => date('Y-m-d', strtotime((string)($row['updated_at'] ?: 'now'))),
        ];
    }

    error_log(sprintf('[sitemap] published=%d, kept=%d, skipped=%d',
        $result->num_rows, count($out), $skipped));

    if ($out === []) {
        throw new RuntimeException('No published pages returned — refusing to write empty sitemap.');
    }
    return $out;
}

// ------------------------------------------------------------------ emit

function generateSitemap(array $pages, string $domain, string $file): void
{
    ksort($pages);

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

    foreach ($pages as $path => $meta) {
        $loc = $domain . $path;
        $pri = ($path === '/') ? HOMEPAGE_PRIORITY : DEFAULT_PRIORITY;

        $xml .= "  <url>" . PHP_EOL;
        $xml .= '    <loc>'        . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>'    . htmlspecialchars($meta['lastmod'], ENT_XML1, 'UTF-8')       . '</lastmod>' . PHP_EOL;
        $xml .= '    <changefreq>' . CHANGEFREQ                                                  . '</changefreq>' . PHP_EOL;
        $xml .= '    <priority>'   . $pri                                                        . '</priority>' . PHP_EOL;
        $xml .= "  </url>" . PHP_EOL;
    }
    $xml .= '</urlset>' . PHP_EOL;

    atomic_write($file, $xml);
}

function generateRobotsTxt(string $domain, array $disallowPaths, string $file): void
{
    $lines = ["User-agent: *", "Allow: /"];
    foreach ($disallowPaths as $p) {
        $p = '/' . ltrim($p, '/');   // FIX: normalise so we never emit "//"
        $lines[] = "Disallow: $p";
    }
    $lines[] = "Sitemap: $domain/sitemap.xml";

    atomic_write($file, implode(PHP_EOL, $lines) . PHP_EOL);
}

// ------------------------------------------------------------------ main

try {
    $pages = getDynamicUrls($conn);
    generateSitemap($pages, DOMAIN, $sitemapFile);
    generateRobotsTxt(DOMAIN, DISALLOW_PATHS, $robotsFile);

    echo 'sitemap.xml written: ' . count($pages) . ' URLs<br>';
    echo 'robots.txt written<br>';
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[sitemap] ' . $e->getMessage());
    echo 'Error generating sitemap: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} finally {
    $conn->close();
}
