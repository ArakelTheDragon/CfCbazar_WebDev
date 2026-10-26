<?php
// /diy/mhr/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------------
// Load reusable functions
// ------------------------
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

// ------------------------
// Settings
// ------------------------
require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';
// settings_local.php sets:
// $NEED_SESSION = false;
// $NEED_LOGIN   = false;
// $NEED_CART    = false;
// $NEED_ADMIN   = false;

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

// --- Local notice include ----------------------------------------------------
include __DIR__ . '/notice.php';

// --- Data directories --------------------------------------------------------
$dataDir     = __DIR__ . '/data';
$contactsDir = $dataDir . '/contacts';
$accountsFile = $dataDir . '/accounts.json';

if (!file_exists($dataDir)) mkdir($dataDir, 0777, true);
if (!file_exists($contactsDir)) mkdir($contactsDir, 0777, true);
if (!file_exists($accountsFile)) file_put_contents($accountsFile, json_encode([]));

function getAccounts(string $file): array {
    return json_decode(file_get_contents($file), true) ?: [];
}

$message     = '';
$selectedAcc = null;

// --- Verification & Open Gate Logic -----------------------------------------
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
                'timestamp'    => date('Y-m-d H:i:s'),
            ];
            file_put_contents($cFile, json_encode($contacts, JSON_PRETTY_PRINT));

            header("Location: account.php?acc=" . urlencode($accNum));
            exit();
        }
    }
}

// --- Search ------------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$allAccounts = getAccounts($accountsFile);
$filteredAccounts = [];

