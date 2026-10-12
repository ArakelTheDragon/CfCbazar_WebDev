<?php
declare(strict_types=1);
/**
 * Consolidated Tracking Helpers
 */

// ===== from updateTrackingJson.php =====
/**
 * ============================================================================
 * Sync product tracking database table to index.json on atwebpages.com
 * File: /includes/updateTrackingJson.php
 * ============================================================================
 */

function updateTrackingJson() {
    global $conn;

    $apiKey = 'CfCbazar_Secure_Track_Key_2026_X9z';
    $remoteUrl = 'http://cfcbazar.atwebpages.com/diy/track/update.php';

    // Verify database connection is active
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return false;
    }

    // Query product tracking table with delivered_at
    $sql = "SELECT id, tracking_number, product_name, description, download_link, status, created_by, created_at, email_downloader, delivered_at 
            FROM tracking 
            ORDER BY id ASC";
    $result = $conn->query($sql);

    if (!$result) {
        return false;
    }

    $trackingData = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $trackingData[] = $row;
    }

    // Prepare JSON envelope
    $payloadData = [
        'status'         => 'success',
        'last_updated'   => date('Y-m-d H:i:s'),
        'total_tracking' => count($trackingData),
        'tracking'       => $trackingData
    ];

    $jsonPayload = json_encode($payloadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // Push JSON payload via HTTP POST to atwebpages.com
    $ch = curl_init($remoteUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-KEY: ' . $apiKey
        ],
        CURLOPT_TIMEOUT        => 5
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200);
}

// ===== from generateTrackingNumber.php =====
/**
 * CfCbazar Tracking Helper Library
 * File: /includes/generateTrackingNumber.php
 *
 * Generates purely numeric tracking numbers.
 */


if (!function_exists('generateTrackingNumber')) {
    /**
     * Generates a unique, purely numeric 10-digit tracking number.
     * Example output: 1234859204 or 8392019482
     *
     * @return string Purely numeric tracking number
     */
    function generateTrackingNumber(): string
    {
        // Generates a 10-digit random numeric string (between 1,000,000,000 and 9,999,999,999)
        return (string) random_int(1000000000, 9999999999);
    }
}

// ===== from formatTrackingNumber.php =====

function formatTrackingNumber(string $tracking): string {
    return trim($tracking);
}

// ===== from getTrackingRecord.php =====

/**
 * ============================================================================
 * Fetches a single tracking record row by its tracking number.
 * File: /includes/getTrackingRecord.php
 * ============================================================================
 */
function getTrackingRecord(string $trackingNumber): ?array {
    global $conn;

    // Verify active MySQLi database connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT id, tracking_number, product_name, description, download_link, status, created_by, created_at, email_downloader, delivered_at 
                            FROM tracking 
                            WHERE tracking_number = ? 
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $trackingNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        $row['id'] = (int)$row['id'];
    }

    return $row ?: null;
}

// ===== from findTracking.php =====

/**
 * Wrapper/Alias to locate a tracking entry by tracking number.
 */
function findTracking(string $trackingNumber): ?array {
    if (function_exists('getTrackingRecord')) {
        return getTrackingRecord($trackingNumber);
    }
    return null;
}

// ===== from getDownloadRecord.php =====

