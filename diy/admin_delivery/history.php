<?php
/**
 * eBay Past 30 Days Order History & Manual Dispatcher
 * Location: /diy/admin_delivery/history.php
 * Brand / Author: CfCbazar Group
 */

require_once __DIR__ . '/../../config.php';

$tokenFile = __DIR__ . '/ebay_tokens.json';
$skuMapFile = __DIR__ . '/sku_map.json';

if (!file_exists($tokenFile)) {
    exit("OAuth tokens file (ebay_tokens.json) not found. Please authenticate via index.php first.");
}

$tokenData = json_decode(file_get_contents($tokenFile), true);
$skuMap = file_exists($skuMapFile) ? json_decode(file_get_contents($skuMapFile), true) : [];

/**
 * Helper to refresh OAuth token if expired
 */
function getValidOAuthToken($tokenData, $tokenFile, $eBayAppID, $eBayCertID) {
    if (!empty($tokenData['access_token']) && !empty($tokenData['expires_at']) && time() < ($tokenData['expires_at'] - 60)) {
        return $tokenData['access_token'];
    }

    $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';
    $credentials = base64_encode($eBayAppID . ':' . $eBayCertID);

    $postData = [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $tokenData['refresh_token'],
        'scope'         => 'https://api.ebay.com/oauth/api_scope'
    ];

    $headers = [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/x-www-form-urlencoded'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $newTokenData = json_decode($response, true);
        $tokenData['access_token'] = $newTokenData['access_token'];
        $tokenData['expires_at']   = time() + ($newTokenData['expires_in'] ?? 7200);
        file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $tokenData['access_token'];
    }
    return false;
}

$accessToken = getValidOAuthToken($tokenData, $tokenFile, $eBayAppID, $eBayCertID);
if (!$accessToken) {
    exit("Failed to refresh eBay OAuth access token.");
}

$statusMessage = '';

// Handle manual email dispatch submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_manual') {
    $targetEmail = trim($_POST['target_email'] ?? '');
    $itemSku     = trim($_POST['item_sku'] ?? '');
    $itemId      = trim($_POST['item_id'] ?? '');
    $itemTitle   = trim($_POST['item_title'] ?? '');
    $orderId     = trim($_POST['order_id'] ?? '');

    if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        $statusMessage = "<div class='alert error'>Invalid email address provided.</div>";
    } else {
        // Resolve link by scanning sku_map.json (checking exact SKU key or matching 'id' values)
        $bookName = $itemTitle;
        $downloadLink = $skuMap['DEFAULT']['link'] ?? "https://cfcbazar.42web.io/downloads/ebook_secure_link.pdf";

        if (!empty($itemSku) && isset($skuMap[$itemSku])) {
            $bookName     = $skuMap[$itemSku]['name'];
            $downloadLink = $skuMap[$itemSku]['link'];
        } else {
            // Scan through map looking for matching item ID
            foreach ($skuMap as $mapKey => $mapData) {
                if (isset($mapData['id']) && (string)$mapData['id'] === (string)$itemId) {
                    $bookName     = $mapData['name'];
                    $downloadLink = $mapData['link'];
                    break;
                }
            }
        }

        $mailPostData = [
            'email'       => $targetEmail,
            'subject'     => "Your Download Link: " . $bookName,
            'message'     => "Thank you for your purchase from CfCbazar Group!\n\nYou can download your digital guide (" . $bookName . ") here:\n" . $downloadLink . "\n\nOrder ID: " . $orderId,
            'heading'     => 'CfCbazar Manual Delivery',
            'intro'       => 'Your order for "' . $bookName . '" has been dispatched manually.',
            'footer_note' => 'Thank you for shopping with CfCbazar Group.',
            'from_name'   => 'CfCbazar Group'
        ];

        $mailScriptUrl = 'https://cfcbazar.8bit.ca/mail.php';
        $chMail = curl_init($mailScriptUrl);
        curl_setopt_array($chMail, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($mailPostData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15
        ]);
        $mailResponse = curl_exec($chMail);
        $mailHttpCode = curl_getinfo($chMail, CURLINFO_HTTP_CODE);
        curl_close($chMail);

        $mailResult = json_decode($mailResponse, true);
        if ($mailHttpCode === 200 && isset($mailResult['success'])) {
            $statusMessage = "<div class='alert success'>Successfully dispatched ebook to " . htmlspecialchars($targetEmail) . " for Order ID: " . htmlspecialchars($orderId) . "</div>";
        } else {
            $statusMessage = "<div class='alert error'>Failed to send email via mail.php.</div>";
        }
    }
}

