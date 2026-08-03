<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dataDir = __DIR__ . '/data';
$contactsDir = $dataDir . '/contacts';
$accountsFile = $dataDir . '/accounts.json';

if (!file_exists($dataDir)) mkdir($dataDir, 0777, true);
if (!file_exists($contactsDir)) mkdir($contactsDir, 0777, true);
if (!file_exists($accountsFile)) file_put_contents($accountsFile, json_encode([]));

function getAccounts($file) {
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveAccounts($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function generateAccountNumber($file) {
    $accounts = getAccounts($file);
    do {
        $accNum = (string) mt_rand(100000000000, 999999999999);
    } while (isset($accounts[$accNum]));
    return $accNum;
}

$message = '';

// Register New Account Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_account') {
    $accNum     = generateAccountNumber($accountsFile);
    $name       = trim($_POST['name'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $password   = trim($_POST['calling_password'] ?? '');
    $maidenName = trim($_POST['maiden_name'] ?? '');
    $dob        = trim($_POST['dob'] ?? '');
    $isServiceActive = isset($_POST['active_service']);

    // Payment Method Selection Logic
    $payMethod = $_POST['payment_method'] ?? 'direct_debit';
    $ddLast2   = ($payMethod === 'direct_debit') ? trim($_POST['dd_last2'] ?? '') : '';
    $cardInput = ($payMethod === 'card') ? trim($_POST['card_number'] ?? '') : '';
    $cardLast4 = !empty($cardInput) ? substr($cardInput, -4) : '';

    if (!empty($name)) {
        $accounts = getAccounts($accountsFile);
        $accounts[$accNum] = [
            'account_number'   => $accNum,
            'name'             => $name,
            'address'          => $address,
            'postal_code'      => $postalCode,
            'calling_password' => $password,
            'maiden_name'      => $maidenName,
            'payment_method'   => $payMethod,
            'dd_last2'         => $ddLast2,
            'card_last4'       => $cardLast4,
            'dob'              => $dob,
            'service_active'   => $isServiceActive,
            'created_at'       => date('Y-m-d H:i:s')
        ];
        saveAccounts($accountsFile, $accounts);
        file_put_contents($contactsDir . '/' . $accNum . '.json', json_encode([], JSON_PRETTY_PRINT));
        $message = "✅ Account created successfully! Account #: <strong>{$accNum}</strong> &nbsp; <a href='index.php?select_acc={$accNum}' style='color: #2b6cb0; font-weight: bold;'>Open & Verify Account →</a>";
    } else {
        $message = "❌ Customer Name is required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MHR Portal - Register New Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; margin: 0; padding: 25px; color: #333; }
    .container { max-width: 600px; margin: 0 auto; }
    .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; }
    h1, h2 { color: #1a365d; margin-top: 0; }
    .form-group { margin-bottom: 15px; }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #4a5568; }
    input[type="text"], input[type="password"], input[type="date"], select { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; }
    button { background: #2b6cb0; color: white; border: none; padding: 11px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; width: 100%; font-size: 16px; }
    button:hover { background: #2c5282; }
    .alert { padding: 12px; background: #ebf8ff; border-left: 4px solid #3182ce; margin-bottom: 20px; border-radius: 4px; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .hidden { display: none; }
  </style>
</head>
<body>

<div class="container">
  <div class="top-bar">
    <h1>📝 Register Account</h1>
    <a href="index.php" style="color: #2b6cb0; font-weight: bold; text-decoration: none;">← Back to Search</a>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert"><?= $message ?></div>
  <?php endif; ?>

  <div class="card">
    <h2>Create Customer Account</h2>
    <form method="POST">
      <input type="hidden" name="action" value="create_account" />
      
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="name" placeholder="John Doe" required />
      </div>
      
      <div class="form-group">
        <label>Address *</label>
        <input type="text" name="address" placeholder="123 Main St" required />
      </div>
      
      <div class="form-group">
        <label>Postal Code *</label>
        <input type="text" name="postal_code" placeholder="Zip / Postal Code" required />
      </div>
      
      <div class="form-group">
        <label>Calling Password *</label>
        <input type="text" name="calling_password" placeholder="Passcode for phone verification" required />
      </div>
      
      <div class="form-group">
        <label>Mother's Maiden Name *</label>
        <input type="text" name="maiden_name" placeholder="Maiden name" required />
      </div>

      <div class="form-group">
        <label>Payment Method *</label>
        <select name="payment_method" id="payment_method" onchange="togglePaymentFields()" required>
          <option value="direct_debit">Direct Debit</option>
          <option value="card">16-Digit Card Account</option>
        </select>
      </div>
      
      <div class="form-group" id="group_dd">
        <label>Last 2 Digits of Direct Debit *</label>
        <input type="text" name="dd_last2" maxlength="2" placeholder="e.g. 45" />
      </div>

      <div class="form-group hidden" id="group_card">
        <label>16-Digit Card Number (or Last 4 Digits) *</label>
        <input type="text" name="card_number" maxlength="16" placeholder="e.g. 4532881299431234" />
      </div>
      
      <div class="form-group">
        <label>Date of Birth (Optional)</label>
        <input type="date" name="dob" />
      </div>
      
      <div class="form-group">
        <label><input type="checkbox" name="active_service" value="1" checked /> Enable Active Services</label>
      </div>
      
      <button type="submit">Create Account</button>
    </form>
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
