<?php
// ----- testapi.php -----
if (session_status() === PHP_SESSION_NONE) session_start();
require 'config.php';

date_default_timezone_set('UTC');

$email = $_SESSION['email'] ?? '';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid or missing session email");
}

// Fetch remote data
echo ">>> Enter remote fetch block\n";
$remote_json = @file_get_contents("http://cfc-api.atwebpages.com/api.php?email=" . urlencode($email));
$remote_data = json_decode($remote_json, true);
$remote_tokens = floatval($remote_data['tokens_earned'] ?? 0);
$remote_mintme = floatval($remote_data['mintme'] ?? 0);
$remote_devices = $remote_data['devices'] ?? [];
echo "<<< Leave remote fetch block\n";

// Update local WorkToken
if ($remote_tokens > 0) {
    echo ">>> Enter WorkToken DB update block\n";
    $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + ? WHERE email = ?");
    $stmt->bind_param("ds", $remote_tokens, $email);
    $stmt->execute();
    $stmt->close();
    echo "<<< Leave WorkToken DB update block\n";
}

// Update local WorkTHR
if ($remote_mintme > 0) {
    echo ">>> Enter WorkTHR DB update block\n";
    $stmt = $conn->prepare("UPDATE workers SET mintme = mintme + ? WHERE email = ?");
    $stmt->bind_param("ds", $remote_mintme, $email);
    $stmt->execute();
    $stmt->close();
    echo "<<< Leave WorkTHR DB update block\n";
}

// Sync devices
if (!empty($remote_devices)) {
    echo ">>> Enter device sync block\n";
    foreach ($remote_devices as $device) {
        $mac = $device['mac_address'] ?? '';
        $last_mine = $device['last_mine_time'] ?? null;

        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/i', $mac)) continue;

        $check = $conn->prepare("SELECT COUNT(*) FROM devices WHERE email = ? AND mac_address = ?");
        $check->bind_param("ss", $email, $mac);
        $check->execute();
        $check->bind_result($exists);
        $check->fetch();
        $check->close();

        $timestamp = strtotime($last_mine);
        $active = ($timestamp !== false && (time() - $timestamp <= 50)) ? 1 : 0;

        if ($exists === 0) {
            $insert = $conn->prepare("INSERT INTO devices (email, mac_address, last_mine_time, active) VALUES (?, ?, ?, ?)");
            $insert->bind_param("sssi", $email, $mac, $last_mine, $active);
            $insert->execute();
            $insert->close();
            echo "Inserted device: $mac\n";
        } else {
            $update = $conn->prepare("UPDATE devices SET last_mine_time = ?, active = ? WHERE email = ? AND mac_address = ?");
            $update->bind_param("siss", $last_mine, $active, $email, $mac);
            $update->execute();
            $update->close();
            echo "Updated device: $mac\n";
        }
    }
    echo "<<< Leave device sync block\n";

    // Auto-deactivate stale devices
    echo ">>> Enter stale device deactivation block\n";
    $stmt = $conn->prepare("
        UPDATE devices
           SET active = 0
         WHERE email = ?
           AND last_mine_time IS NOT NULL
           AND TIMESTAMPDIFF(SECOND, last_mine_time, NOW()) > 50
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();
    echo "<<< Leave stale device deactivation block\n";
}

// Reset remote tokens and mintme
if ($remote_tokens > 0 || $remote_mintme > 0) {
    echo ">>> Enter remote reset block\n";
    $post_data = http_build_query([
        'email'       => $email,
        'mac_address' => 'sync-trigger',
        'tokens'      => 0.12345 // triggers reset of both fields
    ]);
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $post_data
        ]
    ];
    $context = stream_context_create($opts);
    @file_get_contents("http://cfc-api.atwebpages.com/api.php", false, $context);
    echo "<<< Leave remote reset block\n";
}

echo "Tokens synced for $email (moved $remote_tokens WTK, $remote_mintme THR)\n";
?>
