<?php
/**
 * Production eBay Order Processor & PHPMailer Deliverer (OAuth Active Poller)
 * Location: /diy/admin_delivery/process_orders.php
 * Brand / Author: CfCbazar Group
 *
 * Delivery resolution: STRICTLY by SKU.
 *   - SKU found in sku_map.json        → use that entry
 *   - SKU empty or not found           → use DEFAULT entry
 *   - Item ID is never used as a fallback
 *
 * Also marks each successfully processed order as SHIPPED via Trading API CompleteSale.
 */

require_once __DIR__ . '/../../config.php';

$tokenFile  = __DIR__ . '/ebay_tokens.json';
$logFile    = __DIR__ . '/ebay_logs.json';
$skuMapFile = __DIR__ . '/sku_map.json';

if (!file_exists($tokenFile)) {
    exit("OAuth tokens file (ebay_tokens.json) not found. Please authenticate via index.php first.");
}

$tokenData = json_decode(file_get_contents($tokenFile), true);
if (empty($tokenData['refresh_token'])) {
    exit("Invalid token file: missing refresh_token.");
}

/**
 * Load sku_map.json and normalize SKU keys to uppercase, trimmed form.
 */
function loadSkuMap(string $path): array {
    if (!file_exists($path)) {
        return ['DEFAULT' => [
            'name' => 'CfCbazar Digital Guide',
            'link' => 'https://cfcbazar.42web.io/downloads/ebook_secure_link.pdf',
        ]];
    }
    $raw = json_decode(file_get_contents($path), true);
    if (!is_array($raw)) {
        return ['DEFAULT' => [
            'name' => 'CfCbazar Digital Guide',
            'link' => 'https://cfcbazar.42web.io/downloads/ebook_secure_link.pdf',
        ]];
    }

    $normalized = [];
    foreach ($raw as $key => $entry) {
        if (!is_array($entry)) continue;
        if (!isset($entry['name'], $entry['link'])) continue;

        $normKey = ($key === 'DEFAULT') ? 'DEFAULT' : strtoupper(trim((string)$key));
        $normalized[$normKey] = [
            'name' => (string)$entry['name'],
            'link' => (string)$entry['link'],
        ];
    }

    if (!isset($normalized['DEFAULT'])) {
        $normalized['DEFAULT'] = [
            'name' => 'CfCbazar Digital Guide',
            'link' => 'https://cfcbazar.42web.io/downloads/ebook_secure_link.pdf',
        ];
    }
    return $normalized;
}

$skuMap = loadSkuMap($skuMapFile);

/**
 * Resolve a delivery target strictly by SKU.
 */
function resolveDelivery(string $sku, array $skuMap, string $fallbackTitle = 'Your Digital Purchase'): array {
    $key = strtoupper(trim($sku));
    if ($key !== '' && isset($skuMap[$key])) {
        return [$skuMap[$key]['name'], $skuMap[$key]['link'], 'sku'];
    }
    $d = $skuMap['DEFAULT'];
    return [
        $d['name'] !== '' ? $d['name'] : $fallbackTitle,
        $d['link'],
        'default',
    ];
}

