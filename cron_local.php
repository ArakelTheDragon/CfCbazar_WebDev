<?php
// cron_local.php

header('Content-Type: application/json');

// --- Record this cron hit ---------------------------------------------------
$file = __DIR__ . '/cron.json';

$now = [
    'last_hit'  => date('Y-m-d H:i:s'),
    'timestamp' => time(),
];

// --- Task runner -------------------------------------------------------------
// Each task returns 0 on success, 1 on failure.
$tasks = [];

/**
 * Helper: hit a URL and return [ok, httpCode, raw, curlErr, decoded]
 */
function cron_hit(string $url, int $timeout = 30): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'CfCbazar-Cron/1.0',
    ]);
    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    return [
        'ok'       => $curlErr === '' && $httpCode === 200,
        'httpCode' => $httpCode,
        'raw'      => (string)$raw,
        'curlErr'  => $curlErr,
        'decoded'  => json_decode((string)$raw, true),
    ];
}

// Task 1: Facebook autoposter
$autoposterUrl = 'https://cfcbazar.8bit.ca/diy/admin_autoposter/autoposter.php';
$autoposter    = cron_hit($autoposterUrl);

$autoposterOk = (
    $autoposter['curlErr'] === ''
    && $autoposter['httpCode'] === 200
    && is_array($autoposter['decoded'])
    && ($autoposter['decoded']['result']['ok'] ?? false) === true
);

$tasks['/diy/admin_autoposter/autoposter.php'] = $autoposterOk ? 0 : 1;

// Task 2: eBay order processor
$processOrdersUrl = 'https://cfcbazar.8bit.ca/diy/admin_delivery/process_orders.php';
$processOrders    = cron_hit($processOrdersUrl, 60);

// process_orders.php returns plain text, not JSON.
// Success indicators: HTTP 200 AND one of the two known success strings.
$processOrdersOk = (
    $processOrders['curlErr'] === ''
    && $processOrders['httpCode'] === 200
    && (
        strpos($processOrders['raw'], 'Orders processed successfully') !== false
        || strpos($processOrders['raw'], 'Polling complete: No new orders found') !== false
    )
);

$tasks['/diy/admin_delivery/process_orders.php'] = $processOrdersOk ? 0 : 1;

// Add more tasks below in the same pattern:
// $tasks['/other/function.php'] = $someCheck ? 0 : 1;

// --- Build the report -------------------------------------------------------
$report = [
    'last_hit'  => $now['last_hit'],
    'timestamp' => $now['timestamp'],
];

foreach ($tasks as $name => $status) {
    $report[$name] = $status;
}

// --- Persist and echo -------------------------------------------------------
file_put_contents(
    $file,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
