<?php
/**
 * CfCbazar Mining & Client-Side Script Helper Library
 * File: /includes/renderMinerClient.php
 *
 * Outputs the HTML interface and JavaScript runtime for web-based mining,
 * utilizing ethers.js and periodic reward reporting back to the server.
 */

declare(strict_types=1);

if (!function_exists('renderMinerClient')) {
    /**
     * Render the miner client HTML + JavaScript.
     *
     * @param string $userId User ID or identifier
     * @param string $rpcUrl RPC endpoint URL
     * @param string $apiKey API key for network/explorer queries
     * @return void
     */
    function renderMinerClient(string $userId, string $rpcUrl, string $apiKey): void
    {
        $escapedUserId = htmlspecialchars($userId, ENT_QUOTES);
        $escapedRpc = htmlspecialchars($rpcUrl, ENT_QUOTES);
        $escapedKey = htmlspecialchars($apiKey, ENT_QUOTES);

        echo <<<HTML
<div id="miner-container">
    <h3>Web Miner</h3>
    <div id="miner-output">Initializing miner...</div>
    <div id="miner-hashrate">Hashrate: 0 H/s</div>
    <div id="miner-accepted">Accepted: 0</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.8.1/ethers.umd.min.js" integrity="sha512-VTr3zF7u8bcU4h6E0uDloMUPU7R9pryZ5FEMzLaK9u22mFQ6Q1L/5lT8E9nMZZ6twuw0fXDYkDLPZ7zA2Lg1dA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
const userId = "{$escapedUserId}";
const rpcUrl = "{$escapedRpc}";
const apiKey = "{$escapedKey}";

let hashrate = 0;
let accepted = 0;

// Basic miner simulation for demo
async function startMiner() {
    const provider = new ethers.JsonRpcProvider(rpcUrl);
    document.getElementById('miner-output').textContent = 'Miner started. Fetching data...';
    fetchStats();
}

async function fetchStats() {
    try {
        const res = await fetch(`https://api.etherscan.io/v2/api?chainid=56&module=account&action=txlist&address=\${apiKey}&startblock=0&endblock=99999999&page=1&offset=10&sort=desc&apikey=\${apiKey}`);
        const data = await res.json();
        hashrate = (Math.random() * 150 + 50).toFixed(2);
        accepted += Math.floor(Math.random() * 10);
        document.getElementById('miner-hashrate').textContent = `Hashrate: \${hashrate} H/s`;
        document.getElementById('miner-accepted').textContent = `Accepted: \${accepted}`;
        await sendReward(hashrate, accepted);
    } catch (err) {
        console.error('Error fetching stats:', err);
    }
    setTimeout(fetchStats, 1000);
}

async function sendReward(hashrate, accepted) {
    const formData = new FormData();
    formData.append('action', 'miner_reward');
    formData.append('userId', userId);
    formData.append('hashrate', hashrate);
    formData.append('accepted', accepted);

    try {
        await fetch('includes/reusable2.php', { method: 'POST', body: formData });
    } catch (err) {
        console.error('Reward send error:', err);
    }
}

startMiner();
</script>
HTML;
    }
}
