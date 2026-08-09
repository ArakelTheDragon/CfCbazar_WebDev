<?php
declare(strict_types=1);

/**
 * Marks download as delivered and sets the downloader email.
 */
function markDownloadDelivered(int $id, string $emailDownloader): bool {
    global $conn;

    updateTrackingJson();

    $stmt = $conn->prepare("UPDATE tracking SET email_downloader = ?, status = 'delivered' WHERE id = ?");
    $stmt->bind_param("si", $emailDownloader, $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}
