<?php
header('Content-Type: application/json');
include(__DIR__ . '/../config.php');

$method = $_SERVER['REQUEST_METHOD'];
$ip = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$timestamp = date('Y-m-d H:i:s');

// Handle POST to log speed test
if ($method === 'POST') {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    if (!isset($data['max']) || !isset($data['avg'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing 'max' or 'avg'"]);
        exit;
    }

    $max = floatval($data['max']);
    $avg = floatval($data['avg']);

    $stmt = $conn->prepare("INSERT INTO speed_results (ip, max_speed, avg_speed, timestamp, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sddss", $ip, $max, $avg, $timestamp, $userAgent);

    if ($stmt->execute()) {
        $response = ["status" => "success"];
    } else {
        $response = ["status" => "error", "message" => "Database insert failed"];
    }

    $stmt->close();
} else {
    $response = ["status" => "info"];
}

// Return last 10 results
$res = $conn->query("SELECT ip, max_speed AS max, avg_speed AS avg, timestamp, user_agent FROM speed_results ORDER BY timestamp DESC LIMIT 10");

$recent = [];
while ($row = $res->fetch_assoc()) {
    $recent[] = $row;
}

$response['recent'] = $recent;
echo json_encode($response);