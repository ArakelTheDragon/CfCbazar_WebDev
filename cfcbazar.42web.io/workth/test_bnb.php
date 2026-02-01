<?php
// test_bnb.php - fetch last 5 transactions and show BNB transfers with details

include(__DIR__ . '/../config.php');

$address = '0xfbd767f6454bcd07c959da2e48fd429531a1323a';  // Wallet to check
$apiKey = $bscscan_api_key; // Put your BscScan API key here

// API URL for normal transactions (BNB transfers and contract calls)
$url = "https://api.bscscan.com/api?module=account&action=txlist&address=$address&startblock=0&endblock=99999999&page=1&offset=10&sort=desc&apikey=$apiKey";

$response = file_get_contents($url);
$data = json_decode($response, true);

if (!$data || $data['status'] != '1' || empty($data['result'])) {
    echo "Error fetching data or no transactions found.";
    exit;
}

// Filter only BNB transfers (value > 0 and contractAddress empty)
$bnbTransfers = [];
foreach ($data['result'] as $tx) {
    // Check if this is a native BNB transfer (no contract address)
    if (empty($tx['contractAddress']) && $tx['value'] > 0) {
        $bnbTransfers[] = $tx;
    }
    if (count($bnbTransfers) >= 5) break;
}

if (empty($bnbTransfers)) {
    echo "No BNB transfers found in the last 10 transactions.";
    exit;
}

echo "<h2>Last 5 BNB Transfers for $address</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Transaction Hash</th><th>From</th><th>To</th><th>Amount (BNB)</th></tr>";

foreach ($bnbTransfers as $tx) {
    $hash = $tx['hash'];
    $from = $tx['from'];
    $to = $tx['to'];
    // Convert wei to BNB (1 BNB = 10^18 wei)
    $amountBNB = bcdiv($tx['value'], bcpow('10', '18', 18), 8);
    echo "<tr>";
    echo "<td><a href='https://bscscan.com/tx/$hash' target='_blank'>$hash</a></td>";
    echo "<td>$from</td>";
    echo "<td>$to</td>";
    echo "<td>$amountBNB</td>";
    echo "</tr>";
}
echo "</table>";
?>