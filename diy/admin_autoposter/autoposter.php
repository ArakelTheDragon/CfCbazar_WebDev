<?php
// autoposter.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// --- Config ------------------------------------------------------------------
// Load the public config bootstrap (which in turn loads includes/secrets.php).
// Path is relative to this file: ../../config.php
$configFile = __DIR__ . '/../../config.php';

if (!is_readable($configFile)) {
    echo json_encode(['error' => 'config.php not found.']);
    exit;
}

require_once $configFile;

// Your config.php maps defines to legacy variable names:
//   $pageId, $page_token, $api_version, $json_file
// Fall back to the constants if the variables aren't set.
$pageId     = $pageId     ?? (defined('PAGE_ID')     ? PAGE_ID     : '');
$pageToken  = $page_token ?? (defined('PAGE_TOKEN')  ? PAGE_TOKEN  : '');
$apiVersion = $api_version ?? (defined('API_VERSION') ? API_VERSION : 'v26.0');
$jsonFile   = $json_file  ?? (defined('JSON_FILE')   ? JSON_FILE   : __DIR__ . '/posts.json');

if ($pageId === '' || $pageToken === '') {
    echo json_encode(['error' => 'PAGE_ID and PAGE_TOKEN must be set in includes/secrets.php']);
    exit;
}
// -----------------------------------------------------------------------------

/**
 * Pull the first http/https URL out of a block of text.
 * Trailing punctuation is stripped so "see https://x.com/a." doesn't
 * include the period.
 */
function extract_first_url(string $text): string
{
    if (preg_match('~https?://[^\s<>"]+~i', $text, $m)) {
        return rtrim($m[0], '.,;:!?)');
    }
    return '';
}

/**
 * Post to a Facebook Page feed.
 *
 * If $link is non-empty it is sent via the dedicated `link` parameter,
 * which is what makes Facebook fetch the page's Open Graph tags and
 * render a proper preview card. If $link is empty, behavior is identical
 * to a plain text post.
 */
function fb_post(string $pageId, string $token, string $message, string $version, string $link = ''): array
{
    $url = "https://graph.facebook.com/{$version}/{$pageId}/feed";

    $fields = [
        'message'      => $message,
        'access_token' => $token,
    ];

    // Only include link when present, so text-only posts stay unchanged.
    if ($link !== '') {
        $fields['link'] = $link;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => "cURL: {$curlErr}"];
    }

    $decoded = json_decode((string)$response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['id'])) {
        return ['ok' => true, 'post_id' => $decoded['id']];
    }

    return [
        'ok'    => false,
        'error' => $decoded['error']['message'] ?? "HTTP {$httpCode}",
    ];
}

// --- Read the state ----------------------------------------------------------
if (!is_readable($jsonFile)) {
    echo json_encode(['error' => "JSON file not found: {$jsonFile}"]);
    exit;
}

$raw   = file_get_contents($jsonFile);
$state = json_decode($raw, true);

if (!is_array($state) || !isset($state['posts']) || !is_array($state['posts'])) {
    echo json_encode(['error' => 'Invalid posts.json — expected {"next": 0, "posts": [...]}']);
    exit;
}

$posts = $state['posts'];
$next  = (int)($state['next'] ?? 0);

if (empty($posts)) {
    echo json_encode(['error' => 'No posts in posts.json']);
    exit;
}

// Wrap around when we reach the end
if ($next < 0 || $next >= count($posts)) {
    $next = 0;
}

$item = $posts[$next];

// Accept both {"message": "..."} objects and plain strings.
// Also accept an optional explicit "link" field.
$link = '';

if (is_array($item) && isset($item['message']) && is_string($item['message'])) {
    $message = trim($item['message']);
    if (isset($item['link']) && is_string($item['link'])) {
        $link = trim($item['link']);
    }
} elseif (is_string($item)) {
    $message = trim($item);
} else {
    echo json_encode(['error' => "Invalid post at index {$next}"]);
    exit;
}

if ($message === '') {
    echo json_encode(['error' => "Empty message at index {$next}"]);
    exit;
}

// If no explicit link was provided, pull the first URL out of the message text.
// This lets FB build a proper preview card instead of showing the URL as plain text.
if ($link === '') {
    $link = extract_first_url($message);
}

// --- Post it -----------------------------------------------------------------
$result = fb_post($pageId, $pageToken, $message, $apiVersion, $link);

if ($result['ok']) {
    // Advance the pointer only on a successful post
    $state['next'] = $next + 1;
    file_put_contents(
        $jsonFile,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

// --- Report ------------------------------------------------------------------
echo json_encode([
    'posted_index' => $next,
    'total_posts'  => count($posts),
    'next_index'   => $state['next'] ?? $next,
    'message'      => mb_substr($message, 0, 80) . (mb_strlen($message) > 80 ? '…' : ''),
    'link'         => $link,
    'result'       => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
