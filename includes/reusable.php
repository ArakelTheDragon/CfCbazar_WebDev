<?php
/**
 * CfCbazar Master Bootstrap – Consolidated v2
 * File: /includes/reusable.php
 *
 * Loads core configuration, utilities, authentication, layout, and optional helpers.
 */
declare(strict_types=1);

// Core configuration & database
require_once __DIR__ . '/../config.php';

// General utility functions
require_once __DIR__ . '/utils.php';

// File download processing
require_once __DIR__ . '/process_download.php';

// Visit tracking (trackVisit, etc.)
require_once __DIR__ . '/tracking.php';

// Authentication helpers
// Contains: setReturnUrlCookie(), getReturnUrl(), enforce_https(), checkSystemFlags(), etc.
require_once __DIR__ . '/auth.php';

// Mining related functions
require_once __DIR__ . '/miner.php';

// Mining bonus grant
// Contains: grant_mining_bonus()
require_once __DIR__ . '/grant_mining_bonus.php';

// Top user bar (real implementation, overrides any stub)
// Contains: render_top_userbar()
if (is_readable(__DIR__ . '/render_top_userbar.php')) {
    require_once __DIR__ . '/render_top_userbar.php';
}

// Layout & UI renderers
// Contains: include_header(), include_menu(), include_footer(), cfc_footer(),
//           showAdvertPopup(), render_withdraw_link(), render_workthr_teaser(),
//           render_worktoken_teaser(), rfTradeWorkTokens(), render_ecosystem_content(),
//           render_device_table(), renderCaptchaIfNeeded(), render_token_price_tracker(),
//           displayServerStatus(), show_disabled_message()
require_once __DIR__ . '/layout.php';

// Optional helpers (loaded only if the file exists)
$optional = [
    'system_utility_helpers.php',      // System status / utility helpers
    'dashboard_wallet_helpers.php',    // Wallet display helpers
    'render_worktoken_dashboard.php',  // WorkToken dashboard renderer
    'show_disabled_message.php',       // (legacy – now usually in layout.php)
    'redirectToReturnUrl.php',         // redirectToReturnUrl()
    'getStakingStatus.php',            // getStakingStatus()
    'scan_files.php',                  // File scanning utilities
    'task.php',                        // Task related functions
    'add-item.php',                    // Add item helpers
];

foreach ($optional as $file) {
    $path = __DIR__ . '/' . $file;
    if (is_readable($path)) {
        require_once $path;
    }
}
