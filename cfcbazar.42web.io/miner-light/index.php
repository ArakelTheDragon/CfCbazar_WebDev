<?PHP
//require 'config.php';
// Safe include for reusable.php and trackVisit instead of require_once __DIR__ . '/includes/reusable.php';
$reusablePath = __DIR__ . '/../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
    if (function_exists('trackVisit')) trackVisit($conn);
}
trackVisit($conn);
renderCaptchaIfNeeded();

// Set return URL for login redirect
if (function_exists('setReturnUrlCookie')) {
    setReturnUrlCookie('/miner-light/index.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>WorkToken Mining Portal | Earn WTK & WorkTHR Instantly</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- SEO Meta Tags -->
  <meta name="description" content="Mine WTK and WorkTHR WorkTokens instantly with no registration. Earn crypto rewards in-browser, works mobiles, tablets & PCs.">
  <meta name="keywords" content="WorkToken, WTK, WorkTHR, crypto mining, BEP-20 tokens, CfCbazar, browser miner, crypto rewards, PancakeSwap, token liquidity, light miner">
  <meta name="author" content="CfCbazar">

  <!-- Open Graph -->
  <meta property="og:title" content="WorkToken Mining Portal | Earn WTK & WorkTHR Instantly">
  <meta property="og:description" content="Start mining WTK and WorkTHR with your browser. No registration. Earn 0.01 tokens per accepted share.">
  <meta property="og:image" content="/images/miner-banner.png">    <link rel="icon" href="/images/favicon.ico">
  <meta property="og:url" content="https://cfcbazar.42web.io/">
  <meta name="twitter:card" content="summary_large_image">

  <!-- Structured Data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "WorkToken Mining Portal",
    "description": "Browser-based crypto mining for WorkToken (WTK) and WorkTHR. Earn tokens instantly with no registration.",
    "url": "https://cfcbazar.com/",
    "image": "/images/miner-banner.png",
    "author": {
      "@type": "Organization",
      "name": "CfCbazar"
    }
  }
  </script>

  <style>
    body {
      font-family: system-ui, sans-serif;
      margin: 0;
      padding: 0;
      background: #fefefe;
      color: #222;
    }
    header {
      background: #222;
      color: #fff;
      padding: 30px 20px;
      text-align: center;
    }
    header h1 {
      margin: 0;
      font-size: 2em;
    }
    header p {
      margin-top: 10px;
      font-size: 1.1em;
      color: #ccc;
    }
    .banner {
      width: 100%;
      max-width: 800px;
      margin: 20px auto;
      display: block;
      border-radius: 8px;
    }
    section {
      max-width: 800px;
      margin: auto;
      padding: 20px;
    }
    .token-box {
      background: #fff;
      border: 1px solid #ddd;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 8px;
    }
    .token-box h2 {
      margin-top: 0;
    }
    .token-box code {
      background: #eee;
      padding: 2px 6px;
      border-radius: 4px;
    }
    .btn {
      display: inline-block;
      margin-top: 10px;
      padding: 10px 16px;
      background: #007bff;
      color: #fff;
      text-decoration: none;
      border-radius: 6px;
    }
    .btn:hover {
      background: #0056b3;
    }
    footer {
      font-size: 0.9em;
      text-align: center;
      color: #666;
      padding: 20px;
    }
    ul {
      padding-left: 20px;
    }
  </style>
</head>
<body>

<header>
	<img src="/images/miner-banner.png" alt="WorkToken Light Crypto Mining Banner" class="banner">
  <h1>WorkToken Mining Portal</h1>
  <p>No registration. No platform features. Just mine and earn.</p><br>
  
 <?php render_token_price_tracker(); ?>

  <p style="margin-top: 20px;">
    🔄 Want to trade WTK and WorkTHR? Use our live PancakeSwap pair: 
    <a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D" target="_blank" rel="noopener">
      Trade WTK/WorkTHR on PancakeSwap
    </a>.
  </p>
</header>

<section>
  <h2>Who Is This For?</h2>
  <p>
    This page is for users who <strong>do not want to register</strong> or participate in the full <a href="https://cfcbazar.ct.ws/">CfCbazar ecosystem</a>.
    You won’t need to play games, use storefronts, or access platform features.
    Just enter your wallet address and <a href="/miner-light/light.php">start mining</a> <strong>WTK</strong> or <strong>WorkTHR</strong>.
    
    Check our <a href="https://github.com/ArakelTheDragon/CfCbazar-Tokens">repo</a> with more info on the WorkToken(WTK & WorkTHR).
  </p>
  
  <p>Trade on the <a href="https://pancakeswap.finance/swap?inputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D&outputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00">pancakeswap.finance exchange</a>.
  </p>
</section>

<section>
  <h2>How It Works</h2>
  <ul>
    <li>✅ No account or login required</li>
    <li>✅ Web miner runs in-browser and mines other coins (you don’t need to care which)</li>
    <li>✅ All mining profits go to support WTK and WorkTHR liquidity</li>
    <li>✅ Recommended amount for payouts is when you reach <strong>10 WorkTokens</strong></li>
    <li>⚠️ A small <strong>0.1 WorkToken payout fee</strong> is deducted</li>
  </ul>
  <a href="/miner-light/light.php" class="btn">Launch Web Miner</a>
</section>

<section class="token-box">
<div class="card">
  <h2> What is the WorkToken</h2>
  <p> The WorkToken aims for the value of 1 hour of work, so when you have worked 1 hour that's 1 WorkToken. Of course we have not reached that value yet.</p>
  <p>It's a BNB chain token compatible with Metamask/TrustWallet and other BNB wallets.</p>
  <p>It's a proof of work token, when you mine with our web miner it mines other coins(you don't need to care what) and that is converted into WorkTokens or fuels the trading pool and ecosystem.</p>
  </div>
  <div class="card">
    <h2>Wallet Integration</h2>
    <p>Add WTK and WorkTHR to your wallet:</p>
    <ul>
      <li>Open MetaMask or TrustWallet</li>
      <li>Switch to BNB Smart Chain</li>
      <li>Click “Import Token” and paste the contract address</li>
    </ul>
    <p>Once added, you can trade, mine, and earn WorkTokens directly from your wallet.</p>
  </div>