function getDownloadRecord(string $trackingNumber): ?array {
    global $conn;

    updateTrackingJson();

    $stmt = $conn->prepare("SELECT * FROM tracking WHERE tracking_number = ? AND status <> 'pending' LIMIT 1");
    $stmt->bind_param("s", $trackingNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

// ===== from getPendingTracking.php =====

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

// ===== from getAllTracking.php =====

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

// ===== from getUserTracking.php =====

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

// ===== from getPendingCount.php =====

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

// ===== from getTrackingCount.php =====

function getTrackingCount(): int {
    global $conn;

    updateTrackingJson();

    $result = $conn->query("SELECT COUNT(*) AS total FROM tracking");
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return (int)($row['total'] ?? 0);
}

// ===== from getLatestTracking.php =====

function getLatestTracking(int $limit = 10): array {
    updateTrackingJson();
    return array_slice(getAllTracking($limit), 0, $limit);
}

// ===== from getTrackingStatusLabel.php =====
/**
 * CfCbazar Tracking Helper Library
 * File: /includes/getTrackingStatusLabel.php
 *
 * Returns formatted status badges/labels based on tracking status keys.
 */


if (!function_exists('getTrackingStatusLabel')) {
    /**
     * Converts raw tracking status into styled HTML/text labels.
     *
     * @param string $status Current tracking status ('pending', 'in_transit', 'delivered', etc.)
     * @return string Formatted status label
     */
    function getTrackingStatusLabel(string $status): string
    {
        $statusKey = strtolower(trim($status));

        return match ($statusKey) {
            'pending' => '<span style="color:#ffc107;font-weight:bold;">⏳ Pending Approval</span>',
            'in_transit', 'approved' => '<span style="color:#17a2b8;font-weight:bold;">🚚 In Transit</span>',
            'delivered', 'completed' => '<span style="color:#28a745;font-weight:bold;">✅ Delivered</span>',
            'cancelled', 'rejected' => '<span style="color:#dc3545;font-weight:bold;">❌ Cancelled</span>',
            default => '<span style="color:#6c757d;font-weight:bold;">' . htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') . '</span>',
        };
    }
}

// ===== from canDownload.php =====

function canDownload(array $tracking): bool {
    updateTrackingJson();
    return isset($tracking['status']) && $tracking['status'] !== 'pending';
}

// ===== from isPending.php =====

function isPending(array $tracking): bool {
    updateTrackingJson();
    return ($tracking['status'] ?? '') === 'pending';
}

// ===== from isDelivered.php =====

function isDelivered(array $tracking): bool {
    updateTrackingJson();
    return ($tracking['status'] ?? '') === 'delivered';
}

// ===== from isInTransit.php =====

function isInTransit(array $tracking): bool {
    updateTrackingJson();
    return ($tracking['status'] ?? '') === 'in_transit';
}

// ===== from process_admin_approval.php =====

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

// ===== from process_create_tracking.php =====

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

// ===== from process_download.php =====
/**
 * CfCbazar Tracking & Download Helper Library
 * File: /includes/process_download.php
 *
 * Handles GET and POST processing for digital product download requests.
 */


if (!function_exists('process_download')) {
    /**
     * --------------------------------------------------------------------------
     * Download Handler
     * --------------------------------------------------------------------------
     *
     * Handles both:
     *
     * GET  ?download=xxxxx
     * -> Display email form
     *
     * POST ?download=xxxxx
     * -> Validate CSRF
     * -> Save downloader email
     * -> Redirect to download URL
     *
     * This function exits automatically when processing completes.
     * --------------------------------------------------------------------------
     *
     * @return void
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
}

// ===== from markDownloadDelivered.php =====

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

// ===== from validateTrackingCSRF.php =====

/**
 * Validates tracking POST requests against session CSRF token.
 */
function validateTrackingCSRF(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die("Invalid tracking CSRF token.");
        }
    }
}

// ===== from trackingDownloadUrl.php =====

function trackingDownloadUrl(string $tracking): string {
    updateTrackingJson();
    return '?download=' . urlencode($tracking);
}

// ===== from trackingApproveUrl.php =====

function trackingApproveUrl(int $id): string {
    updateTrackingJson();
    return '?approve=' . $id;
}

// ===== from trackingLookupUrl.php =====

function trackingLookupUrl(string $tracking): string {
    updateTrackingJson();
    return '?track=' . urlencode($tracking);
}

if (!function_exists('trackVisit')) {
    /**
     * Tracks a page visit in the `pages` table.
     *
     * Uses UPDATE-first / INSERT-if-missing so that repeat visits to an
     * existing page do NOT consume AUTO_INCREMENT values. Only the first
     * visit to a new page allocates a new id.
     *
     * @param string      $slug        Page slug, filename, or path
     * @param string|null $parentUrl   Parent page URL like "/diy/" (null = top-level)
     * @param string|null $title       Human-readable title (auto-generated if null)
     * @param string|null $template    Template name
     * @param string|null $metaTitle   SEO meta title
     * @param string|null $metaDesc    SEO meta description
     * @return void
     */
    function trackVisit(
        string $slug,
        ?string $parentUrl = null,
        ?string $title = null,
        ?string $template = null,
        ?string $metaTitle = null,
        ?string $metaDesc = null
    ): void {
        global $conn;

        // Only track GET page loads
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }

        // --- Normalize the slug ------------------------------------------
        $slug = trim($slug);
        $slug = preg_split('/[?#]/', $slug, 2)[0] ?? '';
        $slug = preg_replace('#/+#', '/', $slug) ?? $slug;
        $slug = trim($slug, '/');

        if (str_contains($slug, '://')) {
            $slug = preg_replace('#^[a-z]+://[^/]+/#i', '', $slug) ?? $slug;
        }

        $slug = mb_substr($slug, 0, 255);
        if ($slug === '') {
            $slug = 'index.php';
        }

        // --- Normalize the parent URL into a stored string ---------------
        // Stored form: "/diy" — leading slash, no trailing slash.
        $parentId = null;

        if ($parentUrl !== null && trim($parentUrl) !== '') {
            $pSlug = trim($parentUrl);
            $pSlug = preg_split('/[?#]/', $pSlug, 2)[0] ?? '';
            $pSlug = preg_replace('#/+#', '/', $pSlug) ?? $pSlug;
            $pSlug = trim($pSlug, '/');

            if (str_contains($pSlug, '://')) {
                $pSlug = preg_replace('#^[a-z]+://[^/]+/#i', '', $pSlug) ?? $pSlug;
            }

            if ($pSlug !== '') {
                $parentId = '/' . $pSlug;
                $parentId = mb_substr($parentId, 0, 255);
            }
        }

        // --- Default title if none supplied ------------------------------
        if ($title === null || trim($title) === '') {
            $title = ucfirst(str_replace(['.php', '_', '-'], ['', ' ', ' '], $slug));
        }
        $title = mb_substr($title, 0, 255);

        // --- Path matches DB format --------------------------------------
        $path = '/' . $slug;

        // --- Normalize optional strings ----------------------------------
        if ($template === '' || $template === null) {
            $template = null;
        } else {
            $template = mb_substr($template, 0, 100);
        }

        if ($metaTitle === '' || $metaTitle === null) {
            $metaTitle = null;
        } else {
            $metaTitle = mb_substr($metaTitle, 0, 255);
        }

        if ($metaDesc === '' || $metaDesc === null) {
            $metaDesc = null;
        }

        // --- Referrer ----------------------------------------------------
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $referrer = is_string($referrer) ? mb_substr($referrer, 0, 255) : null;

        // ================================================================
        // Step 1: UPDATE the existing row (this is the common case).
        // `visits = visits + 1` always changes the value, so affected_rows
        // reliably reports whether a matching row existed.
        // ================================================================
        $upd = $conn->prepare("
            UPDATE pages
            SET
                title         = ?,
                parent_id     = ?,
                template      = ?,
                meta_title    = ?,
                meta_desc     = ?,
                status        = 'published',
                visits        = visits + 1,
                last_referrer = ?,
                updated_at    = NOW()
            WHERE slug = ?
        ");

        if ($upd) {
            $upd->bind_param(
                'sssssss',
                $title,
                $parentId,
                $template,
                $metaTitle,
                $metaDesc,
                $referrer,
                $slug
            );
            $upd->execute();
            $updatedRows = $upd->affected_rows;
            $upd->close();

            // If a row was matched, we're done — no id consumed.
            if ($updatedRows > 0) {
                return;
            }
        }

        // ================================================================
        // Step 2: No existing row — INSERT one. This is the only place
        // a new AUTO_INCREMENT id gets allocated.
        // ================================================================
        $ins = $conn->prepare("
            INSERT INTO pages
                (title, slug, path, parent_id, template, meta_title, meta_desc,
                 status, visits, last_referrer, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 'published', 1, ?, NOW(), NOW())
        ");

        if ($ins) {
            $ins->bind_param(
                'ssssssss',
                $title,
                $slug,
                $path,
                $parentId,
                $template,
                $metaTitle,
                $metaDesc,
                $referrer
            );
            $ins->execute();
            $ins->close();
        }
    }
}

// ===== from track_bootstrap.php =====
/**
 * CfCbazar Tracking Helper Library
 * File: /includes/track_bootstrap.php
 *
 * Bootstraps security enforcement, database connection, session tracking,
 * and user status initialization for the tracking module.
 */


if (!function_exists('track_bootstrap')) {
    /**
     * Initializes security, system checks, database connection, visit logging,
     * and populates global session user state.
     *
     * @return void
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
}

// ===== from track_shutdown.php =====

/**
 * Handles post-execution shutdown and cleanup for the tracking page.
 */
function track_shutdown(): void {
    // Optional cleanup tasks after page rendering completes
}
