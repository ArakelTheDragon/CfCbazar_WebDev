<?php
declare(strict_types=1);

function getDownloadRecord(string $trackingNumber): ?array {
    global $conn;

    updateTrackingJson();

    $stmt = $conn->prepare("SELECT * FROM tracking WHERE tracking_number = ? AND status <> 'pending' LIMIT 1");
    $stmt->bind_param("s", $trackingNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}
