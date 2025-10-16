<?php
// /help/workthr.php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>WorkToken vs WorkTHR | CfCbazar Help Center</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Learn the difference between WorkToken, WorkTHR, and WorkTH. Understand mining, buying, selling, and usage across CfCbazar." />
  <meta name="keywords" content="WorkToken, WorkTHR, WorkTH, CfCbazar, mining, ESP8266, dashboard, deposit, withdraw, PancakeSwap" />
  <meta name="robots" content="index, follow" />
  <link rel="canonical" href="https://cfcbazar.ct.ws/help/workthr.php" />

  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background: #f9fbfd;
      padding: 20px;
      color: #222;
    }
    header {
      text-align: center;
      margin-bottom: 30px;
    }
    h1 {
      font-size: 24px;
      color: #0077cc;
    }
    section {
      max-width: 800px;
      margin: 0 auto 40px auto;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    h2 {
      color: #333;
      margin-top: 0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 12px;
    }
    th, td {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: left;
    }
    th {
      background: #f0f4f8;
    }
    a {
      color: #0077cc;
      text-decoration: underline;
    }
    .note {
      font-size: 0.9em;
      color: #666;
      margin-top: 10px;
    }
  </style>
</head>
<body>

<header>
  <h1>📘 WorkToken vs WorkTHR</h1>
  <p>Understand the differences between CfCbazar’s active and legacy tokens, and how they affect mining, trading, and usage.</p>
</header>

<section>
  <h2>🪙 Token Overview</h2>
  <table>
    <tr><th>Token</th><th>Chain</th><th>Contract Address</th><th>Status</th></tr>
    <tr><td><strong>WorkToken</strong></td><td>BNB (BEP-20)</td><td><code>0xecbD4E86EE8583c8681E2eE2644FC778848B237D</code></td><td>✅ Active & Unified</td></tr>
    <tr><td><strong>WorkTHR</strong></td><td>BNB (BEP-20)</td><td><code>0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00</code></td><td>⚠️ Active, pending full integration</td></tr>
    <tr><td><strong>WorkTH</strong></td><td>MintMe</td><td>—</td><td>❌ Deprecated</td></tr>
  </table>
  <p class="note">WorkToken is the new unified token replacing both WorkTH and WorkTHR.</p>
</section>

<section>
  <h2>⚒️ Mining Support</h2>
  <p>Our ESP8266-based API uses proof-of-work to mine tokens. The system automatically decides whether you receive <strong>WorkToken</strong> or <strong>WorkTHR</strong> based on internal logic. Each valid mining action earns exactly <code>0.00001</code> tokens.</p>
  <p>The API is still under development, so some features may be experimental or limited.</p>
</section>

<section>
  <h2>📊 Dashboard Display</h2>
  <p>You can view your balances on the CfCbazar dashboard:</p>
  <ul>
    <li><a href="https://cfcbazar.ct.ws/d.php" target="_blank">cfcbazar.ct.ws/d.php</a></li>
    <li><a href="https://cfcbazar.42web.io/d.php" target="_blank">cfcbazar.42web.io/d.php</a></li>
  </ul>
  <p>Displayed tokens:</p>
  <ul>
    <li><strong>dWorkToken</strong>: your mined WorkToken balance</li>
    <li><strong>WorkTHR</strong>: your mined WorkTHR balance</li>
  </ul>
</section>

<section>
  <h2>💰 Buying & Selling</h2>
  <h3>WorkToken</h3>
  <ul>
    <li>Buy/sell via our <a href="https://cc.free.bg/workth/" target="_blank">CfCbazar DApp</a></li>
    <li>Deposit: <a href="https://cfcbazar.ct.ws/buy.php" target="_blank">cfcbazar.ct.ws/buy.php</a> or <a href="https://cfcbazar.42web.io/buy.php" target="_blank">cfcbazar.42web.io/buy.php</a></li>
    <li>Withdraw: <a href="https://cfcbazar.ct.ws/w.php" target="_blank">cfcbazar.ct.ws/w.php</a> or <a href="https://cfcbazar.42web.io/w.php" target="_blank">cfcbazar.42web.io/w.php</a></li>
  </ul>

  <h3>WorkTHR</h3>
  <ul>
    <li>Buy/sell on <a href="https://pancakeswap.finance/" target="_blank">PancakeSwap</a> using contract address <code>0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00</code></li>
    <li>Deposit/withdraw support is coming soon to CfCbazar</li>
  </ul>
</section>

<section>
  <h2>🔄 Migration Strategy</h2>
  <p>CfCbazar is actively transitioning all systems to <strong>WorkToken</strong>. WorkTHR remains supported during this phase, but all new features, guides, and storefronts will prioritize WorkToken.</p>
</section>

<section>
  <h2>🔗 Related Resources</h2>
  <ul>
    <li><a href="https://cc.free.bg/workth/" target="_blank">CfCbazar DApp</a></li>
    <li><a href="https://cfcbazar.ct.ws/d.php" target="_blank">Dashboard</a></li>
    <li><a href="https://cfcbazar.ct.ws/buy.php" target="_blank">Buy WorkToken</a></li>
    <li><a href="https://cfcbazar.ct.ws/w.php" target="_blank">Withdraw WorkToken</a></li>
    <li><a href="https://pancakeswap.finance/" target="_blank">PancakeSwap</a></li>
  </ul>
</section>

<section>
  <h2>💬 Need Help?</h2>
  <p>If you’re unsure which token you’re mining or how to trade, reach out via our <a href="/t.php" target="_blank">Contact Section</a> or check the latest updates on your dashboard.</p>
</section>

</body>
</html>