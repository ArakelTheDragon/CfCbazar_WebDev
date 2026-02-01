<?php
// wallet.php

// Your MintMe API credentials
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
    if ($_GET['mode'] === 'hash') {
        proxy('https://www.mintme.com/dev/api/v2/auth/user/websocket/hash', $apiId, $apiKey);
    }
    elseif ($_GET['mode'] === 'history') {
        $offset = intval($_GET['offset'] ?? 0);
        $limit  = intval($_GET['limit']  ?? 5);
        proxy(
          "https://www.mintme.com/dev/api/v2/auth/user/wallet/history?offset={$offset}&limit={$limit}",
          $apiId, $apiKey
        );
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
  <title>MintMe Wallet (Last 5) + WS Debug</title>
  <style>
    /* Full-page spinner */
    #spinnerOverlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.8);
      display:flex; align-items:center; justify-content:center; z-index:9999; }
    .spinner { width:60px; height:60px; border:8px solid #ccc; border-top:8px solid #06c; border-radius:50%;
      animation:spin 1s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg) } }

    body { font-family:sans-serif; margin:20px; }
    #statusBar { display:inline-block; padding:8px 12px; background:#f4f4f4; margin-bottom:20px;
      border-radius:4px; font-weight:bold; }

    table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    th, td { padding:8px; border:1px solid #ddd; text-align:left; }
    th { background:#f4f4f4; }

    /* Copy button */
    #copyButton { margin-bottom:10px; padding:6px 12px; background:#06c; color:#fff;
      border:none; border-radius:4px; cursor:pointer; }
    #copyButton:active { background:#005bb5; }

    /* Debug console */
    #debugConsole { max-height:200px; overflow-y:auto; background:#272822; padding:10px;
      font-family:monospace; white-space:pre-wrap; border:1px solid #444; }
    .log-entry { margin:2px 0; }
    .log-entry.log   { color:#f8f8f2; }
    .log-entry.info  { color:#06c;    }
    .log-entry.warn  { color:#fa0;    }
    .log-entry.error { color:#f33;    }
  </style>
</head>
<body>

  <!-- Spinner Overlay -->
  <div id="spinnerOverlay"><div class="spinner"></div></div>

  <h1>Wallet Transaction History (Last 5)</h1>
  <div id="statusBar">Initializing…</div>

  <!-- Transactions Table -->
  <table id="txTable" hidden>
    <thead><tr><th>Date</th><th>Hash</th><th>Amount</th><th>Status</th></tr></thead>
    <tbody></tbody>
  </table>

  <!-- Copy Logs Button -->
  <button id="copyButton">Copy Console Logs</button>

  <!-- Debug Console -->
  <div id="debugConsole"></div>

<script>
  // 1. On-page logger
  const dbg = document.getElementById('debugConsole');
  let autoScroll = true;
  dbg.addEventListener('scroll', () => {
    const {scrollTop, scrollHeight, clientHeight} = dbg;
    autoScroll = (scrollTop + clientHeight) >= (scrollHeight - 5);
  });
  function log(type, ...args) {
    const entry = document.createElement('div');
    entry.className = 'log-entry ' + type;
    const ts  = new Date().toLocaleTimeString();
    const msg = args.map(a =>
      typeof a==='object' ? JSON.stringify(a,null,2) : String(a)
    ).join(' ');
    entry.textContent = `[${ts}] ${type.toUpperCase()}: ${msg}`;
    dbg.appendChild(entry);
    if (autoScroll) entry.scrollIntoView({behavior:'smooth',block:'end'});
    console[type](...args);
  }

  // 2. Status bar helper
  const statusBar = document.getElementById('statusBar');
  function setStatus(text, isError=false) {
    statusBar.textContent = text;
    statusBar.style.background = isError?'#fde':'#dfd';
    statusBar.style.color      = isError?'#900':'#060';
  }

  // 3. Copy button
  document.getElementById('copyButton').addEventListener('click', () => {
    const txt = dbg.textContent;
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(txt)
        .then(()=> log('info','Logs copied to clipboard'))
        .catch(e=> log('error','Copy failed:',e));
    } else {
      const ta = document.createElement('textarea');
      ta.value = txt; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy');
      document.body.removeChild(ta);
      log('info','Logs copied (fallback)');
    }
  });

  // 4. Remove spinner & start
  document.addEventListener('DOMContentLoaded', () => {
    const ov = document.getElementById('spinnerOverlay');
    ov.style.transition='opacity 0.3s'; ov.style.opacity='0';
    ov.addEventListener('transitionend',()=>ov.remove());
    initialize();
  });

  // 5. Main flow
  async function initialize() {
    try {
      // Fetch history first (non-blocking)
      setStatus('Loading last 5 transactions…');
      const hRes = await fetch('?mode=history&offset=0&limit=5');
      const hTxt = await hRes.text();
      log('info','History status:',hRes.status);
      log('info','History raw:',hTxt);
      const hJson = JSON.parse(hTxt);
      if (hRes.status!==200||!Array.isArray(hJson)) throw new Error('History fetch failed');
      renderTable(hJson);
      setStatus(`Transactions loaded (${hRes.status})`);

      // Now open WS with fresh hash
      openWebSocket();
    }
    catch(err) {
      log('error',err.message); setStatus(err.message,true);
    }
  }

  // 6. Render table
  function renderTable(txns) {
    const tbl = document.getElementById('txTable');
    const body= tbl.querySelector('tbody');
    body.innerHTML='';
    txns.forEach(tx=>{
      const r=document.createElement('tr');
      r.innerHTML=`
        <td>${tx.date}</td>
        <td>${tx.hash}</td>
        <td>${tx.amount}</td>
        <td>${tx.status.statusCode}</td>
      `;
      body.appendChild(r);
    });
    tbl.hidden=false;
  }

  // 7. Open Phoenix WS w/ vsn & heartbeat
  async function openWebSocket() {
    try {
      setStatus('Fetching WebSocket hash…');
      const res = await fetch('?mode=hash');
      const txt = await res.text();
      log('info','Hash status:',res.status);
      log('info','Hash raw:',txt);
      const js = JSON.parse(txt);
      if(res.status!==200||!js.hash) throw new Error('Hash fetch failed');

      const url = `wss://www.mintme.com/dev/socket/websocket?hash=${js.hash}&vsn=2.0.0`;
      log('info','Connecting WS to:',url);
      setStatus('Connecting WebSocket…');
      const sock = new WebSocket(url);

      let ref=0,
          hbInterval;

      sock.onopen = ()=> {
        log('info','WS onopen');
        setStatus('Joining channel…');
        sock.send(JSON.stringify({
          event:'phx_join', topic:'user:wallet_history',
          payload: {}, ref:ref++
        }));
        // start heartbeat every 30s
        hbInterval = setInterval(()=> {
          sock.send(JSON.stringify({
            event:'heartbeat',
            topic:'phoenix',
            payload:{}, ref:ref++
          }));
        }, 30000);
      };

      sock.onmessage = e=>{
        log('info','WS message:',e.data);
        const m = JSON.parse(e.data);
        if(m.event==='phx_reply'&&m.payload.status==='ok') {
          setStatus('WebSocket connected');
        }
        if(m.event==='new_transaction'&&m.payload) {
          const tx=m.payload,
                bd=document.querySelector('#txTable tbody'),
                row=document.createElement('tr');
          row.innerHTML=`
            <td>${tx.date}</td>
            <td>${tx.hash}</td>
            <td>${tx.amount}</td>
            <td>${tx.status.statusCode}</td>
          `;
          bd.prepend(row);
          log('info','Appended new tx:',tx);
        }
      };

      sock.onerror = err=>{
        log('error','WS error event:',err);
        setStatus('WebSocket error',true);
      };

      sock.onclose = ev=>{
        log('warn','WS closed:',{code:ev.code,reason:ev.reason,wasClean:ev.wasClean});
        setStatus(`WS closed (code ${ev.code})`,true);
        clearInterval(hbInterval);
      };
    }
    catch(err) {
      log('error',err.message); setStatus(err.message,true);
    }
  }
</script>
</body>
</html>