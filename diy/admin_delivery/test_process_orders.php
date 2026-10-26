<?php
/**
 * Interactive Single/Multi Order Dispatcher Test Runner with Dropdown Selector
 * Location: /diy/admin_delivery/test_process_orders.php
 * Brand / Author: CfCbazar Group
 */

require_once __DIR__ . '/../../config.php';

$tokenFile  = __DIR__ . '/ebay_tokens.json';
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

$mailScriptUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname(dirname($_SERVER['PHP_SELF']))) . '/mail.php';
if (!filter_var($mailScriptUrl, FILTER_VALIDATE_URL)) {
    $mailScriptUrl = 'https://cfcbazar.8bit.ca/mail.php';
}

$statusMessage = '';

// ----------------------------------------------------
// Step 2: Dispatch Emails Upon Single Selection
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_single_dispatch') {
    $payloadJson = $_POST['selected_order_payload'] ?? '';
    $item = json_decode($payloadJson, true);

    if (empty($item)) {
        $statusMessage = "<div class='alert error'>Invalid order selection payload.</div>";
    } else {
        // 1. Send Buyer / Client Email
        $chMail1 = curl_init($mailScriptUrl);
        curl_setopt_array($chMail1, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($item['buyer_mail_payload']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15
        ]);
        $res1 = curl_exec($chMail1);
        $code1 = curl_getinfo($chMail1, CURLINFO_HTTP_CODE);
        curl_close($chMail1);

        // 2. Send Admin Audit Report Email
        $chMail2 = curl_init($mailScriptUrl);
        curl_setopt_array($chMail2, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($item['admin_mail_payload']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15
        ]);
        $res2 = curl_exec($chMail2);
        $code2 = curl_getinfo($chMail2, CURLINFO_HTTP_CODE);
        curl_close($chMail2);

        $j1 = json_decode($res1, true);
        $j2 = json_decode($res2, true);

        if ($code1 === 200 && isset($j1['success']) && $code2 === 200 && isset($j2['success'])) {
            $statusMessage = "<div class='alert success'>Successfully dispatched test emails (Buyer + Admin) for Order ID: <strong>" . htmlspecialchars($item['order_id']) . "</strong>.</div>";
        } else {
            $statusMessage = "<div class='alert error'>Failed to dispatch test emails via mail.php.</div>";
        }
    }
}

// ----------------------------------------------------
// Step 1: Scan Past 30 Days Orders
// ----------------------------------------------------
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
$xml = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA);
$jsonString = json_encode($xml);
$orderArrayData = json_decode($jsonString, true);
$orders = $orderArrayData['OrderArray']['Order'] ?? [];
if (isset($orders['OrderID'])) {
    $orders = [$orders];
}

$preparedQueue = [];

