<?php
// 1. API credentials
$apiId  = 'f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3';
$apiKey = 'eafc42e3628bc072cb643e855d24b9206598000f91088bdb980f49810d2a1132';

// 2. Pagination parameters
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit  = isset($_GET['limit'])  ? intval($_GET['limit'])  : 5;

// 3. Function to perform a GET request and decode JSON
function fetchJson($url, $apiId, $apiKey) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'X-API-ID: '  . $apiId,
            'X-API-KEY: ' . $apiKey,
        ],
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        die('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);
    $json = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die('JSON error: ' . json_last_error_msg());
    }
    return $json;
}

// 4. Fetch history (array) and hash (object)
$historyJson = fetchJson(
    "https://www.mintme.com/dev/api/v2/auth/user/wallet/history?offset={$offset}&limit={$limit}",
    $apiId, $apiKey
);

// Ensure it's an array; fallback to empty
$history = is_array($historyJson) ? $historyJson : [];

// Fetch the hash endpoint
$hashJson = fetchJson(
    'https://www.mintme.com/dev/api/v2/auth/user/websocket/hash',
    $apiId, $apiKey
);
$wsHash = isset($hashJson['hash']) ? $hashJson['hash'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MintMe Wallet with Correct JSON Handling</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px; border: 1px solid #ddd; }
        th { background: #f4f4f4; text-align: left; }
        #loading { color: #555; margin-bottom: 10px; }
        #debugConsole {
            height: 200px; overflow-y: auto;
            background: #272822; color: #f8f8f2;
            padding: 10px; font-family: monospace;
            white-space: pre-wrap; border: 1px solid #444;
        }
    </style>
</head>
<body>

<h1>Wallet Transaction History</h1>

<div id="loading">Connecting for real-time updates…</div>

<?php if (empty($history)): ?>
    <p>No transactions found.</p>
<?php else: ?>
    <table id="txTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Hash</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $tx): ?>
                <tr>
                    <td><?= htmlspecialchars($tx['date']) ?></td>
                    <td><?= htmlspecialchars($tx['hash']) ?></td>
                    <td><?= htmlspecialchars($tx['amount']) ?></td>
                    <td><?= htmlspecialchars($tx['status']['statusCode']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p>
      <a href="?offset=<?= max(0, $offset - $limit) ?>&limit=<?= $limit ?>">&laquo; Previous</a>
      |
      <a href="?offset=<?= $offset + $limit ?>&limit=<?= $limit ?>">Next &raquo;</a>
    </p>
<?php endif; ?>

<h2>Debug Console</h2>
<pre id="debugConsole"></pre>

<script>
// 5. On-page console logger
(function(){
    const logEl = document.getElementById('debugConsole');
    function out(type, ...args) {
        const msg = args.map(a => 
            typeof a === 'object' ? JSON.stringify(a, null, 2) : a
        ).join(' ');
        logEl.textContent += `[${type}] ` + msg + '\n';
        logEl.scrollTop = logEl.scrollHeight;
        console[type](...args);
    }
    ['log','info','warn','error'].forEach(level => {
        console[level] = (...args) => out(level, ...args);
    });
})();

// 6. Expose data
const initialHistory = <?= json_encode($history) ?>;
const wsHash         = '<?= $wsHash ?>';

console.group('MintMe Debug');
console.log('History Array:', initialHistory);
console.log('WebSocket Hash:', wsHash);
console.groupEnd();

if (!wsHash) {
    console.error('No hash → cannot open WebSocket');
} else {
    const sock = new WebSocket(`wss://www.mintme.com/dev/socket/websocket?hash=${wsHash}`);
    let ref = 0;
    document.getElementById('loading').style.display = 'block';

    sock.onopen = () => {
        console.info('WS open, sending join…');
        sock.send(JSON.stringify({
            event: 'phx_join',
            topic: 'user:wallet_history',
            payload: {},
            ref: ref++
        }));
    };

    sock.onmessage = evt => {
        console.log('WS message:', evt.data);
        const msg = JSON.parse(evt.data);
        if (msg.event === 'new_transaction' && msg.payload) {
            const tx = msg.payload;
            const tbody = document.querySelector('#txTable tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${tx.date}</td>
                <td>${tx.hash}</td>
                <td>${tx.amount}</td>
                <td>${tx.status.statusCode}</td>
            `;
            tbody.prepend(row);
            console.info('New tx added:', tx);
        }
    };

    sock.onerror = err => console.error('WS error:', err);
    sock.onclose = ev => console.warn('WS closed:', ev);
}
</script>

</body>
</html>