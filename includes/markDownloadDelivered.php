<?php
declare(strict_types=1);

/**
 * ============================================================================
 * Marks download as delivered, records downloader email and timestamp,
 * and updates index.json on atwebpages.com
 * File: /includes/markDownloadDelivered.php
 * ============================================================================
 */
function markDownloadDelivered(int $id, string $emailDownloader): bool {
    global $conn;

    // Verify active MySQLi connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return false;
    }

    // Update status, set email_downloader, and update delivered_at timestamp
    $stmt = $conn->prepare("UPDATE tracking SET email_downloader = ?, status = 'delivered', delivered_at = NOW() WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("si", $emailDownloader, $id);
    $ok = $stmt->execute();
    $stmt->close();

    // Trigger JSON sync AFTER database status has successfully updated
    if ($ok && function_exists('updateTrackingJson')) {
        updateTrackingJson();
    }

    return $ok;
}
