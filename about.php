<?php
// /about.php

require_once __DIR__ . '/includes/reusable.php';

// --- Core logic (run BEFORE output) ---
enforce_https();
checkSystemFlags($conn);
trackVisit("about.php");
$return_url = '/about.php';

// --- Render layout ---
$title = "About CfCbazar";
include_menu();
include_header();

// Optional UI
showAdvertPopup();
render_top_userbar();
?>

<style>
/* Push page content below menu + top user bar dynamically */
body {
    padding-top: calc(var(--menu-height, 145px) + 160px) !important;
}
</style>

<div class="container">
  <h1>About CfCbazar</h1>

  <p><strong>CfCbazar</strong> (short for <strong>"CfC Bazar"</strong>) is a unique online platform blending smart deals, do-it-yourself innovation, entertainment, and blockchain-powered tokens — all in one place.</p>

  <p>The name “Bazar” is the <em>Czech spelling</em> of "bazaar", chosen for its cultural flair and distinctiveness in global online search results.</p>

  <h2>What We Offer</h2>
  <p>At CfCbazar, we combine value, creativity, and technology to deliver:</p>

  <ul>
    <li><strong>Smart Deals:</strong> Up to 80% discounts on curated products, technology, lifestyle, and digital goods</li>
    <li><strong>DIY:</strong> Tutorials and hacks in everyday life, electronics, software, and creative engineering</li>
    <li><strong>Games:</strong> Play free browser games like click the circle, guess the word & guess the number or explore premium experiences with WorkTokens</li>
    <li><strong>Music:</strong> Featuring CfC Music TV — our YouTube playlist with original productions. You can also explore our other playlists (Movies, Smart Deals, Games & Sport Highlights) on our <a href="https://youtube.com/@cfcbazar/playlists" target="_blank">YouTube channel</a></li>
    <li><strong>WorkToken Economy:</strong> Our dual-token system for value exchange</li>
  </ul>

  <h2>The WorkToken System</h2>
  <p>Our WorkToken model merges blockchain flexibility with platform-specific rewards. We maintain a reserve of blockchain WorkTokens, which backs the platform credits used to reward users in games and features.</p>

  <ul>
    <li><strong>Blockchain WorkTokens:</strong> Tradable tokens you can buy, sell, and transfer using our decentralized <a href="https://cc.free.bg/workth/" target="_blank">app (dApp)</a> with wallets like MetaMask</li>
    <li><strong>Platform Credit WorkTokens:</strong> Internal credits for in-platform purchases, games, and features. Not directly redeemable outside CfCbazar, but convertible under certain conditions</li>
  </ul>

  <p><em>Tip:</em> For trading blockchain WorkTokens, visit our <a href="https://cc.free.bg/workth/" target="_blank">Smart Contract page</a>. For platform credit rules, see our <a href="/t.php">Terms & Conditions</a>.</p>

  <h2>Our Mission</h2>
  <p>We believe that innovation, creativity, and fair value should be within everyone's reach. CfCbazar empowers people to create, play, learn, and shop without overspending — delivering value for cents.</p>

  <h3>Why Choose CfCbazar?</h3>
  <ul>
    <li>Transparent and fair WorkToken system</li>
    <li>Exclusive discounts on digital and physical products</li>
    <li>Global community of makers, gamers, and creatives</li>
    <li>Secure blockchain integration</li>
  </ul>

  <h2>Join the Movement</h2>
  <p>Whether you’re a gamer, a DIY enthusiast, a music lover, or a savvy shopper, CfCbazar offers tools and opportunities to help you get more for less.</p>

  <p>We are not an LLC or SRO, but a small independent team building this project.</p>

  <br>
  <a class="back-link" href="/help/index.php">← Back to Help Center</a>

  <div class="footer">© CfCbazar</div>
</div>

<?php include_footer(); ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const menu = document.querySelector(".main-nav");
    if (menu) {
        const h = menu.offsetHeight;
        document.documentElement.style.setProperty("--menu-height", h + "px");
    }
});
</script>
