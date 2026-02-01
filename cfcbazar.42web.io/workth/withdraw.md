<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config.php'; 

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
$minWithdraw = 5.0;
$fee = 1.0;
$error = '';
$success = '';
$withdrawals = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_amount'])) {
        $amount = floatval($_POST['withdraw_amount']);
        $wallet = trim($_POST['wallet_address']);

        if (!$wallet) {
            throw new Exception("Wallet address is required.");
        }

        if ($amount < $minWithdraw) {
            throw new Exception("Minimum withdrawal is 5 WorkTokens.");
        }

        $stmt = $conn->prepare("SELECT tokens_earned FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $balance = floatval($stmt->fetchColumn());

        if ($amount > $balance) {
            throw new Exception("Insufficient token balance.");
        }

        // Insert withdrawal
        $stmt = $conn->prepare("INSERT INTO withdraws (email, wallet_address, amount, fee, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$email, $wallet, $amount, $fee]);

        // Subtract tokens (optional: delay until confirmed on-chain)
        $stmt = $conn->prepare("UPDATE users SET tokens_earned = tokens_earned - ? WHERE email = ?");
        $stmt->execute([$amount, $email]);

        $success = "Withdrawal request submitted!";
    }

    // Fetch withdrawal history
    $stmt = $conn->prepare("SELECT * FROM withdraws WHERE email = ? ORDER BY created_at DESC");
    $stmt->execute([$email]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
    // Optional: error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Withdraw WorkTokens</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2em; }
        .alert { padding: 10px; margin-bottom: 10px; }
        .error { background-color: #fdd; border: 1px solid #d66; }
        .success { background-color: #dfd; border: 1px solid #6d6; }
        table { border-collapse: collapse; width: 100%; margin-top: 1em; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
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
<?php if (empty($withdrawals)): ?>
    <p>No withdrawals yet.</p>
<?php else: ?>
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
<?php endif; ?>

</body>
</html>