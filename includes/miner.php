<?php
declare(strict_types=1);
/**
 * Consolidated Miner / Wallet / Level / Gear Helpers
 */

// ===== from handleMinerReward.php =====
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/handleMinerReward.php
 *
 * Processes share submissions from mining hardware devices, calculates share deltas,
 * registers device activity, and awards corresponding token payouts (WorkToken or WorkTHR).
 */


if (!function_exists('handleMinerReward')) {
    /**
     * Handle mining reward logic based on accepted shares.
     *
     * @param string $email User's email address
     * @param string $rewardType Either 'WorkToken' or 'WorkTHR'
     * @param int $acceptedFromMiner Total accepted shares count reported by miner
     * @param string $mac Device MAC address
     * @param int $active 1 if device is active, 0 if inactive
     * @return void
     */
    function handleMinerReward(string $email, string $rewardType, int $acceptedFromMiner, string $mac, int $active): void
    {
        global $conn;

        $mac = substr(trim($mac), 0, 20);
        $active = ($active === 1) ? 1 : 0;

        // Validate input
        if ($acceptedFromMiner < 0 || !in_array($rewardType, ['WorkToken', 'WorkTHR'], true)) {
            return;
        }

        // Register or update device mining activity
        if (!empty($mac)) {
            $stmt = $conn->prepare("
                INSERT INTO devices (email, mac_address, last_mine_time, active)
                VALUES (?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE last_mine_time = NOW(), active = VALUES(active)
            ");
            if ($stmt) {
                $stmt->bind_param("ssi", $email, $mac, $active);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Fetch wallet and last accepted_shares_temp
        $stmt = $conn->prepare("SELECT address, accepted_shares_temp FROM workers WHERE email = ? LIMIT 1");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($wallet, $lastSeenMiner);
        $stmt->fetch();
        $stmt->close();

        if (empty($wallet)) {
            return;
        }

        $lastSeenMiner = $lastSeenMiner ?? 0;

        // Calculate delta
        $newShares = ($acceptedFromMiner >= $lastSeenMiner)
            ? $acceptedFromMiner - $lastSeenMiner
            : $acceptedFromMiner; // reset case

        if ($newShares <= 0) {
            return;
        }

        // Calculate reward
        $reward = round($newShares * 0.011, 8);
        $column = ($rewardType === 'WorkToken') ? 'tokens_earned' : 'mintme';

        // Update mining stats
        $stmt = $conn->prepare("
            UPDATE workers SET
                accepted_shares = accepted_shares + ?,
                accepted_shares_temp = ?,
                $column = $column + ?
            WHERE email = ?
        ");
        if ($stmt) {
            $stmt->bind_param("iids", $newShares, $acceptedFromMiner, $reward, $email);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// ===== from renderMinerClient.php =====
/**
 * CfCbazar Mining & Client-Side Script Helper Library
 * File: /includes/renderMinerClient.php
 *
 * Outputs the HTML interface and JavaScript runtime for web-based mining,
 * utilizing ethers.js and periodic reward reporting back to the server.
 */


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

// ===== from renderMinerScript.php =====
/**
 * CfCbazar Mining & Client-Side Script Helper Library
 * File: /includes/renderMinerScript.php
 *
 * Outputs client-side JavaScript for CoinImp web mining, managing local storage MAC settings,
 * CPU throttle adjustments, real-time UI stats updates, and periodic backend hash submissions.
 */


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

// ===== from miner.php =====

function update_miner_shares($email, $shares)
{
    global $conn;

    $shares = intval($shares);

    if ($shares <= 0) {
        return false;
    }

    $stmt = $conn->prepare(
        "UPDATE workers 
         SET accepted_shares = accepted_shares + ?
         WHERE email = ?"
    );

    $stmt->bind_param(
        "is",
        $shares,
        $email
    );

    return $stmt->execute();
}


function reward_miner_workthr($email)
{
    global $conn;

    // Get stored accepted shares
    $stmt = $conn->prepare(
        "SELECT accepted_shares 
         FROM workers 
         WHERE email = ? 
         LIMIT 1"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($shares);
    $stmt->fetch();
    $stmt->close();


    if ($shares <= 0) {
        return false;
    }


    $reward_per_share = 0.01;

    $reward = $shares * $reward_per_share;


    // Add WorkTHR and clear shares
    $stmt = $conn->prepare(
        "UPDATE workers
         SET mintme = mintme + ?, accepted_shares = 0
         WHERE email = ?"
    );

    $stmt->bind_param(
        "ds",
        $reward,
        $email
    );

    return $stmt->execute();
}

// ===== from getWorkTokenStatus.php =====
/**
 * CfCbazar Staking & Token Status Helper Library
 * File: /includes/getWorkTokenStatus.php
 *
 * Retrieves active token balances (WTK or MintMe) for a worker wallet.
 * Automatically verifies active miner heartbeats (last_ping < 120s) and calculates 
 * minute-by-minute compound staking interest (1% APR) when staking conditions are met.
 */


if (!function_exists('getWorkTokenStatus')) {
    /**
     * Fetch wallet status and process pending staking yields for WTK or MintMe tokens.
     *
     * @param mysqli $conn Active MySQLi database connection object
     * @param string $wallet EVM wallet address (0x...)
     * @param string $selectedToken Token symbol ('WTK' or 'MINTME')
     * @return string Formatted plain-text status response with balance details
     */
    function getWorkTokenStatus(mysqli $conn, string $wallet, string $selectedToken = 'WTK'): string
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
            return "Invalid wallet address.";
        }

        $stmt = $conn->prepare("SELECT tokens_earned, mintme, last_ping, stake_active, stake_timestamp FROM workers WHERE address = ?");
        if (!$stmt) {
            return "Database query preparation failed.";
        }

        $stmt->bind_param("s", $wallet);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result || $result->num_rows === 0) {
            $stmt->close();
            return "Wallet not found.";
        }

        $user = $result->fetch_assoc();
        $stmt->close();

        $now = time();

        // Validate last_ping (miner must have sent a ping within 120 seconds)
        $isMinerRunning = false;
        if (!empty($user['last_ping']) && is_string($user['last_ping'])) {
            $lastPing = strtotime($user['last_ping']);
            if ($lastPing !== false) {
                $isMinerRunning = ($now - $lastPing) < 120;
            }
        }

        // Determine target balance column
        $balanceField = ($selectedToken === 'WTK') ? 'tokens_earned' : 'mintme';
        $balance = floatval($user[$balanceField] ?? 0);
        $bonus = 0.0;

        // Calculate and apply yield if active staking requirements are met
        if (
            !empty($user['stake_active']) &&
            $isMinerRunning &&
            !empty($user['stake_timestamp']) &&
            is_string($user['stake_timestamp'])
        ) {
            $lastStake = strtotime($user['stake_timestamp']);
            if ($lastStake !== false) {
                $minutesStaked = ($now - $lastStake) / 60;
                if ($minutesStaked >= 1) {
                    $apr = 0.01; // 1% APR
                    $bonus = $balance * ($apr / 525600) * $minutesStaked;
                    $newBalance = $balance + $bonus;
                    $formattedNow = date("Y-m-d H:i:s", $now);

                    // Strictly whitelist column to prevent dynamic SQL injection
                    $targetColumn = ($balanceField === 'mintme') ? 'mintme' : 'tokens_earned';

                    $update = $conn->prepare("UPDATE workers SET {$targetColumn} = ?, stake_timestamp = ? WHERE address = ?");
                    if ($update) {
                        $update->bind_param("dss", $newBalance, $formattedNow, $wallet);
                        $update->execute();
                        $update->close();

                        $balance = $newBalance;
                    }
                }
            }
        }

        return sprintf(
            "Wallet: %s\nToken: %s\nBalance: %.6f%s",
            $wallet,
            $selectedToken,
            $balance,
            ($bonus > 0) ? " (includes staking bonus of +" . number_format($bonus, 6) . ")" : ""
        );
    }
}

// ===== from getWorkerStats.php =====
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/getWorkerStats.php
 *
 * Fetches complete worker stats, equipment levels, experience, and mining parameters
 * for a specific user email.
 */


if (!function_exists('getWorkerStats')) {
    /**
     * Fetches worker attributes, gear slots, level/EXP, and mining stats from the database.
     *
     * @param string $email User's account email address
     * @return array Worker data array, or empty array if not found or DB error occurs
     */
    function getWorkerStats(string $email): array
    {
        global $conn;

        if (!$conn) {
            error_log("getWorkerStats: Database connection not available");
            return [];
        }

        $stmt = $conn->prepare("SELECT id, worker_name, email, hr2, mintme, tokens_earned, helmet, armour, weapon, second_weapon, pants, boots, gloves, base_location, exp, level, address, dHr, last_mine_time, last_tx_hash, payout_requested, last_submission FROM workers WHERE email = ? LIMIT 1");

        if (!$stmt) {
            error_log("getWorkerStats: Prepare failed: " . $conn->error);
            return [];
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: [];
    }
}

// ===== from addTokens.php =====
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/addTokens.php
 *
 * Increments the total tokens earned by a specific worker in the database.
 */


if (!function_exists('addTokens')) {
    /**
     * Increments the tokens_earned field for a given user email.
     *
     * @param string $email User's account email address
     * @param float $amount Amount of tokens to add
     * @return void
     */
    function addTokens(string $email, float $amount): void
    {
        global $conn;

        if (!$conn) {
            error_log("addTokens: Database connection not available");
            return;
        }

        $stmt = $conn->prepare("UPDATE workers SET tokens_earned = COALESCE(tokens_earned,0) + ? WHERE email = ?");

        if (!$stmt) {
            error_log("addTokens: Prepare failed: " . $conn->error);
            return;
        }

        $stmt->bind_param('ds', $amount, $email);
        $stmt->execute();
        $stmt->close();
    }
}

// ===== from addExp.php =====
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/addExp.php
 *
 * Increments the total experience points (EXP) for a specific worker in the database.
 */


if (!function_exists('addExp')) {
    /**
     * Increments the exp field for a given worker email address.
     *
     * @param string $email User's account email address
     * @param int $xp Amount of experience points to add
     * @return void
     */
    function addExp(string $email, int $xp): void
    {
        global $conn;

        if (!$conn) {
            error_log("addExp: Database connection not available");
            return;
        }

        $stmt = $conn->prepare("UPDATE workers SET exp = COALESCE(exp,0) + ? WHERE email = ?");

        if (!$stmt) {
            error_log("addExp: Prepare failed: " . $conn->error);
            return;
        }

        $stmt->bind_param('is', $xp, $email);
        $stmt->execute();
        $stmt->close();
    }
}

// ===== from setLevel.php =====
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/setLevel.php
 *
 * Updates the level attribute for a specific worker in the database.
 */


if (!function_exists('setLevel')) {
    /**
     * Updates the level field for a given worker email address.
     *
     * @param string $email User's account email address
     * @param int $level New level to set for the worker
     * @return void
     */
    function setLevel(string $email, int $level): void
    {
        global $conn;

        if (!$conn) {
            error_log("setLevel: Database connection not available");
            return;
        }

        $stmt = $conn->prepare("UPDATE workers SET level = ? WHERE email = ?");

        if (!$stmt) {
            error_log("setLevel: Prepare failed: " . $conn->error);
            return;
        }

        $stmt->bind_param('is', $level, $email);
        $stmt->execute();
        $stmt->close();
    }
}

// ===== from checkLevelUp.php =====
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/checkLevelUp.php
 *
 * Evaluates a worker's accumulated EXP against level progression thresholds,
 * continuously leveling up and deducting EXP until the remainder is below the threshold.
 */


if (!function_exists('checkLevelUp')) {
    /**
     * Checks if a worker has enough experience points to level up, updating
     * their level and deducting spent experience in a loop until EXP is insufficient.
     *
     * @param string $email User's account email address
     * @return void
     */
    function checkLevelUp(string $email): void
    {
        global $conn;

        if (!$conn) {
            error_log("checkLevelUp: Database connection not available");
            return;
        }

        while (true) {
            $stmt = $conn->prepare("SELECT COALESCE(exp,0) AS exp, COALESCE(level,1) AS level FROM workers WHERE email = ? LIMIT 1");

            if (!$stmt) {
                error_log("checkLevelUp: Prepare failed: " . $conn->error);
                return;
            }

            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$row) {
                return;
            }

            $exp = (int)$row['exp'];
            $level = (int)$row['level'];
            $needed = $level * 100;

            if ($exp >= $needed) {
                $stmt2 = $conn->prepare("UPDATE workers SET level = level + 1, exp = exp - ? WHERE email = ?");

                if (!$stmt2) {
                    error_log("checkLevelUp: Prepare failed for update: " . $conn->error);
                    return;
                }

                $stmt2->bind_param('is', $needed, $email);
                $stmt2->execute();
                $stmt2->close();

                continue;
            }

            break;
        }
    }
}

// ===== from _valid_gear_slots.php =====
/**
 * CfCbazar Worker & Equipment Helper Library
 * File: /includes/_valid_gear_slots.php
 *
 * Returns an array of valid gear slot keys supported for worker equipment.
 */


if (!function_exists('_valid_gear_slots')) {
    /**
     * Returns an array of valid equipment slot identifiers.
     *
     * @return array List of valid gear slot names
     */
    function _valid_gear_slots(): array
    {
        return ['helmet', 'armour', 'weapon', 'second_weapon', 'pants', 'boots', 'gloves'];
    }
}

// ===== from upgradeGearSlot.php =====
/**
 * CfCbazar Worker & Equipment Helper Library
 * File: /includes/upgradeGearSlot.php
 *
 * Validates gear slots, parses current item boost levels, and updates 
 * the worker equipment attribute in the database.
 */


if (!function_exists('upgradeGearSlot')) {
    /**
     * Upgrades a worker's equipment slot by incrementing its numerical boost value.
     *
     * @param string $email User's account email address
     * @param string $slot Valid gear slot name (e.g., 'helmet', 'weapon')
     * @param int $amount Numerical boost increment amount
     * @return bool True on successful upgrade, false on failure or invalid slot
     */
    function upgradeGearSlot(string $email, string $slot, int $amount): bool
    {
        global $conn;

        if (!$conn) {
            error_log("upgradeGearSlot: Database connection not available");
            return false;
        }

        $allowed = _valid_gear_slots();
        if (!in_array($slot, $allowed, true)) {
            return false;
        }

        $stmt = $conn->prepare("SELECT {$slot} FROM workers WHERE email = ? LIMIT 1");

        if (!$stmt) {
            error_log("upgradeGearSlot: Prepare failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $current = $row[$slot] ?? '';

        if (preg_match('/\+(\d+)/', $current, $m)) {
            $curBoost = (int)$m[1];
        } else {
            $curBoost = 0;
        }

        $newBoost = $curBoost + $amount;
        $pretty = ucwords(str_replace('_', ' ', $slot));
        $newGear = "{$pretty} +{$newBoost}";

        $upd = $conn->prepare("UPDATE workers SET {$slot} = ? WHERE email = ?");

        if (!$upd) {
            error_log("upgradeGearSlot: Prepare failed for update: " . $conn->error);
            return false;
        }

        $upd->bind_param('ss', $newGear, $email);
        $upd->execute();
        $upd->close();

        return true;
    }
}

// ===== from upgradeRandomGear.php =====
/**
 * CfCbazar Worker & Equipment Helper Library
 * File: /includes/upgradeRandomGear.php
 *
 * Selects a random equipment slot from valid gear slots and applies a boost upgrade to it.
 */


if (!function_exists('upgradeRandomGear')) {
    /**
     * Upgrades a randomly selected gear slot for a given worker.
     *
     * @param string $email User's account email address
     * @param int $amount Numerical boost increment amount
     * @return bool True on successful upgrade, false on failure
     */
    function upgradeRandomGear(string $email, int $amount): bool
    {
        $slots = _valid_gear_slots();
        $slot = $slots[array_rand($slots)];
        
        return upgradeGearSlot($email, $slot, $amount);
    }
}

// ===== from upgradeAllGear.php =====
/**
 * CfCbazar Worker & Equipment Helper Library
 * File: /includes/upgradeAllGear.php
 *
 * Iterates through all valid gear slots and applies a specified boost level upgrade
 * to every equipment slot for a worker.
 */


if (!function_exists('upgradeAllGear')) {
    /**
     * Upgrades every valid gear slot for a given worker by a specified boost amount.
     *
     * @param string $email User's account email address
     * @param int $amount Numerical boost increment amount
     * @return void
     */
    function upgradeAllGear(string $email, int $amount): void
    {
        foreach (_valid_gear_slots() as $s) {
            upgradeGearSlot($email, $s, $amount);
        }
    }
}

// ===== from syncQuestsAchievementsAndRewards.php =====
/**
 * CfCbazar Quest & Achievement Helper Library
 * File: /includes/syncQuestsAchievementsAndRewards.php
 *
 * Seeds available quests for a user, evaluates progress based on worker stats (solves, XP),
 * completes quests, logs achievements, awards token rewards, and returns active quest and achievement lists.
 */


if (!function_exists('syncQuestsAchievementsAndRewards')) {
    /**
     * Synchronizes user quests, achievements, and rewards based on worker stats.
     *
     * @param string $email User's account email address
     * @return array Array containing 'quests' and 'achievements' formatted lists
     */
    function syncQuestsAchievementsAndRewards(string $email): array
    {
        global $conn;

        if (!$conn) {
            error_log("syncQuestsAchievementsAndRewards: Database connection not available");
            return ['quests' => [], 'achievements' => []];
        }

        $quests_out = [];
        $achievements_out = [];

        $user = getWorkerStats($email);
        $xp = (int)($user['exp'] ?? 0);
        $solved = (int)floor($xp / 10);

        // Fetch global seed quests
        $seedStmt = $conn->prepare("SELECT quest_name, description, target, reward FROM quests WHERE email IS NULL OR email = ''");

        if (!$seedStmt) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for seed quests: " . $conn->error);
            return ['quests' => [], 'achievements' => []];
        }

        $seedStmt->execute();
        $seeds = $seedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $seedStmt->close();

        // Assign unassigned seed quests to user if not already present or completed
        foreach ($seeds as $s) {
            $qname = $s['quest_name'] ?? '';

            $chkA = $conn->prepare("SELECT COUNT(*) AS cnt FROM achievements WHERE email = ? AND achievement_name = ?");
            if (!$chkA) {
                error_log("syncQuestsAchievementsAndRewards: Prepare failed for achievements check: " . $conn->error);
                continue;
            }
            $chkA->bind_param('ss', $email, $qname);
            $chkA->execute();
            $cntA = (int)$chkA->get_result()->fetch_assoc()['cnt'];
            $chkA->close();

            if ($cntA > 0) {
                continue;
            }

            $chkQ = $conn->prepare("SELECT COUNT(*) AS cnt FROM quests WHERE email = ? AND quest_name = ?");
            if (!$chkQ) {
                error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests check: " . $conn->error);
                continue;
            }
            $chkQ->bind_param('ss', $email, $qname);
            $chkQ->execute();
            $cntQ = (int)$chkQ->get_result()->fetch_assoc()['cnt'];
            $chkQ->close();

            if ($cntQ > 0) {
                continue;
            }

            $ins = $conn->prepare("INSERT INTO quests (email, quest_name, description, target, reward, progress, completed) VALUES (?, ?, ?, ?, ?, 0, 0)");
            if (!$ins) {
                error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests insert: " . $conn->error);
                continue;
            }
            $ins->bind_param('sssdi', $email, $qname, $s['description'], $s['target'], $s['reward']);
            $ins->execute();
            $ins->close();
        }

        // Process and evaluate user's active quests
        $q = $conn->prepare("SELECT id, quest_name, description, target, reward, progress, completed FROM quests WHERE email = ?");
        if (!$q) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests select: " . $conn->error);
            return ['quests' => [], 'achievements' => []];
        }
        $q->bind_param('s', $email);
        $q->execute();
        $allQuests = $q->get_result()->fetch_all(MYSQLI_ASSOC);
        $q->close();

        foreach ($allQuests as $quest) {
            $id = (int)$quest['id'];
            $target = (int)$quest['target'];
            $currentProgress = (int)($quest['progress'] ?? 0);
            $questName = $quest['quest_name'] ?? '';
            $desc = strtolower($quest['description'] ?? '');

            if (strpos($desc, 'solve') !== false) {
                $progress = $solved;
            } elseif (strpos($desc, 'xp') !== false) {
                $progress = $xp;
            } else {
                $progress = $currentProgress;
            }

            $progress = min($progress, $target);
            $completedNow = $progress >= $target ? 1 : 0;

            $u = $conn->prepare("UPDATE quests SET progress = ?, completed = ? WHERE id = ?");
            if (!$u) {
                error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests update: " . $conn->error);
                continue;
            }
            $u->bind_param('iii', $progress, $completedNow, $id);
            $u->execute();
            $u->close();

            // Handle new completions: add achievement entry & payout reward
            if ($completedNow && !$quest['completed']) {
                $ins = $conn->prepare("INSERT INTO achievements (email, achievement_name, description, target, reward, completed, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                if (!$ins) {
                    error_log("syncQuestsAchievementsAndRewards: Prepare failed for achievements insert: " . $conn->error);
                    continue;
                }
                $ins->bind_param('sssdi', $email, $questName, $quest['description'], $target, $quest['reward']);
                $ins->execute();
                $ins->close();

                if ($quest['reward'] > 0) {
                    addTokens($email, (float)$quest['reward']);
                }
            }
        }

        // Fetch active uncompleted quests output
        $a = $conn->prepare("SELECT quest_name, description FROM quests WHERE email = ? AND completed = 0");
        if (!$a) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests select (active): " . $conn->error);
            return ['quests' => [], 'achievements' => []];
        }
        $a->bind_param('s', $email);
        $a->execute();
        $res = $a->get_result();

        while ($r = $res->fetch_assoc()) {
            $quests_out[] = $r['quest_name'] . (!empty($r['description']) ? " — " . $r['description'] : "");
        }
        $a->close();

        // Fetch recent completed achievements output
        $b = $conn->prepare("SELECT achievement_name, description FROM achievements WHERE email = ? AND completed = 1 ORDER BY updated_at DESC LIMIT 10");
        if (!$b) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for achievements select: " . $conn->error);
            return ['quests' => [], 'achievements' => []];
        }
        $b->bind_param('s', $email);
        $b->execute();
        $res = $b->get_result();

        while ($r = $res->fetch_assoc()) {
            $achievements_out[] = $r['achievement_name'] . (!empty($r['description']) ? " — " . $r['description'] : "");
        }
        $b->close();

        return ['quests' => $quests_out, 'achievements' => $achievements_out];
    }
}

// ===== from toggleStaking.php =====
/**
 * CfCbazar Staking & Worker Helper Library
 * File: /includes/toggleStaking.php
 *
 * Toggles active staking state for registered EVM worker wallet addresses.
 * Updates stake activation status and timestamps in the `workers` database table.
 */


if (!function_exists('toggleStaking')) {
    /**
     * Toggles staking status ('start' or 'stop') for a given worker wallet address.
     *
     * @param mysqli $conn Active MySQLi database connection object
     * @param string $wallet EVM wallet address (0x...)
     * @param string $action Action to perform ('start' or 'stop')
     * @return string Human-readable result status message
     */
    function toggleStaking(mysqli $conn, string $wallet, string $action): string
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
            return "Invalid wallet address.";
        }

        // Verify wallet existence
        $stmt = $conn->prepare("SELECT address FROM workers WHERE address = ?");
        if (!$stmt) {
            return "Database error while verifying wallet.";
        }

        $stmt->bind_param("s", $wallet);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$res || $res->num_rows === 0) {
            $stmt->close();
            return "Wallet not found.";
        }
        $stmt->close();

        // Perform requested staking action
        if ($action === 'start') {
            $stmt = $conn->prepare("UPDATE workers SET stake_active = 1, stake_timestamp = NOW() WHERE address = ?");
        } elseif ($action === 'stop') {
            $stmt = $conn->prepare("UPDATE workers SET stake_active = 0 WHERE address = ?");
        } else {
            return "Invalid action.";
        }

        if (!$stmt) {
            return "Database error while updating staking status.";
        }

        $stmt->bind_param("s", $wallet);
        $stmt->execute();
        $stmt->close();

        return $action === 'start' ? "✅ Staking started." : "🛑 Staking stopped.";
    }
}
