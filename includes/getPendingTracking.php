<?php
declare(strict_types=1);

function getPendingTracking(): array {
    global $conn;

    updateTrackingJson();
    $rows = [];

    $result = $conn->query("SELECT id, tracking_number, product_name FROM tracking WHERE status='pending' ORDER BY id DESC");
    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();

    return $rows;
}
