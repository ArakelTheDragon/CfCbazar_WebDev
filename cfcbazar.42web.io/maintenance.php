<?php
// Block bots by user agent
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$bot_patterns = '/bot|crawl|spider|slurp|wget|curl|python|axios|httpclient|java|libwww|facebookexternalhit|google|bing|applebot|twitterbot/i';
if (preg_match($bot_patterns, $ua)) {
    http_response_code(403);
    exit('Access denied');
}

// Inline traffic logger with referrer
function log_traffic($filename = 'traffic_log.json') {
    $entry = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'time' => date('Y-m-d H:i:s'),
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
    ];

    $log = [];
    if (file_exists($filename)) {
        $existing = file_get_contents($filename);
        $log = json_decode($existing, true) ?? [];
    }

    $log[] = $entry;
    file_put_contents($filename, json_encode($log, JSON_PRETTY_PRINT));
}

log_traffic();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CfCbazar Maintenance</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <style>
    body {
      background: #f8f9fa;
      font-family: Arial, sans-serif;
      text-align: center;
      padding: 60px 20px;
      color: #333;
    }
    .logo {
      max-width: 200px;
      margin-bottom: 30px;
    }
    .message {
      font-size: 1.6em;
      margin-bottom: 20px;
    }
    .subtext {
      font-size: 1em;
      color: #666;
    }
    .spinner {
      margin: 40px auto;
      width: 50px;
      height: 50px;
      border: 6px solid #ccc;
      border-top-color: #007bff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .footer {
      margin-top: 60px;
      font-size: 0.9em;
      color: #999;
    }
  </style>
</head>
<body>
  <img src="/images/cfcbazar-banner.jpg" alt="CfCbazar Logo" class="logo">
  <div class="message">🚧 CfCbazar is temporarily offline due to high traffic</div>
  <div class="subtext">We’ve received more visits than expected. Our servers are catching their breath.</div>
  <div class="spinner"></div>
  <div class="subtext" style="margin-top:30px;">Please check back in 24 hours. We appreciate your patience!</div>
  <div class="footer">© CfCbazar. All rights reserved.</div>
</body>
</html> 