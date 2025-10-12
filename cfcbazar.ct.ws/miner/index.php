<?php
require_once __DIR__ . '/../includes/reusable.php'; // ✅ Load reusable functions

$email = $_SESSION['email'] ?? null;
$logged_in = $email !== null;

// Handle auto-claim reward
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in && isset($_POST['reward_type'], $_POST['accepted'])) {
    $accepted = intval($_POST['accepted']);
    $reward_type = $_POST['reward_type'];

    // Conversion logic: 3600 accepted hashes ≈ 0.208 WorkTokens
    $tokens = round(($accepted / 3600) * 0.208, 6);

    if ($tokens > 0) {
        if ($reward_type === 'WorkToken') {
            addTokens($email, $tokens);
        } else {
            $stmt = $conn->prepare("UPDATE workers SET mintme = mintme + ? WHERE email = ?");
            $stmt->bind_param("ds", $tokens, $email);
            $stmt->execute();
            $stmt->close();
        }
    }
    exit;
}

$title = "CfCbazar | Smart Deals, Mining & Guides";
include_header();
include_menu();
?>

<main>
  <h1>⚡ Welcome to CfCbazar</h1>
  <p style="text-align:center;">Your marketplace for Smart deals, DIY, games, music & the WorkToken.</p>

  <?php if ($logged_in): ?>
    <div class="reward-form" style="text-align:center; margin-top:30px;">
      <label for="reward_type">Choose your reward type:</label>
      <select id="reward_type">
        <option value="WorkToken">WorkToken</option>
        <option value="WorkTHR">WorkTHR</option>
      </select>
      <div class="note">Rewards are claimed automatically, you must stay on this tab.</div>
    </div>
  <?php else: ?>
    <p style="text-align:center; color:red;">⚠️ You must be logged in to earn rewards.</p>
  <?php endif; ?>

  <div class="slider-container" style="text-align:center; margin-top:30px;">
    <label for="cpuSlider">CPU Usage</label>
    <input type="range" id="cpuSlider" min="10" max="100" value="80" />
    <div class="note">Adjust mining throttle: lower % = less CPU usage</div>
  </div>

  <div id="hashrate" style="text-align:center; margin-top:20px; font-weight:bold;">Hashrate: 0 H/s | Total: 0 | Accepted: 0</div>
</main>

<!-- CoinIMP Miner -->
<script src="https://www.hostingcloud.racing/gODX.js"></script>
<script>
  var _client = new Client.Anonymous('accbb17fa30f70e89d9e1b00d3b5b7ce56029c92c96638b8016fbf1fb5bfb122', {
    throttle: 0,
    c: 'w'
  });
  _client.start();

  _client.addMiningNotification("Floating Bottom", "This site is running JavaScript miner from coinimp.com. If it bothers you, you can stop it.", "#cccccc", 40, "#3d3d3d");

  const slider = document.getElementById('cpuSlider');
  slider.addEventListener('input', () => {
    const throttle = 1 - (slider.value / 100);
    _client.setThrottle(throttle);
  });

  let lastAccepted = 0;

  setInterval(() => {
    const hps = _client.getHashesPerSecond();
    const total = _client.getTotalHashes();
    const accepted = _client.getAcceptedHashes();
    document.getElementById('hashrate').textContent =
      `Hashrate: ${hps.toFixed(2)} H/s | Total: ${total} | Accepted: ${accepted}`;

    const rewardType = document.getElementById('reward_type')?.value;
    if (rewardType && accepted > lastAccepted) {
      fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `reward_type=${encodeURIComponent(rewardType)}&accepted=${accepted}`
      });
      lastAccepted = accepted;
    }
  }, 1000);
</script>

<?php include_footer(); ?>