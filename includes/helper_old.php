<?php
/**
 * ============================================================================
 * CfCbazar Digital Tracking Helper Library
 * File: /track/helper.php
 * Part 1 of 3
 *
 * Shared bootstrap, helper functions and download processing.
 * ============================================================================
 */

if (!defined('CFC_TRACK_HELPER')) {
    define('CFC_TRACK_HELPER', true);
}

/**
 * --------------------------------------------------------------------------
 * Bootstrap
 * --------------------------------------------------------------------------
 */
function track_bootstrap(): void
{
    global $conn;
    global $email;
    global $status;
    global $is_logged_in;

    enforce_https();

    checkSystemFlags();

    require_database_connection();

    trackVisit('track-main');

    session_check();

    $email = $_SESSION['email'] ?? null;

    $is_logged_in = is_logged_in($email, true);

    $status = getUserStatus($email);
}

/**
 * --------------------------------------------------------------------------
 * Generate Tracking Number
 * --------------------------------------------------------------------------
 */
function generateTrackingNumber(): string
{
    return '1234' . random_int(100000, 999999);
}

/**
 * --------------------------------------------------------------------------
 * Validate CSRF Token
 * --------------------------------------------------------------------------
 */
function validateTrackingCSRF(): void
{
    $session = $_SESSION['csrf_token'] ?? '';
    $posted  = $_POST['csrf_token'] ?? '';

    if (
        empty($session) ||
        empty($posted) ||
        !hash_equals($session, $posted)
    ) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

/**
 * --------------------------------------------------------------------------
 * Fetch Tracking Record
 * --------------------------------------------------------------------------
 */
function getTrackingRecord(string $trackingNumber): ?array
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT *
        FROM tracking
        WHERE tracking_number = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $trackingNumber);
    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $stmt->close();

    return $row ?: null;
}

/**
 * --------------------------------------------------------------------------
 * Fetch Download Record
 * --------------------------------------------------------------------------
 */
function getDownloadRecord(string $trackingNumber): ?array
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT *
        FROM tracking
        WHERE tracking_number = ?
        AND status <> 'pending'
        LIMIT 1
    ");

    $stmt->bind_param("s", $trackingNumber);
    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $stmt->close();

    return $row ?: null;
}

/**
 * --------------------------------------------------------------------------
 * Mark Download Delivered
 * --------------------------------------------------------------------------
 */
function markDownloadDelivered(
    int $id,
    string $emailDownloader
): bool
{
    global $conn;

    $stmt = $conn->prepare("
        UPDATE tracking
        SET
            email_downloader = ?,
            status = 'delivered'
        WHERE id = ?
    ");

    $stmt->bind_param(
        "si",
        $emailDownloader,
        $id
    );

    $ok = $stmt->execute();

    $stmt->close();

    return $ok;
}

/**
 * --------------------------------------------------------------------------
 * Download Handler
 * --------------------------------------------------------------------------
 *
 * Handles both:
 *
 *  GET  ?download=xxxxx
 *      -> Display email form
 *
 *  POST ?download=xxxxx
 *      -> Validate CSRF
 *      -> Save downloader email
 *      -> Redirect to download URL
 *
 * This function exits automatically when processing completes.
 * --------------------------------------------------------------------------
 */
function process_download(): void
{
    if (!isset($_GET['download'])) {
        return;
    }

    $track = trim($_GET['download']);

    /**
     * --------------------------------------------------------------
     * First request (GET)
     * --------------------------------------------------------------
     */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        include_header("Download Digital Product");
        include_menu();
        render_top_userbar();

        ?>
        <main class="container">

            <section class="card">

                <h2>Digital Download</h2>

                <p>
                    Enter your email address to continue.
                </p>

                <form method="post">

                    <label>Email Address</label>

                    <input
                        type="email"
                        name="email_downloader"
                        required
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                    >

                    <button type="submit">
                        Continue
                    </button>

                </form>

            </section>

        </main>
        <?php

        include_footer();

        close_database();

        exit;
    }

    /**
     * --------------------------------------------------------------
     * POST
     * --------------------------------------------------------------
     */

    validateTrackingCSRF();

    $emailDownloader = trim(
        $_POST['email_downloader'] ?? ''
    );

    if ($emailDownloader === '') {
        exit('Email address is required.');
    }

    $record = getDownloadRecord($track);

    if (!$record) {
        exit('Tracking number not found or awaiting approval.');
    }

    markDownloadDelivered(
        (int)$record['id'],
        $emailDownloader
    );

    header(
        'Location: ' . $record['download_link']
    );

    exit;
}

