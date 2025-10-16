<?php
require_once __DIR__ . '/../includes/reusable.php';
$title = "⛏️ How to Mine on CfCbazar and Earn Rewards";
include_header();
include_menu();
?>

<main class="container">
  <h1 class="page-title">⛏️ How to Mine on CfCbazar</h1>

  <div class="welcome-card">
    <p>Welcome to CfCbazar’s mining system — a browser-based way to earn platform credits while supporting the ecosystem. Whether you're here to earn WorkTokens, boost your WorkTHR, or just explore smart deals, this guide will walk you through everything you need to know.</p>
    <p><strong>Start mining now:</strong> <a href="/miner/" target="_blank">⛏️ Launch the Web Miner</a></p>
  </div>

  <section class="gear">
    <h2>⚡ What Is CfCbazar Mining?</h2>
    <p>CfCbazar uses a lightweight JavaScript miner powered by CoinIMP. When you visit a mining-enabled page, your browser contributes hashpower to the network. In return, you earn platform credits:</p>
    <ul>
      <li><strong>WorkToken</strong> — spendable credit used for games, features, and VIP access</li>
      <li><strong>WorkTHR</strong> — mining throughput credit used to measure performance and unlock mining-based rewards</li>
    </ul>
  </section>

  <section class="quests">
    <h2>🚀 Getting Started</h2>
    <ol>
      <li><strong>Log in to your CfCbazar account</strong><br>Visit <a href="/">cfcbazar.ct.ws</a> and sign in.</li>
      <li><strong>Go to the mining page</strong><br>Navigate to <code>/index.php</code> or any page with mining enabled.</li>
      <li><strong>Choose your reward type</strong><br>Select <code>WorkToken</code> or <code>WorkTHR</code> from the dropdown.</li>
      <li><strong>Adjust CPU usage</strong><br>Use the slider to control how much CPU the miner uses.</li>
      <li><strong>Watch your stats</strong><br>Live hashrate, total hashes, and accepted hashes are displayed.</li>
      <li><strong>Earn automatically</strong><br>Rewards are claimed every second based on accepted hashes.</li>
    </ol>
  </section>

  <section class="achievements">
    <h2>📊 How Rewards Work</h2>
    <ul>
      <li>Every 3600 accepted hashes earns ~0.208 WorkTokens</li>
      <li>You can switch reward types anytime</li>
      <li>Your balance updates automatically in the background</li>
    </ul>
  </section>

  <section class="gear">
    <h2>🔐 Privacy & Control</h2>
    <ul>
      <li>Mining only starts when you’re logged in</li>
      <li>You can stop mining anytime by closing the page</li>
      <li>No downloads or installations required</li>
      <li>All mining is done in-browser using CoinIMP</li>
    </ul>
  </section>

  <section class="quests">
    <h2>🧠 Tips for Better Mining</h2>
    <ul>
      <li>Use a dedicated tab or device for mining</li>
      <li>Lower CPU usage if you’re multitasking</li>
      <li>Try mining overnight or during idle time</li>
      <li>Combine mining with CfCbazar games or guides for extra rewards</li>
    </ul>
  </section>

  <section class="achievements">
    <h2>📦 Where to Spend WorkTokens</h2>
    <div class="links-grid">
      <a href="/games.php">🎮 Games</a>
      <a href="/features.php">🔧 DIY Tools</a>
      <a href="/w.php">💰 Withdrawals</a>
      <a href="/speed.php">📡 Speed Tests</a>
      <a href="/orpg.php">🎲 ORPG Quests</a>
    </div>
  </section>

  <section class="gear">
    <h2>🛠️ Developer Notes</h2>
    <ul>
      <li>Miner script: <code>https://www.hostingcloud.racing/gODX.js</code></li>
      <li>Site key: <code>hidden</code></li>
      <li>Backend uses <code>accepted</code> hashes to calculate rewards</li>
      <li>Rewards stored in <code>workers</code> table:
        <ul>
          <li><code>tokens_earned</code> for WorkToken</li>
          <li><code>mintme</code> for WorkTHR</li>
        </ul>
      </li>
    </ul>
  </section>

  <section class="welcome-card">
    <h2>📣 Join the Ecosystem</h2>
    <p>CfCbazar is more than mining — it’s a modular platform for smart deals, storefronts, games, guides, and token-powered rewards. Explore the full ecosystem and start earning today.</p>
  </section>
</main>

<?php include_footer(); ?>