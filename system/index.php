<?php
// /system/index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set HTTP 500 response code immediately
http_response_code(500);

// ------------------------
// Load Reusable Engine
// ------------------------
$reusablePath = __DIR__ . '/../includes/reusable.php';

if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("500 Internal Server Error - System library missing.");
}

// ------------------------
// System & Security
// ------------------------
enforce_https();
require_database_connection();
checkSystemFlags();
trackVisit("system-index-error");

// ------------------------
// User & Session
// ------------------------
session_check();
$email = null;
$is_logged_in = is_logged_in($email);

// ------------------------
// Layout
// ------------------------
$title = "500 Internal Server Error - CfCbazar";
include_header($title);
include_menu();

render_top_userbar();
?>

<main class="container">
    <div class="card" style="padding: 2.5em; text-align: center; margin: 40px auto; max-width: 600px; border-top: 4px solid var(--danger, #dc3545);">
        <h1 style="font-size: 2.5em; margin-bottom: 0.2em; color: var(--danger, #dc3545);">⚠️ 500</h1>
        <h2>Internal Server Error</h2>
        <p style="margin: 1.5em 0; color: #666; line-height: 1.6;">
            Direct access to this directory is prohibited or an unexpected internal error has occurred.
        </p>
        <p>
            <a href="/index.php" class="btn" style="display: inline-block; padding: 10px 20px; background: var(--primary, #28a745); color: #fff; text-decoration: none; border-radius: var(--radius, 8px);">
                Return to Home
            </a>
        </p>
    </div>
</main>

<?php
include_footer();
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/system/index.php",
    "System Index Source Code"
);
close_database();
?>
