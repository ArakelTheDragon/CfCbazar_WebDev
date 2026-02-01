<?php
// test_wallet.php
session_start();
// --- Simple authentication check ---
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// Your MintMe API credentials (keep server-side only)
$apiId  = 'f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3';
$apiKey = 'eafc42e3628bc072cb643e855d24b9206598000f91088bdb980f49810d2a1132';

// Proxy logic for AJAX (bypass CORS)
if (isset($_GET['mode'])) {
    header('Content-Type: application/json; charset=UTF-8');

    function proxy($url, $apiId, $apiKey) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                "X-API-ID: $apiId",
                "X-API-KEY: $apiKey",
            ],
            CURLOPT_FAILONERROR    => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        http_response_code($code);
        echo $body;
    }

    // Dispatch based on ?mode=
    switch ($_GET['mode']) {
        case 'hash':
            proxy('https://www.mintme.com/dev/api/v2/auth/user/websocket/hash', $apiId, $apiKey);
            break;

        case 'history':
            $offset = intval($_GET['offset'] ?? 0);
            $limit  = intval($_GET['limit']  ?? 5);
            proxy(
              "https://www.mintme.com/dev/api/v2/auth/user/wallet/history?offset={$offset}&limit={$limit}",
              $apiId, $apiKey
            );
            break;

        default:
            http_response_code(400);
            echo json_encode(['error'=>'unknown mode']);
            break;
    }
    exit;
}
?><!DOCTYPE html><html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Test MintMe WS + History</title>
  <script src="https://unpkg.com/phoenix@latest/priv/static/phoenix.js"></script>
  <style>
    body { font-family: sans-serif; margin: 20px; }
    #statusBar { padding: 8px 12px; background: #f4f4f4; border-radius: 4px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { padding: 8px; border: 1px solid #ddd; }
    th { background: #f4f4f4; }
  </style>
</head>
<body>
  <h1>MintMe Wallet Test</h1>
  <div id="statusBar">Initializing…</div>
  <table id="txTable" hidden>
    <thead><tr><th>Date</th><th>Hash</th><th>Amount</th><th>Status</th></tr></thead>
    <tbody></tbody>
  </table><script>
  const statusBar = document.getElementById('statusBar');
  function setStatus(txt, isError=false) {
    statusBar.textContent = txt;
    statusBar.style.background = isError ? '#fde' : '#dfd';
    statusBar.style.color = isError ? '#900' : '#060';
  }

  // Fetch history & then open Phoenix WS
  async function initialize() {
    setStatus('Loading last 5 transactions…');
    const res = await fetch('?mode=history&offset=0&limit=5');
    if (res.status !== 200) {
      setStatus(`History error ${res.status}`, true);
      return;
    }
    const data = await res.json();
    renderTable(data);
    setStatus('Transactions loaded');

    // Now get WS hash
    setStatus('Fetching WebSocket hash…');
    const hRes = await fetch('?mode=hash');
    if (hRes.status !== 200) {
      setStatus(`Hash error ${hRes.status}`, true);
      return;
    }
    const { hash } = await hRes.json();
    if (!hash) { setStatus('Invalid hash', true); return; }

    openChannel(hash);
  }

  function renderTable(txns) {
    const tbl = document.getElementById('txTable');
    const body = tbl.querySelector('tbody');
    body.innerHTML = '';
    txns.forEach(tx => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${tx.date}</td>
        <td>${tx.hash}</td>
        <td>${Number(tx.amount).toFixed(5)}</td>
        <td>${tx.status.statusCode}</td>
      `;
      body.appendChild(tr);
    });
    tbl.hidden = false;
  }

  function openChannel(rawHash) {
    const token = encodeURIComponent(rawHash);
    const socket = new phoenix.Socket(`wss://www.mintme.com/dev/socket`, {
      params: { token },
    });
    socket.connect();

    const chan = socket.channel('user:wallet_history', {});
    chan.join()
        .receive('ok', () => setStatus('WebSocket connected'))
        .receive('error', () => setStatus('Join failed', true));

    chan.on('new_transaction', tx => {
      const tbody = document.querySelector('#txTable tbody');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${tx.date}</td>
        <td>${tx.hash}</td>
        <td>${Number(tx.amount).toFixed(5)}</td>
        <td>${tx.status.statusCode}</td>
      `;
      tbody.prepend(tr);
    });
  }

  document.addEventListener('DOMContentLoaded', initialize);
</script></body>
</html>