<?php
declare(strict_types=1);

/**
 * Creates a new tracking record from POST payload.
 */
function process_create_tracking(): ?string {
    global $conn, $status;

    updateTrackingJson();

    if (($status ?? 0) <= 0) {
        return null;
    }

    if (!isset($_POST['create_tracking'])) {
        return null;
    }

    validateTrackingCSRF();

    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $downloadLink = trim($_POST['download_link'] ?? '');
    $creatorEmail = $_SESSION['email'] ?? '';

    if ($productName === '' || $downloadLink === '' || $creatorEmail === '') {
        return null;
    }

    do {
        $tracking = generateTrackingNumber();
        $exists = getTrackingRecord($tracking);
    } while ($exists !== null);

    $stmt = $conn->prepare("
        INSERT INTO tracking (tracking_number, product_name, description, download_link, status, created_by) 
        VALUES (?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->bind_param("sssss", $tracking, $productName, $description, $downloadLink, $creatorEmail);
    $stmt->execute();
    $stmt->close();

    return $tracking;
}
