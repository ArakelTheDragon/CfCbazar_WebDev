<?php
// Load API key
require_once(__DIR__ . '/../config.php');

// Wallet to check (replace with actual user address or collect from frontend)
$walletAddress = '0xe8911e98a00d36a1841945d6270611510f1c7e88';

// Token symbol
$tokenSymbol = 'WORKTH';

$apiUrl = "https://www.mintme.com/api/v2/wallets/$walletAddress/balances";

$headers = [
    "X-API-KEY: $api_key"
];

// Call the MintMe API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
curl_close($ch);

// Decode and extract token balance
$data = json_decode($response, true);

$balance = 0;
if (is_array($data)) {
    foreach ($data as $token) {
        if (strtoupper($token['symbol']) === $tokenSymbol) {
            $balance = $token['balance'];
            break;
        }
    }
}

// Display results
echo "<h2>Wallet: $walletAddress</h2>";
echo "<p>$tokenSymbol Balance: $balance</p>";
?>