function getValidOAuthToken($tokenData, $tokenFile, $eBayAppID, $eBayCertID) {
    if (!empty($tokenData['access_token']) && !empty($tokenData['expires_at']) && time() < ($tokenData['expires_at'] - 60)) {
        return $tokenData['access_token'];
    }

    $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';
    $credentials = base64_encode($eBayAppID . ':' . $eBayCertID);

    $postData = [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $tokenData['refresh_token'],
        'scope'         => 'https://api.ebay.com/oauth/api_scope',
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
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

/**
 * Mark an eBay order as shipped via Trading API CompleteSale.
 * Returns true on Ack=Success (or Ack=Warning with no errors), false otherwise.
 */
function markOrderShipped(
    string $orderId,
    string $accessToken,
    string $eBayDevID,
    string $eBayAppID,
    string $eBayCertID
): bool {
    $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n"
         . '<CompleteSaleRequest xmlns="urn:ebay:apis:eBLBaseComponents">' . "\n"
         . '  <RequesterCredentials>' . "\n"
         . '    <eBayAuthToken>' . htmlspecialchars($accessToken, ENT_XML1) . '</eBayAuthToken>' . "\n"
         . '  </RequesterCredentials>' . "\n"
         . '  <OrderID>' . htmlspecialchars($orderId, ENT_XML1) . '</OrderID>' . "\n"
         . '  <Shipped>true</Shipped>' . "\n"
         . '</CompleteSaleRequest>';

    $headers = [
        'X-EBAY-API-COMPATIBILITY-LEVEL: 1173',
        'X-EBAY-API-DEV-NAME: '   . $eBayDevID,
        'X-EBAY-API-APP-NAME: '   . $eBayAppID,
        'X-EBAY-API-CERT-NAME: '  . $eBayCertID,
        'X-EBAY-API-CALL-NAME: CompleteSale',
        'X-EBAY-API-SITEID: 0',
        'Content-Type: text/xml',
    ];

    $ch = curl_init('https://api.ebay.com/ws/api.dll');
    curl_setopt_array($ch, [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr !== '' || $httpCode !== 200) {
        error_log("[process_orders] CompleteSale HTTP {$httpCode} for order {$orderId}: {$curlErr}");
        return false;
    }

    $parsed = @simplexml_load_string($response);
    if ($parsed === false) {
        error_log("[process_orders] CompleteSale invalid XML for order {$orderId}: " . substr($response, 0, 500));
        return false;
    }

    $ack = (string)($parsed->Ack ?? '');

    if ($ack === 'Success' || $ack === 'Warning') {
        // Even on Warning, if no Errors block is present, consider it shipped.
        if (isset($parsed->Errors)) {
            $msg = (string)($parsed->Errors->LongMessage ?? $parsed->Errors->ShortMessage ?? 'unknown');
            error_log("[process_orders] CompleteSale warning for order {$orderId}: {$msg}");
            // Ack=Warning with Errors: still counts as shipped on eBay; log but do not fail.
        }
        return true;
    }

    $errMsg = (string)($parsed->Errors->LongMessage ?? $parsed->Errors->ShortMessage ?? 'unknown');
    error_log("[process_orders] CompleteSale Ack='{$ack}' for order {$orderId}: {$errMsg}");
    return false;
}

$accessToken = getValidOAuthToken($tokenData, $tokenFile, $eBayAppID, $eBayCertID);
if (!$accessToken) {
    exit("Failed to refresh eBay OAuth access token.");
}

$endpointUrl = 'https://api.ebay.com/ws/api.dll';

$xmlRequest = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
  <RequesterCredentials>
    <eBayAuthToken>{$accessToken}</eBayAuthToken>
  </RequesterCredentials>
  <DetailLevel>ReturnAll</DetailLevel>
  <NumberOfDays>3</NumberOfDays>
  <OrderRole>Seller</OrderRole>
  <OrderStatus>Completed</OrderStatus>
</GetOrdersRequest>
XML;

$headers = [
    'X-EBAY-API-COMPATIBILITY-LEVEL: 1173',
    'X-EBAY-API-DEV-NAME: '   . $eBayDevID,
    'X-EBAY-API-APP-NAME: '   . $eBayAppID,
    'X-EBAY-API-CERT-NAME: '  . $eBayCertID,
    'X-EBAY-API-CALL-NAME: GetOrders',
    'X-EBAY-API-SITEID: 0',
    'Content-Type: text/xml',
];

$ch = curl_init($endpointUrl);
curl_setopt_array($ch, [
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => $xmlRequest,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr !== '' || $httpCode !== 200) {
    exit("GetOrders Request Failed: HTTP {$httpCode} - {$curlErr}");
}

$logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
if (!is_array($logs)) $logs = [];
$processedOrderIds = array_column($logs, 'order_id');

libxml_use_internal_errors(true);
$xml = simplexml_load_string($response);
if ($xml === false) {
    exit("Failed to parse eBay XML response.");
}

$orders  = $xml->OrderArray->Order ?? [];
$updated = false;

$mailScriptUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http")
    . "://" . $_SERVER['HTTP_HOST']
    . dirname(dirname(dirname($_SERVER['PHP_SELF']))) . '/mail.php';
if (!filter_var($mailScriptUrl, FILTER_VALIDATE_URL)) {
    $mailScriptUrl = 'https://cfcbazar.8bit.ca/mail.php';
}

foreach ($orders as $order) {
    $orderId   = (string)$order->OrderID;
    $itemSku   = strtoupper(trim((string)($order->TransactionArray->Transaction->Item->SKU ?? '')));
    $itemId    = (string)($order->TransactionArray->Transaction->Item->ItemID ?? '');
    $itemTitle = (string)($order->TransactionArray->Transaction->Item->Title ?? 'Your Digital Purchase');

    $totalPrice  = (string)($order->Total ?? '0.00');
    $currency    = (string)($order->Currency ?? 'USD');
    $transaction = $order->TransactionArray->Transaction ?? null;
    $itemPrice   = (string)($transaction->TransactionPrice ?? '0.00');
    $taxesPaid   = (string)($order->ShippingDetails->SalesTax->SalesTaxAmount ?? '0.00');
    $netValue    = number_format((float)$itemPrice - (float)$taxesPaid, 2, '.', '');

    if (in_array($orderId, $processedOrderIds, true)) {
        continue;
    }

    $rawBuyerEmail      = (string)($transaction->Buyer->Email ?? 'N/A');
    $deliveryBuyerEmail = $rawBuyerEmail;

    // eBay masks buyer emails for most orders; send to admin so nothing is lost.
    if (empty($deliveryBuyerEmail)
        || strpos($deliveryBuyerEmail, 'invalid') !== false
        || strpos($deliveryBuyerEmail, 'ebay.com') !== false) {
        $deliveryBuyerEmail = 'cfcbazar@gmail.com';
    }

    // STRICT SKU LOOKUP
    [$bookName, $downloadLink, $matchedBy] = resolveDelivery($itemSku, $skuMap, $itemTitle);

    if ($matchedBy === 'default' && $itemSku !== '') {
        error_log("[process_orders] Unknown SKU '{$itemSku}' for order {$orderId}; using DEFAULT.");
    } elseif ($matchedBy === 'default' && $itemSku === '') {
        error_log("[process_orders] Order {$orderId} has no SKU; using DEFAULT.");
    }

    $allSentSuccessfully = true;

    // 1. Buyer delivery email
    $buyerEmailSubject = "Your Download Link: " . $bookName;
    $buyerEmailMessage = "Thank you for your purchase from CfCbazar Group!\n\n"
        . "You can download your digital guide ({$bookName}) here:\n"
        . $downloadLink . "\n\n"
        . "If you have any questions or experience issues downloading, feel free to reply directly to this email.";

    $chMail = curl_init($mailScriptUrl);
    curl_setopt_array($chMail, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'email'       => $deliveryBuyerEmail,
            'subject'     => $buyerEmailSubject,
            'message'     => $buyerEmailMessage,
            'heading'     => 'CfCbazar Digital Delivery',
            'intro'       => 'Your order for "' . $bookName . '" has been processed successfully.',
            'footer_note' => 'Thank you for shopping with CfCbazar Group.',
            'from_name'   => 'CfCbazar Group',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res1  = curl_exec($chMail);
    $code1 = curl_getinfo($chMail, CURLINFO_HTTP_CODE);
    curl_close($chMail);
    $json1 = json_decode($res1, true);
    if ($code1 !== 200 || !isset($json1['success'])) {
        $allSentSuccessfully = false;
    }

    // 2. Admin audit email
    $adminSubject = "[Order Audit] Processed Order ID: " . $orderId;
    $adminMessage = "An automated order was processed and delivered.\n\n"
        . "--- ORDER DETAILS ---\n"
        . "Order ID: " . $orderId . "\n"
        . "Item Title: " . $itemTitle . "\n"
        . "SKU: " . ($itemSku !== '' ? $itemSku : '(none)') . "\n"
        . "Item ID: " . $itemId . "\n"
        . "Matched by: " . $matchedBy . "\n"
        . "Client Email (raw from eBay): " . $rawBuyerEmail . "\n"
        . "Delivered To: " . $deliveryBuyerEmail . "\n\n"
        . "--- FINANCIAL BREAKDOWN ---\n"
        . "Item Price: " . $itemPrice . " " . $currency . "\n"
        . "Taxes: " . $taxesPaid . " " . $currency . "\n"
        . "Total Order Price: " . $totalPrice . " " . $currency . "\n"
        . "Estimated Net Value: " . $netValue . " " . $currency . "\n\n"
        . "Download Link Sent: " . $downloadLink;

    $chMailAdmin = curl_init($mailScriptUrl);
    curl_setopt_array($chMailAdmin, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'email'       => 'cfcbazar@gmail.com',
            'subject'     => $adminSubject,
            'message'     => $adminMessage,
            'heading'     => 'Order Processing Audit Report',
            'intro'       => 'Order #' . $orderId . ' has been successfully fulfilled.',
            'footer_note' => 'CfCbazar Automated Poller System',
            'from_name'   => 'CfCbazar System',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res2  = curl_exec($chMailAdmin);
    $code2 = curl_getinfo($chMailAdmin, CURLINFO_HTTP_CODE);
    curl_close($chMailAdmin);
    $json2 = json_decode($res2, true);
    if ($code2 !== 200 || !isset($json2['success'])) {
        $allSentSuccessfully = false;
    }

    // 3. Mark the order as SHIPPED via CompleteSale
    $shippedOk = false;
    if ($allSentSuccessfully) {
        $shippedOk = markOrderShipped($orderId, $accessToken, $eBayDevID, $eBayAppID, $eBayCertID);

        if ($shippedOk) {
            error_log("[process_orders] Order {$orderId} marked as shipped.");
        } else {
            error_log("[process_orders] Order {$orderId} NOT marked as shipped; will retry next poll.");
        }
    }

    // Only record locally if BOTH emails succeeded AND eBay confirmed shipped.
    if ($allSentSuccessfully && $shippedOk) {
        $logs[] = [
            'order_id'     => $orderId,
            'item_sku'     => $itemSku,
            'item_id'      => $itemId,
            'item_title'   => $itemTitle,
            'client_email' => $rawBuyerEmail,
            'sent_to'      => $deliveryBuyerEmail,
            'matched_by'   => $matchedBy,
            'book_name'    => $bookName,
            'shipped'      => true,
            'net_value'    => $netValue . ' ' . $currency,
            'processed_at' => date('Y-m-d H:i:s'),
        ];
        $processedOrderIds[] = $orderId;
        $updated = true;
    }
}

if ($updated) {
    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Orders processed successfully, download links dispatched, orders marked as shipped, and detailed admin audit reports sent to cfcbazar@gmail.com.";
} else {
    echo "Polling complete: No new orders found.";
}
