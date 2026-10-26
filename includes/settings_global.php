<?php
/**
 * settings_global.php
 * Site-wide defaults. Loaded on every page after reusable.php.
 * Do not put page-specific flags here — use settings_local.php for those.
 */

declare(strict_types=1);

// Default flags (pages override these in settings_local.php)
$NEED_SESSION = false;
$NEED_LOGIN   = false;
$NEED_CART    = false;
$NEED_ADMIN   = false;

// Global site constants
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'CfCbazar');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://cfcbazar.8bit.ca');
}
if (!defined('ASSETS_CSS')) {
    define('ASSETS_CSS', '/assets/css');
}
if (!defined('ASSETS_JS')) {
    define('ASSETS_JS', '/assets/js');
}

// Default template
$DEFAULT_TEMPLATE = 'Generic';

// Error / debug (can be overridden per environment)
if (!isset($GLOBAL_DISPLAY_ERRORS)) {
    $GLOBAL_DISPLAY_ERRORS = true;
}
