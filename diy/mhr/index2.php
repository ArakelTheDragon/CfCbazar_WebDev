<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// File directories
$dataDir = __DIR__ . '/data';
$contactsDir = $dataDir . '/contacts';
$accountsFile = $dataDir . '/accounts.json';

// Create directories if they don't exist
if (!file_exists($dataDir)) mkdir($dataDir, 0777, true);
if (!file_exists($contactsDir)) mkdir($contactsDir, 0777, true);
if (!file_exists($accountsFile)) file_put_contents($accountsFile, json_encode([]));

// Helper: Read Accounts
function getAccounts($file) {
    $data = file_get_contents($file);
    return json_decode($data, true) ?: [];
}

// Helper: Save Accounts
function saveAccounts($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Helper: Generate Unique 12-Digit Account Number
function generateAccountNumber($file) {
    $accounts = getAccounts($file);
    do {
        $accNum = (string) mt_rand(100000000000, 999999999999);
    } while (isset($accounts[$accNum])); // Ensure uniqueness
    return $accNum;
}

$message = '';

// 1. HANDLE NEW ACCOUNT CREATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_account') {
    $accNum     = generateAccountNumber($accountsFile);
    $name       = trim($_POST['name'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $isServiceActive = isset($_POST['active_service']) ? true : false;

    if (!empty($name)) {
        $accounts = getAccounts($accountsFile);
        $accounts[$accNum] = [
            'account_number' => $accNum,
            'name'           => $name,
            'address'        => $address,
            'postal_code'    => $postalCode,
            'service_active' => $isServiceActive,
            'created_at'     => date('Y-m-d H:i:s')
        ];
        saveAccounts($accountsFile, $accounts);

        // Initialize separate JSON file for contacts
        $contactFile = $contactsDir . '/' . $accNum . '.json';
        file_put_contents($contactFile, json_encode([], JSON_PRETTY_PRINT));

        $message = "✅ Account created successfully! Account Number: <strong>{$accNum}</strong>";
    } else {
        $message = "⚠️ Please fill in the Name field.";
    }
}

// 2. HANDLE SERVICE TICK BOX TOGGLE
if (isset($_GET['toggle_service'])) {
    $accToToggle = $_GET['toggle_service'];
    $accounts = getAccounts($accountsFile);
    if (isset($accounts[$accToToggle])) {
        $accounts[$accToToggle]['service_active'] = !$accounts[$accToToggle]['service_active'];
        saveAccounts($accountsFile, $accounts);
    }
    header("Location: mhr_system.php");
    exit();
}

// 3. HANDLE ADDING CONTACT TO SEPARATE JSON FILE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_contact') {
    $accNum      = $_POST['acc_num'];
    $contactName = trim($_POST['contact_name'] ?? '');
    $contactPhone= trim($_POST['contact_phone'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    $contactFile = $contactsDir . '/' . $accNum . '.json';
    
    if (file_exists($contactFile) && !empty($contactName)) {
        $contacts = json_decode(file_get_contents($contactFile), true) ?: [];
        $contacts[] = [
            'contact_name'  => $contactName,
            'phone'         => $contactPhone,
            'notes'         => $notes,
            'timestamp'     => date('Y-m-d H:i:s')
        ];
        file_put_contents($contactFile, json_encode($contacts, JSON_PRETTY_PRINT));
        $message = "✅ Contact record added to <strong>{$accNum}.json</strong>!";
    }
}

// 4. SEARCH & FILTER
$search = trim($_GET['search'] ?? '');
$allAccounts = getAccounts($accountsFile);
$filteredAccounts = [];

if (!empty($search)) {
    foreach ($allAccounts as $acc) {
        if (
            strpos($acc['account_number'], $search) !== false ||
            stripos($acc['name'], $search) !== false ||
            stripos($acc['postal_code'], $search) !== false
        ) {
            $filteredAccounts[$acc['account_number']] = $acc;
        }
    }
} else {
    $filteredAccounts = $allAccounts;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MHR Account System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin: 0; padding: 25px; color: #333; }
    .container { max-width: 1100px; margin: 0 auto; }
    .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; }
    h1, h2, h3 { color: #1a365d; margin-top: 0; }
    
    /* Form Elements */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { margin-bottom: 15px; }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #4a5568; }
    input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; }
    .checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600; }
    .checkbox-label input { width: 20px; height: 20px; }
    
    button { background: #2b6cb0; color: white; border: none; padding: 11px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; }
    button:hover { background: #2c5282; }
    .btn-sm { padding: 5px 10px; font-size: 12px; }
    
    .alert { padding: 12px; background: #ebf8ff; border-left: 4px solid #3182ce; margin-bottom: 20px; border-radius: 4px; }
    
    /* Table Styling */
    table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
    th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
    th { background: #2b6cb0; color: white; }
    
    .status-active { color: #2f855a; font-weight: bold; }
    .status-inactive { color: #c53030; font-weight: bold; }
    .contact-box { background: #f7fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 8px; font-size: 13px; }
  </style>
</head>
<body>

<div class="container">
  <h1>📋 MHR Account & Service System</h1>

  <?php if (!empty($message)): ?>
    <div class="alert"><?= $message ?></div>
  <?php endif; ?>

  <div class="grid-2">
    <div class="card">
      <h2>Add New Account</h2>
      <form method="POST">
        <input type="hidden" name="action" value="create_account" />
        
        <div class="form-group">
          <label>1. Account Number</label>
          <input type="text" value="[ Auto-Generated 12-Digit Number ]" disabled style="background: #edf2f7; color: #718096;" />
        </div>

        <div class="form-group">
          <label for="name">2. Full Name</label>
          <input type="text" id="name" name="name" placeholder="John Doe" required />
        </div>

        <div class="form-group">
          <label for="address">3. Address & Postal Code</label>
          <input type="text" id="address" name="address" placeholder="123 Main Street" style="margin-bottom: 8px;" />
          <input type="text" id="postal_code" name="postal_code" placeholder="Postal / Zip Code" required />
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="active_service" value="1" checked />
            4. Service Active?
          </label>
        </div>

        <button type="submit">Create Account</button>
      </form>
    </div>

    <div class="card">
      <h2>Search Directory</h2>
      <form method="GET">
        <div class="form-group">
          <label for="search">Search by Account #, Name, or Postal Code</label>
          <input type="text" id="search" name="search" placeholder="Type account #, name, or zip code..." value="<?= htmlspecialchars($search) ?>" />
        </div>
        <button type="submit">Search</button>
        <?php if (!empty($search)): ?>
          <a href="mhr_system.php" style="margin-left: 10px; color: #718096; text-decoration: none;">Clear Filter</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="card">
    <h2>Accounts Database (saved to <code>accounts.json</code>)</h2>
    <table>
      <thead>
        <tr>
          <th>Account #</th>
          <th>Name</th>
          <th>Address & Postal Code</th>
          <th>Active Service</th>
          <th>Box 5: Contact Records (Separate JSON)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($filteredAccounts)): ?>
          <?php foreach ($filteredAccounts as $accNum => $acc): ?>
            <tr>
              <td><strong><?= $accNum ?></strong></td>
              <td><?= htmlspecialchars($acc['name']) ?></td>
              <td>
                <?= htmlspecialchars($acc['address']) ?><br>
                <small><strong>Postal Code:</strong> <?= htmlspecialchars($acc['postal_code']) ?></small>
              </td>
              <td>
                <a href="?toggle_service=<?= $accNum ?>" style="text-decoration: none;">
                  <?php if ($acc['service_active']): ?>
                    <span class="status-active">☑️ Active</span>
                  <?php else: ?>
                    <span class="status-inactive">☐ Inactive</span>
                  <?php endif; ?>
                </a>
              </td>
              <td>
                <form method="POST" style="margin-bottom: 10px;">
                  <input type="hidden" name="action" value="add_contact" />
                  <input type="hidden" name="acc_num" value="<?= $accNum ?>" />
                  <input type="text" name="contact_name" placeholder="Contact Name" required style="padding: 4px; margin-bottom: 4px; font-size: 12px;" />
                  <input type="text" name="contact_phone" placeholder="Phone Number" style="padding: 4px; margin-bottom: 4px; font-size: 12px;" />
                  <textarea name="notes" placeholder="Notes / Log..." style="height: 40px; font-size: 12px; margin-bottom: 4px;"></textarea>
                  <button type="submit" class="btn-sm">Log Contact</button>
                </form>

                <?php
                $cFile = $contactsDir . '/' . $accNum . '.json';
                $contactsList = file_exists($cFile) ? json_decode(file_get_contents($cFile), true) : [];
                ?>
                <small><strong>File:</strong> <code>contacts/<?= $accNum ?>.json</code></small>
                <?php if (!empty($contactsList)): ?>
                  <?php foreach (array_reverse($contactsList) as $c): ?>
                    <div class="contact-box">
                      <strong><?= htmlspecialchars($c['contact_name']) ?></strong> (<?= htmlspecialchars($c['phone']) ?>)<br>
                      <em><?= htmlspecialchars($c['notes']) ?></em><br>
                      <small style="color: #a0aec0;"><?= $c['timestamp'] ?></small>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="contact-box">No contacts logged yet.</div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5">No matching accounts found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
