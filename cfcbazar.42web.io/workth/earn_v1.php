<?php
session_start();
include(__DIR__ . '/../config.php');

// Set PHP timezone to UTC
date_default_timezone_set('UTC');

// Tell MySQL session to use UTC
$conn->query("SET time_zone = '+00:00'");

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['username'];

// Fetch user tokens and last_mine_time
$stmt = $conn->prepare("SELECT tokens_earned, last_mine_time FROM workers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$tokens = $user['tokens_earned'];
$lastMine = $user['last_mine_time'] ? strtotime($user['last_mine_time']) : null;
$now = time();
$canMine = false;
$msg = "";

// Check if user has tokens and if 24h have passed since last mine
if ($tokens >= 1 && $lastMine !== null && ($now - $lastMine) >= 86400) {
    $reward = floor($tokens * 0.02);
    if ($reward < 1) $reward = 1;

    $tokens += $reward;

    // Update tokens, but don't update timestamp here (wait for button press)
    $update = $conn->prepare("UPDATE workers SET tokens_earned = ? WHERE email = ?");
    $update->bind_param("ds", $tokens, $email);
    $update->execute();

    $msg = "✅ You received $reward WorkTokens for mining!";
    $canMine = true; // allow pressing button again
} elseif ($tokens >= 1 && ($lastMine === null || ($now - $lastMine) >= 86400)) {
    $canMine = true;
}

// Handle Mine Now button: only update the timestamp and freeze button
if (isset($_POST['mine_now']) && $canMine) {
    $stmt = $conn->prepare("UPDATE workers SET last_mine_time = NOW() WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $msg = "⏱️ Mining started. Come back in 24h for your reward!";
    $canMine = false;
    $lastMine = $now;
}

// Calculate remaining cooldown time
$remaining = 0;
if ($lastMine !== null) {
    $remaining = 86400 - ($now - $lastMine);
    if ($remaining < 0) $remaining = 0;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Earn WorkTokens</title>
  <style>
    body { font-family: Arial, sans-serif; background: #121212; color: #eee; text-align: center; padding: 40px; }
    h2 { color: #00e676; }
    .btn {
      padding: 12px 24px;
      font-size: 16px;
      background-color: #00c853;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 20px;
    }
    .btn:disabled {
      background-color: #555;
      cursor: not-allowed;
    }
    .info { margin-top: 20px; font-size: 14px; }
  </style>
</head>
<body>

<h2>🪙 Earn WorkTokens</h2>

<p>Your current WorkToken balance: <strong><?= htmlspecialchars($tokens) ?></strong></p>

<?php if ($msg) echo "<p><strong>" . htmlspecialchars($msg) . "</strong></p>"; ?>

<?php if ($tokens < 1): ?>
  <p>You need at least 1 WorkToken to start staking.<br>Buy 100 WorkTokens for 0.004 BNB.</p>
<?php else: ?>
  <form method="post">
    <button class="btn" type="submit" name="mine_now" <?= $canMine ? '' : 'disabled' ?>>
      <?= $canMine ? 'Mine Now' : '⛏️ Come back later' ?>
    </button>
  </form>

  <div class="info">
    <?php if (!$canMine): ?>
      <p>Next mining available in: <strong><?= gmdate("H:i:s", $remaining) ?></strong></p>
    <?php else: ?>
      <p>Press Mine Now to start your next 24h cycle.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

</body>
</html>