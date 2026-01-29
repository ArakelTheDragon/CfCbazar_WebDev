<?php
// bot-warning.php
date_default_timezone_set('UTC');

$logFile = __DIR__ . '/bot_hits.json';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$time = date('Y-m-d H:i:s');

// Identify bot name from User-Agent
if (preg_match('/googlebot/i', $userAgent)) {
    $bot = 'Googlebot';
} elseif (preg_match('/facebookexternalhit/i', $userAgent)) {
    $bot = 'Facebook';
} elseif (preg_match('/bingbot/i', $userAgent)) {
    $bot = 'Bingbot';
} elseif (preg_match('/applebot/i', $userAgent)) {
    $bot = 'Applebot';
} else {
    $bot = 'Unknown';
}

// Load existing log
$log = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];

// Update hit count
if (!isset($log[$bot])) {
    $log[$bot] = ['hits' => 0, 'last' => null];
}
$log[$bot]['hits'] += 1;
$log[$bot]['last'] = $time;

// Save updated log
file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bot Access Delayed</title>
</head>
<body>
  <h1>Access Delayed</h1>
  <p>Hello <strong><?php echo htmlspecialchars($bot); ?></strong>, our system is currently limiting automated access.</p>
  <p>Please try again after 24 hours. Your visit has been logged.</p>
</body>
</html>