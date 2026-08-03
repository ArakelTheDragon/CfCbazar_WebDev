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

$message = '';
$selectedAcc = null;

// Verification & Open Gate Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_and_open') {
    $accNum  = $_POST['acc_num'] ?? '';
    $channel = $_POST['channel'] ?? '';
    $accounts = getAccounts($accountsFile);
    $acc = $accounts[$accNum] ?? null;

    if ($acc) {
        $verified = false;
        $auditLog = "";

        $storedPass   = $acc['calling_password'] ?? '';
        $storedMaiden = $acc['maiden_name'] ?? '';
        $storedDD     = $acc['dd_last2'] ?? '';
        $storedCard4  = $acc['card_last4'] ?? '';

        if ($channel === 'backoffice') {
            $verified = true;
            $auditLog = "Opened directly via Backoffice.";
        } elseif ($channel === 'phone') {
            $confirmedDetails = isset($_POST['confirm_details']);
            $isHolder = $_POST['is_holder'] ?? 'no';

            if (!$confirmedDetails) {
                $message = "❌ Name and address MUST be confirmed first.";
            } else {
                if ($isHolder === 'yes') {
                    $passedPass = isset($_POST['pass_known']) && $_POST['pass_known'] === 'yes';
                    if ($passedPass) {
                        $enteredPass = trim($_POST['calling_password'] ?? '');
                        if ($storedPass !== '' && strtolower($enteredPass) === strtolower($storedPass)) {
                            $verified = true;
                            $auditLog = "Phone Access: Account Holder verified via Calling Password.";
                        } else {
                            $message = "❌ Incorrect calling password.";
                        }
                    } else {
                        $fallback = $_POST['fallback_type'] ?? '';
                        $fallbackVal = trim($_POST['fallback_value'] ?? '');

                        if ($fallback === 'maiden' && $storedMaiden !== '' && strtolower($fallbackVal) === strtolower($storedMaiden)) {
                            $verified = true;
                            $auditLog = "Phone Access: Verified via Mother's Maiden Name.";
                        } elseif ($fallback === 'dd' && $storedDD !== '' && $fallbackVal === $storedDD) {
                            $verified = true;
                            $auditLog = "Phone Access: Verified via Direct Debit (Last 2 Digits).";
                        } elseif ($fallback === 'card4' && $storedCard4 !== '' && $fallbackVal === $storedCard4) {
                            $verified = true;
                            $auditLog = "Phone Access: Verified via 16-Digit Card (Last 4 Digits).";
                        } else {
                            $message = "❌ Security fallback verification failed.";
                        }
                    }
                } else {
                    $callerName = trim($_POST['caller_name'] ?? '');
                    $callerPass = trim($_POST['caller_password'] ?? '');
                    if (!empty($callerName) && $storedPass !== '' && strtolower($callerPass) === strtolower($storedPass)) {
                        $verified = true;
                        $auditLog = "Phone Access: Non-Account Holder ({$callerName}) verified via Password.";
                    } else {
                        $message = "❌ Non-account holder verification failed.";
                    }
                }
            }
        }

        if ($verified) {
            $cFile = $contactsDir . '/' . $accNum . '.json';
            $contacts = file_exists($cFile) ? (json_decode(file_get_contents($cFile), true) ?: []) : [];
            $logId = 'LOG-' . mt_rand(100000, 999999);
            $contacts[] = [
                'log_id'       => $logId,
                'contact_name' => ($channel === 'phone') ? 'Phone Verification' : 'System Access',
                'phone'        => 'N/A',
                'notes'        => $auditLog,
                'timestamp'    => date('Y-m-d H:i:s')
            ];
            file_put_contents($cFile, json_encode($contacts, JSON_PRETTY_PRINT));

            header("Location: account.php?acc=" . urlencode($accNum));
            exit();
        }
    }
}

$search = trim($_GET['search'] ?? '');
$allAccounts = getAccounts($accountsFile);
$filteredAccounts = [];

if (!empty($search)) {
    foreach ($allAccounts as $acc) {
        if (
            strpos($acc['account_number'], $search) !== false ||
            stripos($acc['name'] ?? '', $search) !== false ||
            stripos($acc['postal_code'] ?? '', $search) !== false
        ) {
            $filteredAccounts[$acc['account_number']] = $acc;
        }
    }
} else {
    $filteredAccounts = $allAccounts;
}

