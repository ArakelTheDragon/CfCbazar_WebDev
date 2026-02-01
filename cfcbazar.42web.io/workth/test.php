<?php
// --- CONFIG ---
$contractAddress = '0xC62C8dccf79b3243804F2E3D2C5886EB9db816FA';
$apiKey = '1EG38UTURQJMZBGQPZKMSK39QJSQ4CHT7G'; // BscScan Testnet API key
$fromBlock = 'latest'; // You can use a specific block for history
$eventSignature = '0x7859f85cb7c65f1735c263ac0e4f419ce3656c88d1c309d26ec1810c41dba03f'; // keccak256("WTKPurchase(address,uint256,uint256,uint256)")
$buyVipSelector = '0x2c8275fe'; // First 4 bytes of keccak256("buyVIP()")

// --- STEP 1: Get WTKPurchase logs ---
$logsUrl = "https://api-testnet.bscscan.com/api?module=logs&action=getLogs"
    . "&fromBlock=$fromBlock"
    . "&toBlock=latest"
    . "&address=$contractAddress"
    . "&topic0=$eventSignature"
    . "&apikey=$apiKey";

$logs = json_decode(file_get_contents($logsUrl), true);
if (!isset($logs['result'])) {
    die("Error fetching logs.");
}

foreach ($logs['result'] as $log) {
    $txHash = $log['transactionHash'];

    // --- STEP 2: Fetch transaction input ---
    $txUrl = "https://api-testnet.bscscan.com/api?module=proxy&action=eth_getTransactionByHash"
        . "&txhash=$txHash"
        . "&apikey=$apiKey";

    $tx = json_decode(file_get_contents($txUrl), true);
    if (!isset($tx['result']['input'])) continue;

    $input = $tx['result']['input'];

    if (str_starts_with($input, $buyVipSelector)) {
        // Decode buyer address from topic1
        $buyerHex = $log['topics'][1];
        $buyerAddress = '0x' . substr($buyerHex, 26);

        // Decode amountWTK (data[0:64])
        $amountWTK = hexdec(substr($log['data'], 0, 66)) / 1e18;

        // Decode priceBNBPerWTK (data[64:128])
        $priceBNBPerWTK = hexdec(substr($log['data'], 66, 64)) / 1e18;

        // Decode bnbSpent (data[128:192])
        $bnbSpent = hexdec(substr($log['data'], 130, 64)) / 1e18;

        echo " VIP Purchase Detected:\n";
        echo "- Buyer: $buyerAddress\n";
        echo "- WTK: $amountWTK\n";
        echo "- Price per WTK: $priceBNBPerWTK BNB\n";
        echo "- BNB Spent: $bnbSpent\n";
        echo "- Tx: https://testnet.bscscan.com/tx/$txHash\n\n";
    }
}