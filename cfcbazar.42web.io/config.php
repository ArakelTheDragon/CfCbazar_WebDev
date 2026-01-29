<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
$servername = "";
$username = "";
$password = "";
$dbname = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Metamask private key
$meta_private_key = '';

// Meta wallet
$meta_wallet = '';

// Token proxy address
$token_address = '';

// Token logic address
$token_logic_address ='';
// BNB api v2 etherscan
$bscscan_api_key = '';

// MintMe API public Key
$api_key = "";
$oauth_client_id     = "";
$oauth_client_secret = "";

// Optional: Access token if you obtain it through OAuth flow
$oauth_access_token = ""; // Not used on mintme

// === Add Mintme Private Key Here ===
$private_key = ""; // <-- Add your private key securely here
$mintme_wallet =  "";

// SMTP Credentials (Brevo)
$smtp_host = '';
$smtp_user = '';
$smtp_pass = '';
?>