if ($search !== '') {
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

// --- Page metadata -----------------------------------------------------------
$url        = "/diy/mhr/index.php";
$parent_url = "/diy/mhr";
$title      = "MHR Portal - Search Accounts";
$template   = "Generic";
$meta_title = "MHR Portal - Search Accounts";
$meta_desc  = "Search MHR accounts, verify callers, and open account records.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<main class="content">
    <div class="container">

        <div class="top-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;">
            <h1>📋 MHR Account Portal</h1>
            <a href="register.php" class="btn-register" style="background:#2b6cb0;color:#fff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:600;">➕ Register New Account</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert" style="padding:12px;background:#ebf8ff;border-left:4px solid #3182ce;margin-bottom:20px;border-radius:4px;"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($selectedAcc): ?>
            <div class="verify-box" style="background:#fffaf0;border:2px solid #dd6b20;padding:20px;border-radius:8px;margin-bottom:25px;">
                <h2>🔐 Security Check: Account <?= $selectedAcc['account_number'] ?> (<?= htmlspecialchars($selectedAcc['name'] ?? '') ?>)</h2>

                <div class="verify-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
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
                                    <label><input type="checkbox" name="confirm_details" value="1"> Confirmed Name &amp; Address with caller?</label>
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

                                    <div id="fallback_sec" class="hidden" style="display:none;">
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

                                <div id="non_holder_sec" class="hidden" style="display:none;">
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

                            <button type="submit" style="background:#dd6b20;color:#fff;border:none;padding:11px 20px;border-radius:4px;font-weight:600;cursor:pointer;">Verify &amp; Open Account</button>
                            <a href="index.php" style="margin-left:15px;color:#718096;text-decoration:none;">Cancel</a>
                        </form>
                    </div>

                    <div>
                        <div class="ref-answers-box" style="background:#fff;border:1px solid #feebc8;border-left:4px solid #dd6b20;padding:15px;border-radius:6px;">
                            <h3 style="margin-top:0;color:#c05621;font-size:16px;border-bottom:1px solid #feebc8;padding-bottom:8px;">💡 Verification Answers Reference</h3>
                            <div class="ref-item" style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;">
                                <span class="ref-label" style="color:#742a2a;font-weight:600;">Customer Name:</span>
                                <span class="ref-value" style="font-family:monospace;background:#feebc8;padding:2px 8px;border-radius:4px;color:#7b341e;font-weight:bold;"><?= htmlspecialchars($selectedAcc['name'] ?? 'N/A') ?></span>
                            </div>
                            <div class="ref-item" style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;">
                                <span class="ref-label" style="color:#742a2a;font-weight:600;">Calling Password:</span>
                                <span class="ref-value" style="font-family:monospace;background:#feebc8;padding:2px 8px;border-radius:4px;color:#7b341e;font-weight:bold;"><?= htmlspecialchars($selectedAcc['calling_password'] ?? 'N/A') ?></span>
                            </div>
                            <div class="ref-item" style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;">
                                <span class="ref-label" style="color:#742a2a;font-weight:600;">Mother's Maiden Name:</span>
                                <span class="ref-value" style="font-family:monospace;background:#feebc8;padding:2px 8px;border-radius:4px;color:#7b341e;font-weight:bold;"><?= htmlspecialchars($selectedAcc['maiden_name'] ?? 'N/A') ?></span>
                            </div>
                            <div class="ref-item" style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;">
                                <span class="ref-label" style="color:#742a2a;font-weight:600;">Payment Method:</span>
                                <span class="ref-value" style="font-family:monospace;background:#feebc8;padding:2px 8px;border-radius:4px;color:#7b341e;font-weight:bold;"><?= ($selectedAcc['payment_method'] ?? '') === 'card' ? '16-Digit Card' : 'Direct Debit' ?></span>
                            </div>
                            <?php if (($selectedAcc['payment_method'] ?? '') === 'card'): ?>
                                <div class="ref-item" style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;">
                                    <span class="ref-label" style="color:#742a2a;font-weight:600;">Last 4 Digits of Card:</span>
                                    <span class="ref-value" style="font-family:monospace;background:#feebc8;padding:2px 8px;border-radius:4px;color:#7b341e;font-weight:bold;"><?= htmlspecialchars($selectedAcc['card_last4'] ?? 'N/A') ?></span>
                                </div>
                            <?php else: ?>
                                <div class="ref-item" style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;">
                                    <span class="ref-label" style="color:#742a2a;font-weight:600;">Last 2 Digits of DD:</span>
                                    <span class="ref-value" style="font-family:monospace;background:#feebc8;padding:2px 8px;border-radius:4px;color:#7b341e;font-weight:bold;"><?= htmlspecialchars($selectedAcc['dd_last2'] ?? 'N/A') ?></span>
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
            <table style="width:100%;border-collapse:collapse;margin-top:15px;background:#fff;">
                <thead>
                    <tr>
                        <th style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;background:#2b6cb0;color:#fff;">Account #</th>
                        <th style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;background:#2b6cb0;color:#fff;">Name</th>
                        <th style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;background:#2b6cb0;color:#fff;">Address &amp; Postal Code</th>
                        <th style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;background:#2b6cb0;color:#fff;">Services</th>
                        <th style="padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;background:#2b6cb0;color:#fff;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($filteredAccounts)): ?>
                        <?php foreach ($filteredAccounts as $accNum => $acc): ?>
                            <tr>
                                <td style="padding:12px;border-bottom:1px solid #e2e8f0;"><strong><?= $accNum ?></strong></td>
                                <td style="padding:12px;border-bottom:1px solid #e2e8f0;"><?= htmlspecialchars($acc['name'] ?? '') ?></td>
                                <td style="padding:12px;border-bottom:1px solid #e2e8f0;"><?= htmlspecialchars($acc['address'] ?? '') ?> (<?= htmlspecialchars($acc['postal_code'] ?? '') ?>)</td>
                                <td style="padding:12px;border-bottom:1px solid #e2e8f0;"><?= (!empty($acc['service_active'])) ? '🟢 Active' : '🔴 Inactive' ?></td>
                                <td style="padding:12px;border-bottom:1px solid #e2e8f0;">
                                    <a href="?select_acc=<?= $accNum ?>"><button type="button">Open Account</button></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="padding:12px;border-bottom:1px solid #e2e8f0;">No accounts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</main>

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

<?php
// --- mandatory footer ---
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/mhr",
    "MHR Portal Source Code"
);
include_footer();
close_database();
?>
