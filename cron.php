<?php
// ---------------------------------------------
// CRON DIAGNOSTICS
// ---------------------------------------------
$timestamp = date("Y-m-d H:i:s");
$server_ip = $_SERVER['SERVER_ADDR'] ?? 'CLI';
$script    = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
$memory    = round(memory_get_usage() / 1024 / 1024, 2) . " MB";

$subject = "Cron Triggered at $timestamp";

$message = "
Cron job executed successfully.

Timestamp: $timestamp
Server IP: $server_ip
Script Path: $script
Memory Usage: $memory
";

// ---------------------------------------------
// SEND POST REQUEST TO admin_mailer.php
// ---------------------------------------------
$url = "/mail_enhanced.php";

$postData = [
    "email"       => "cfcbazar@gmail.com",
    "subject"     => $subject,
    "verify_code" => $message,
    "debug"       => 1   // tells admin_mailer.php to enable SMTP debug
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// ⭐ REQUIRED FIX: Ignore self-signed SSL certificate (InfinityFree/ByetHost)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$error    = curl_error($ch);
curl_close($ch);

$email_status = $error ? "ERROR: $error" : "OK (admin_mailer.php executed)";

// ---------------------------------------------
// BROWSER DEBUG OUTPUT
// ---------------------------------------------
if (php_sapi_name() !== 'cli') {
    echo "<pre style='background:#111;color:#0f0;padding:20px;border-radius:8px;font-size:14px;'>";
    echo "=== CRON.PHP DEBUG CONSOLE ===\n\n";
    echo "Timestamp:        $timestamp\n";
    echo "Server IP:        $server_ip\n";
    echo "Script Path:      $script\n";
    echo "Memory Usage:     $memory\n\n";
    echo "Email Status:     $email_status\n\n";
    echo "Raw Response From admin_mailer.php:\n$response\n";
    echo "=== END DEBUG ===";
    echo "</pre>";
}
