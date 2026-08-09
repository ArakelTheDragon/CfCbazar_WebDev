<?php
declare(strict_types=1);

/**
 * Processes administrator approval for pending tracking numbers.
 */
function process_admin_approval(): void {
    global $conn, $status;

    updateTrackingJson();

    if (($status ?? 0) !== 1) {
        return;
    }

    if (!isset($_GET['approve'])) {
        return;
    }

    $id = (int)$_GET['approve'];
    if ($id <= 0) {
        return;
    }

    $stmt = $conn->prepare("UPDATE tracking SET status='in_transit' WHERE id=? AND status='pending'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: ./");
    exit;
}
