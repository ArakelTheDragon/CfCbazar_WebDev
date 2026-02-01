<?php
// wallet.php

// 1. Your API credentials
$apiId  = 'f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3';
$apiKey = 'eafc42e3628bc072cb643e855d24b9206598000f91088bdb980f49810d2a1132';

// 2. Proxy logic for AJAX calls (bypass CORS)
if (isset($_GET['mode'])) {
    header('Content-Type: application/json; charset=UTF-8');
    // Proxy helper
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
        echo json_encode(['error' => 'unknown mode']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MintMe Wallet (Last 5) + Debug Console</title>
  <style>
    /* Full-page spinner */
    #spinnerOverlay {
      position: fixed; top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(255,255,255,0.8);
      display: flex; align-items: center;
      justify-content: center; z-index: 9999;
    }
    .spinner {
      width: 60px; height: 60px;
      border: 8px solid #ccc;
      border-top: 8px solid #06c;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    body { font-family: sans-serif; margin: 20px; }
    #statusBar {
      display: inline-block; padding: 8px 12px;
      background: #f4f4f4; margin-bottom: 20px;
      border-radius: 4px; font-weight: bold;
    }
    table {
      width: 100%; border-collapse: collapse;
      margin-bottom: 20px;
    }
    th, td {
      padding: 8px; border: 1px solid #ddd; text-align: left;
    }
    th { background: #f4f4f4; }

    /* Debug console */
    #debugConsole {
      max-height: 200px;
      overflow-y: auto;
      background: #272822;
      padding: 10px;
      font-family: monospace;
      white-space: pre-wrap;
      border: 1px solid #444;
    }
    .log-entry { white-space: pre-wrap; margin: 2px 0; }
    .log-entry.log   { color: #f8f8f2; }
    .log-entry.info  { color: #06c;    }
    .log-entry.warn  { color: #fa0;    }
    .log-entry.error { color: #f33;    }
  </style>
</head>
<body>

  <!-- Spinner Overlay -->
  <div id="spinnerOverlay">
    <div class="spinner"></div>
  </div>

  <h1>Wallet Transaction History (Last 5)</h1>
  <div id="statusBar">Initializing…</div>

  <!-- Transactions Table -->
  <table id="txTable" hidden>
    <thead>
      <tr>
        <th>Date</th><th>Hash</th><th>Amount</th><th>Status</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>

  <!-- Debug Console -->
  <h2>Debug Console</h2>
  <div id="debugConsole"></div>

<script>
  // 1. Improved on-page console
  const dbg = document.getElementById('debugConsole');
  let autoScroll = true;
  dbg.addEventListener('scroll', () => {
    const { scrollTop, scrollHeight, clientHeight } = dbg;
    autoScroll = (scrollTop + clientHeight) >= (scrollHeight - 5);
  });

  function log(type, ...args) {
    const entry = document.createElement('div');
    entry.className = 'log-entry ' + type;
    const ts = new Date().toLocaleTimeString();
    const msg = args.map(a =>
      typeof a === 'object' ? JSON.stringify(a, null, 2) : String(a)
    ).join(' ');
    entry.textContent = `[${ts}] ${type.toUpperCase()}: ${msg}`;
    dbg.appendChild(entry);
    if (autoScroll) entry.scrollIntoView({ behavior: 'smooth', block: 'end' });
    console[type](...args);
  }

  // 2. Status bar helper
  const statusBar = document.getElementById('statusBar');
  function setStatus(text, isError = false) {
    statusBar.textContent = text;
    statusBar.style.background = isError ? '#fde' : '#dfd';
    statusBar.style.color      = isError ? '#900' : '#060';
  }

  // 3. Remove spinner & start
  document.addEventListener('DOMContentLoaded', () => {
    const ov = document.getElementById('spinnerOverlay');
    ov.style.transition = 'opacity 0.3s';
    ov.style.opacity = '0';
    ov.addEventListener('transitionend', () => ov.remove());
    initialize();
  });

  // 4. Main logic
  async function initialize() {
    try {
      // Fetch WebSocket hash
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

      // Fetch history
      setStatus('Fetching transaction history…');
      const histRes = await fetch('?mode=history&offset=0&limit=5');
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

      // Open WebSocket
      openWebSocket(wsHash);
    }
    catch (err) {
      log('error', err.message);
      setStatus(err.message, true);
    }
  }

  // 5. Render table rows
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

  // 6. WebSocket setup
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