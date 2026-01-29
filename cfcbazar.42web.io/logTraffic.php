<?php
function logTraffic($logFile = 'traffic_log.json') {
    $entry = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'time' => date('Y-m-d H:i:s'),
    ];

    // Load existing log
    $log = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];

    // Append new entry
    $log[] = $entry;

    // Save updated log
    file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
}