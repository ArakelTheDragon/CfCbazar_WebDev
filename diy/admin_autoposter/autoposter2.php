<?php
// fb_auto_post.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// --- Config ---------------------------------------------------------------
$pageId    = '930733330124779';
$pageToken = 'EAAM8IqzH7bwBSjuhCC9VsDIF8ztzPQ0iPqh0di0BZA0cB6ZBRpc2e81ttpwBtOcavKs0BVJZAEnZC1hHNDzl0w1TLhZBIUbZChLORWMkgcy7Yl3yFghRNil87lfzneLu1xGzTfTHog8Wgy03mqdimyeCQxuZARxuvy6M4mbdp82lwwYLVL6525DZBuvdG0zwA8mAHIDP';
$jsonFile  = __DIR__ . '/posts.json';
$apiVersion = 'v26.0';
// --------------------------------------------------------------------------

function fb_post(string $pageId, string $token, string $message, string $version = 'v26.0'): array
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

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['id'])) {
        return ['ok' => true, 'post_id' => $decoded['id']];
    }

    return [
        'ok'    => false,
        'error' => $decoded['error']['message'] ?? "HTTP {$httpCode}",
    ];
}

// --- Read JSON ------------------------------------------------------------
if (!is_readable($jsonFile)) {
    echo json_encode(['error' => "JSON file not found: {$jsonFile}"]);
    exit;
}

$raw = file_get_contents($jsonFile);
$items = json_decode($raw, true);

if (!is_array($items)) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// --- Normalise to a list of messages -------------------------------------
// Accepts either:
//   ["message 1", "message 2"]
// or:
//   [{"message": "..."}, {"message": "..."}]
$messages = [];
foreach ($items as $item) {
    if (is_string($item) && trim($item) !== '') {
        $messages[] = trim($item);
    } elseif (is_array($item) && !empty($item['message']) && is_string($item['message'])) {
        $messages[] = trim($item['message']);
    }
}

if (empty($messages)) {
    echo json_encode(['error' => 'No valid messages in JSON']);
    exit;
}

// --- Post each message ----------------------------------------------------
$results = [];
foreach ($messages as $i => $msg) {
    $result = fb_post($pageId, $pageToken, $msg, $apiVersion);
    $results[] = [
        'index'   => $i,
        'message' => mb_substr($msg, 0, 80) . (mb_strlen($msg) > 80 ? '…' : ''),
        'result'  => $result,
    ];

    // Small delay to avoid rate limits
    usleep(500000); // 0.5s
}

echo json_encode(['posted' => count($results), 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
