<?php
// /pagename.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/includes/settings_global.php';
require_once __DIR__ . '/includes/settings_local.php';
// settings_local.php sets:
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN   = false;

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {          // starts session only if $NEED_* flags require it
    exit(1);
}

// --- Page metadata -----------------------------------------------------------
$url        = "/pagename.php";
$parent_url = NULL;
$title      = "Page Title";
$template   = "Generic";
$meta_title = "Meta Title";
$meta_desc  = "Meta description";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);            // <head>, CSS/JS from /assets/, nav

// --- page body (high-level functions only) ---
echo '<main class="content">';
// ...
echo '</main>';

// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/pagename.php",
    "Source Code"
);
include_footer();
close_database();
?>
