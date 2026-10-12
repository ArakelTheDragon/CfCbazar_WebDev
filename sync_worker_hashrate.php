<?php
// sync_worker_hashrate.php

require 'config.php'; // Your database connection file

$apiUrl = 'web.gonspool.com/api/accounts/0xe8911e98a00d36a1841945d6270611510f1c7e88';

// Function to fetch data using cURL
function fetchGonspoolData($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Optional: disable SSL verification
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "cURL error: " . curl_error($ch);
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return $response;
}

// Fetch and decode JSON
$response = fetchGonspoolData($apiUrl);
if (!$response) {
    die("Failed to fetch data from Gonspool.");
}

$data = json_decode($response, true);
if (!isset($data['workers']) || !is_array($data['workers'])) {
    die("Invalid API response structure.");
}

// Loop through Gonspool workers and update hr2 in database
foreach ($data['workers'] as $workerId => $stats) {
    $id = intval($workerId);           // Convert worker key to integer
    $hashrate = intval($stats['hr2']); // Stable hashrate value

    // Update hr2 for matching worker ID
    $stmt = $conn->prepare("UPDATE workers SET hr2 = ? WHERE id = ?");
    $stmt->bind_param("ii", $hashrate, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "✅ Updated worker ID $id with hr2 = $hashrate\n";
    } else {
        echo "⚠️ Worker ID $id not found in database\n";
    }

    $stmt->close();
}

echo "✅ Hashrate sync complete.\n";
?>