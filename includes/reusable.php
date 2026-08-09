<?php
/**
 * CfCbazar Master Function Index & Core Bootstrapper
 * File: /includes/reusable.php
 * Compatible with PHP 8.2+
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require Master Configuration
require_once __DIR__ . '/../config.php';

// ============================================================================
// INCLUDES REGISTRY
// ============================================================================
// ============================================================================
// --- 🔄 Data Sync & Tracking Helpers ---
// ============================================================================

// updateTrackingJson() -> Exports database tracking entries to index.json for flat-file JSON syncing
require_once __DIR__ . '/updateTrackingJson.php';

// track_bootstrap() -> Boots session, enforcement checks, DB validation, and page visit logging
require_once __DIR__ . '/track_bootstrap.php';

// track_shutdown() -> Handles script termination, cleanup, and safe DB connection closing
require_once __DIR__ . '/track_shutdown.php';

// validateTrackingCSRF() -> Validates CSRF token submitted in tracking POST forms
require_once __DIR__ . '/validateTrackingCSRF.php';

// generateTrackingNumber() -> string: Generates a unique 10-digit numeric tracking ID
require_once __DIR__ . '/generateTrackingNumber.php';

// e($value) -> string: Escapes HTML special characters for safe output (htmlspecialchars wrapper)
require_once __DIR__ . '/e.php';


// ============================================================================
// --- ⚡ Handlers & Processors ---
// ============================================================================

// process_admin_approval() -> Processes admin actions approving pending tracking items ('pending' -> 'in_transit')
require_once __DIR__ . '/process_admin_approval.php';

// process_create_tracking() -> Handles form submission to create a new digital tracking item
require_once __DIR__ . '/process_create_tracking.php';

// process_download() -> Displays download email form (GET) and processes product link redirects (POST)
require_once __DIR__ . '/process_download.php';

// markDownloadDelivered($id, $email) -> bool: Marks record status as 'delivered' and updates buyer email
require_once __DIR__ . '/markDownloadDelivered.php';


// ============================================================================
// --- 🗄️ Database Queries & Fetchers ---
// ============================================================================

// getTrackingRecord($trackingNumber) -> array|null: Fetches a single tracking record row by its tracking number
require_once __DIR__ . '/getTrackingRecord.php';

// findTracking($query) -> array: Searches tracking records by tracking number or search query string
require_once __DIR__ . '/findTracking.php';

// getDownloadRecord($trackingNumber) -> array|null: Fetches an approved/ready tracking record for download verify
require_once __DIR__ . '/getDownloadRecord.php';

// getPendingTracking() -> array: Fetches all tracking items currently awaiting admin approval ('pending')
require_once __DIR__ . '/getPendingTracking.php';

// getAllTracking($limit = 50) -> array: Fetches all tracking records from the database
require_once __DIR__ . '/getAllTracking.php';

// getUserTracking($email) -> array: Fetches all tracking records created by a specific user email
require_once __DIR__ . '/getUserTracking.php';

// getPendingCount() -> int: Returns total count of items awaiting admin approval (for badges/alerts)
require_once __DIR__ . '/getPendingCount.php';

// getTrackingCount() -> int: Returns total count of all tracking entries stored in the database
require_once __DIR__ . '/getTrackingCount.php';

// getLatestTracking($limit = 10) -> array: Fetches the most recently created tracking records
require_once __DIR__ . '/getLatestTracking.php';


// ============================================================================
// --- 🏷️ Status Logic & Labeling ---
// ============================================================================

// getTrackingStatusLabel($status) -> string: Returns styled HTML status badge ('Pending', 'In Transit', 'Delivered')
require_once __DIR__ . '/getTrackingStatusLabel.php';

// canDownload($tracking) -> bool: Returns true if tracking record status is approved and ready (not 'pending')
require_once __DIR__ . '/canDownload.php';

// isPending($tracking) -> bool: Returns true if tracking record is currently pending admin approval
require_once __DIR__ . '/isPending.php';

// isDelivered($tracking) -> bool: Returns true if tracking item status is marked as 'delivered'
require_once __DIR__ . '/isDelivered.php';

// isInTransit($tracking) -> bool: Returns true if tracking item status is approved and 'in_transit'
require_once __DIR__ . '/isInTransit.php';


// ============================================================================
// --- 🔗 Formatting & Link Builders ---
// ============================================================================

// formatTrackingNumber($tracking) -> string: Standardizes and formats raw tracking numbers for UI output
require_once __DIR__ . '/formatTrackingNumber.php';

// trackingDownloadUrl($tracking) -> string: Generates public product download URL query string (?download=xxxx)
require_once __DIR__ . '/trackingDownloadUrl.php';

// trackingApproveUrl($id) -> string: Generates admin approval link query string (?approve=id)
require_once __DIR__ . '/trackingApproveUrl.php';

// trackingLookupUrl($tracking) -> string: Generates public tracking lookup query string (?track=xxxx)
require_once __DIR__ . '/trackingLookupUrl.php';


// ============================================================================
// --- 🎨 Layout, UI & Teasers ---
// ============================================================================

// include_header() -> Renders standard site HTML <head> header and global stylesheet links
require_once __DIR__ . '/include_header.php';

// include_menu() -> Renders central navigation bar and dropdown links
require_once __DIR__ . '/include_menu.php';

// include_footer() -> Renders site footer markup and closing HTML tags
require_once __DIR__ . '/include_footer.php';

// render_ecosystem_content() -> Renders main CfCbazar platform hub cards and feature teasers
require_once __DIR__ . '/render_ecosystem_content.php';

// render_device_table($email) -> Renders active mining hardware device table for user dashboard
require_once __DIR__ . '/render_device_table.php';

// renderCaptchaIfNeeded() -> Renders anti-bot CAPTCHA verification challenge if triggered
require_once __DIR__ . '/renderCaptchaIfNeeded.php';

// render_worktoken_dashboard($email) -> Renders WTK/WorkTHR balance overview and staking dashboard widget
require_once __DIR__ . '/render_worktoken_dashboard.php';

// render_token_price_tracker() -> Renders live cryptocurrency exchange rate price ticker card
require_once __DIR__ . '/render_token_price_tracker.php';

// show_disabled_message($featureName) -> Renders alert box when a module is temporarily disabled
require_once __DIR__ . '/show_disabled_message.php';

// displayServerStatus() -> Renders live server health, database state, and service connectivity badges
require_once __DIR__ . '/displayServerStatus.php';

// render_top_userbar() -> Renders fixed sticky top bar with user email, role rank, and live token balances
require_once __DIR__ . '/render_top_userbar.php';

// cfc_footer($githubUrl, $toolName) -> Renders GitHub open-source repository link box
require_once __DIR__ . '/cfc_footer.php';

// Container file for render_withdraw_link(), render_workthr_teaser(), render_worktoken_teaser(), rfTradeWorkTokens()
require_once __DIR__ . '/ui_teaser_renderers.php';

// showAdvertPopup() -> Renders promotional popup modal overlay for visitors
require_once __DIR__ . '/showAdvertPopup.php';


// ============================================================================
// --- ⛏️ Worker & Mining Operations ---
// ============================================================================

// getWorkerStats($email) -> array: Retrieves worker active status, total shares, hashrate, and earned tokens
require_once __DIR__ . '/getWorkerStats.php';

// addTokens($email, $amount, $type = 'WTK') -> bool: Credits earned WTK or WorkTHR tokens to a worker account
require_once __DIR__ . '/addTokens.php';

// setLevel($email, $level) -> bool: Updates worker account level in database
require_once __DIR__ . '/setLevel.php';

// checkLevelUp($email) -> bool: Evaluates worker accumulated XP against level thresholds and handles level ups
require_once __DIR__ . '/checkLevelUp.php';

// handleMinerReward($email, $rewardType, $accepted, $mac, $active) -> Processes POST mining share reward submissions
require_once __DIR__ . '/handleMinerReward.php';

// renderMinerScript() -> Injects frontend browser mining JavaScript web-worker engine into HTML
require_once __DIR__ . '/renderMinerScript.php';

// renderMinerClient() -> Renders web browser miner user interface controls (start/stop/threads)
require_once __DIR__ . '/renderMinerClient.php';


// ============================================================================
// --- ⚙️ Gear Systems ---
// ============================================================================

// _valid_gear_slots() -> array: Returns allowed equipment slot identifiers (CPU, GPU, RAM, RIG, etc.)
require_once __DIR__ . '/_valid_gear_slots.php';

// upgradeGearSlot($email, $slot, $amount) -> bool: Upgrades a specific equipment slot for a user
require_once __DIR__ . '/upgradeGearSlot.php';

// upgradeRandomGear($email, $amount) -> string: Selects and upgrades a random gear slot
require_once __DIR__ . '/upgradeRandomGear.php';

// upgradeAllGear($email, $amount) -> bool: Upgrades all equipped gear slots simultaneously
require_once __DIR__ . '/upgradeAllGear.php';


// ============================================================================
// --- 🏆 Quests, Achievements & User Accounts ---
// ============================================================================

// syncQuestsAchievementsAndRewards($email) -> Evaluates quest progress, unlocks achievements, awards bonuses
require_once __DIR__ . '/syncQuestsAchievementsAndRewards.php';

// getUserStatus($email) -> int: Returns user role level (1=Admin, 2=Mod, 3=Contributor, 4=VIP, 5=User, 0=Guest)
require_once __DIR__ . '/getUserStatus.php';

// Container file for session_check(), is_logged_in(), csrf_token(), and logout_user()
require_once __DIR__ . '/auth_session_helpers.php';

// logoutUser() -> Destroys active user session, clears cookies, and redirects to login page
require_once __DIR__ . '/logoutUser.php';

// Container file for load_dashboard_data(), save_wallet(), buy_vip(), and grant_dashboard_bonus()
require_once __DIR__ . '/dashboard_wallet_helpers.php';


// ============================================================================
// --- 📈 Navigation, Analytics & System Routing ---
// ============================================================================

// trackVisit($pageType = 'page') -> Logs current page view hit and visitor metadata into analytics table
require_once __DIR__ . '/trackVisit.php';

// setReturnUrlCookie($path, $expireSeconds = 300) -> Saves target URL in cookie for post-login redirection
require_once __DIR__ . '/setReturnUrlCookie.php';

// redirectToReturnUrl($fallback = '/index.php') -> Redirects user back to target URL stored in return cookie
require_once __DIR__ . '/redirectToReturnUrl.php';

// checkSystemFlags($conn) -> Checks database system settings for maintenance mode or feature locks
require_once __DIR__ . '/checkSystemFlags.php';

// redirectToCfCbazar42web() -> Redirects incoming requests to canonical cfcbazar.42web.io domain
require_once __DIR__ . '/redirectToCfCbazar42web.php';

// Container file for enforce_https(), grant_mining_bonus(), reward_miner_workthr(), rvPageRedirect(), close_database(), require_database_connection()
require_once __DIR__ . '/system_utility_helpers.php';


// ============================================================================
// --- 🪙 Staking Engine ---
// ============================================================================

// toggleStaking($conn, $wallet, $action) -> Processes token deposit or withdrawal for active staking pools
require_once __DIR__ . '/toggleStaking.php';

// getWorkTokenStatus($conn, $wallet, $selectedToken = 'WTK') -> array: Fetches token circulating supply & balance
require_once __DIR__ . '/getWorkTokenStatus.php';

// getStakingStatus($conn, $wallet) -> array: Retrieves active staked balances, yields, and lock periods
require_once __DIR__ . '/getStakingStatus.php';

// ============================================================================
// GLOBAL INITIALIZATION & ROUTING HANDLERS
// ============================================================================

// Initialize database connection
global $conn;
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("reusable.php: DB connection failed - " . $conn->connect_error);
    http_response_code(500);
    die("Database connection failed");
}

// Generate session CSRF token if uninitialized
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Global POST route handler for miner rewards
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'miner_reward') {
    $email       = $_POST['userId'] ?? '';
    $rewardType  = $_POST['reward_type'] ?? 'WorkToken';
    $accepted    = (int)($_POST['accepted'] ?? 0);
    $mac         = $_POST['mac_address'] ?? '';
    $active      = (int)($_POST['active'] ?? 0);

    if (function_exists('handleMinerReward')) {
        handleMinerReward($email, $rewardType, $accepted, $mac, $active);
    }
    exit('ok');
}
