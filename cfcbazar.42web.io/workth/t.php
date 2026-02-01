<?php
header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login.php");
    exit();
}

$email = $_SESSION['username'];

$minWithdraw = 5.0;
$fee         = 1.0;
$rateUSD     = 0.000111889; // 1 WorkToken = $0.000111889
$bnbUSD      = 600; // update this to real BNB price if needed
$error       = '';
$success     = '';
$balance     = 0.0;
$withdrawals = [];

// Fetch balance from workers table
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
        $error = "Invalid wallet format. Use MetaMask or BNB-compatible address.";
    } elseif ($amount < $minWithdraw) {
        $error = "Minimum withdrawal is $minWithdraw WorkTokens.";
    } elseif ($amount > $balance) {
        $error = "Insufficient balance.";
    } else {
        // Store withdrawal
        $stmt = $conn->prepare("INSERT INTO withdraws (email, wallet_address, amount, fee, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("ssdd", $email, $wallet, $amount, $fee);
        $stmt->execute();
        $stmt->close();

        // Deduct balance
        $stmt = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned - ? WHERE email = ?");
        $stmt->bind_param("ds", $amount, $email);
        $stmt->execute();
        $stmt->close();

        $success = " Withdrawal of $amount WorkTokens submitted. It will be processed as BNB.";
        $balance -= $amount;
    }
}

// Fetch previous withdrawals
$stmt = $conn->prepare("SELECT amount, fee, wallet_address, status, created_at FROM withdraws WHERE email = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $withdrawals[] = $row;
}
$stmt->close();

// BNB calculation JS output
$bnbRate = $rateUSD / $bnbUSD;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Withdraw WorkTokens in BNB</title>
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
        .preview { font-weight: bold; color: #0f5132; margin-top: 10px; }
    </style>
</head>
<body>

<div class="box">
    <h2>Withdraw WorkTokens as BNB</h2>
    <p><strong>Logged in as:</strong> <?= htmlspecialchars($email) ?></p>
    <p><strong>Token Balance:</strong> <?= number_format($balance, 4) ?> WorkTokens</p>
    <p><strong>Exchange Rate:</strong> 1 WorkToken = $<?= number_format($rateUSD, 8) ?> USD  <?= number_format($bnbRate, 10) ?> BNB</p>
</div>

<?php if ($error): ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="msg success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<form method="POST">
    <label for="wallet">BNB Wallet Address:</label>
    <input type="text" name="wallet" id="wallet" pattern="^0x[a-fA-F0-9]{40}$" required>

    <label for="amount">Amount of WorkTokens:</label>
    <input type="number" name="amount" id="amount" step="0.00000001" min="<?= $minWithdraw ?>" required oninput="updateBNB()">

    <div class="preview" id="bnbPreview">BNB Equivalent: 0 BNB</div>

    <div class="actions">
        <button type="submit">Submit Withdrawal</button>
        <a href="/index.php" class="button-link">Home</a>
    </div>
</form>

<div class="box">
    <h3>Your Withdrawal History</h3>
    <?php if (empty($withdrawals)): ?>
        <p>No withdrawal records yet.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>WorkTokens</th>
                <th>Fee</th>
                <th>Wallet</th>
                <th>Status</th>
                <th>Time</th>
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

<script>
    const bnbRate = <?= $bnbRate ?>;
    function updateBNB() {
        const amount = parseFloat(document.getElementById('amount').value);
        const bnb = isNaN(amount) ? 0 : (amount * bnbRate).toFixed(10);
        document.getElementById('bnbPreview').textContent = `BNB Equivalent: ${bnb} BNB`;
    }
</script>

</body>
</html>