foreach ($orders as $order) {
    $orderId   = (string)($order['OrderID'] ?? '');
    $createdTime = (string)($order['CreatedTime'] ?? '');
    $transaction = $order['TransactionArray']['Transaction'] ?? [];
    if (isset($transaction['TransactionID'])) {
        $transaction = [$transaction];
    }
    
    $tx = $transaction[0] ?? [];
    $item      = $tx['Item'] ?? [];
    $itemSku   = strtoupper(trim((string)($item['SKU'] ?? '')));
    $itemId    = (string)($item['ItemID'] ?? '');
    $itemTitle = (string)($item['Title'] ?? 'Digital eBook');

    $totalPrice   = (string)($order['Total'] ?? '0.00');
    $currency     = (string)($order['Currency'] ?? 'USD');
    $itemPrice    = (float)($tx['TransactionPrice'] ?? '0.00');

    // Robust Tax Extraction
    $taxAmount = 0.00;
    if (isset($tx['eBayCollectAndRemitTaxes'])) {
        $remitTaxes = $tx['eBayCollectAndRemitTaxes'];
        if (isset($remitTaxes['TotalTaxAmount'])) {
            $taxAmount = (float)$remitTaxes['TotalTaxAmount'];
        } elseif (isset($remitTaxes['TaxDetails'])) {
            $details = $remitTaxes['TaxDetails'];
            if (isset($details['TaxAmount'])) {
                $taxAmount = (float)$details['TaxAmount'];
            } elseif (is_array($details)) {
                foreach ($details as $det) {
                    if (is_array($det) && isset($det['TaxAmount'])) {
                        $taxAmount += (float)$det['TaxAmount'];
                    }
                }
            }
        }
    }
    if ($taxAmount === 0.00 && isset($tx['Taxes'])) {
        $taxesNode = $tx['Taxes'];
        if (isset($taxesNode['TotalTaxAmount'])) {
            $taxAmount = (float)$taxesNode['TotalTaxAmount'];
        } elseif (isset($taxesNode['TaxDetails'])) {
            $details = $taxesNode['TaxDetails'];
            if (isset($details['TaxAmount'])) {
                $taxAmount = (float)$details['TaxAmount'];
            } elseif (is_array($details)) {
                foreach ($details as $det) {
                    if (is_array($det) && isset($det['TaxAmount'])) {
                        $taxAmount += (float)$det['TaxAmount'];
                    }
                }
            }
        }
    }

    $netValue = number_format($itemPrice - $taxAmount, 2, '.', '');
    $taxFormatted = number_format($taxAmount, 2, '.', '');
    $priceFormatted = number_format($itemPrice, 2, '.', '');

    $rawBuyerEmail = (string)($tx['Buyer']['Email'] ?? 'N/A');
    $deliveryBuyerEmail = $rawBuyerEmail;

    if (empty($deliveryBuyerEmail) || strpos($deliveryBuyerEmail, 'invalid') !== false || strpos($deliveryBuyerEmail, 'ebay.com') !== false) {
        $deliveryBuyerEmail = "cfcbazar@gmail.com";
    }

    // Lookup link in sku_map.json
    $bookName = $itemTitle;
    $downloadLink = $skuMap['DEFAULT']['link'] ?? "https://cfcbazar.42web.io/downloads/ebook_secure_link.pdf";

    if (!empty($itemSku) && isset($skuMap[$itemSku])) {
        $bookName     = $skuMap[$itemSku]['name'];
        $downloadLink = $skuMap[$itemSku]['link'];
    } else {
        foreach ($skuMap as $mapKey => $mapData) {
            if (isset($mapData['id']) && (string)$mapData['id'] === (string)$itemId) {
                $bookName     = $mapData['name'];
                $downloadLink = $mapData['link'];
                break;
            }
        }
    }

    $buyerSubject = "Your Download Link: " . $bookName;
    $buyerMessage = "Thank you for your purchase from CfCbazar Group!\n\nYou can download your digital guide (" . $bookName . ") here:\n" . $downloadLink . "\n\nIf you have any questions, feel free to reply to this email.";

    $buyerMailPayload = [
        'email'       => $deliveryBuyerEmail,
        'subject'     => $buyerSubject,
        'message'     => $buyerMessage,
        'heading'     => 'CfCbazar Digital Delivery (Test Run)',
        'intro'       => 'Your order for "' . $bookName . '" has been processed.',
        'footer_note' => 'Thank you for shopping with CfCbazar Group.',
        'from_name'   => 'CfCbazar Group'
    ];

    $adminSubject = "[TEST AUDIT] Processed Order ID: " . $orderId;
    $adminMessage = "An automated test order email dispatch occurred.\n\n" .
                    "--- ORDER DETAILS ---\n" .
                    "Order ID: " . $orderId . "\n" .
                    "Item Title: " . $itemTitle . "\n" .
                    "SKU / Item ID: " . ($itemSku !== '' ? $itemSku : $itemId) . "\n" .
                    "Client Email (Raw): " . $rawBuyerEmail . "\n" .
                    "Delivered To: " . $deliveryBuyerEmail . "\n\n" .
                    "--- FINANCIAL BREAKDOWN ---\n" .
                    "Price: " . $priceFormatted . " " . $currency . "\n" .
                    "Tax: " . $taxFormatted . " " . $currency . "\n" .
                    "Net Value: " . $netValue . " " . $currency . "\n" .
                    "Total Paid: " . $totalPrice . " " . $currency . "\n\n" .
                    "Download Link Sent: " . $downloadLink;

    $adminMailPayload = [
        'email'       => 'cfcbazar@gmail.com',
        'subject'     => $adminSubject,
        'message'     => $adminMessage,
        'heading'     => 'Order Processing Audit Report (Test Run)',
        'intro'       => 'Order #' . $orderId . ' audit summary.',
        'footer_note' => 'CfCbazar Automated Test Runner',
        'from_name'   => 'CfCbazar System'
    ];

    $preparedQueue[] = [
        'order_id'           => $orderId,
        'created_time'       => $createdTime,
        'item_title'         => $itemTitle,
        'raw_buyer_email'    => $rawBuyerEmail,
        'delivery_email'     => $deliveryBuyerEmail,
        'book_name'          => $bookName,
        'download_link'      => $downloadLink,
        'financials'         => "Price: {$priceFormatted} {$currency} | Tax: {$taxFormatted} {$currency} | Net: {$netValue} {$currency}",
        'buyer_mail_payload' => $buyerMailPayload,
        'admin_mail_payload' => $adminMailPayload
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CfCbazar - Single Order Dispatch Inspector</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h2, h3 { color: #2c3e50; margin-top: 0; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 8px; color: #2c3e50; }
        select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px; background: #fff; box-sizing: border-box; }
        .order-card { border: 1px solid #e1e8ed; border-radius: 6px; background: #fafbfc; overflow: hidden; margin-top: 15px; display: none; }
        .order-card.active { display: block; }
        .order-header { padding: 12px 15px; background: #edf2f7; display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 14px; color: #2c3e50; }
        .order-body { padding: 20px; background: #fff; border-top: 1px solid #e1e8ed; font-size: 14px; }
        .email-preview { background: #1e1e1e; color: #dcdcdc; padding: 14px; border-radius: 6px; font-family: monospace; white-space: pre-wrap; margin-top: 8px; font-size: 13px; line-height: 1.4; }
        .btn-dispatch { background: #27ae60; color: #fff; border: none; padding: 12px 24px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; width: 100%; margin-top: 15px; }
        .btn-dispatch:hover { background: #219653; }
        .tag { background: #3182ce; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .tag.admin { background: #8e44ad; }
    </style>
</head>
<body>
<div class="container">
    <h2>Interactive Order Dispatch Inspector</h2>
    <p>Select a specific order from the dropdown below to inspect its item details, financial breakdown, and exact email previews before dispatching.</p>

    <?php echo $statusMessage; ?>

    <?php if (empty($preparedQueue)): ?>
        <p style="text-align:center; color:#777;">No completed orders found in the past 30 days.</p>
    <?php else: ?>
        <div class="form-group">
            <label for="orderSelect">Select Order:</label>
            <select id="orderSelect" onchange="switchOrder(this.value)">
                <option value="">-- Choose an Order to Inspect --</option>
                <?php foreach ($preparedQueue as $idx => $item): ?>
                    <option value="<?php echo $idx; ?>">
                        ID: <?php echo htmlspecialchars($item['order_id']); ?> | <?php echo htmlspecialchars(substr($item['item_title'], 0, 50)); ?>... (<?php echo htmlspecialchars($item['financials']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php foreach ($preparedQueue as $idx => $item): ?>
            <div id="card-<?php echo $idx; ?>" class="order-card">
                <div class="order-header">
                    <span>Order ID: <?php echo htmlspecialchars($item['order_id']); ?></span>
                    <span><?php echo htmlspecialchars($item['financials']); ?></span>
                </div>
                <div class="order-body">
                    <p><strong>Created Time:</strong> <?php echo htmlspecialchars($item['created_time']); ?></p>
                    <p><strong>Item Title:</strong> <?php echo htmlspecialchars($item['item_title']); ?></p>
                    <p><strong>Raw eBay Buyer Email:</strong> <code><?php echo htmlspecialchars($item['raw_buyer_email']); ?></code></p>
                    <p><strong>Delivered To Email:</strong> <code style="color:#27ae60; font-weight:bold;"><?php echo htmlspecialchars($item['delivery_email']); ?></code></p>
                    <p><strong>Resolved Google Drive Link:</strong> <a href="<?php echo htmlspecialchars($item['download_link']); ?>" target="_blank"><?php echo htmlspecialchars($item['download_link']); ?></a></p>
                    
                    <div style="margin-top: 15px;">
                        <span class="tag">Email #1 Preview — Buyer Delivery</span>
                        <div class="email-preview"><strong>To:</strong> <?php echo htmlspecialchars($item['buyer_mail_payload']['email']); ?>
<strong>Subject:</strong> <?php echo htmlspecialchars($item['buyer_mail_payload']['subject']); ?>

<?php echo htmlspecialchars($item['buyer_mail_payload']['message']); ?></div>
                    </div>

                    <div style="margin-top: 15px;">
                        <span class="tag admin">Email #2 Preview — Admin Audit Report</span>
                        <div class="email-preview"><strong>To:</strong> <?php echo htmlspecialchars($item['admin_mail_payload']['email']); ?>
<strong>Subject:</strong> <?php echo htmlspecialchars($item['admin_mail_payload']['subject']); ?>

<?php echo htmlspecialchars($item['admin_mail_payload']['message']); ?></div>
                    </div>

                    <form method="POST" onsubmit="return confirm('Dispatch these test emails for Order ID <?php echo htmlspecialchars($item['order_id']); ?>?');">
                        <input type="hidden" name="action" value="execute_single_dispatch">
                        <input type="hidden" name="selected_order_payload" value="<?php echo htmlspecialchars(json_encode($item)); ?>">
                        <button type="submit" class="btn-dispatch">Send Test Emails for This Order</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function switchOrder(idx) {
    // Hide all cards
    const cards = document.getElementsByClassName('order-card');
    for (let i = 0; i < cards.length; i++) {
        cards[i].classList.remove('active');
    }
    // Show selected card if valid
    if (idx !== "") {
        const target = document.getElementById('card-' + idx);
        if (target) {
            target.classList.add('active');
        }
    }
}
</script>
</body>
</html>
