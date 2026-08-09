<?php
declare(strict_types=1);

function getPendingCount(): int {
    global $conn;

    updateTrackingJson();

    $result = $conn->query("SELECT COUNT(*) AS total FROM tracking WHERE status='pending'");
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return (int)($row['total'] ?? 0);
}
