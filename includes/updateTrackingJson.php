<?php
declare(strict_types=1);

/**
 * Updates the local index.json file with current tracking entries from MySQL database.
 *
 * @param string|null $filePath Custom path to index.json (defaults to /diy/track/index.json)
 * @return bool True on successful file write, false on failure
 */
function updateTrackingJson(?string $filePath = null): bool {
    global $conn;

    // Default target path if not provided
    if ($filePath === null) {
        $filePath = __DIR__ . '/../diy/track/index.json';
    }

    // Ensure the destination directory exists
    $directory = dirname($filePath);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            error_log("updateTrackingJson(): Failed to create directory: {$directory}");
            return false;
        }
    }

    // Fetch tracking entries from the database
    $sql = "SELECT id, tracking_number, product_name, description, download_link, status, created_by, created_at 
            FROM tracking 
            ORDER BY id DESC";

    $result = $conn->query($sql);

    if (!$result) {
        error_log("updateTrackingJson(): DB Query failed - " . $conn->error);
        return false;
    }

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $result->free();

    // Encode payload as pretty-printed JSON
    $jsonOutput = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($jsonOutput === false) {
        error_log("updateTrackingJson(): JSON encoding error - " . json_last_error_msg());
        return false;
    }

    // Save payload to file with an exclusive lock (LOCK_EX) to prevent race conditions
    if (file_put_contents($filePath, $jsonOutput, LOCK_EX) === false) {
        error_log("updateTrackingJson(): Failed writing to {$filePath}");
        return false;
    }

    return true;
}
