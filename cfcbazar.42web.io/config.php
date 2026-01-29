<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
$servername = "sql313.infinityfree.com";
$username = "if0_39103611";
$password = "53098516";
$dbname = "if0_39103611_db1";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Metamask private key
$meta_private_key = '93d4933b9b09376b6fc99d538979662478ae3830f8c80d92031270e491be753e';

// Meta wallet
$meta_wallet = '0xFBd767f6454bCd07c959da2E48fD429531A1323A';

// Token proxy address
$token_address = '0xecbD4E86EE8583c8681E2eE2644FC778848B237D';

// Token logic address
$token_logic_address ='0x81Ee896fec1d1d834f322E7FdC25032884831111';
// BNB api v2 etherscan
$bscscan_api_key = 'NX14GVNPT9WQCY8PN6IUTUYK3Z8D5ZFPQM';

// MintMe API public Key
$api_key = "f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3";
$oauth_client_id     = "2196_5dltl1mlqvc4wgw0g00o4g4wsw844g8osowg4s4c48soowko4g";
$oauth_client_secret = "4qg3hayh3iww88sws4ok4kgcks4888wsw40kcs8o4oo8go48cs";

// Optional: Access token if you obtain it through OAuth flow
$oauth_access_token = ""; // Not used on mintme

// === Add Mintme Private Key Here ===
$private_key = "5af52915758ad40a9ee4a150e53af22a6874d1aef2bcde23971a35c07e399192"; // <-- Add your private key securely here
$mintme_wallet =  "0xe8911e98a00d36a1841945d6270611510f1c7e88";

// SMTP Credentials (Brevo)
$smtp_host = 'smtp-relay.brevo.com';
$smtp_user = '8fccf4001@smtp-brevo.com';
$smtp_pass = '3qgNzOXmhbCYSvP9';
?>