<?php
/**
 * Public config bootstrap – no secrets here.
 * Loads secrets from includes/secrets.php (blocked by .htaccess).
 */

declare(strict_types=1);

// Production error handling
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Load secrets (must exist and be protected)
$secretsFile = __DIR__ . '/includes/secrets.php';
if (!is_readable($secretsFile)) {
    http_response_code(500);
    error_log('CRITICAL: secrets.php missing or unreadable');
    die('Configuration error.');
}
require_once $secretsFile;

// Database connection
$servername = DB_HOST;
$username   = DB_USER;
$password   = DB_PASS;
$dbname     = DB_NAME;

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die('Database connection error.');
}

// Map defines to the old variable names used across the codebase
$meta_private_key     = META_PRIVATE_KEY;
$meta_wallet          = META_WALLET;
$token_address        = TOKEN_ADDRESS;
$token_logic_address  = TOKEN_LOGIC_ADDRESS;
$bscscan_api_key      = BSCSCAN_API_KEY;
$api_key              = MINTME_API_KEY;
$oauth_client_id      = MINTME_OAUTH_CLIENT_ID;
$oauth_client_secret  = MINTME_OAUTH_CLIENT_SECRET;
$oauth_access_token   = '';
$private_key          = MINTME_PRIVATE_KEY;
$mintme_wallet        = MINTME_WALLET;
$smtp_host            = SMTP_HOST;
$smtp_user            = SMTP_USER;
$smtp_pass            = SMTP_PASS;
$API_openrouter       = OPENROUTER_API_KEY;
