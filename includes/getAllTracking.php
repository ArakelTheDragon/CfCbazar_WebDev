<?php
declare(strict_types=1);

function getAllTracking(int $limit = 100): array {
    global $conn;

    updateTrackingJson();
    $limit = max(1, (int)$limit);
    $rows = [];

    $result = $conn->query("SELECT tracking_number, product_name, status FROM tracking ORDER BY id DESC LIMIT {$limit}");
    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();

    return $rows;
}