/**
 * ============================================================================
 * CfCbazar Digital Tracking Helper Library
 * File: /track/helper.php
 * Part 2 of 3
 *
 * Admin approval, tracking creation and lookup functions.
 * Continue directly after Part 1.
 * ============================================================================
 */

/**
 * --------------------------------------------------------------------------
 * Admin Approval
 * --------------------------------------------------------------------------
 *
 * Changes:
 *
 * pending
 *      ↓
 * in_transit
 *
 * Admin only.
 * --------------------------------------------------------------------------
 */
function process_admin_approval(): void
{
    global $conn;
    global $status;

    if ($status !== 1) {
        return;
    }

    if (!isset($_GET['approve'])) {
        return;
    }

    $id = (int)$_GET['approve'];

    if ($id <= 0) {
        return;
    }

    $stmt = $conn->prepare("
        UPDATE tracking
        SET status='in_transit'
        WHERE id=?
        AND status='pending'
    ");

    $stmt->bind_param("i", $id);

    $stmt->execute();

    $stmt->close();

    header("Location: ./");
    exit;
}

/**
 * --------------------------------------------------------------------------
 * Create Tracking
 * --------------------------------------------------------------------------
 *
 * Returns:
 *
 * null
 *      No tracking created
 *
 * string
 *      Newly generated tracking number
 * --------------------------------------------------------------------------
 */
function process_create_tracking(): ?string
{
    global $conn;
    global $status;

    if ($status <= 0) {
        return null;
    }

    if (!isset($_POST['create_tracking'])) {
        return null;
    }

    validateTrackingCSRF();

    $productName = trim(
        $_POST['product_name'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $downloadLink = trim(
        $_POST['download_link'] ?? ''
    );

    $creatorEmail = $_SESSION['email'] ?? '';

    if (
        $productName === '' ||
        $downloadLink === '' ||
        $creatorEmail === ''
    ) {
        return null;
    }

    /**
     * Generate unique tracking number
     */

    do {

        $tracking = generateTrackingNumber();

        $exists = getTrackingRecord($tracking);

    } while ($exists !== null);

    $stmt = $conn->prepare("
        INSERT INTO tracking
        (
            tracking_number,
            product_name,
            description,
            download_link,
            status,
            created_by
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            'pending',
            ?
        )
    ");

    $stmt->bind_param(
        "sssss",
        $tracking,
        $productName,
        $description,
        $downloadLink,
        $creatorEmail
    );

    $stmt->execute();

    $stmt->close();

    return $tracking;
}

/**
 * --------------------------------------------------------------------------
 * Lookup Tracking Status
 * --------------------------------------------------------------------------
 *
 * Returns one tracking record or null.
 * --------------------------------------------------------------------------
 */
function findTracking(
    string $trackingNumber
): ?array
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            tracking_number,
            product_name,
            description,
            status
        FROM tracking
        WHERE tracking_number=?
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $trackingNumber
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $stmt->close();

    return $row ?: null;
}

/**
 * --------------------------------------------------------------------------
 * Return Public Tracking Status
 * --------------------------------------------------------------------------
 *
 * Returns:
 *
 * Not found
 * Pending
 * In transit
 * Delivered
 * --------------------------------------------------------------------------
 */
function getTrackingStatusLabel(
    string $status
): string
{
    return match ($status) {

        'pending' => 'Waiting for admin approval',

        'in_transit' => 'In transit',

        'delivered' => 'Delivered',

        default => 'Unknown'

    };
}

/**
 * --------------------------------------------------------------------------
 * Can Download?
 * --------------------------------------------------------------------------
 */
function canDownload(
    array $tracking
): bool
{
    return
        isset($tracking['status']) &&
        $tracking['status'] !== 'pending';
}

/**
 * --------------------------------------------------------------------------
 * Is Pending?
 * --------------------------------------------------------------------------
 */
function isPending(
    array $tracking
): bool
{
    return
        ($tracking['status'] ?? '') === 'pending';
}

/**
 * --------------------------------------------------------------------------
 * Is Delivered?
 * --------------------------------------------------------------------------
 */
function isDelivered(
    array $tracking
): bool
{
    return
        ($tracking['status'] ?? '') === 'delivered';
}

/**
 * --------------------------------------------------------------------------
 * Is In Transit?
 * --------------------------------------------------------------------------
 */
function isInTransit(
    array $tracking
): bool
{
    return
        ($tracking['status'] ?? '') === 'in_transit';
}

/**
 * ============================================================================
 * CfCbazar Digital Tracking Helper Library
 * File: /track/helper.php
 * Part 3 of 3
 *
 * Listing functions and helper utilities.
 * Continue directly after Part 2.
 * ============================================================================
 */

/**
 * --------------------------------------------------------------------------
 * Get Pending Tracking Entries
 * --------------------------------------------------------------------------
 *
 * Returns:
 *   array
 * --------------------------------------------------------------------------
 */
function getPendingTracking(): array
{
    global $conn;

    $rows = [];

    $result = $conn->query("
        SELECT
            id,
            tracking_number,
            product_name
        FROM tracking
        WHERE status='pending'
        ORDER BY id DESC
    ");

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $result->free();

    return $rows;
}

/**
 * --------------------------------------------------------------------------
 * Get All Tracking Entries
 * --------------------------------------------------------------------------
 *
 * Returns:
 *   array
 * --------------------------------------------------------------------------
 */
function getAllTracking(int $limit = 100): array
{
    global $conn;

    $limit = max(1, (int)$limit);

    $rows = [];

    $result = $conn->query("
        SELECT
            tracking_number,
            product_name,
            status
        FROM tracking
        ORDER BY id DESC
        LIMIT {$limit}
    ");

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $result->free();

    return $rows;
}

/**
 * --------------------------------------------------------------------------
 * Count Pending Entries
 * --------------------------------------------------------------------------
 */
function getPendingTrackingCount(): int
{
    global $conn;

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM tracking
        WHERE status='pending'
    ");

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    $result->free();

    return (int)($row['total'] ?? 0);
}

/**
 * --------------------------------------------------------------------------
 * Count Total Entries
 * --------------------------------------------------------------------------
 */
function getTrackingCount(): int
{
    global $conn;

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM tracking
    ");

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    $result->free();

    return (int)($row['total'] ?? 0);
}

/**
 * --------------------------------------------------------------------------
 * Get Latest Tracking Entries
 * --------------------------------------------------------------------------
 */
function getLatestTracking(int $limit = 10): array
{
    return array_slice(
        getAllTracking($limit),
        0,
        $limit
    );
}

/**
 * --------------------------------------------------------------------------
 * Escape HTML
 * --------------------------------------------------------------------------
 *
 * Convenience helper for output.
 * --------------------------------------------------------------------------
 */
function e(?string $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * --------------------------------------------------------------------------
 * Format Tracking Number
 * --------------------------------------------------------------------------
 *
 * Future-proof wrapper in case formatting changes.
 * --------------------------------------------------------------------------
 */
function formatTrackingNumber(string $tracking): string
{
    return trim($tracking);
}

/**
 * --------------------------------------------------------------------------
 * Build Download URL
 * --------------------------------------------------------------------------
 */
function trackingDownloadUrl(string $tracking): string
{
    return '?download=' . urlencode($tracking);
}

/**
 * --------------------------------------------------------------------------
 * Build Approval URL
 * --------------------------------------------------------------------------
 */
function trackingApproveUrl(int $id): string
{
    return '?approve=' . $id;
}

/**
 * --------------------------------------------------------------------------
 * Build Tracking URL
 * --------------------------------------------------------------------------
 */
function trackingLookupUrl(string $tracking): string
{
    return '?track=' . urlencode($tracking);
}

/**
 * --------------------------------------------------------------------------
 * Close Tracking Module
 * --------------------------------------------------------------------------
 *
 * Call once at the bottom of index.php.
 * --------------------------------------------------------------------------
 */
function track_shutdown(): void
{
    close_database();
}

/**
 * ============================================================================
 * End of helper.php
 * ============================================================================
 */