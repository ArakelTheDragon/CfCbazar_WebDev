<?php
header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../config.php';

// Login enforcement
if (!isset($_SESSION['username'])) {
    header("Location: /login.php");
    exit();
}

$email = $_SESSION['username'];

$minWithdraw = 5.0;
$fee         = 1.0;
$success     = '';
$error       = '';
$balance     = 0.0;
$withdrawals = [];

// Fetch balance
$stmt = $conn->prepare("SELECT tokens_earned FROM workers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

// Handle withdrawal form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wallet = trim($_POST['wallet'] ?? '');
    $amount = round(floatval($_POST['amount'] ?? 0), 8);

    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
        $error = "Invalid wallet format. Use MintMe/MetaMask wallet.";
    } elseif ($amount < $minWithdraw) {
        $error = "Minimum withdrawal is $minWithdraw WorkTokens.";
    } elseif ($amount > $balance) {
        $error = "Insufficient balance.";
    } else {
        // Insert withdrawal
        $stmt = $conn->prepare("INSERT INTO withdraws (email, wallet_address, amount, fee, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("ssdd", $email, $wallet, $amount, $fee);
        $stmt->execute();
        $stmt->close();

        // Deduct tokens
        $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned - ? WHERE email = ?");
        $stmt->bind_param("ds", $amount, $email);
        $stmt->execute();
        $stmt->close();

        $success = "✅ Withdrawal of $amount WorkTokens submitted.";
        $balance -= $amount;
    }
}

// Fetch past withdrawals
$stmt = $conn->prepare("SELECT amount, fee, wallet_address, status, created_at FROM withdraws WHERE email = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $withdrawals[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Withdraw WorkTokens</title>
    <style>
        body { font-family: sans-serif; background: #eef2f7; padding: 20px; }
        form, .box, table { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        label { display: block; font-weight: bold; margin-top: 12px; }
        input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; margin-top: 5px; }
        .actions { margin-top: 20px; display: flex; gap: 10px; }
        button, .button-link { padding: 12px; background: #2563eb; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; text-decoration: none; text-align: center; }
        .msg { padding: 10px; border-radius: 6px; margin-bottom: 10px; }
        .error { background: #fee2e2; color: #b91c1c; }
        .success { background: #dcfce7; color: #15803d; }
        .badge { font-size: 14px; color: #444; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f9fafb; }
    </style>
</head>
<body>

<div class="box">
    <h2>Withdraw WorkTokens</h2>
    <p><strong>Logged in as:</strong> <?= htmlspecialchars($email) ?></p>
    <p><strong>Token Balance:</strong> <?= number_format($balance, 4) ?> WorkTokens</p>
</div>

<?php if ($error): ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="msg success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<form method="POST">
    <label for="wallet">Wallet Address:</label>
    <input type="text" name="wallet" id="wallet" pattern="^0x[a-fA-F0-9]{40}$" required>
    <div class="badge">✅ Compatible with <strong>MintMe</strong> and <strong>MetaMask</strong> wallets.</div>

    <label for="amount">Amount:</label>
    <input type="number" name="amount" step="0.00000001" min="<?= $minWithdraw ?>" required>

    <div class="actions">
        <button type="submit">Submit Withdrawal</button>
        <a href="/index.html" class="button-link">Home</a>
    </div>
</form>

<div class="box">
    <h3>Your Withdrawal History</h3>
    <?php if (empty($withdrawals)): ?>
        <p>No withdrawal records yet.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Amount</th>
                <th>Fee</th>
                <th>Wallet</th>
                <th>Status</th>
                <th>Timestamp</th>
            </tr>
            <?php foreach ($withdrawals as $w): ?>
                <tr>
                    <td><?= htmlspecialchars($w['amount']) ?></td>
                    <td><?= htmlspecialchars($w['fee']) ?></td>
                    <td><?= htmlspecialchars($w['wallet_address']) ?></td>
                    <td><?= htmlspecialchars($w['status']) ?></td>
                    <td><?= htmlspecialchars($w['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>

