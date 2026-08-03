<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dataDir = __DIR__ . '/data';
$contactsDir = $dataDir . '/contacts';
$accountsFile = $dataDir . '/accounts.json';

function getAccounts($file) {
    return json_decode(file_get_contents($file), true) ?: [];
}

$accNum = $_GET['acc'] ?? '';
$accounts = getAccounts($accountsFile);
$account = $accounts[$accNum] ?? null;

if (!$account) {
    die("<h1>❌ Error: Account not found.</h1><p><a href='index.php'>Return to Search Portal</a></p>");
}

$message = '';

// Handle Logging New Contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_contact') {
    $contactName  = trim($_POST['contact_name'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    if (!empty($contactName)) {
        $cFile = $contactsDir . '/' . $accNum . '.json';
        $contacts = file_exists($cFile) ? json_decode(file_get_contents($cFile), true) : [];
        
        // Auto-Generate Log ID (e.g. LOG-592810)
        $logId = 'LOG-' . mt_rand(100000, 999999);

        $contacts[] = [
            'log_id'       => $logId,
            'contact_name' => $contactName,
            'phone'        => $contactPhone,
            'notes'        => $notes,
            'timestamp'    => date('Y-m-d H:i:s')
        ];

        file_put_contents($cFile, json_encode($contacts, JSON_PRETTY_PRINT));
        $message = "✅ Contact log <strong>{$logId}</strong> created successfully!";
    }
}

// Read & Filter Contact Logs
$cFile = $contactsDir . '/' . $accNum . '.json';
$rawContacts = file_exists($cFile) ? json_decode(file_get_contents($cFile), true) : [];

// Filters
$startDate   = $_GET['start_date'] ?? '';
$endDate     = $_GET['end_date'] ?? '';
$searchLogId = trim($_GET['search_log_id'] ?? '');

$filteredContacts = [];

foreach ($rawContacts as $c) {
    $logDate = date('Y-m-d', strtotime($c['timestamp']));
    
    $matchDate = true;
    if (!empty($startDate) && $logDate < $startDate) $matchDate = false;
    if (!empty($endDate) && $logDate > $endDate) $matchDate = false;

    $matchLogId = true;
    if (!empty($searchLogId) && stripos($c['log_id'], $searchLogId) === false) $matchLogId = false;

    if ($matchDate && $matchLogId) {
        $filteredContacts[] = $c;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Logs - Account #<?= htmlspecialchars($accNum) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f2f5; margin: 0; padding: 25px; color: #333; }
    .container { max-width: 1000px; margin: 0 auto; }
    .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; }
    h1, h2 { color: #1a365d; margin-top: 0; }

    /* Navigation Menu */
    .nav-menu { display: flex; background: #1a365d; border-radius: 8px; overflow: hidden; margin-bottom: 25px; }
    .nav-menu a { flex: 1; padding: 15px; text-align: center; color: white; text-decoration: none; font-weight: 600; border-right: 1px solid #2c5282; }
    .nav-menu a:last-child { border-right: none; }
    .nav-menu a.active, .nav-menu a:hover { background: #2b6cb0; }

    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { margin-bottom: 15px; }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #4a5568; }
    input[type="text"], input[type="date"], textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; }
    button { background: #2b6cb0; color: white; border: none; padding: 10px 18px; border-radius: 4px; font-weight: 600; cursor: pointer; }
    button:hover { background: #2c5282; }
    .alert { padding: 12px; background: #ebf8ff; border-left: 4px solid #3182ce; margin-bottom: 20px; border-radius: 4px; }
    
    .contact-card { background: #f7fafc; border-left: 4px solid #2b6cb0; padding: 15px; margin-bottom: 12px; border-radius: 4px; }
    .log-badge { background: #2b6cb0; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
    .filter-box { background: #edf2f7; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
  </style>
</head>
<body>

<div class="container">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
    <h1>📞 Contact Log Manager: Account <?= $accNum ?></h1>
    <a href="index.php" style="color: #2b6cb0; text-decoration: none; font-weight: bold;">← Exit Account</a>
  </div>

  <div class="nav-menu">
    <a href="account.php?acc=<?= urlencode($accNum) ?>">Account Information</a>
    <a href="account.php?acc=<?= urlencode($accNum) ?>#services">Active Services</a>
    <a href="contacts.php?acc=<?= urlencode($accNum) ?>" class="active">Log New Contact & Contact History Log</a>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert"><?= $message ?></div>
  <?php endif; ?>

  <div class="grid-2">
    <div class="card">
      <h2>Log New Contact</h2>
      <form method="POST">
        <input type="hidden" name="action" value="add_contact" />
        <div class="form-group">
          <label>Caller / Contact Name</label>
          <input type="text" name="contact_name" placeholder="Name of person contacted" required />
        </div>
        <div class="form-group">
          <label>Phone Number</label>
          <input type="text" name="contact_phone" placeholder="Phone number" />
        </div>
        <div class="form-group">
          <label>Interaction Notes</label>
          <textarea name="notes" placeholder="Enter log notes..." required style="height: 100px;"></textarea>
        </div>
        <button type="submit">Save & Generate Log Number</button>
      </form>
    </div>

    <div class="card">
      <h2>Contact History Log</h2>
      
      <div class="filter-box">
        <form method="GET">
          <input type="hidden" name="acc" value="<?= htmlspecialchars($accNum) ?>" />
          <div style="display: flex; gap: 10px; margin-bottom: 10px;">
            <div style="flex:1;">
              <label><small>Start Date:</small></label>
              <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" />
            </div>
            <div style="flex:1;">
              <label><small>End Date:</small></label>
              <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" />
            </div>
          </div>
          <div class="form-group">
            <input type="text" name="search_log_id" value="<?= htmlspecialchars($searchLogId) ?>" placeholder="Search by Log ID (e.g. LOG-123456)..." />
          </div>
          <button type="submit" style="padding: 6px 14px; font-size: 13px;">Filter Logs</button>
          <a href="contacts.php?acc=<?= urlencode($accNum) ?>" style="margin-left: 10px; font-size: 13px; color: #718096; text-decoration: none;">Reset Filters</a>
        </form>
      </div>

      <?php if (!empty($filteredContacts)): ?>
        <?php foreach (array_reverse($filteredContacts) as $log): ?>
          <div class="contact-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
              <span class="log-badge"><?= htmlspecialchars($log['log_id']) ?></span>
              <small style="color: #a0aec0;"><?= $log['timestamp'] ?></small>
            </div>
            <strong><?= htmlspecialchars($log['contact_name']) ?></strong> 
            <?php if (!empty($log['phone']) && $log['phone'] !== 'N/A'): ?>
              (<?= htmlspecialchars($log['phone']) ?>)
            <?php endif; ?>
            <br>
            <span style="color: #4a5568; margin-top: 4px; display: block;"><?= nl2br(htmlspecialchars($log['notes'])) ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No matching contact logs found.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
