<?php
session_start();
include("config.php");

// Page visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
    $upd->bind_param('s', $path);
    $upd->execute();
    if ($upd->affected_rows === 0) {
        $slug  = ltrim($path, '/');
        $slug  = $slug === '' ? 'index' : $slug;
        $title = ucfirst(str_replace(['-', '_'], ' ', $slug));

        $ins = $conn->prepare("
            INSERT INTO pages (title, slug, path, visits, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");
        if ($ins) {
            $ins->bind_param('sss', $title, $slug, $path);
            $ins->execute();
            $ins->close();
        }
    }
    $upd->close();
}

if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit();
}

$email = $_SESSION['email'];
$coin = $_POST['coin'] ?? '';
$message = "";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("DB Connection failed");

$stmt = $conn->prepare("SELECT address FROM workers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$wallet = strtolower($row['address'] ?? '');

if ($_SERVER["REQUEST_METHOD"] == "POST" && $wallet && $coin) {
    $bscscan_api_key = $bscscan_api_key;
    $earned = 0;
    $newDeposits = [];

    if ($coin === "BNB") {
        $url = "https://api.bscscan.com/api?module=account&action=txlist&address=$wallet&startblock=0&endblock=99999999&sort=desc&apikey=$bscscan_api_key";
        $data = json_decode(file_get_contents($url), true);
        if ($data && $data['status'] == "1") {
            foreach ($data['result'] as $tx) {
                if (strtolower($tx['to']) === $wallet) {
                    $tx_hash = $tx['hash'];
                    $bnb_amount = $tx['value'] / 1e18;

                    $check = $conn->prepare("SELECT id FROM deposit_amounts WHERE tx_hash = ?");
                    $check->bind_param("s", $tx_hash);
                    $check->execute();
                    $check_result = $check->get_result();

                    if ($check_result->num_rows === 0) {
                        $tokens = $bnb_amount / 0.00001;
                        $earned += $tokens;
                        $newDeposits[] = ['amount' => $bnb_amount, 'token' => 'BNB', 'tx_hash' => $tx_hash];
                    }
                }
            }
        }
    } elseif ($coin === "WorkToken") {
        $url = "https://api.bscscan.com/api?module=account&action=tokentx&address=$wallet&startblock=0&endblock=99999999&sort=desc&apikey=$bscscan_api_key";
        $data = json_decode(file_get_contents($url), true);

        if ($data && $data['status'] == "1") {
            foreach ($data['result'] as $tx) {
                if (strtolower($tx['to']) === $wallet) {
                    $tx_hash = $tx['hash'];
                    $token_amount = $tx['value'] / (10 ** intval($tx['tokenDecimal']));

                    $check = $conn->prepare("SELECT id FROM deposit_amounts WHERE tx_hash = ?");
                    $check->bind_param("s", $tx_hash);
                    $check->execute();
                    $check_result = $check->get_result();

                    if ($check_result->num_rows === 0) {
                        $earned += $token_amount;
                        $newDeposits[] = ['amount' => $token_amount, 'token' => $tx['tokenSymbol'], 'tx_hash' => $tx_hash];
                    }
                }
            }
        }
    }

    // Credit user and record deposits
    if ($earned > 0) {
        $update = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + ? WHERE email = ?");
        $update->bind_param("ds", $earned, $email);
        $update->execute();

        foreach ($newDeposits as $deposit) {
            $insert = $conn->prepare("INSERT INTO deposit_amounts (email, amount, token, address, tx_hash) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param("sdsss", $email, $deposit['amount'], $deposit['token'], $wallet, $deposit['tx_hash']);
            $insert->execute();
        }

        $message = "✅ You’ve earned " . number_format($earned, 4) . " tokens from new deposits.";
    } else {
        $message = "⚠️ No new deposits found for selected coin.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deposit Tokens</title>
<style>
:root {
    --primary-color: #4A90E2;
    --secondary-color: #2C3E50;
    --background-color: #F4F7FA;
    --text-color: #333;
    --success-color: #28A745;
    --warning-color: #DC3545;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: var(--background-color);
    color: var(--text-color);
    line-height: 1.6;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}
.container {
    max-width: 500px;
    width: 100%;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 30px;
    text-align: center;
}
h2 { font-size: 1.8rem; color: var(--secondary-color); margin-bottom: 10px; }
h3 { font-size: 1.1rem; color: var(--text-color); margin-bottom: 20px; font-weight: 400; }
form { display: flex; flex-direction: column; align-items: center; gap: 15px; }
.form-group { width: 100%; display: flex; flex-direction: column; align-items: center; }
label { font-size: 1rem; font-weight: 600; margin-bottom: 8px; color: var(--secondary-color); }
select {
    width: 100%;
    max-width: 300px;
    padding: 12px;
    font-size: 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background-color: #fff;
}
button {
    padding: 12px 24px;
    font-size: 1rem;
    font-weight: 600;
    color: white;
    background-color: var(--primary-color);
    border: none;
    border-radius: 8px;
    cursor: pointer;
}
.message {
    margin-top: 20px;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    max-width: 400px;
    width: 100%;
    text-align: center;
}
.message.success { background-color: rgba(40,167,69,0.1); color: var(--success-color); }
.message.error { background-color: rgba(220,53,69,0.1); color: var(--warning-color); }
#loadingMessage { margin-top: 15px; color: var(--primary-color); font-weight: 600; display: none; }
@media (max-width:480px){.container{padding:20px;}h2{font-size:1.5rem;}h3{font-size:1rem;}select,button{font-size:0.95rem;}}
</style>
</head>
<body>
<div class="container">
<h2>Deposit Tokens</h2>
<h3>To get WorkToken Platform Credit, deposit WorkTokens or BNB and press Check Deposit.</h3>
<form method="post" id="depositForm">
    <div class="form-group">
        <label for="coin">Select Coin:</label>
        <select name="coin" id="coin" required>
            <option value="">--Choose--</option>
            <option value="BNB" <?php if($coin==='BNB') echo 'selected'; ?>>BNB</option>
            <option value="WorkToken" <?php if($coin==='WorkToken') echo 'selected'; ?>>WorkToken</option>
        </select>
    </div>
    <button type="submit" id="checkBtn">Check Deposit</button>
    <div id="loadingMessage">Checking deposits, please wait...</div>
</form>

<?php if ($message): ?>
    <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
</div>

<script>
const form = document.getElementById('depositForm');
const loading = document.getElementById('loadingMessage');

form.addEventListener('submit', () => {
    loading.style.display = 'block';
});
</script>
</body>
</html>