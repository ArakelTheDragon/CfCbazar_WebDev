<?php
require_once 'includes/reusable.php';
show_disabled_message("Etherscan API heavily loaded so unusable");

session_start();
include("config.php"); // $conn, $bscscan_api_key, $meta_wallet, $token_address

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ==== Page visit tracker ====
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
$upd->bind_param('s', $path);
$upd->execute();
if ($upd->affected_rows === 0) {
    $slug  = ltrim($path, '/');
    $slug  = $slug === '' ? 'index' : $slug;
    $title = ucfirst(str_replace(['-', '_'], ' ', $slug));
    $ins = $conn->prepare("INSERT INTO pages (title, slug, path, visits, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())");
    $ins->bind_param("sss", $title, $slug, $path);
    $ins->execute();
    $ins->close();
}
$upd->close();

// ==== Session check ====
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit();
}

$email = $_SESSION['email'];
$message = "";
$earned = 0;

// ==== Get user wallet ====
$stmt = $conn->prepare("SELECT address FROM workers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$wallet = strtolower($row['address'] ?? '');

// ==== Save wallet if updated ====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['wallet_input'])) {
    $new_wallet = strtolower(trim($_POST['wallet_input']));
    if ($new_wallet && $new_wallet !== $wallet) {
        $update_wallet = $conn->prepare("UPDATE workers SET address = ? WHERE email = ?");
        $update_wallet->bind_param("ss", $new_wallet, $email);
        $update_wallet->execute();
        $wallet = $new_wallet;
        $message = "✅ Wallet address updated.";
    }
}

// ==== Helper: fetch JSON via cURL ====
function fetch_json($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['User-Agent: CfCbazar/2.0']
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ==== Process deposit check ====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['coin']) && $wallet) {
    $coin = $_POST['coin'];
    $newDeposits = [];
    $chainId = 56;

    if (in_array($coin, ["WorkToken", "WorkTHR"])) {
        $message = "⚠️ For now only BNB can be deposited for platform credit.";
    } elseif ($coin === "BNB") {
        $url = "https://api.etherscan.io/v2/api?chainid={$chainId}&module=account&action=txlist&address={$wallet}&startblock=0&endblock=99999999&page=1&offset=20&sort=desc&apikey={$bscscan_api_key}";
        $debug = ['wallet' => $wallet, 'coin' => $coin, 'url' => $url];
        $data = fetch_json($url);
        $debug['raw_response'] = $data;

        $txs = $data['result'] ?? $data['data'] ?? [];
        if (!is_array($txs)) {
            $txs = [];
            $message = "⚠️ API returned unexpected format. No transactions found.";
        }

        foreach ($txs as $tx) {
            $from = strtolower($tx['from'] ?? $tx['fromAddress'] ?? '');
            $to   = strtolower($tx['to'] ?? $tx['toAddress'] ?? '');
            $hash = $tx['hash'] ?? '';
            $value = $tx['value'] ?? '0';

            if ($from === $wallet && $to === strtolower($meta_wallet)) {
                $amount = is_numeric($value) ? floatval($value) / 1e18 : 0;
                $check = $conn->prepare("SELECT id FROM deposit_amounts WHERE tx_hash = ?");
                $check->bind_param("s", $hash);
                $check->execute();
                $check_result = $check->get_result();

                if ($check_result->num_rows === 0) {
                    $tokens = $amount / 0.00001;
                    $earned += $tokens;
                    $newDeposits[] = ['amount' => $amount, 'token' => "BNB", 'tx_hash' => $hash];
                }
            }
        }

        if ($earned > 0) {
            $update = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + ? WHERE email = ?");
            $update->bind_param("ds", $earned, $email);
            $update->execute();

            foreach ($newDeposits as $dep) {
                $insert = $conn->prepare("INSERT INTO deposit_amounts (email, amount, token, address, tx_hash) VALUES (?, ?, ?, ?, ?)");
                $insert->bind_param("sdsss", $email, $dep['amount'], $dep['token'], $wallet, $dep['tx_hash']);
                $insert->execute();
            }

            $message = "✅ You’ve earned " . number_format($earned, 4) . " tokens from new deposits.";
        } elseif (!$message) {
            $message = "⚠️ No new deposits found for selected coin.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Deposit Tokens</title>
<style>
body { font-family: Arial; padding: 20px; background: #f9f9f9; }
form { text-align: center; margin-top: 50px; }
select, input, button { padding: 10px; margin: 10px; border-radius: 6px; border: 1px solid #ccc; }
.message { text-align: center; margin-top: 20px; font-weight: bold; }
#debugConsole { background: #111; color: #0f0; font-family: monospace; padding: 10px; margin-top: 30px; height: 250px; overflow-y: scroll; border-radius: 8px; border: 1px solid #333; }
</style>
</head>
<body>

<h2>Deposit Tokens</h2>
<form method="post" onsubmit="return delaySubmit();">
    <label for="wallet_input">Your Wallet Address:</label><br>
    <input type="text" name="wallet_input" id="wallet_input" value="<?php echo htmlspecialchars($wallet); ?>" required><br>

    <label for="coin">Select Coin:</label><br>
    <select name="coin" id="coin" required>
        <option value="">--Choose--</option>
        <option value="BNB">BNB</option>
        <option value="WorkToken">WorkToken</option>
        <option value="WorkTHR">WorkTHR</option>
    </select><br>

    <button type="submit" id="submitBtn">Check Deposit</button>
</form>

<?php if ($message): ?>
<div class="message"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div id="debugConsole"></div>

<script>
function delaySubmit() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = "Checking... (wait 5s)";
    setTimeout(() => {
        btn.disabled = false;
        btn.textContent = "Check Deposit";
        document.forms[0].submit();
    }, 5000);
    return false;
}

const dbg = document.getElementById('debugConsole');
const log = (...args) => {
    const msg = args.map(a => typeof a === 'object' ? JSON.stringify(a) : a).join(' ');
    const line = document.createElement('div');
    line.textContent = '> ' + msg;
    dbg.appendChild(line);
    dbg.scrollTop = dbg.scrollHeight;
    console.log(...args);
};

log("Debug console active.");
log("User wallet: <?php echo htmlspecialchars($wallet); ?>");
log("Selected coin: <?php echo htmlspecialchars($_POST['coin'] ?? ''); ?>");
log("Earned tokens: <?php echo htmlspecialchars($earned); ?>");
log("Message: <?php echo htmlspecialchars($message); ?>");
<?php if (isset($debug)): ?>
log("Debug request URL: <?php echo $debug['url']; ?>");
log("Etherscan raw response:", <?php echo json_encode($debug['raw_response']); ?>);
<?php endif; ?>
</script>

</body>
</html>