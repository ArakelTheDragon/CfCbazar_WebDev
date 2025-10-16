<?php
require_once __DIR__ . '/includes/reusable.php';
require_once __DIR__ . '/includes/reusable2.php';

global $conn;
if (!$conn instanceof mysqli || $conn->connect_errno) {
    die(json_encode(['error' => 'Database connection not available']));
}

track_visits();

$email = $_SESSION['email'] ?? null;
if (!$email) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'You must be logged in to request a withdrawal.']);
    } else {
        include_header();
        include_menu();
        echo '<main class="dashboard-container"><section class="wallet-form"><p>You must be logged in to request a withdrawal.</p></section></main>';
        include_footer();
    }
    exit;
}

// Fetch worker record
$stmt = $conn->prepare("SELECT email, address, tokens_earned, mintme FROM workers WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$worker = $result->fetch_assoc();
$stmt->close();

if (!$worker) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Worker record not found.']);
    } else {
        include_header();
        include_menu();
        echo '<main class="dashboard-container"><section class="wallet-form"><p>Worker record not found.</p></section></main>';
        include_footer();
    }
    exit;
}

$worker_email = $worker['email'];
$worker_address_db = trim($worker['address'] ?? '');
$tokens_available = is_null($worker['tokens_earned']) ? 0.0 : (float)$worker['tokens_earned'];
$workTHR_available = is_null($worker['mintme']) ? 0.0 : (float)$worker['mintme'];

// ---- EMAIL SENDER ----
function send_via_mailphp_http(string $to, string $verify_code): array {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $url = $scheme . '://' . $host . '/mail.php';

    $post = http_build_query([
        'email' => $to,
        'verify_code' => $verify_code
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);

    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception("cURL error: $curlErr");
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        $snippet = substr($resp, 0, 2000);
        throw new Exception("mail.php returned invalid response (HTTP $httpCode): " . $snippet);
    }

    return $json;
}

// ---- POST HANDLER ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $wallet = $worker_address_db !== '' ? $worker_address_db : trim($_POST['wallet_address'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $token_type = $_POST['token_type'] ?? 'WorkToken';

    if ($amount <= 0) {
        echo json_encode(['error' => 'Invalid withdrawal amount.']);
        exit;
    }

    // Check token balance based on type
    if ($token_type === 'WorkToken' && $amount > $tokens_available) {
        echo json_encode(['error' => 'You cannot withdraw more WorkTokens than available.']);
        exit;
    } elseif ($token_type === 'WorkTHR' && $amount > $workTHR_available) {
        echo json_encode(['error' => 'You cannot withdraw more WorkTHR than available.']);
        exit;
    }

    if (empty($wallet) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
        echo json_encode(['error' => 'Please provide a valid wallet address (0x...).']);
        exit;
    }

    $fee = 1.0;
    $net_amount = max(0.0, $amount - $fee);

    $ins = $conn->prepare("INSERT INTO withdraws (email, wallet_address, amount, fee, status, direction, token_type) VALUES (?, ?, ?, ?, 'pending', 'withdraw', ?)");
    $ins->bind_param("ssdds", $worker_email, $wallet, $amount, $fee, $token_type);

    if (!$ins->execute()) {
        echo json_encode(['error' => "Database error: " . $ins->error]);
        exit;
    }

    $withdraw_id = $ins->insert_id;
    $ins->close();

    $admin_verify_text = "User: {$worker_email} | Wallet: {$wallet} | Amount: " . number_format($amount, 8, '.', '') . " {$token_type} | RequestID: {$withdraw_id}";

    // Admin email (static)
    try {
        $adminResp = send_via_mailphp_http('cfcbazar@gmail.com', $admin_verify_text);
        if (empty($adminResp['success'])) {
            echo json_encode(['error' => "Admin email failed: " . ($adminResp['error'] ?? json_encode($adminResp))]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Admin email failed: ' . $e->getMessage()]);
        exit;
    }

    // User email best-effort
    try {
        $user_verify_text = "Withdrawal #{$withdraw_id} received: " . number_format($amount, 8, '.', '') . " {$token_type}. We'll process it soon.";
        send_via_mailphp_http($worker_email, $user_verify_text);
    } catch (Exception $e) {
        // ignore user email failure
    }

    echo json_encode(['success' => 'Withdrawal request submitted successfully', 'withdraw_id' => $withdraw_id]);
    exit;
}

// ---- GET (HTML RENDER) ----
$title = "💸 Withdraw WorkTokens or WorkTHR";
include_header();
include_menu();
?>

<main class="dashboard-container">
  <section class="wallet-form">
    <h3>Withdraw Tokens</h3>
    <p><strong>Your Email:</strong> <?= htmlspecialchars($worker_email) ?></p>
    <p><strong>WorkTokens:</strong> <?= htmlspecialchars(number_format($tokens_available, 8, '.', '')) ?></p>
    <p><strong>WorkTHR:</strong> <?= htmlspecialchars(number_format($workTHR_available, 8, '.', '')) ?></p>

    <form id="withdrawForm" method="post" novalidate>
      <label for="wallet_address">Wallet Address</label>
      <?php if ($worker_address_db !== ''): ?>
        <input type="text" name="wallet_address" value="<?= htmlspecialchars($worker_address_db) ?>" class="readonly" readonly>
        <div class="small">Using wallet from your profile. To change it, update your account settings.</div>
      <?php else: ?>
        <input type="text" name="wallet_address" placeholder="0x..." required>
      <?php endif; ?>

      <label for="amount">Amount to Withdraw</label>
      <input type="number" name="amount" step="0.0001" min="0.0001" required>

      <label for="token_type">Token Type</label>
      <select name="token_type" required>
        <option value="WorkToken">WorkToken</option>
        <option value="WorkTHR">WorkTHR</option>
      </select>

      <div class="small">A standard fee of <?= htmlspecialchars(number_format(1.0, 8, '.', '')) ?> WorkTokens will be applied.</div>
      <button type="submit">Submit Withdrawal Request</button>
    </form>
    <div id="message" role="status"></div>
  </section>
</main>

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

<?php include_footer(); ?>
