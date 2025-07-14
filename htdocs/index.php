<?php
// index.php – Worker Dashboard with Market + Feature Links
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("config.php");

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    unset($_SESSION['username']);
    header('Location: login.php');
    exit();
}

$email = $_SESSION['username'];

// === Ensure user has a row in workers table ===
$check_worker = $conn->prepare("SELECT id FROM workers WHERE email = ?");
$check_worker->bind_param("s", $email);
$check_worker->execute();
$check_worker->store_result();

if ($check_worker->num_rows === 0) {
    // Insert a new blank worker record for this user
    $worker_name = $email;
    $insert_worker = $conn->prepare("
        INSERT INTO workers (email, worker_name)
        VALUES (?, ?)
    ");
    $insert_worker->bind_param("ss", $email, $worker_name);
    $insert_worker->execute();
}

// Handle worker link
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["worker_name"])) {
    $worker_name = trim($_POST["worker_name"]);
    if (strpos($worker_name, $email) !== false) {
        $update_worker = $conn->prepare("
            UPDATE workers 
               SET email = ?
             WHERE worker_name = ?
        ");
        $update_worker->bind_param("ss", $email, $worker_name);
        $update_worker->execute();
        $message = "Worker linked successfully!";
    } else {
        $message = "Worker name must contain your email.";
    }
}

// Fetch worker stats
$query = "SELECT worker_name, hr2, dHr, tokens_earned, mintme 
            FROM workers 
           WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// Market data
$marketData = null;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.mintme.com/dev/api/v2/auth/markets?offset=0&limit=500");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "accept: application/json",
    "X-API-ID: $api_key",
    "X-API-KEY: $private_key"
]);
$response = curl_exec($ch);
curl_close($ch);

if ($response !== false) {
    $markets = json_decode($response, true);
    foreach ($markets as $market) {
        if ($market['base']['symbol'] === 'WorkTH' 
            && $market['quote']['symbol'] === 'MINTME') {
            $marketData = $market;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Worker Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 20px; background: #f2f2f2; color: #333; }
        h2, h3 { color: #333; text-align: center; }
        .table-container { overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; text-align: center; }
        th { background-color: #4CAF50; color: white; }
        form { margin-top: 25px; text-align: center; }
        input[type="text"] { padding: 10px; width: 80%; max-width: 300px; border-radius: 6px; border: 1px solid #ccc; margin-bottom: 10px; }
        button { padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .logout-btn { margin-top: 30px; padding: 10px 20px; background: red; color: white; text-decoration: none; border-radius: 6px; display: inline-block; }
        .message { text-align: center; margin: 15px 0; }
        .success { color: green; }
        .error { color: red; }
        .links { text-align: center; margin-top: 20px; }
        .links a { display: block; margin: 8px 0; color: #4CAF50; text-decoration: none; font-weight: bold; }
        .market-dashboard { max-width: 600px; margin: 40px auto 0; background: linear-gradient(145deg, #ffffff, #e6e6e6); border-radius: 16px; padding: 20px 30px; box-shadow: 0 8px 16px rgba(0,0,0,0.1); }
        .market-dashboard h3 { font-size: 1.6em; margin-bottom: 15px; color: #333; text-align: center; }
        .market-dashboard .info { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 1em; }
        .market-dashboard .info p { margin: 0; }
        .market-dashboard .cta { text-align: center; margin-top: 20px; }
        .market-dashboard .cta a { background-color: #4CAF50; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; }
        @media screen and (max-width: 600px) {
            .market-dashboard .info { grid-template-columns: 1fr; }
            input[type="text"] { width: 90%; }
        }
    </style>
    <script>
        // Auto-refresh every 50 seconds
        setInterval(() => window.location.reload(), 50000);
    </script>
</head>
<body>

    <h2>Welcome, <?php echo htmlspecialchars($email); ?>!</h2>

    <div class="table-container">
        <h3>Your Worker Stats</h3>
        <table>
            <tr>
                <th>Worker Name</th>
                <th>HR2 (Stable)</th>
                <th>dHr (Duino)</th>
                <th>dWorkTokens</th>
                <th>mWorkTokens</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['worker_name']); ?></td>
                <td><?php echo $row['hr2']; ?> H/s</td>
             <td><?php echo number_format((float)($row['dHr'] ?? 0), 0); ?> H/s</td>
                <td><?php echo number_format((float)($row['tokens_earned'] ?? 0), 4); ?></td>
                <td><?php echo number_format($row['mintme'], 4); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <form method="post">
        <h3>Link Your Worker</h3>
        <?php if (isset($message)): ?>
            <div class="message <?php echo strpos($message, 'success') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <input type="text" name="worker_name" placeholder="Your worker name..." required>
        <br>
        <button type="submit">Link Worker</button>
    </form>

    <div class="links">
        <a href="https://cfcbazar.ct.ws/r.php?go=21" target="_blank">🚚 Shopping Assistant</a>
        <a href="https://cfcbazar.ct.ws/r.php?go=22" target="_blank">🔗 Smart Deals</a>
        <a href="https://cfcbazar.ct.ws/r.php?go=23" target="_blank">🎮 Games</a>
        <a href="https://cfcbazar.ct.ws/r.php?go=24" target="_blank">🔧 DIY & features</a>
        <a href="https://cfcbazar.ct.ws/r.php?go=25" target="_blank">🎵 Music</a>
        <a href="https://cfcbazar.ct.ws/r.php?go=28" target="_blank">💰 Buy Platform Credit With BNB</a>
        <a href="https://cfcbazar.ct.ws/r.php?go=26" target="_blank">📖 About the WorkToken</a>
    </div>

     <div class="market-dashboard">
        <h3>📈 WorkTH / MINTME Market</h3>
        <?php if ($marketData): ?>
        <div class="info">
            <p><strong>Last Price:</strong> <?php echo $marketData['lastPrice']; ?> MINTME</p>
            <p><strong>Open:</strong> <?php echo $marketData['openPrice']; ?> MINTME</p>
            <p><strong>24h Volume:</strong> <?php echo $marketData['dayVolume']; ?> WorkTH</p>
            <p><strong>Buy Depth:</strong> <?php echo $marketData['buyDepth']; ?> MINTME</p>
            <p><strong>24h Change:</strong> <?php echo $marketData['changePercentage']; ?>%</p>
        </div>
        <?php else: ?>
        <p style="text-align: center;">Market data unavailable</p>
        <?php endif; ?>
        <div class="cta">
            <a href="https://cfcbazar.ct.ws/r.php?go=27" target="_blank">🚀 Trade WorkTH on MintMe</a>
        </div>
    </div>

    <div style="text-align: center;">
        <a class="logout-btn" href="?logout=1">🔓 Logout</a>
    </div>

</body>
</html>