if (isset($_GET['select_acc']) && isset($allAccounts[$_GET['select_acc']])) {
    $selectedAcc = $allAccounts[$_GET['select_acc']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MHR Portal - Search Accounts</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; margin: 0; padding: 25px; color: #333; }
    .container { max-width: 1100px; margin: 0 auto; }
    .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; }
    h1, h2 { color: #1a365d; margin-top: 0; }
    .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .btn-register { background: #2b6cb0; color: white; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-weight: 600; display: inline-block; }
    .btn-register:hover { background: #2c5282; }
    .form-group { margin-bottom: 15px; }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #4a5568; }
    input[type="text"], input[type="password"], select { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; }
    button { background: #2b6cb0; color: white; border: none; padding: 11px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; }
    button:hover { background: #2c5282; }
    .alert { padding: 12px; background: #ebf8ff; border-left: 4px solid #3182ce; margin-bottom: 20px; border-radius: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
    th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    th { background: #2b6cb0; color: white; }
    
    .verify-box { background: #fffaf0; border: 2px solid #dd6b20; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
    .verify-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .ref-answers-box { background: #fff; border: 1px solid #feebc8; border-left: 4px solid #dd6b20; padding: 15px; border-radius: 6px; }
    .ref-answers-box h3 { margin-top: 0; color: #c05621; font-size: 16px; border-bottom: 1px solid #feebc8; padding-bottom: 8px; }
    .ref-item { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
    .ref-label { color: #742a2a; font-weight: 600; }
    .ref-value { font-family: monospace; background: #feebc8; padding: 2px 8px; border-radius: 4px; color: #7b341e; font-weight: bold; }
    .hidden { display: none; }
  </style>
</head>
<body>

<div class="container">
  <div class="top-header">
    <h1>📋 MHR Account Portal</h1>
    <a href="register.php" class="btn-register">➕ Register New Account</a>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert"><?= $message ?></div>
  <?php endif; ?>

  <?php if ($selectedAcc): ?>
    <div class="verify-box">
      <h2>🔐 Security Check: Account <?= $selectedAcc['account_number'] ?> (<?= htmlspecialchars($selectedAcc['name'] ?? '') ?>)</h2>
      
      <div class="verify-grid">
        <div>
          <form method="POST">
            <input type="hidden" name="action" value="verify_and_open" />
            <input type="hidden" name="acc_num" value="<?= $selectedAcc['account_number'] ?>" />

            <div class="form-group">
              <label>Access Channel:</label>
              <select name="channel" id="channel" onchange="toggleChannel()" required>
                <option value="phone">Phone Call</option>
                <option value="backoffice">Backoffice (Direct Open)</option>
              </select>
            </div>

            <div id="phone_questions">
              <div class="form-group">
                <label><input type="checkbox" name="confirm_details" value="1"> Confirmed Name & Address with caller?</label>
              </div>

              <div class="form-group">
                <label>Is caller the Account Holder?</label>
                <select name="is_holder" id="is_holder" onchange="toggleHolder()">
                  <option value="yes">Yes (Account Holder)</option>
                  <option value="no">No (3rd Party)</option>
                </select>
              </div>

              <div id="holder_sec">
                <div class="form-group">
                  <label>Knows Calling Password?</label>
                  <select name="pass_known" id="pass_known" onchange="togglePass()">
                    <option value="yes">Yes</option>
                    <option value="no">No (Use Security Fallback)</option>
                  </select>
                </div>

                <div class="form-group" id="pass_input">
                  <label>Enter Calling Password:</label>
                  <input type="password" name="calling_password" placeholder="Enter password...">
                </div>

                <div id="fallback_sec" class="hidden">
                  <div class="form-group">
                    <label>Security Fallback Question:</label>
                    <select name="fallback_type">
                      <option value="maiden">Mother's Maiden Name</option>
                      <?php if (($selectedAcc['payment_method'] ?? '') === 'card'): ?>
                        <option value="card4">Last 4 Digits of Card Number</option>
                      <?php else: ?>
                        <option value="dd">Last 2 Digits of Direct Debit</option>
                      <?php endif; ?>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Security Answer:</label>
                    <input type="text" name="fallback_value" placeholder="Enter answer...">
                  </div>
                </div>
              </div>

              <div id="non_holder_sec" class="hidden">
                <div class="form-group">
                  <label>Caller's Name:</label>
                  <input type="text" name="caller_name" placeholder="Caller name...">
                </div>
                <div class="form-group">
                  <label>Account Calling Password:</label>
                  <input type="password" name="caller_password" placeholder="Account password...">
                </div>
              </div>
            </div>

            <button type="submit" style="background: #dd6b20;">Verify & Open Account</button>
            <a href="index.php" style="margin-left: 15px; color: #718096; text-decoration: none;">Cancel</a>
          </form>
        </div>

        <div>
          <div class="ref-answers-box">
            <h3>💡 Verification Answers Reference</h3>
            <div class="ref-item">
              <span class="ref-label">Customer Name:</span>
              <span class="ref-value"><?= htmlspecialchars($selectedAcc['name'] ?? 'N/A') ?></span>
            </div>
            <div class="ref-item">
              <span class="ref-label">Calling Password:</span>
              <span class="ref-value"><?= htmlspecialchars($selectedAcc['calling_password'] ?? 'N/A') ?></span>
            </div>
            <div class="ref-item">
              <span class="ref-label">Mother's Maiden Name:</span>
              <span class="ref-value"><?= htmlspecialchars($selectedAcc['maiden_name'] ?? 'N/A') ?></span>
            </div>
            <div class="ref-item">
              <span class="ref-label">Payment Method:</span>
              <span class="ref-value"><?= ($selectedAcc['payment_method'] ?? '') === 'card' ? '16-Digit Card' : 'Direct Debit' ?></span>
            </div>
            <?php if (($selectedAcc['payment_method'] ?? '') === 'card'): ?>
              <div class="ref-item">
                <span class="ref-label">Last 4 Digits of Card:</span>
                <span class="ref-value"><?= htmlspecialchars($selectedAcc['card_last4'] ?? 'N/A') ?></span>
              </div>
            <?php else: ?>
              <div class="ref-item">
                <span class="ref-label">Last 2 Digits of DD:</span>
                <span class="ref-value"><?= htmlspecialchars($selectedAcc['dd_last2'] ?? 'N/A') ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Search Directory</h2>
    <form method="GET">
      <div class="form-group">
        <label>Search by Account #, Name, or Postal Code</label>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Type account #, name, zip..." />
      </div>
      <button type="submit">Search Accounts</button>
    </form>
  </div>

  <div class="card">
    <h2>Account Directory List</h2>
    <table>
      <thead>
        <tr>
          <th>Account #</th>
          <th>Name</th>
          <th>Address & Postal Code</th>
          <th>Services</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($filteredAccounts)): ?>
          <?php foreach ($filteredAccounts as $accNum => $acc): ?>
            <tr>
              <td><strong><?= $accNum ?></strong></td>
              <td><?= htmlspecialchars($acc['name'] ?? '') ?></td>
              <td><?= htmlspecialchars($acc['address'] ?? '') ?> (<?= htmlspecialchars($acc['postal_code'] ?? '') ?>)</td>
              <td><?= (!empty($acc['service_active'])) ? '🟢 Active' : '🔴 Inactive' ?></td>
              <td>
                <a href="?select_acc=<?= $accNum ?>"><button type="button">Open Account</button></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5">No accounts found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleChannel() {
  const channel = document.getElementById('channel').value;
  document.getElementById('phone_questions').style.display = (channel === 'phone') ? 'block' : 'none';
}
function toggleHolder() {
  const isHolder = document.getElementById('is_holder').value === 'yes';
  document.getElementById('holder_sec').style.display = isHolder ? 'block' : 'none';
  document.getElementById('non_holder_sec').style.display = isHolder ? 'none' : 'block';
}
function togglePass() {
  const passKnown = document.getElementById('pass_known').value === 'yes';
  document.getElementById('pass_input').style.display = passKnown ? 'block' : 'none';
  document.getElementById('fallback_sec').style.display = passKnown ? 'none' : 'block';
}
</script>
</body>
</html>
