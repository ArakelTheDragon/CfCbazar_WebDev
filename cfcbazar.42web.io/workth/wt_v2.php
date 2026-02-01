<?php
// test_wallet.php
session_start();
// --- Simple auth check ---
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// MintMe API credentials (server-side only)
$apiId  = 'f196acadb20fc8d89d502a49bf41191e82291fc4a602e3b6414b59db221e86d3';
$apiKey = 'eafc42e3628bc072cb643e855d24b9206598000f91088bdb980f49810d2a1132';

// AJAX proxy for CORS
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
    }
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MintMe Wallet Test + Copyable Debug Console</title>
  <script src="https://unpkg.com/phoenix@1.7.0/priv/static/phoenix.js"></script>
  <style>
    body { font-family: sans-serif; margin: 20px; }
    #spinnerOverlay {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(255,255,255,0.8);
      display: flex; align-items: center; justify-content: center;
      z-index: 9999;
    }
    .spinner {
      width: 60px; height: 60px;
      border: 8px solid #ccc;
      border-top: 8px solid #06c;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    #statusBar {
      padding: 8px 12px; background: #f4f4f4;
      border-radius: 4px; margin-bottom: 20px;
    }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
    th { background: #f4f4f4; }

    #copyButton {
      display: inline-block; margin-bottom: 10px;
      padding: 6px 12px; background: #06c; color: #fff;
      border: none; border-radius: 4px; cursor: pointer;
    }
    #copyButton:active { background: #005bb5; }

    #debugConsole {
      max-height: 200px; overflow-y: auto;
      background: #272822; padding: 10px;
      font-family: monospace; white-space: pre-wrap;
      border: 1px solid #444;
    }
    .log-entry { margin: 2px 0; }
    .log-entry.log   { color: #f8f8f2; }
    .log-entry.info  { color: #06c;    }
    .log-entry.warn  { color: #fa0;    }
    .log-entry.error { color: #f33;    }
  </style>
</head>
<body>

  <div id="spinnerOverlay"><div class="spinner"></div></div>

  <h1>MintMe Wallet Test</h1>
  <div id="statusBar">Initializing…</div>

  <table id="txTable" hidden>
    <thead>
      <tr><th>Date</th><th>Hash</th><th>Amount</th><th>Status</th></tr>
    </thead>
    <tbody></tbody>
  </table>

  <button id="copyButton">Copy Console Logs</button>
  <div id="debugConsole"></div>

<script>
  // On-page logger with auto-scroll
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

  // Status bar helper
  const statusBar = document.getElementById('statusBar');
  function setStatus(txt, isError = false) {
    statusBar.textContent = txt;
    statusBar.style.background = isError ? '#fde' : '#dfd';
    statusBar.style.color      = isError ? '#900' : '#060';
  }

  // Improved copyLogs with fallback and user prompt
  function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.top = '-9999px';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try {
      const success = document.execCommand('copy');
      if (success) log('info', 'Console logs copied (fallback)');
      else throw new Error('execCommand returned false');
    } catch (e) {
      log('error', 'Fallback copy failed', e);
      window.prompt('Copy logs manually:', text);
    }
    document.body.removeChild(ta);
  }

  async function copyLogs() {
    const text = dbg.textContent;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
        log('info', 'Console logs copied to clipboard');
      } else {
        fallbackCopy(text);
      }
    } catch (err) {
      log('warn', 'Clipboard API failed, falling back', err);
      fallbackCopy(text);
    }
  }

  document.getElementById('copyButton')
    .addEventListener('click', copyLogs);

  // Remove spinner and start
  document.addEventListener('DOMContentLoaded', () => {
    const ov = document.getElementById('spinnerOverlay');
    ov.style.transition = 'opacity 0.3s'; ov.style.opacity = '0';
    ov.addEventListener('transitionend', () => ov.remove());
    initialize();
  });

  // Render transactions
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

  // Main flow
  async function initialize() {
    try {
      setStatus('Loading last 5 transactions…');
      let res = await fetch('?mode=history&offset=0&limit=5');
      log('info', 'History status:', res.status);
      let txt = await res.text();
      log('info', 'History raw:', txt);
      let txns = JSON.parse(txt);
      renderTable(txns);
      setStatus('Transactions loaded');

      setStatus('Fetching WebSocket hash…');
      res = await fetch('?mode=hash');
      log('info', 'Hash status:', res.status);
      txt = await res.text();
      log('info', 'Hash raw:', txt);
      const { hash } = JSON.parse(txt);
      if (!hash) throw new Error('Invalid hash');

      openChannel(hash);
    } catch (err) {
      log('error', err.message);
      setStatus(err.message, true);
    }
  }

  // Open Phoenix socket + channel
  function openChannel(hash) {
    const { Socket } = window.Phoenix;
    const socket = new Socket("wss://www.mintme.com/dev/socket/websocket", {
      params: { hash }
    });
    log('info', 'Connecting WS…');
    setStatus('Connecting WebSocket…');
    socket.connect();

    const chan = socket.channel("user:wallet_history", {});
    chan.join()
      .receive("ok", () => {
        log('info', 'Join OK');
        setStatus('WebSocket connected');
      })
      .receive("error", resp => {
        log('error', 'Join failed', resp);
        setStatus('Join failed', true);
      });

    chan.onError(e => {
      log('error', 'Channel error', e);
      setStatus('Channel error', true);
    });
    chan.onClose(e => {
      log('warn', 'Channel close', { code: e.code, reason: e.reason });
      setStatus(`WS closed (code ${e.code})`, true);
    });

    chan.on("new_transaction", tx => {
      log('info', 'New transaction', tx);
      const tbody = document.querySelector('#txTable tbody');
      const tr    = document.createElement('tr');
      tr.innerHTML = `
        <td>${tx.date}</td>
        <td>${tx.hash}</td>
        <td>${Number(tx.amount).toFixed(5)}</td>
        <td>${tx.status.statusCode}</td>
      `;
      tbody.prepend(tr);
    });
  }
</script>
</body>
</html>