<?php
// /worktoken/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../includes/settings_global.php';
require_once __DIR__ . '/../includes/settings_local.php';
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
if (bootstrap() !== 0) {
    exit(1);
}

// --- Page metadata -----------------------------------------------------------
$url        = "/worktoken/index.php";
$parent_url = "/worktoken";
$title      = "WorkToken — Disabled";
$template   = "Generic";
$meta_title = "WorkToken — Disabled";
$meta_desc  = "The WorkToken system is currently disabled.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<main class="content">
    <div class="container">

        <div class="card" style="text-align:center;padding:48px 24px;">
            <h1 style="margin:0 0 16px 0;font-size:1.75rem;">💰 WorkToken</h1>

            <p style="margin:0 0 8px 0;font-size:1.1rem;font-weight:600;color:#ef4444;">
                This system is currently disabled.
            </p>

            <p style="margin:0;color:#94a3b8;">
                The WorkToken service is not available at this time. Please check back later.
            </p>
        </div>

    </div>
</main>

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/worktoken",
    "WorkToken Source Code"
);
include_footer();
close_database();
?>
