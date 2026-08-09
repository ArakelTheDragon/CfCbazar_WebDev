<?php
declare(strict_types=1);

function getUserTracking(string $email): array {
    global $conn;

    updateTrackingJson();
    $rows = [];

    $stmt = $conn->prepare("SELECT id, tracking_number, product_name, status, created_at FROM tracking WHERE created_by = ? ORDER BY id DESC");
    if (!$stmt) {
        return $rows;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}
