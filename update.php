<?php
// update.php - InfinityFree
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/reusable.php';

$target_url   = "http://cfcbazar.atwebpages.com/save_json.php";
$secret_token = $cron_secret_token ?? "cfc_secure_cron_pass_2026";

// 1. Fetch data using local SQL connection ($conn)
$exportData = [];
$result = $conn->query("SELECT * FROM workers");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $exportData[] = $row;
    }
}

// 2. Build JSON payload
$payload = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'records'   => $exportData
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// 3. Post JSON payload directly to save_json.php on AwardSpace
$ch = curl_init($target_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-CRON-TOKEN: ' . $secret_token
    ]
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// 4. Output execution status
echo json_encode([
    'status'            => ($http_code === 200) ? 'success' : 'failed',
    'http_code'         => $http_code,
    'curl_error'        => $curl_error,
    'awardspace_output' => json_decode($response, true) ?? $response
]);
