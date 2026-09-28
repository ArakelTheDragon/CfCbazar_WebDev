<?php
// /miner/index.php — CfCbazar Worker Dashboard
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$reusablePath = __DIR__ . '/../includes/reusable.php';
require_once $reusablePath;

if (function_exists('setReturnUrlCookie')) {
    setReturnUrlCookie('/miner/index.php');
}

$title = "WorkToken Mining Portal | Earn WTK & WorkTHR Instantly";
include_header();
include_menu();
showAdvertPopup();
render_top_userbar();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $title ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- SEO Meta Tags -->
<meta name="description" content="Mine WTK and WorkTHR WorkTokens instantly with no registration. Earn crypto rewards in-browser, works on mobiles, tablets & PCs.">
<meta name="keywords" content="WorkToken, WTK, WorkTHR, crypto mining, BEP-20 tokens, CfCbazar, browser miner, crypto rewards, PancakeSwap, token liquidity, light miner">
<meta name="author" content="CfCbazar">

<!-- Open Graph -->
<meta property="og:title" content="WorkToken Mining Portal | Earn WTK & WorkTHR Instantly">
<meta property="og:description" content="Start mining WTK and WorkTHR with your browser. No registration. Earn 0.01 tokens per accepted share.">
<meta property="og:image" content="/images/miner-banner.png">
<meta property="og:url" content="https://cfcbazar.com/">
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

<link rel="stylesheet" href="/css/styles.css">
</head>
<body>

<div class="container">

    <div class="card" style="text-align:center;">
        <img src="/images/miner-banner.png" alt="WorkToken Light Crypto Mining Banner" style="max-width:100%; border-radius:12px;">
        <h1>WorkToken Mining Portal</h1>
        <p>No registration. No platform features. Just mine and earn.</p>

        <p style="margin-top: 20px;">
            🔄 Want to trade WTK and WorkTHR?  
            <a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D" target="_blank" rel="noopener">
                Trade WTK/WorkTHR on PancakeSwap
            </a>
        </p>
    </div>

    <div class="card">
        <h2>Who Is This For?</h2>
        <p>
            This page is for users who <strong>do not want to register</strong> or participate in the full 
            <a href="https://cfcbazar.ct.ws/">CfCbazar ecosystem</a>.
        </p>
        <p>
            Just enter your wallet address and 
            <a href="/miner-light/light.php">start mining</a> <strong>WTK</strong> or <strong>WorkTHR</strong>.
        </p>
        <p>
            Check our <a href="https://github.com/ArakelTheDragon/CfCbazar-Tokens">GitHub repo</a> for more info.
        </p>
    </div>

    <div class="card">
        <h2>How It Works</h2>
        <ul>
            <li>✅ No account or login required</li>
            <li>✅ Web miner runs in-browser</li>
            <li>✅ Mining profits support WTK & WorkTHR liquidity</li>
            <li>✅ Recommended payout: <strong>10 WorkTokens</strong></li>
            <li>⚠️ Payout fee: <strong>0.1 WorkToken</strong></li>
        </ul>
        <a href="/miner-light/light.php" class="btn">Launch Web Miner</a>
    </div>

    <div class="card">
        <h2>What is the WorkToken?</h2>
        <p>The WorkToken aims to represent the value of 1 hour of work.</p>
        <p>It's a BNB chain token compatible with MetaMask/TrustWallet.</p>
        <p>
            When you mine with our web miner, it mines other coins and converts them into WorkTokens or fuels the liquidity pool.
        </p>
    </div>

    <div class="card">
        <h2>Wallet Integration</h2>
        <p>Add WTK and WorkTHR to your wallet:</p>
        <ul>
            <li>Open MetaMask or TrustWallet</li>
            <li>Switch to BNB Smart Chain</li>
            <li>Click “Import Token” and paste the contract address</li>
        </ul>
    </div>

    <div class="card">
        <h2>🪙 WTK WorkToken (Stable)</h2>
        <ul>
            <li><strong>Contract:</strong> <code>0xecbD4E86EE8583c8681E2eE2644FC778848B237D</code></li>
            <li><strong>Decimals:</strong> 18</li>
            <li><strong>Trading:</strong> CfCbazar dApp</li>
            <li><strong>Whitepaper:</strong> <a href="/WhitePaper_WTK.md" target="_blank">WhitePaper_WTK.md</a></li>
        </ul>
    </div>

    <div class="card">
        <h2>🪙 WorkTHR (WTHR)</h2>
        <ul>
            <li><strong>Contract:</strong> <code>0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00</code></li>
            <li><strong>Decimals:</strong> 18</li>
            <li><strong>Total Supply:</strong> 999,999,999 WTHR</li>
            <li><strong>Trading:</strong> 
                <a href="https://pancakeswap.finance/swap?inputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D&outputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00" target="_blank">
                    PancakeSwap
                </a>
            </li>
            <li><strong>Whitepaper:</strong> <a href="/WhitePaper_WorkTHR.md" target="_blank">WhitePaper_WorkTHR.md</a></li>
        </ul>
    </div>

    <div class="card" style="text-align:center;">
        <p>Contact: <a href="mailto:cfcbazar@gmail.com">cfcbazar@gmail.com</a></p>
        <p>&copy; WorkToken Project — Powered by CfCbazar</p>
    </div>

</div>

<?php include_footer(); ?>
</body>
</html>

