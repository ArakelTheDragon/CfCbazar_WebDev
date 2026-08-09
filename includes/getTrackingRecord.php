<?php
declare(strict_types=1);

/**
 * ============================================================================
 * Fetches a single tracking record row by its tracking number.
 * File: /includes/getTrackingRecord.php
 * ============================================================================
 */
function getTrackingRecord(string $trackingNumber): ?array {
    global $conn;

    // Verify active MySQLi database connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT id, tracking_number, product_name, description, download_link, status, created_by, created_at, email_downloader, delivered_at 
                            FROM tracking 
                            WHERE tracking_number = ? 
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $trackingNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        $row['id'] = (int)$row['id'];
    }

    return $row ?: null;
}
