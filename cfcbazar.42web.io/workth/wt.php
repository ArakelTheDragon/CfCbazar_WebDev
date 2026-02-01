<?php
// /workth/wallet.php

session_start();

// 1. Load our central config (adjust the path if needed)
require_once __DIR__ . '/../config.php';
// config.php defines:
//   $api_key     — public MintMe API ID
//   $private_key — private MintMe API key

// 2. Simple auth guard
if (! isset($_SESSION['username'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

// 3. Map to headers
$apiId  = $api_key;
$apiKey = $private_key;
if (empty($apiId) || empty($apiKey)) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('API credentials not configured');
}

// 4. HTTP helper
function curlGet(string $url, string $apiId, string $apiKey): array {
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
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = @json_decode($raw, true);
    return ['code'=>$code, 'raw'=>$raw, 'json'=>$json];
}

// 5. Fetch WS hash first (avoids 401 on /history)
$hashRes = curlGet(
    'https://www.mintme.com/dev/api/v2/auth/user/websocket/hash',
    $apiId, $apiKey
);
if ($hashRes['code'] !== 200 || !isset($hashRes['json']['hash'])) {
    header('HTTP/1.1 502 Bad Gateway');
    exit('Error getting WS hash: ' . htmlspecialchars($hashRes['raw']));
}
$wsHash = $hashRes['json']['hash'];

// 6. Fetch last 5 transactions
$histRes = curlGet(
    'https://www.mintme.com/dev/api/v2/auth/user/wallet/history?offset=0&limit=5',
    $apiId, $apiKey
);
if ($histRes['code'] !== 200 || !is_array($histRes['json'])) {
    header('HTTP/1.1 502 Bad Gateway');
    exit('Error getting history: ' . htmlspecialchars($histRes['raw']));
}
$transactions = $histRes['json'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My MintMe Wallet</title>

  <!-- Phoenix JS client -->
  <script src="https://unpkg.com/phoenix@1.7.0/priv/static/phoenix.js"></script>
  <style>
    /* Spinner overlay */
    #spinnerOverlay {
      position: fixed; top:0; left:0; width:100%; height:100%;
      background: rgba(255,255,255,0.8);
      display: flex; align-items: center; justify-content: center;
      z-index: 9999;
    }
    .spinner {
      width: 60px; height: 60px;
      border: 8px solid #ccc; border-top: 8px solid #06c;
      border-radius: 50%; animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg) } }

    body { font-family: sans-serif; margin: 20px; }
    #statusBar {
      padding: 8px 12px; background: #f4f4f4;
      border-radius: 4px; margin-bottom: 20px;
    }
    table {
      width:100%; border-collapse: collapse; margin-bottom: 20px;
    }
    th, td {
      padding: 8px; border: 1px solid #ddd; text-align: left;
    }
    th { background: #f4f4f4; }

    #copyButton {
      margin-bottom: 10px; padding: 6px 12px;
      background: #06c; color: #fff; border: none;
      border-radius: 4px; cursor: pointer;
    }

    #debugConsole {
      max-height: 200px; overflow-y: auto;
      background: #272822; color: #f8f8f2;
      padding: 10px; font-family: monospace;
      white-space: pre-wrap; border: 1px solid #444;
    }
    .log-entry { margin: 2px 0; }
    .log-entry.log   { color: #f8f8f2; }
    .log-entry.info  { color: #06c;    }
    .log-entry.warn  { color: #fa0;    }
    .log-entry.error { color: #f33;    }
  </style>
</head>
<body>

  <!-- Spinner -->
  <div id="spinnerOverlay"><div class="spinner"></div></div>

  <h1>My MintMe Wallet</h1>
  <div id="statusBar">Initializing…</div>

  <!-- Transactions Table -->
  <table id="txTable" hidden>
    <thead>
      <tr><th>Date</th><th>Hash</th><th>Amount</th><th>Status</th></tr>
    </thead>
    <tbody></tbody>
  </table>

  <!-- Copyable Debug Console -->
  <button id="copyButton">Copy Console Logs</button>
  <div id="debugConsole"></div>

  <script>
    // 7. Inject server data
    const initial = {
      wsHash:     <?= json_encode($wsHash) ?>,
      history:    <?= json_encode($transactions, JSON_PRETTY_PRINT) ?>,
      rawHash:    <?= json_encode($hashRes['raw']) ?>,
      rawHistory: <?= json_encode($histRes['raw']) ?>
    };

    // 8. Logger + auto-scroll
    const dbg = document.getElementById('debugConsole');
    let autoScroll = true;
    dbg.addEventListener('scroll', () => {
      const {scrollTop, scrollHeight, clientHeight} = dbg;
      autoScroll = scrollTop + clientHeight >= scrollHeight - 5;
    });
    function log(type, ...args) {
      const entry = document.createElement('div');
      entry.className = 'log-entry ' + type;
      const ts = new Date().toLocaleTimeString();
      const txt = args.map(a =>
        typeof a==='object' ? JSON.stringify(a,null,2) : String(a)
      ).join(' ');
      entry.textContent = `[${ts}] ${type.toUpperCase()}: ${txt}`;
      dbg.appendChild(entry);
      if (autoScroll) entry.scrollIntoView({behavior:'smooth',block:'end'});
    }

    // 9. Catch all JS errors
    window.onerror = (msg, src, ln, col, err) => log('error','JS error', msg, src+':'+ln, err);
    window.onunhandledrejection = ev => log('error','Unhandled promise rejection', ev.reason);

    // Status bar helper
    const statusBar = document.getElementById('statusBar');
    function setStatus(txt, isError=false) {
      statusBar.textContent = txt;
      statusBar.style.background = isError ? '#fde' : '#dfd';
      statusBar.style.color      = isError ? '#900' : '#060';
    }

    // Copy console logs
    document.getElementById('copyButton').onclick = async () => {
      try {
        if (navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(dbg.textContent);
        } else {
          let ta = document.createElement('textarea');
          ta.value = dbg.textContent;
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
        }
        log('info','Console logs copied');
      } catch(e) {
        log('error','Copy failed', e);
      }
    };

    // Remove spinner immediately and kick off init
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('spinnerOverlay').remove();
      init();
    });

    // Render history table
    function renderHistory(arr) {
      const tb = document.getElementById('txTable');
      const bd = tb.querySelector('tbody');
      bd.innerHTML = '';
      arr.forEach(tx => {
        let tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${tx.date}</td>
          <td>${tx.hash}</td>
          <td>${Number(tx.amount).toFixed(5)}</td>
          <td>${tx.status.statusCode}</td>
        `;
        bd.appendChild(tr);
      });
      tb.hidden = false;
    }

    // Main initialization
    async function init() {
      try {
        log('info','Hash status: 200 raw:', initial.rawHash);
        log('info','History status: 200 raw:', initial.rawHistory);

        renderHistory(initial.history);
        setStatus('Transactions loaded');

        setStatus('Connecting WebSocket…');
        const { Socket } = window.Phoenix;
        const socket = new Socket("wss://www.mintme.com/dev/socket/websocket", {
          params: { hash: initial.wsHash }
        });

        socket.onOpen( () => log('info','Socket open') );
        socket.onError(e => log('error','Socket error', e) );
        socket.onClose(e => log('warn','Socket close', e) );

        socket.connect();

        const chan = socket.channel("user:wallet_history", {});
        chan.join(10000)
          .receive("ok",    ()=> setStatus('WebSocket connected'))
          .receive("error", ()=> setStatus('Join failed', true))
          .receive("timeout",()=>setStatus('Join timeout', true));

        chan.onError(e => log('error','Channel error', e));
        chan.onClose(e => log('warn','Channel close', e));
        chan.on("new_transaction", tx => {
          log('info','New transaction', tx);
          let bd = document.querySelector('#txTable tbody');
          let tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${tx.date}</td>
            <td>${tx.hash}</td>
            <td>${Number(tx.amount).toFixed(5)}</td>
            <td>${tx.status.statusCode}</td>
          `;
          bd.prepend(tr);
        });

        // Clean up on unload
        window.addEventListener('beforeunload', () => {
          chan.leave();
          socket.disconnect();
        });
      }
      catch(err) {
        log('error','Init failed', err);
        setStatus('Error', true);
      }
    }
  </script>
</body>
</html>