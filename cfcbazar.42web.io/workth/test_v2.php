<?php
// test.php

// API credentials (consider moving these to a separate config file)
$api_id  = "f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3";
$api_key = "eafc42e3628bc072cb643e855d24b9206598000f91088bdb980f49810d2a1132";

// Set required headers
$headers = [
    "accept: application/json",
    "X-API-ID: $api_id",
    "X-API-KEY: $api_key"
];

// Build the URL for the wallet history endpoint with offset and limit parameters
$url = "https://www.mintme.com/dev/api/v2/auth/user/wallet/history?offset=0&limit=10";

// Initialize cURL, set options, and execute the request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "Error: " . curl_error($ch);
    curl_close($ch);
    exit;
}
curl_close($ch);

// Decode the JSON response into an associative array
$transactions = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Error: " . json_last_error_msg();
    exit;
}
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Last 10 Wallet Transactions</title>
    <style>
      body { font-family: Arial, sans-serif; }
      table { border-collapse: collapse; width: 100%; margin-top: 20px; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #f5f5f5; }
      tr:nth-child(even){background-color: #f9f9f9;}
    </style>
  </head>
  <body>
    <h1>Last 10 Wallet Transactions</h1>
    <?php if (empty($transactions)): ?>
      <p>No transactions found.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Transaction Hash</th>
            <th>Amount</th>
            <th>Fee</th>
            <th>Status</th>
            <th>Type</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $tx): ?>
            <tr>
              <td><?php echo htmlspecialchars($tx['date']); ?></td>
              <td><?php echo htmlspecialchars($tx['hash']); ?></td>
              <td><?php echo htmlspecialchars($tx['amount']); ?></td>
              <td><?php echo htmlspecialchars($tx['fee']); ?></td>
              <td><?php echo htmlspecialchars($tx['status']['statusCode']); ?></td>
              <td><?php echo htmlspecialchars($tx['type']['typeCode']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </body>
</html>