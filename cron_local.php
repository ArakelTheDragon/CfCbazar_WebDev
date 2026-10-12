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

// Task 1: Facebook autoposter
$autoposterUrl = 'https://cfcbazar.8bit.ca/diy/admin_autoposter/autoposter.php';

$ch = curl_init($autoposterUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => 'CfCbazar-Cron/1.0',
]);
$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

$decoded   = json_decode((string)$raw, true);
$autoposterOk = (
    $curlErr === ''
    && $httpCode === 200
    && is_array($decoded)
    && ($decoded['result']['ok'] ?? false) === true
);

$tasks['/diy/admin_autoposter/autoposter.php'] = $autoposterOk ? 0 : 1;

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
