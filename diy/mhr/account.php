<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dataDir = __DIR__ . '/data';
$accountsFile = $dataDir . '/accounts.json';

function getAccounts($file) {
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveAccounts($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$accNum = $_GET['acc'] ?? '';
$accounts = getAccounts($accountsFile);
$account = $accounts[$accNum] ?? null;

if (!$account) {
    die("<h1>❌ Error: Account not found.</h1><p><a href='index.php'>Return to Search Portal</a></p>");
}

$message = '';

// 1. HANDLE EDIT & UPDATE ACCOUNT DETAILS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_account') {
    $name       = trim($_POST['name'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $password   = trim($_POST['calling_password'] ?? '');
    $maidenName = trim($_POST['maiden_name'] ?? '');
    $dob        = trim($_POST['dob'] ?? '');

    $payMethod = $_POST['payment_method'] ?? 'direct_debit';
    $ddLast2   = ($payMethod === 'direct_debit') ? trim($_POST['dd_last2'] ?? '') : '';
    $cardInput = ($payMethod === 'card') ? trim($_POST['card_number'] ?? '') : '';
    $cardLast4 = !empty($cardInput) ? substr($cardInput, -4) : ($account['card_last4'] ?? '');

    if (!empty($name)) {
        $accounts[$accNum]['name']             = $name;
        $accounts[$accNum]['address']          = $address;
        $accounts[$accNum]['postal_code']      = $postalCode;
        $accounts[$accNum]['calling_password'] = $password;
        $accounts[$accNum]['maiden_name']      = $maidenName;
        $accounts[$accNum]['payment_method']   = $payMethod;
        $accounts[$accNum]['dd_last2']         = $ddLast2;
        $accounts[$accNum]['card_last4']       = $cardLast4;
        $accounts[$accNum]['dob']              = $dob;

        saveAccounts($accountsFile, $accounts);
        $account = $accounts[$accNum];
        $message = "✅ Account details updated successfully!";
    } else {
        $message = "⚠️ Customer Name cannot be empty.";
    }
}

// 2. TOGGLE SERVICES
if (isset($_GET['toggle_service'])) {
    $accounts[$accNum]['service_active'] = !$accounts[$accNum]['service_active'];
    saveAccounts($accountsFile, $accounts);
    header("Location: account.php?acc=" . urlencode($accNum));
    exit();
}

$currentPayMethod = $account['payment_method'] ?? 'direct_debit';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Account Dashboard - <?= htmlspecialchars($accNum) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; margin: 0; padding: 25px; color: #333; }
    .container { max-width: 1000px; margin: 0 auto; }
    .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; }
    h1, h2 { color: #1a365d; margin-top: 0; }
    
    .nav-menu { display: flex; background: #1a365d; border-radius: 8px; overflow: hidden; margin-bottom: 25px; }
    .nav-menu a { flex: 1; padding: 15px; text-align: center; color: white; text-decoration: none; font-weight: 600; border-right: 1px solid #2c5282; }
    .nav-menu a:last-child { border-right: none; }
    .nav-menu a.active, .nav-menu a:hover { background: #2b6cb0; }

    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { margin-bottom: 15px; }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #4a5568; }
    input[type="text"], input[type="date"], select { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; }
    button { background: #2b6cb0; color: white; border: none; padding: 10px 18px; border-radius: 4px; font-weight: 600; cursor: pointer; }
    button:hover { background: #2c5282; }
    .alert { padding: 12px; background: #ebf8ff; border-left: 4px solid #3182ce; margin-bottom: 20px; border-radius: 4px; }
    .status-active { color: #2f855a; font-weight: bold; background: #c6f6d5; padding: 6px 12px; border-radius: 12px; }
    .status-inactive { color: #9b2c2c; font-weight: bold; background: #fed7d7; padding: 6px 12px; border-radius: 12px; }
    .hidden { display: none; }
  </style>
</head>
<body>

<div class="container">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
    <h1>👤 Account Dashboard: <?= htmlspecialchars($account['name'] ?? '') ?></h1>
    <a href="index.php" style="color: #2b6cb0; text-decoration: none; font-weight: bold;">← Exit Account</a>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert"><?= $message ?></div>
  <?php endif; ?>

  <div class="nav-menu">
    <a href="#info" class="active">Account Information</a>
    <a href="#services">Active Services</a>
    <a href="contacts.php?acc=<?= urlencode($accNum) ?>">Log New Contact & Contact History Log</a>
  </div>

  <div class="grid-2">
    <div class="card" id="info">
      <h2>Account Information (Editable)</h2>
      <form method="POST">
        <input type="hidden" name="action" value="update_account" />
        
        <div class="form-group">
          <label>Account Number (Read-Only)</label>
          <input type="text" value="<?= $accNum ?>" disabled style="background: #edf2f7;" />
        </div>

        <div class="form-group">
          <label for="name">Customer Name</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($account['name'] ?? '') ?>" required />
        </div>

        <div class="form-group">
          <label for="address">Address</label>
          <input type="text" id="address" name="address" value="<?= htmlspecialchars($account['address'] ?? '') ?>" required />
        </div>

        <div class="form-group">
          <label for="postal_code">Postal Code</label>
          <input type="text" id="postal_code" name="postal_code" value="<?= htmlspecialchars($account['postal_code'] ?? '') ?>" required />
        </div>

        <div class="form-group">
          <label for="calling_password">Calling Password</label>
          <input type="text" id="calling_password" name="calling_password" value="<?= htmlspecialchars($account['calling_password'] ?? '') ?>" required />
        </div>

        <div class="form-group">
          <label for="maiden_name">Mother's Maiden Name</label>
          <input type="text" id="maiden_name" name="maiden_name" value="<?= htmlspecialchars($account['maiden_name'] ?? '') ?>" required />
        </div>

        <div class="form-group">
          <label for="payment_method">Payment Method</label>
          <select name="payment_method" id="payment_method" onchange="togglePaymentFields()">
            <option value="direct_debit" <?= $currentPayMethod === 'direct_debit' ? 'selected' : '' ?>>Direct Debit</option>
            <option value="card" <?= $currentPayMethod === 'card' ? 'selected' : '' ?>>16-Digit Card Account</option>
          </select>
        </div>

        <div class="form-group <?= $currentPayMethod === 'card' ? 'hidden' : '' ?>" id="group_dd">
          <label for="dd_last2">Last 2 Digits of Direct Debit</label>
          <input type="text" id="dd_last2" name="dd_last2" maxlength="2" value="<?= htmlspecialchars($account['dd_last2'] ?? '') ?>" />
        </div>

        <div class="form-group <?= $currentPayMethod === 'direct_debit' ? 'hidden' : '' ?>" id="group_card">
          <label for="card_number">16-Digit Card (Last 4: <?= htmlspecialchars($account['card_last4'] ?? 'N/A') ?>)</label>
          <input type="text" id="card_number" name="card_number" maxlength="16" placeholder="Enter new 16-digit card..." />
        </div>

        <div class="form-group">
          <label for="dob">Date of Birth (Optional)</label>
          <input type="date" id="dob" name="dob" value="<?= htmlspecialchars($account['dob'] ?? '') ?>" />
        </div>

        <div class="form-group">
          <label>Registered Date (Read-Only)</label>
          <input type="text" value="<?= htmlspecialchars($account['created_at'] ?? '') ?>" disabled style="background: #edf2f7;" />
        </div>

        <button type="submit">Save Changes</button>
      </form>
    </div>

    <div class="card" id="services">
      <h2>Active Services Status</h2>
      <p>Current Service State:</p>
      <p style="margin-bottom: 25px;">
        <?php if (!empty($account['service_active'])): ?>
          <span class="status-active">☑️ Active Service</span>
        <?php else: ?>
          <span class="status-inactive">☐ Inactive Service</span>
        <?php endif; ?>
      </p>

      <a href="account.php?acc=<?= $accNum ?>&toggle_service=1">
        <button type="button" style="background: <?= !empty($account['service_active']) ? '#e53e3e' : '#38a169' ?>;">
          <?= !empty($account['service_active']) ? 'Deactivate Services' : 'Activate Services' ?>
        </button>
      </a>
    </div>
  </div>
</div>

<script>
function togglePaymentFields() {
  const method = document.getElementById('payment_method').value;
  if (method === 'card') {
    document.getElementById('group_card').classList.remove('hidden');
    document.getElementById('group_dd').classList.add('hidden');
  } else {
    document.getElementById('group_dd').classList.remove('hidden');
    document.getElementById('group_card').classList.add('hidden');
  }
}
</script>
</body>
</html>
