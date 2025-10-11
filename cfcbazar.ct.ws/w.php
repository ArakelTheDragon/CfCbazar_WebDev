<?php
session_start();
require_once "config.php"; // loads $conn

// If POST, return JSON. If GET, show form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
}

// require login
if (empty($_SESSION['email'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['error' => 'You must be logged in to request a withdrawal.']);
        exit;
    } else {
        echo "<p>You must be logged in to request a withdrawal.</p>";
        exit;
    }
}

$session_email = $_SESSION['email'];

// fetch worker record (use column 'address' for wallet)
$worker_stmt = $conn->prepare("SELECT email, address, tokens_earned FROM workers WHERE email = ? LIMIT 1");
$worker_stmt->bind_param("s", $session_email);
$worker_stmt->execute();
$worker_result = $worker_stmt->get_result();
$worker = $worker_result->fetch_assoc();
$worker_stmt->close();

if (!$worker) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['error' => 'Worker record not found.']);
        exit;
    } else {
        echo "<p>Worker record not found.</p>";
        exit;
    }
}

$worker_email = $worker['email'];
$worker_address_db = trim($worker['address'] ?? '');
$tokens_available = is_null($worker['tokens_earned']) ? 0.0 : (float)$worker['tokens_earned'];

/**
 * Call existing mail.php via HTTP (absolute URL).
 * mail.php expects POST params: email, verify_code
 * We put the admin info into verify_code (mail.php template will show it).
 *
 * Throws Exception on error.
 */
function send_via_mailphp_http(string $to, string $verify_code): array {
    // Build absolute URL to mail.php on the same host/scheme as current request
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // If your mail.php is in a subfolder, adjust path accordingly (e.g. '/path/mail.php')
    $url = $scheme . '://' . $host . '/mail.php';

    $post = http_build_query([
        'email' => $to,
        'verify_code' => $verify_code
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // set proper header
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    // Optional: if your environment has SSL issues, you can disable verification (not recommended)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception("cURL error: $curlErr");
    }

    // Try decode JSON. If mail.php returned HTML error page, include it in exception
    $json = json_decode($resp, true);
    if (!is_array($json)) {
        // include HTTP status and first 1000 chars of response for debugging
        $snippet = substr($resp, 0, 2000);
        throw new Exception("mail.php returned invalid response (HTTP $httpCode): " . $snippet);
    }

    return $json;
}

// POST handling: create withdraw and call mail.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // prefer address from DB; if DB empty, accept wallet from POST
    $posted_wallet = trim($_POST['wallet_address'] ?? '');
    $wallet = $worker_address_db !== '' ? $worker_address_db : $posted_wallet;

    $amount = floatval($_POST['amount'] ?? 0);

    // validation
    if ($amount <= 0) {
        echo json_encode(['error' => 'Invalid withdrawal amount.']);
        exit;
    }
    if ($amount > $tokens_available) {
        echo json_encode(['error' => 'You cannot withdraw more than your available tokens.']);
        exit;
    }
    if (empty($wallet) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
        echo json_encode(['error' => 'Please provide a valid wallet address (0x...).']);
        exit;
    }

    $fee = 1.0;
    $net_amount = $amount - $fee;
    if ($net_amount < 0) $net_amount = 0.0;

    // insert withdraw record
    $ins = $conn->prepare("INSERT INTO withdraws (email, wallet_address, amount, fee, status, direction, token_type) VALUES (?, ?, ?, ?, 'pending', 'withdraw', 'WorkTH')");
    $ins->bind_param("ssdd", $worker_email, $wallet, $amount, $fee);
    if (!$ins->execute()) {
        $err = $ins->error;
        $ins->close();
        echo json_encode(['error' => "Database error: $err"]);
        exit;
    }
    $withdraw_id = $ins->insert_id;
    $ins->close();

    // Build compact admin message to place into mail.php's verify_code field
    $admin_verify_text = "User: {$worker_email} | Wallet: {$wallet} | Amount: " . number_format($amount,8,'.','') . " | RequestID: {$withdraw_id}";

    // call mail.php (admin)
    try {
        $adminResp = send_via_mailphp_http('cfcbazar@gmail.com', $admin_verify_text);
        if (empty($adminResp['success'])) {
            $errMsg = $adminResp['error'] ?? json_encode($adminResp);
            // optional: update withdraws.status = 'failed' if needed
            echo json_encode(['error' => "Admin email failed: $errMsg"]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Admin email failed: ' . $e->getMessage()]);
        exit;
    }

    // send confirmation to user (best-effort)
    try {
        $user_verify_text = "Withdrawal #{$withdraw_id} received: " . number_format($amount,8,'.','') . " WorkTokens. We'll process it soon.";
        $userResp = send_via_mailphp_http($worker_email, $user_verify_text);
        // ignore errors for user email
    } catch (Exception $e) {
        // ignore
    }

    echo json_encode(['success' => 'Withdrawal request submitted successfully', 'withdraw_id' => $withdraw_id]);
    exit;
}

// GET -> render HTML form
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Withdraw WorkTokens</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
    body { font-family: Arial, sans-serif; max-width: 640px; margin: 36px auto; padding: 0 12px; }
    input, button { width:100%; padding:10px; margin:8px 0; box-sizing:border-box; }
    label { display:block; margin-top:10px; font-weight:600; }
    .small { font-size:0.9rem; color:#666; margin-bottom:8px; }
    .success { color:green; }
    .error { color:#b00; }
    .readonly { background:#f6f6f6; }
</style>
</head>
<body>

<h2>Withdraw WorkTokens</h2>

<p><strong>Your Email:</strong> <?= htmlspecialchars($worker_email) ?></p>
<p><strong>Available Tokens:</strong> <?= htmlspecialchars(number_format($tokens_available, 8, '.', '')) ?> WorkTokens</p>

<form id="withdrawForm" method="post" novalidate>
    <label>Wallet Address</label>
    <?php if ($worker_address_db !== ''): ?>
        <input type="text" name="wallet_address" value="<?= htmlspecialchars($worker_address_db) ?>" class="readonly" readonly>
        <div class="small">Using wallet from your profile. To change it, update your account settings.</div>
    <?php else: ?>
        <input type="text" name="wallet_address" placeholder="0x..." required>
    <?php endif; ?>

    <label>Amount to Withdraw</label>
    <input type="number" name="amount" step="0.0001" min="0.0001" required>

    <div class="small">A standard fee of <?= htmlspecialchars(number_format(1.0, 8, '.', '')) ?> WorkTokens will be applied.</div>

    <button type="submit">Submit Withdrawal Request</button>
</form>

<div id="message" role="status"></div>

<script>
document.getElementById('withdrawForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);

    const res = await fetch(window.location.href, {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    });

    let json;
    try { json = await res.json(); } catch (err) {
        document.getElementById('message').className = 'error';
        document.getElementById('message').textContent = 'Server returned invalid response.';
        return;
    }

    const md = document.getElementById('message');
    if (json.success) {
        md.className = 'success';
        md.textContent = json.success + " (ID: " + json.withdraw_id + ")";
        form.reset();
        <?php if ($worker_address_db !== ''): ?>
        document.querySelector('input[name="wallet_address"]').value = "<?= htmlspecialchars($worker_address_db) ?>";
        <?php endif; ?>
    } else {
        md.className = 'error';
        md.textContent = json.error || 'Something went wrong.';
    }
});
</script>

</body>
</html>