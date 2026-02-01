<?php
session_start();
require_once __DIR__ . '/../config.php'; // Ensure this file initializes $conn (PDO recommended)

// Redirect if not logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
$minWithdraw = 5.0;
$fee = 1.0;
$error = '';
$success = '';

// Handle withdrawal form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_amount'])) {
    $amount = floatval($_POST['withdraw_amount']);
    $wallet = trim($_POST['wallet_address']);

    if ($amount < $minWithdraw) {
        $error = "Minimum withdrawal is 5 WorkTokens.";
    } else {
        // Fetch user's balance
        $stmt = $conn->prepare("SELECT tokens_earned FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $balance = floatval($stmt->fetchColumn());

        if ($amount > $balance) {
            $error = "Insufficient token balance.";
        } else {
            // Record withdrawal in DB
            $stmt = $conn->prepare("INSERT INTO withdraws (email, wallet_address, amount, fee, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$email, $wallet, $amount, $fee]);

            // Deduct tokens (you may prefer to hold off until processed)
            $stmt = $conn->prepare("UPDATE users SET tokens_earned = tokens_earned - ? WHERE email = ?");
            $stmt->execute([$amount, $email]);

            $success = "Withdrawal request submitted!";
        }
    }
}

// Fetch withdrawal history
$stmt = $conn->prepare("SELECT * FROM withdraws WHERE email = ? ORDER BY created_at DESC");
$stmt->execute([$email]);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Withdraw WorkTokens</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2em; }
        table { border-collapse: collapse; width: 100%; margin-top: 1em; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .alert { padding: 10px; margin-bottom: 10px; }
        .error { background-color: #fdd; }
        .success { background-color: #dfd; }
    </style>
</head>
<body>

<h2>Withdraw WorkTokens</h2>
<a href="home.php">🏠 Home</a><br><br>

<?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php elseif ($success): ?>
    <div class="alert success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST">
    <label>Withdrawal Amount (min 5):</label><br>
    <input type="number" name="withdraw_amount" min="5" step="0.00000001" required><br><br>

    <label>Your Wallet Address:</label><br>
    <input type="text" name="wallet_address" required><br><br>

    <button type="submit">Withdraw</button>
</form>

<h3>Your Withdrawals</h3>
<table>
    <tr>
        <th>Amount</th>
        <th>Fee</th>
        <th>Recipient</th>
        <th>Status</th>
        <th>Time</th>
    </tr>
    <?php foreach ($withdrawals as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['amount']) ?></td>
            <td><?= htmlspecialchars($row['fee']) ?></td>
            <td><?= htmlspecialchars($row['wallet_address']) ?></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>