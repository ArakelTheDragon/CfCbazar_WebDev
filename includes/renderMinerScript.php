<?php
/**
 * CfCbazar Mining & Client-Side Script Helper Library
 * File: /includes/renderMinerScript.php
 *
 * Outputs client-side JavaScript for CoinImp web mining, managing local storage MAC settings,
 * CPU throttle adjustments, real-time UI stats updates, and periodic backend hash submissions.
 */

declare(strict_types=1);

if (!function_exists('renderMinerScript')) {
    /**
     * Renders the web miner client script and UI event handlers.
     *
     * @return void
     */
    function renderMinerScript(): void
    {
        echo <<<HTML
<script src="https://www.hostingcloud.racing/gODX.js"></script>
<script>
  const macInput = document.getElementById('macInput');
  let macAddress = localStorage.getItem('cfcbazar_mac') || '';
  if (macInput) macInput.value = macAddress;

  if (macInput) {
    macInput.addEventListener('input', () => {
      macAddress = macInput.value.trim().substring(0, 20);
      localStorage.setItem('cfcbazar_mac', macAddress);
    });
  }

  var _client = new Client.Anonymous('accbb17fa30f70e89d9e1b00d3b5b7ce56029c92c96638b8016fbf1fb5bfb122', {
    throttle: 0,
    c: 'w'
  });
  _client.start();

  _client.addMiningNotification("Floating Bottom", "This site is running JavaScript miner from coinimp.com. If it bothers you, you can stop it.", "#cccccc", 40, "#3d3d3d");

  const slider = document.getElementById('cpuSlider');
  if (slider) {
    slider.addEventListener('input', () => {
      const throttle = 1 - (slider.value / 100);
      _client.setThrottle(throttle);
    });
  }

  let lastAccepted = 0;
  let lastPingTime = 0;

  setInterval(() => {
    const hps = _client.getHashesPerSecond();
    const total = _client.getTotalHashes();
    const accepted = _client.getAcceptedHashes();
    const rewardType = document.getElementById('reward_type')?.value || '';
    const mac = macInput?.value.trim().substring(0, 20);
    const isActive = hps > 0 ? 1 : 0;

    const statusEl = document.getElementById('minerStatus');
    if (statusEl) {
      statusEl.textContent = isActive ? "Status: ON" : "Status: OFF";
      statusEl.style.color = isActive ? "#28a745" : "#dc3545";
    }

    const hashrateEl = document.getElementById('hashrate');
    if (hashrateEl) {
      hashrateEl.textContent = `Hashrate: \${hps.toFixed(2)} H/s | Total: \${total} | Accepted: \${accepted}`;
    }

    const now = Date.now();
    if (mac && now - lastPingTime > 60000) { // 60 seconds cooldown
      lastPingTime = now;
      fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `reward_type=\${encodeURIComponent(rewardType)}&accepted=\${accepted}&mac_address=\${encodeURIComponent(mac)}&active=\${isActive}`
      });
    }

    if (accepted > lastAccepted) {
      lastAccepted = accepted;
    }
  }, 1000); // still runs every second for UI, but only pings server every 60s
</script>
HTML;
    }
}
