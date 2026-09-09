<?php
/**
 * ============================================================================
 * Sync product tracking database table to index.json on atwebpages.com
 * File: /includes/updateTrackingJson.php
 * ============================================================================
 */

function updateTrackingJson() {
    global $conn;

    $apiKey = 'CfCbazar_Secure_Track_Key_2026_X9z';
    $remoteUrl = 'http://cfcbazar.atwebpages.com/diy/track/update.php';

    // Verify database connection is active
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return false;
    }

    // Query product tracking table with delivered_at
    $sql = "SELECT id, tracking_number, product_name, description, download_link, status, created_by, created_at, email_downloader, delivered_at 
            FROM tracking 
            ORDER BY id ASC";
    $result = $conn->query($sql);

    if (!$result) {
        return false;
    }

    $trackingData = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $trackingData[] = $row;
    }

    // Prepare JSON envelope
    $payloadData = [
        'status'         => 'success',
        'last_updated'   => date('Y-m-d H:i:s'),
        'total_tracking' => count($trackingData),
        'tracking'       => $trackingData
    ];

    $jsonPayload = json_encode($payloadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // Push JSON payload via HTTP POST to atwebpages.com
    $ch = curl_init($remoteUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-KEY: ' . $apiKey
        ],
        CURLOPT_TIMEOUT        => 5
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200);
}
