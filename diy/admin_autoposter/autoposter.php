<?php
// autoposter.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// --- Config ------------------------------------------------------------------
$pageId     = '930733330124779';           // move to config.php when you're ready
$pageToken  = 'EAAM8IqzH7bwBSjuhCC9VsDIF8ztzPQ0iPqh0di0BZA0cB6ZBRpc2e81ttpwBtOcavKs0BVJZAEnZC1hHNDzl0w1TLhZBIUbZChLORWMkgcy7Yl3yFghRNil87lfzneLu1xGzTfTHog8Wgy03mqdimyeCQxuZARxuvy6M4mbdp82lwwYLVL6525DZBuvdG0zwA8mAHIDP';
$apiVersion = 'v26.0';
$jsonFile   = __DIR__ . '/posts.json';
// -----------------------------------------------------------------------------

function fb_post(string $pageId, string $token, string $message, string $version): array
{
    $url = "https://graph.facebook.com/{$version}/{$pageId}/feed";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'message'      => $message,
            'access_token' => $token,
        ]),
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

// Accept both {"message": "..."} objects and plain strings
if (is_array($item) && isset($item['message']) && is_string($item['message'])) {
    $message = trim($item['message']);
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

// --- Post it -----------------------------------------------------------------
$result = fb_post($pageId, $pageToken, $message, $apiVersion);

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
    'result'       => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