</section>

<section class="token-box">
  <h2>🪙 WTK WorkToken (Stable)</h2>
  <p>
    A dynamic-supply BEP-20 token used for internal credit, storefront access, and platform features.
    Minted when users buy, and burned (sent to a recycle pool) when sold.
  </p>
  <ul>
    <li><strong>Contract:</strong> <code>0xecbD4E86EE8583c8681E2eE2644FC778848B237D</code></li>
    <li><strong>Decimals:</strong> 18</li>
    <li><strong>Trading:</strong> CfCbazar dApp</li>
    <li><strong>Whitepaper:</strong> <a href="/WhitePaper_WTK.md" target="_blank">WhitePaper_WTK.md</a></li>
  </ul>
</section>

<section class="token-box">
  <h2>🪙 WorkTHR (WTHR)</h2>
  <p>
    A fixed-supply BEP-20 token used for mining rewards, platform credit conversion, and external trading.
  </p>
  <ul>
    <li><strong>Contract:</strong> <code>0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00</code></li>
    <li><strong>Decimals:</strong> 18</li>
    <li><strong>Total Supply:</strong> 999,999,999 WTHR</li>
    <li><strong>Trading:</strong> <a href="https://pancakeswap.finance/swap?inputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D&outputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00" target="_blank">PancakeSwap</a></li>
    <li><strong>Whitepaper:</strong> <a href="/WhitePaper_WorkTHR.md" target="_blank">WhitePaper_WorkTHR.md</a></li>
  </ul>
</section>

<footer style="text-align: center;">
  Contact: <a href="mailto:cfcbazar@gmail.com">cfcbazar@gmail.com</a><br>
  &copy; WorkToken Project — Powered by CfCbazar
</footer>

</body>
</html>