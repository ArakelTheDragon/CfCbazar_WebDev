<?php
/**
 * eBay Raw Order Data Inspector & JSON Viewer
 * Location: /diy/admin_delivery/debug_orders.php
 * Brand / Author: CfCbazar Group
 */

require_once __DIR__ . '/../../config.php';

$tokenFile = __DIR__ . '/ebay_tokens.json';

if (!file_exists($tokenFile)) {
    exit("OAuth tokens file (ebay_tokens.json) not found. Please authenticate via index.php first.");
}

$tokenData = json_decode(file_get_contents($tokenFile), true);
if (empty($tokenData['refresh_token'])) {
    exit("Invalid token file: missing refresh_token.");
}

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

// Execute Trading API GetOrders Call for past 30 days
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr !== '' || $httpCode !== 200) {
    exit("GetOrders Request Failed: HTTP {$httpCode} - {$curlErr}");
}

// Parse XML response into an associative array via JSON conversion for deep inspection
libxml_use_internal_errors(true);
$xmlObject = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA);
$jsonString = json_encode($xmlObject);
$orderArrayData = json_decode($jsonString, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CfCbazar - eBay Raw Orders Debugger</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h2 { color: #2c3e50; margin-top: 0; }
        pre { background: #1e1e1e; color: #dcdcdc; padding: 15px; border-radius: 6px; overflow-x: auto; font-family: Consolas, Monaco, monospace; font-size: 13px; line-height: 1.4; max-height: 700px; }
        .info { background: #e2e8f0; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; font-weight: bold; color: #2d3748; }
    </style>
</head>
<body>
<div class="container">
    <h2>eBay GetOrders API - Raw JSON Payload Inspector</h2>
    <div class="info">API Endpoint: <code><?php echo htmlspecialchars($endpointUrl); ?></code> | Status: HTTP <?php echo $httpCode; ?></div>
    <p>Below is the complete hierarchical JSON representation of all data returned by eBay's Trading API for orders across the past 30 days:</p>
    
    <pre><?php echo htmlspecialchars(json_encode($orderArrayData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
</div>
</body>
</html>
