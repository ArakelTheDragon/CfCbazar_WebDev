<?php
/**
 * eBay OAuth Callback & Management Dashboard Hub
 * Location: /diy/admin_delivery/index.php
 * Brand / Author: CfCbazar Group
 */

// Load root configuration containing $eBayAppID, $eBayCertID, and $eBaySignNew
require_once __DIR__ . '/../../config.php';

// Handle case where user declines authorization
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    echo "<h3>Authorization Declined</h3>";
    echo "<p>You chose not to grant consent to the application.</p>";
    exit;
}

// Intercept authorization code returned by eBay during OAuth redirect
if (isset($_GET['code'])) {
    $authCode = $_GET['code'];
    
    // Explicitly use your registered redirect URL matching the Developer Portal settings
    $redirectUri = 'https://cfcbazar.8bit.ca/diy/admin_delivery/index.php';

    $tokenUrl    = 'https://api.ebay.com/identity/v1/oauth2/token';
    $credentials = base64_encode($eBayAppID . ':' . $eBayCertID);

    $postData = [
        'grant_type'    => 'authorization_code',
        'code'          => $authCode,
        'redirect_uri'  => $redirectUri
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
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200) {
        $tokenData = json_decode($response, true);
        
        // Calculate and append token expiration timestamp
        $tokenData['expires_at'] = time() + ($tokenData['expires_in'] ?? 7200);

        // Persist tokens into local state memory
        $tokenFile = __DIR__ . '/ebay_tokens.json';
        file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        echo "<h2>Authentication Successful!</h2>";
        echo "<p>Your OAuth tokens have been successfully generated and saved to <code>ebay_tokens.json</code>.</p>";
        echo '<p><a href="index.php">Return to Dashboard</a></p>';
    } else {
        echo "<h2>Token Exchange Failed</h2>";
        echo "<p>HTTP Code: {$httpCode}</p>";
        echo "<p>Curl Error: {$curlErr}</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
    exit;
}

// Default dashboard layout with navigation menu and auth button
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CfCbazar eBay Delivery Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding-top: 40px; background: #f4f7f6; color: #333; margin: 0; }
        .wrapper { max-width: 500px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn { display: inline-block; background: #0064d2; color: #fff; padding: 12px 24px; font-size: 16px; font-weight: bold; text-decoration: none; border-radius: 4px; margin-top: 15px; }
        .btn:hover { background: #004b9b; }
        .nav-panel { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .nav-btn { background: #2c3e50; color: #fff; padding: 10px 16px; font-size: 14px; font-weight: bold; text-decoration: none; border-radius: 4px; }
        .nav-btn:hover { background: #1a252f; }
        .nav-btn.secondary { background: #27ae60; }
        .nav-btn.secondary:hover { background: #219653; }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Quick Links Navigation Panel -->
        <div class="card" style="padding: 20px;">
            <h4 style="margin-top:0; color:#2c3e50;">Delivery System Management</h4>
            <div class="nav-panel">
                <a href="process_orders.php" class="nav-btn" target="_blank">Run Order Poller</a>
                <a href="history.php" class="nav-btn" target="_blank">View 30-Day History</a>
                <a href="add_sku.php" class="nav-btn secondary" target="_blank">Manage SKU Map</a>
            </div>
        </div>

        <!-- Main Authentication Card -->
        <div class="card">
            <h3>CfCbazar eBay Delivery Endpoint</h3>
            <p>Click below to re-authenticate your production account and refresh your OAuth tokens.</p>
            <a href="<?php echo htmlspecialchars($eBaySignNew); ?>" class="btn">Sign in to Production with eBay</a>
        </div>
    </div>
</body>
</html>
