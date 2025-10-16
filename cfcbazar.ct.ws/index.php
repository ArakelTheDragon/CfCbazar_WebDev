<?php
// CfCbazar Homepage — Public Access with Modular Layout & Visit Tracking
require 'includes/reusable.php';
require 'includes/reusable2.php';

// Optional: get email if logged in
$userEmail = $_SESSION['email'] ?? '';
$title = 'CfCbazar - Smart Deals, DIY, Games, Music & the WorkToken';

include_header();
?>
<meta name="description" content="Discover CfCbazar, your marketplace for smart deals, DIY, games, music, and the WorkToken. Sign up for exclusive features & the WorkToken!">
<?php include_menu(); ?>

<main class="container home-container" style="padding-top: 70px;">
  <h1 class="page-title">✨ CfCbazar</h1>

  <section class="welcome-card">
    <h2>Your Hub for Smart Deals and Tools</h2>
    <?php if ($userEmail): ?>
      <p>Welcome back, <strong><?= htmlspecialchars($userEmail) ?></strong>! Explore our <a href="https://www.facebook.com/share/1FVqQ43L35/">Smart deals</a>, <a href="/projects/">DIY</>, <a href="/games.php">games</a>, <a href="https://youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu&si=3q_vMZ9cayg-su2W">Music</a>, and <a href="/miner/">the WorkToken</a>. By using this site, you agree to our <a href="/t.php" aria-label="Terms of Service">Terms of Service</a>, <a href="/t.php" aria-label="Privacy Policy">Privacy Policy</a>, and <a href="/c.php" aria-label="Cookies Policy">Cookies Policy</a>.</p>
    <?php else: ?>
      <p>Welcome to <strong>CfCbazar</strong>! Explore our <a href="https://www.facebook.com/share/1FVqQ43L35/">Smart deals</a>, <a href="/projects/">DIY</>, <a href="/games.php">games</a>, <a href="https://youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu&si=3q_vMZ9cayg-su2W">Music</a>, and <a href="/d.php">the WorkToken</a>. By using this site, you agree to our <a href="/t.php" aria-label="Terms of Service">Terms of Service</a>, <a href="/t.php" aria-label="Privacy Policy">Privacy Policy</a>, and <a href="/c.php" aria-label="Cookies Policy">Cookies Policy</a>.</p>
    <?php endif; ?>
    <p>Join the users already enjoying our platform for seamless URL management, gaming, and WorkToken rewards. <a href="/help/how-cfcbazar-works-what-we-provide-and-how-to-get-worktokens-workthr-.php">Check what we do</a> & explore all of our <a href="/features.php">features</a>.</p>
  </section>

  <section class="links-grid" aria-label="Explore CfCbazar Features">
    <h2>Explore Our Features</h2>
    <a href="/speed.php" target="_blank" class="link-card" aria-label="Internet Speed Test">📡 <span>Internet Speed Test</span></a>
    <a href="/orpg.php" target="_blank" class="link-card" aria-label="WorkToken ORPG and Token Quest">🎲 <span>WorkToken ORPG / Token Quest</span></a>
    <a href="/d.php" target="_blank" rel="noopener" class="link-card" aria-label="WorkToken Platform">💰 <span>WorkToken Platform</span></a>
    <a href="/pow" target="_blank" rel="noopener" class="link-card" aria-label="Mine Platform Credits">⛏️ <span>Mine</span></a>
    <a href="https://fb.com/workthrp" target="_blank" rel="noopener" class="link-card" aria-label="Smart Deals on Facebook">🔗 <span>Smart Deals</span></a>
    <a href="/features.php" target="_blank" rel="noopener" class="link-card" aria-label="DIY Tools and Features">🔧 <span>DIY & Features</span></a>
    <a href="/games.php" target="_blank" rel="noopener" class="link-card" aria-label="Online Games">🎮 <span>Online Games</span></a>
    <a href="https://youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu" target="_blank" rel="noopener" class="link-card" aria-label="Music Playlist">🎵 <span>Music Playlist</span></a>
    <a href="/register.php" rel="noopener" class="link-card" aria-label="Sign In or Register">🏠 <span>Sign In / Register</span></a>
    <a href="/tv.php" target="_blank" rel="noopener" class="link-card" aria-label="Free YouTube TV">📺 <span>Free YouTube TV</span></a>
    <a href="https://ebay.us/m/DM1tRs" target="_blank" rel="noopener" class="link-card" aria-label="Visit our store">🚚 <span>Visit our store</span></a>
    <a href="https://github.com/ArakelTheDragon/CfCbazar-Tokens" target="_blank" rel="noopener" class="link-card" aria-label="About WorkToken">📖 <span>About WorkToken</span></a>
    <a href="/help/" target="_blank" rel="noopener" class="link-card" aria-label="Help Center">❓ <span>Help Center</span></a>
    <a href="/about.php" rel="noopener" class="link-card" aria-label="About CfCbazar">ℹ️ <span>About CfCbazar</span></a>
    <a href="/t.php" rel="noopener" class="link-card" aria-label="Terms and Privacy Policy">📜 <span>Terms & Privacy</span></a>
    <a href="/c.php" rel="noopener" class="link-card" aria-label="Cookies Policy">🍪 <span>Cookies Policy</span></a>
  </section>

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "CfCbazar",
    "url": "https://cfcbazar.ct.ws",
    "description": "CfCbazar is your marketplace for smart deals, DIY, games, music, and the WorkToken.",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://cfcbazar.ct.ws/?s={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }
  </script>
</main>
<?php showAdvertPopup(); ?>
<?php include_footer(); ?>