// Fetch Orders for Past 30 Days via GetOrders Trading API
$endpointUrl = 'https://api.ebay.com/ws/api.dll';
$xmlRequest = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
  <RequesterCredentials>
    <eBayAuthToken>{$accessToken}</eBayAuthToken>
  </RequesterCredentials>
  <DetailLevel>ReturnAll</DetailLevel>
  <NumberOfDays>30</NumberOfDays>
  <OrderRole>Seller</OrderRole>
  <OrderStatus>Completed</OrderStatus>
</GetOrdersRequest>
XML;

$headers = [
    'X-EBAY-API-COMPATIBILITY-LEVEL: 1173',
    'X-EBAY-API-DEV-NAME: ' . $eBayDevID,
    'X-EBAY-API-APP-NAME: ' . $eBayAppID,
    'X-EBAY-API-CERT-NAME: ' . $eBayCertID,
    'X-EBAY-API-CALL-NAME: GetOrders',
    'X-EBAY-API-SITEID: 0',
    'Content-Type: text/xml'
];

$ch = curl_init($endpointUrl);
curl_setopt_array($ch, [
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => $xmlRequest,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30
]);

$response = curl_exec($ch);
curl_close($ch);

libxml_use_internal_errors(true);
$xml = simplexml_load_string($response);
$orders = ($xml !== false && isset($xml->OrderArray->Order)) ? $xml->OrderArray->Order : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CfCbazar - 30-Day Order History</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h2 { color: #2c3e50; margin-top: 0; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed; font-size: 14px; }
        th { background: #2c3e50; color: #fff; }
        tr:hover { background: #f8f9fa; }
        input[type="email"] { padding: 8px; width: 200px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #27ae60; color: #fff; border: none; padding: 8px 14px; font-weight: bold; border-radius: 4px; cursor: pointer; }
        button:hover { background: #219653; }
        .sku-badge { background: #e2e8f0; padding: 3px 6px; border-radius: 4px; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h2>eBay Order History (Past 30 Days)</h2>
    <p>Review completed sales from the past 30 days and manually dispatch ebook download links to any email address.</p>
    
    <?php echo $statusMessage; ?>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Created Time</th>
                <th>Item Title / SKU</th>
                <th>Buyer Email</th>
                <th>Manual Dispatch Email</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($orders) === 0): ?>
                <tr><td colspan="6" style="text-align:center; color:#777;">No completed orders found in the past 30 days.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): 
                    $orderId     = (string)$order->OrderID;
                    $createdTime = (string)$order->CreatedTime;
                    $itemTitle   = (string)($order->TransactionArray->Transaction->Item->Title ?? 'N/A');
                    $itemSku     = strtoupper(trim((string)($order->TransactionArray->Transaction->Item->SKU ?? '')));
                    $itemId      = (string)($order->TransactionArray->Transaction->Item->ItemID ?? '');
                    $buyerEmail  = (string)($order->TransactionArray->Transaction->Buyer->Email ?? '');
                    if (empty($buyerEmail) || strpos($buyerEmail, 'ebay.com') !== false) {
                        $buyerEmail = 'cfcbazar@gmail.com';
                    }
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($orderId); ?></strong></td>
                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($createdTime))); ?></td>
                    <td>
                        <div><?php echo htmlspecialchars($itemTitle); ?></div>
                        <span class="sku-badge">SKU: <?php echo htmlspecialchars($itemSku !== '' ? $itemSku : $itemId); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($buyerEmail); ?></td>
                    <td>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="send_manual">
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>">
                            <input type="hidden" name="item_sku" value="<?php echo htmlspecialchars($itemSku); ?>">
                            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($itemId); ?>">
                            <input type="hidden" name="item_title" value="<?php echo htmlspecialchars($itemTitle); ?>">
                            <input type="email" name="target_email" value="cfcbazar@gmail.com" required>
                    </td>
                    <td>
                            <button type="submit">Send Email</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
