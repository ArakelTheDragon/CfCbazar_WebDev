<?php
// wallet.php

// 1. Your API credentials
$apiId  = 'f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3';
$apiKey = 'eafc42e3628bc072cb643e855d24b9206598000f91088bdb980f49810d2a1132';

// 2. Proxy logic for AJAX calls
if (isset($_GET['mode'])) {
    header('Content-Type: application/json');

    // Helper to proxy a GET request and echo status + body
    function proxy($url, $apiId, $apiKey) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
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

    if ($_GET['mode'] === 'hash') {
        proxy('https://www.mintme.com/dev/api/v2/auth/user/websocket/hash', $apiId, $apiKey);
    }
    elseif ($_GET['mode'] === 'history') {
        $offset = intval($_GET['offset'] ?? 0);
        $limit  = intval($_GET['limit']  ?? 5);
        proxy("https://www.mintme.com/dev/api/v2/auth/user/wallet/history?offset={$offset}&limit={$limit}", $apiId, $apiKey);
    }
    else {
        http_response_code(400);
        echo json_encode(['error'=>'unknown mode']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MintMe Wallet (Last 5) + Debug</title>
  <style>
    /* full‐page spinner */
    #spinnerOverlay {
      position: fixed; top:0; left:0;
      width:100%; height:100%;
      background: rgba(255,255,255,0.8);
      display: flex; align-items:center;
      justify-content:center; z-index:9999;
    }
    .spinner {
      width:60px; height:60px;
      border:8px solid #ccc;
      border-top:8px solid #06c;
      border-radius:50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg) } }

    body { font-family: sans-serif; margin:20px; }
    #statusBar {
      display:inline-block; padding:8px 12px;
      background:#f4f4f4; margin-bottom:20px;
      border-radius:4px; font-weight:bold;
    }
    table {
      width:100%; border-collapse: collapse;
      margin-bottom:20px;
    }
    th, td {
      padding:8px; border:1px solid #ddd; text-align:left;
    }
    th { background:#f4f4f4; }
    #debugConsole {
      height:200px; overflow-y:auto;
      background:#272822; color:#f8f8f2;
      padding:10px; font-family: monospace;
      white-space: pre-wrap; border:1px solid #444;
    }
  </style>
</head>
<body>

  <!-- Spinner Overlay -->
  <div id="spinnerOverlay">
    <div class="spinner"></div>
  </div>

  <h1>MintMe Wallet (Last 5 Transactions)</h1>
  <div id="statusBar">Initializing…</div>

  <!-- Transactions Table -->
  <table id="txTable" hidden>
    <thead>
      <tr><th>Date</th><th>Hash</th><th>Amount</th><th>Status</th></tr>
    </thead>
    <tbody></tbody>
  </table>

  <!-- Debug Console -->
  <h2>Debug Console</h2>
  <pre id="debugConsole"></pre>

<script>
// On‐page debug console writer
const dbg = document.getElementById('debugConsole');
function log(type, ...args) {
  const prefix = `[${type.toUpperCase()}] `;
  const msg = args.map(a =>
    (typeof a === 'object') ? JSON.stringify(a, null,2) : String(a)
  ).join(' ');
  dbg.textContent += prefix + msg + '\n';
  dbg.scrollTop = dbg.scrollHeight;
  console[type](...args);
}

// Update status bar
const statusBar = document.getElementById('statusBar');
function setStatus(text, isError = false) {
  statusBar.textContent = text;
  statusBar.style.background = isError ? '#fde' : '#dfd';
  statusBar.style.color      = isError ? '#900' : '#060';
}

// Remove spinner immediately after DOM parsed
document.addEventListener('DOMContentLoaded', () => {
  const ov = document.getElementById('spinnerOverlay');
  ov.style.transition = 'opacity 0.3s';
  ov.style.opacity = '0';
  ov.addEventListener('transitionend', ()=> ov.remove());
  initialize();
});

// Main logic
async function initialize() {
  try {
    // 1. Fetch WebSocket hash
    setStatus('Fetching WebSocket hash…');
    const hashRes = await fetch('?mode=hash');
    const hashText = await hashRes.text();
    let hashJson;
    try { hashJson = JSON.parse(hashText); } catch { hashJson = null; }
    log('info', 'Hash status:', hashRes.status);
    log('info', 'Hash response raw:', hashText);
    if (hashRes.status !== 200 || !hashJson?.hash) {
      throw new Error('Hash fetch failed');
    }
    setStatus(`Hash OK (${hashRes.status})`);
    const wsHash = hashJson.hash;

    // 2. Fetch transaction history
    setStatus('Fetching transaction history…');
    const histRes = await fetch(`?mode=history&offset=0&limit=5`);
    const histText = await histRes.text();
    let histJson;
    try { histJson = JSON.parse(histText); } catch { histJson = null; }
    log('info', 'History status:', histRes.status);
    log('info', 'History response raw:', histText);
    if (histRes.status !== 200 || !Array.isArray(histJson)) {
      throw new Error('History fetch failed');
    }
    renderTable(histJson);
    setStatus(`Transactions loaded (${histRes.status})`);

    // 3. Open WebSocket for real-time updates
    openWebSocket(wsHash);
  }
  catch (err) {
    log('error', err.message);
    setStatus(err.message, true);
  }
}

// Render transactions into table
function renderTable(transactions) {
  const tbl = document.getElementById('txTable');
  const tbody = tbl.querySelector('tbody');
  tbody.innerHTML = '';
  transactions.forEach(tx => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${tx.date}</td>
      <td>${tx.hash}</td>
      <td>${tx.amount}</td>
      <td>${tx.status.statusCode}</td>
    `;
    tbody.appendChild(row);
  });
  tbl.hidden = false;
}

// WebSocket logic
function openWebSocket(hash) {
  setStatus('Connecting WebSocket…');
  const socket = new WebSocket(`wss://www.mintme.com/dev/socket/websocket?hash=${hash}`);
  let ref = 0;

  socket.onopen = () => {
    setStatus('Joining WS channel…');
    socket.send(JSON.stringify({
      event: 'phx_join',
      topic: 'user:wallet_history',
      payload: {},
      ref: ref++
    }));
  };

  socket.onmessage = e => {
    log('info', 'WS message:', e.data);
    let msg;
    try { msg = JSON.parse(e.data); } catch { return; }
    if (msg.event === 'phx_reply' && msg.payload.status === 'ok') {
      setStatus('WebSocket connected');
    }
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
      log('info', 'Appended new transaction:', tx);
    }
  };

  socket.onerror = err => {
    log('error', 'WebSocket error', err);
    setStatus('WebSocket error', true);
  };
  socket.onclose = ev => {
    log('warn', 'WebSocket closed', ev);
    setStatus(`WS closed (code ${ev.code})`, true);
  };
}
</script>

</body>
</html>