<?php
require "../config.php"; // correct path for your structure

$data = json_decode(file_get_contents("php://input"), true);

$worker = intval($data['worker']);
$accepted = intval($data['accepted']);

// Fetch worker
$stmt = $db->prepare("SELECT accepted_shares, dropdown FROM workers WHERE id = ?");
$stmt->execute([$worker]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) exit("Invalid worker");

// Only count NEW shares
$newShares = $accepted - $row['accepted_shares'];
if ($newShares < 1) exit("No new shares");

// Reward rates
$rate_thr = 0.0000005; // WorkTHR per share
$rate_wtk = 0.00001;   // WorkToken per share (disabled)

// Determine reward type
$rewardType = $row['dropdown'];
$reward = 0;

if ($rewardType === "WorkTHR") {
    $reward = $newShares * $rate_thr;
} else {
    // WTK disabled → force WorkTHR
    $reward = $newShares * $rate_thr;
    $rewardType = "WorkTHR";
}

// Update worker
$stmt = $db->prepare("
    UPDATE workers 
    SET 
        accepted_shares = accepted_shares + ?,
        accepted_shares_temp = accepted_shares_temp + ?,
        tokens_earned = tokens_earned + ?,
        last_submission = NOW(),
        dropdown = ?
    WHERE id = ?
");
$stmt->execute([$newShares, $newShares, $reward, $rewardType, $worker]);

echo "OK";

