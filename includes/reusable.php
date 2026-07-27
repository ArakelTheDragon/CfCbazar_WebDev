<?php
// /includes/reusable.php
/* === Function Index ===

enforce_https()

// Layout / UI
include_header()
include_menu()
include_url_header()
include_footer()
render_ecosystem_content()
render_device_table()
renderMinerScript()
renderMinerClient()
showAdvertPopup()
renderCaptchaIfNeeded()
render_worktoken_dashboard()
render_withdraw_link()
render_workthr_teaser()
render_worktoken_teaser()
render_token_price_tracker()
show_disabled_message()
displayServerStatus()
render_top_userbar()

// Worker / Mining
getWorkerStats($email)
addTokens($email, $amount)
addExp($email, $xp)
setLevel($email, $level)
checkLevelUp($email)
handleMinerReward($email, $rewardType, $acceptedFromMiner, $mac, $active)
grant_mining_bonus($email)

// Gear System
_valid_gear_slots()
upgradeGearSlot($email, $slot, $amount)
upgradeRandomGear($email, $amount)
upgradeAllGear($email, $amount)

// Quests / Achievements
syncQuestsAchievementsAndRewards($email)

// Devices
getDevices($email, $cooldownSeconds = 3, $source = 'auto') - obsolete

// Authentication / Session
logoutUser()
getUserStatus($conn)
setReturnUrlCookie($path, $expireSeconds = 300)
redirectToReturnUrl($fallback = '/index.php')

// Analytics
trackVisit($conn)

// System
checkSystemFlags($conn)
redirectToCfCbazar42web()

// Staking
toggleStaking($conn, $wallet, $action)
getWorkTokenStatus($conn, $wallet, $selectedToken = 'WTK')
getStakingStatus($conn, $wallet)

*/
declare(strict_types=1);

// ===============================
// includes/reusable.php
// Compatible with PHP 8.2+
// ===============================

if (session_status() === PHP_SESSION_NONE) session_start();

// Require config
require_once __DIR__ . '/../config.php';

// global database connection
global $conn;
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("reusable.php: DB connection failed - " . $conn->connect_error);
    http_response_code(500);
    die("Database connection failed");
}

// --- Optional HTTPS Enforcement (disabled for free hosting) ---
if (!function_exists('enforce_https')) {
    function enforce_https() {
        // Disable this on free hosting if SSL is not supported
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            if (!headers_sent()) {
                header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
                exit;
            }
        }
    }
}
// To enable, uncomment this:
//enforce_https();


// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Usage add at the top of the page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
}
*/
 
// HTML helpers
function include_header() {
    global $title;
    $title = $title ?? 'CfCbazar - Your marketplace for Smart Deals, DIY, games, music & the WorkToken';
    $description = 'CfCbazar offers URL shortening, power usage calc, survivor tool calc, value of work per hour for different professions, products and services and the WorkToken ecosystem for mining and spending WTK. Join now!';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
	echo '<meta name="trustpilot-one-time-domain-verification-id" content="4c6c3303-6308-414f-b3f4-9735179a3877"/>';
    echo '<meta name="csrf_token" content="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<meta name="description" content="' . htmlspecialchars($description) . '">';
    echo '<meta name="keywords" content="CfCbazar, smart deals, DIY tools, games, music, WorkToken, platform credits, online tools, power usage calc, survival budget calc, value of 1h of work table">';
    echo '<meta name="robots" content="index, follow">';
    echo '<meta name="author" content="CfCbazar">';
    // Open Graph for social media
    echo '<meta property="og:title" content="' . htmlspecialchars($title) . '">';
    echo '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
    echo '<meta property="og:type" content="website">';
    echo '<meta property="og:url" content="https://cfcbazar.42web.io' . htmlspecialchars($_SERVER['REQUEST_URI']) . '">';
    echo '<meta property="og:image" content="https://cfcbazar.42web.io/images/cfcbazar-banner.jpg">';
    // Twitter Card
    echo '<meta name="twitter:card" content="summary_large_image">';
    echo '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">';
    echo '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';
    echo '<meta name="twitter:image" content="https://cfcbazar.42web.io/images/cfcbazar-banner.jpg">';
    echo '<link rel="stylesheet" href="/css/styles.css">';
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>';
    echo '</head><body>';
    echo '<header class="header">';
    //echo '<h1><img src="/images/cfcbazar-banner.jpg" alt="CfCbazar Logo" loading="lazy"> CfCbazar</h1>';
    echo '</header>';
}
//include_header();

function include_menu(): void
{
    global $conn;

    $email = strtolower(trim($_SESSION['email'] ?? ''));
    $logged_in = !empty($email);
    $is_admin = false;

    // Check if the logged-in user is an administrator
    if ($logged_in) {
        $stmt = $conn->prepare("SELECT status FROM users WHERE email = ? LIMIT 1");

        if (!$stmt) {
            error_log("include_menu(): Prepare failed - " . $conn->error);
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $is_admin = ((int)$row['status'] === 1);
            }

            $stmt->close();
        }
    }

    // Menu items
    $menu = [
        ['🏠 Home', '/index.php', false],
        ['🔗 Smart Deals', 'https://www.facebook.com/groups/195994786555718/', true],
        ['🔧 DIY Tools', '/diy/index.php', true],
        ['🎮 Games', '/games/index.php', true],
        ['🎵 Music', 'https://youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu', true],
        ['⛏️ Proof Of Work Center', '/pow/', true],
        ['💰 WorkToken', '/worktoken/index.php', true],
        ['🚚 Visit Store', 'https://ebay.us/m/DM1tRs', true],
        ['📖 About WorkToken', 'https://github.com/ArakelTheDragon/CfCbazar-Tokens', true],
        ['❓ Help Center', '/help/', true],
        ['ℹ️ About Us', '/about.php', false],
        ['📜 Terms & Privacy', '/t.php', false],
        ['🍪 Cookies', '/c.php', false],
    ];

    echo '<nav class="main-nav">';
    echo '<button class="menu-toggle" aria-expanded="false" aria-label="Toggle navigation">☰ Menu</button>';
    echo '<ul class="nav-menu">';

    foreach ($menu as [$title, $url, $newTab]) {

        echo '<li><a href="' . htmlspecialchars($url) . '"';

        if ($newTab) {
            echo ' target="_blank" rel="noopener noreferrer"';
        }

        echo '>' . htmlspecialchars($title) . '</a></li>';
    }

    // Login / Logout
    if ($logged_in) {
        echo '<li><a href="/logout.php">🚪 Log Out</a></li>';
    } else {
        echo '<li><a href="/login.php">🔑 Sign In / Register</a></li>';
    }

    // Admin menu
    if ($is_admin) {
        echo '<li><a href="/admin.php">🛠️ Admin</a></li>';
    }

    echo '</ul>';
    echo '</nav>';
}

// for shortner only
function include_url_header() {
    echo '<h1 class="page-title">🔗 URL Shortener</h1>';
    echo '<p>Shorten your links and track clicks with CfCbazar. Log in to create and manage your short URLs.</p>';
}

function include_footer() {
    echo '<footer class="footer" style="padding:1em; background:#f8f8f8; text-align:center; font-size:0.95em;">';
    echo '<p>&copy; CfCbazar. All rights reserved.</p>';
    echo '<p><a href="/t.php">Privacy Policy</a> | <a href="/t.php">Terms</a></p>';
    echo '<p style="margin-top:1em;">📢 Follow us for official updates:</p>';
    echo '<ul class="social-links" style="list-style:none; padding:0; margin:0; display:flex; flex-wrap:wrap; justify-content:center; gap:0.5em;">';
    echo '<li><a href="https://x.com/workthrp" target="_blank" rel="noopener">🐦 WorkToken on X</a></li>';
    echo '<li><a href="https://x.com/cfcbazargroup" target="_blank" rel="noopener">🐦 CfCbazar Group on X</a></li>';
    echo '<li><a href="https://www.facebook.com/share/12J6NS1M2cY/" target="_blank" rel="noopener">📘 WorkToken on Facebook</a></li>';
    echo '<li><a href="https://www.facebook.com/share/1CshFfT6bG/" target="_blank" rel="noopener">📘 CfCbazar on Facebook</a></li>';
    echo '<li><a href="https://youtube.com/@worktoken?si=PtWNenpqAYYadD0V" target="_blank" rel="noopener">📺 WorkToken on YouTube</a></li>';
    echo '<li><a href="https://youtube.com/@cfcbazar?si=LkDTc8EPU1vr9MNR" target="_blank" rel="noopener">📺 CfCbazar on YouTube</a></li>';
    echo '<li><a href="https://www.tiktok.com/@worktoken?_t=ZN-90uYlCvmRks&_r=1" target="_blank" rel="noopener">🎵 WorkToken on TikTok</a></li>';
    echo '<li><a href="https://www.tiktok.com/@cfcbazar?_t=ZN-90uYo1jYz4A&_r=1" target="_blank" rel="noopener">🎵 CfCbazar on TikTok</a></li>';
    echo '<li><a href="https://github.com/ArakelTheDragon/CfCbazar-Tokens" target="_blank" rel="noopener">🧠 CfCbazar-Tokens on GitHub</a></li>';
    echo '<li><a href="https://pancakeswap.finance/swap?inputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D&outputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00" target="_blank" rel="noopener">💱 Trade WorkTHR/WTK on PancakeSwap</a></li>';
    echo '</ul>';
    echo '<p style="margin-top:1em;">📬 Contact us: <a href="mailto:cfcbazar@gmail.com">cfcbazar@gmail.com</a></p>';
    echo '</footer>';

    // Load your main JS
    echo '<script src="/js/scripts.js" defer></script>';

    // Dynamic menu height fix (GLOBAL)
    echo '<script>
    document.addEventListener("DOMContentLoaded", () => {
        const menu = document.querySelector(".main-nav");
        if (menu) {
            document.documentElement.style.setProperty("--menu-height", menu.offsetHeight + "px");
        }
    });
    </script>';

    echo '</body></html>';
}

// Ecosystem helper
function render_ecosystem_content() {
    ?>
    <main class="ecosystem-section">
        <h1>💠 CfCbazar Blockchain Token Ecosystem & Credit Flow</h1>
        <div class="chart-container">
            <canvas id="tokenChart"></canvas>
        </div>
        <section class="ecosystem-flow">
            <h2>🔁 How CfCbazar Converts Blockchain Tokens into Platform Credits</h2>
            <ul>
                <li><strong>Blockchain Reserve:</strong> Backs all platform credits</li>
                <li><strong>Platform Credits:</strong> Used for games, features, and withdrawals</li>
                <li><strong>To Get Credits:</strong> Send WorkTokens or BNB to <code class="token-address">0xFBd767f6454bCd07c959da2E48fD429531A1323A</code></li>
                <li><strong>On Withdraw:</strong> You receive WorkTokens from <code class="token-address">0xFBd767f6454bCd07c959da2E48fD429531A1323A</code></li>
            </ul>
            <p>Learn more about <a href="/worktoken/index.php">WorkToken mechanics</a> or explore <a href="/games/index.php">CfCbazar games</a>.</p>
        </section>
        <div class="deposit-instructions">
            <canvas id="qr-canvas" data-qr-value="0xFBd767f6454bCd07c959da2E48fD429531A1323A"></canvas>
            <button onclick="downloadQR()">Download QR Code</button>
        </div>
    </main>
    <?php
}

// Worker/gear/quest functions
function getWorkerStats(string $email) {
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

function addTokens(string $email, float $amount) {
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

function addExp(string $email, int $xp) {
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

function setLevel(string $email, int $level) {
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

function checkLevelUp(string $email) {
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
        if (!$row) return;
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

function _valid_gear_slots(): array {
    return ['helmet','armour','weapon','second_weapon','pants','boots','gloves'];
}

function upgradeGearSlot(string $email, string $slot, int $amount) {
    global $conn;
    if (!$conn) {
        error_log("upgradeGearSlot: Database connection not available");
        return false;
    }
    $allowed = _valid_gear_slots();
    if (!in_array($slot, $allowed, true)) return false;
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
    $pretty = ucwords(str_replace('_',' ',$slot));
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

function upgradeRandomGear(string $email, int $amount) {
    $slots = _valid_gear_slots();
    $slot = $slots[array_rand($slots)];
    return upgradeGearSlot($email, $slot, $amount);
}

function upgradeAllGear(string $email, int $amount) {
    foreach (_valid_gear_slots() as $s) upgradeGearSlot($email, $s, $amount);
}

function syncQuestsAchievementsAndRewards(string $email) {
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
    $seedStmt = $conn->prepare("SELECT quest_name, description, target, reward FROM quests WHERE email IS NULL OR email = ''");
    if (!$seedStmt) {
        error_log("syncQuestsAchievementsAndRewards: Prepare failed for seed quests: " . $conn->error);
        return ['quests' => [], 'achievements' => []];
    }
    $seedStmt->execute();
    $seeds = $seedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $seedStmt->close();
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
        if ($cntA > 0) continue;
        $chkQ = $conn->prepare("SELECT COUNT(*) AS cnt FROM quests WHERE email = ? AND quest_name = ?");
        if (!$chkQ) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests check: " . $conn->error);
            continue;
        }
        $chkQ->bind_param('ss', $email, $qname);
        $chkQ->execute();
        $cntQ = (int)$chkQ->get_result()->fetch_assoc()['cnt'];
        $chkQ->close();
        if ($cntQ > 0) continue;
        $ins = $conn->prepare("INSERT INTO quests (email, quest_name, description, target, reward, progress, completed) VALUES (?, ?, ?, ?, ?, 0, 0)");
        if (!$ins) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests insert: " . $conn->error);
            continue;
        }
        $ins->bind_param('sssdi', $email, $qname, $s['description'], $s['target'], $s['reward']);
        $ins->execute();
        $ins->close();
    }
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
        $currentProgress = (int)$quest['progress'] ?? 0;
        $questName = $quest['quest_name'] ?? '';
        $desc = strtolower($quest['description'] ?? '');
        if (strpos($desc,'solve') !== false) {
            $progress = $solved;
        } elseif (strpos($desc,'xp') !== false) {
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

function render_device_table($devices, $type) {
    if (empty($devices)) {
        return;
    }

    $icon = ($type === 'active') ? '🟢' : '🔴';
    $class = ($type === 'active') ? 'active' : 'inactive';
    echo "<h4>$icon " . ucfirst($type) . " Devices</h4>";
    echo "<table class='device-table $class' role='grid' aria-label='$type Devices'>";
    echo "<thead><tr><th>MAC Address</th><th>Last Mine Time</th><th>Status</th><th>Action</th></tr></thead><tbody>";

    foreach ($devices as $device) {
        $mac = htmlspecialchars($device['mac_address']);
        $last_mine = htmlspecialchars($device['last_mine_time'] ?? 'Never');
        $status = $device['active'] ? '1' : '0';
        echo "
        <tr>
            <td>$mac</td>
            <td>$last_mine</td>
            <td>$status</td>
            <td>
                <form method='POST' style='display:inline;'>
                    <input type='hidden' name='csrf_token' value='" . htmlspecialchars($_SESSION['csrf_token']) . "'>
                    <input type='hidden' name='delete_mac' value='$mac'>
                    <button type='submit' class='delete-btn' onclick=\"return confirm('Delete device $mac?');\">🗑️ Delete</button>
                </form>
            </td>
        </tr>";
    }

    echo "</tbody></table>";
}


// Reusable2.php
//declare(strict_types=1); // moved to top of reusable.php

// ===============================
// includes/reusable2.php
// Compatible with PHP 8.2+
// ===============================

// --- Database connection assumed to be global ---
global $conn;


/** Old
 * Handle mining reward logic.
 *
 * @param string $email       User's email address
 * @param string $rewardType  Either 'WorkToken' or 'WorkTHR'
 * @param int    $accepted    Number of accepted hashes
 * @param string $mac         Device MAC address
 * @param int    $active      1 if device is active, 0 if inactive
 */
 /*
function handleMinerReward(string $email, string $rewardType, int $acceptedFromMiner, string $mac, int $active) {
    global $conn;

    $mac = substr(trim($mac), 0, 20);
    $active = ($active === 1) ? 1 : 0;

    // Register or update device mining activity
    if (!empty($mac)) {
        $stmt = $conn->prepare("
            INSERT INTO devices (email, mac_address, last_mine_time, active)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE last_mine_time = NOW(), active = VALUES(active)
        ");
        $stmt->bind_param("ssi", $email, $mac, $active);
        $stmt->execute();
        $stmt->close();
    }

    // Fetch current accepted shares and last seen miner value
    $stmt = $conn->prepare("SELECT accepted_shares, accepted_shares_temp FROM workers WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($accepted_shares, $lastSeenMiner);
    $stmt->fetch();
    $stmt->close();

    $lastSeenMiner = $lastSeenMiner ?? 0;

    // Compute delta since last call
    if ($acceptedFromMiner >= $lastSeenMiner) {
        $newShares = $acceptedFromMiner - $lastSeenMiner;
    } else {
        // Miner restarted or counter reset
        $newShares = $acceptedFromMiner; // take new miner shares as delta
    }

    if ($newShares <= 0) return; // nothing to reward

    // Compute token reward
    $tokens = round(($newShares / 3600) * 0.208, 18);
    $column = ($rewardType === 'WorkToken') ? 'tokens_earned' : 'mintme';

    // Update database: total shares, last seen miner, and reward
    $stmt = $conn->prepare("
        UPDATE workers 
        SET $column = $column + ?, 
            accepted_shares = accepted_shares + ?, 
            accepted_shares_temp = ?
        WHERE email = ?
    ");
    $stmt->bind_param("ddds", $tokens, $newShares, $acceptedFromMiner, $email);
    $stmt->execute();
    $stmt->close();
}
*/

/** New
 * Handle mining reward logic based on accepted shares.
 *
 * @param string $email       User's email address
 * @param string $rewardType  Either 'WTK' or 'WorkTHR'
 * @param int    $accepted    Number of accepted shares from miner
 * @param string $mac         Device MAC address
 * @param int    $active      1 if device is active, 0 if inactive
 */
function handleMinerReward(string $email, string $rewardType, int $acceptedFromMiner, string $mac, int $active): void {
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
    if (!$stmt) return;

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($wallet, $lastSeenMiner);
    $stmt->fetch();
    $stmt->close();

    if (empty($wallet)) return;

    $lastSeenMiner = $lastSeenMiner ?? 0;

    // Calculate delta
    $newShares = ($acceptedFromMiner >= $lastSeenMiner)
        ? $acceptedFromMiner - $lastSeenMiner
        : $acceptedFromMiner; // reset case

    if ($newShares <= 0) return;

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

/*
function renderMinerInterface(bool $logged_in) {
    ?>
    <main class="dashboard-container">
      <h1 class="page-title">⛏️ CfCbazar Web Miner</h1>
      <p style="text-align:center;">Mine platform credits directly in your browser. Stay on this tab to earn rewards.</p>

      <?php if ($logged_in): ?>
        <div class="reward-form" style="text-align:center; margin-top:30px;">
          <label for="reward_type">Choose your reward type:</label>
          <select id="reward_type">
            <option value="WorkToken">WorkToken</option>
            <option value="WorkTHR">WorkTHR</option>
          </select>
          <div class="note">Rewards are claimed automatically every second.</div>
        </div>

        <div class="mac-form" style="text-align:center; margin-top:30px;">
          <label for="macInput">Device MAC Address:</label>
          <input type="text" id="macInput" maxlength="20" placeholder="e.g. 74:46:A0:91:46:D0" style="padding:8px; width:220px;" />
          <div class="note">Enter your device MAC address (up to 20 characters). You can change it anytime.</div>
        </div>
      <?php else: ?>
        <div style="text-align:center; margin-top:30px;">
          <p style="color:red; font-weight:bold;">⚠️ You must be logged in to earn rewards.</p>
          <a href="/login.php" class="button" style="display:inline-block; margin-top:10px; padding:12px 24px; background:linear-gradient(135deg, #28a745, #1e7e34); color:#fff; border-radius:8px; text-decoration:none; font-weight:600;">
            🔐 Log In to Start Mining
          </a>
        </div>
      <?php endif; ?>

      <div class="slider-container" style="text-align:center; margin-top:30px;">
        <label for="cpuSlider">CPU Usage</label>
        <input type="range" id="cpuSlider" min="10" max="100" value="80" />
        <div class="note">Adjust mining throttle: lower % = less CPU usage</div>
      </div>

      <div id="hashrate" style="text-align:center; margin-top:20px; font-weight:bold;">Hashrate: 0 H/s | Total: 0 | Accepted: 0</div>
      <div id="minerStatus" style="text-align:center; margin-top:10px; font-weight:bold; color:#dc3545;">Status: OFF</div>
    </main>
    <?php
}
*/
function renderMinerScript() {
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

/**
 * Render the miner client HTML + JavaScript.
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

// --- Handle POST miner reward requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'miner_reward') {
    $email       = $_POST['userId'] ?? ''; // assuming userId is email
    $rewardType  = $_POST['reward_type'] ?? 'WorkToken';
    $accepted    = (int)($_POST['accepted'] ?? 0);
    $mac         = $_POST['mac_address'] ?? '';
    $active      = (int)($_POST['active'] ?? 0);

    handleMinerReward($email, $rewardType, $accepted, $mac, $active);
    exit('ok');
}

function logoutUser(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_unset();
    session_destroy();
    header('Location: /login.php');
    exit();
}

// Call with showAdvertPopup();

function showAdvertPopup() {

    $linkUrl = '/contact.php';

    $linkText = 'Click here to report en error/feature!';

    $delay = 3000;



    echo <<<HTML

    <div id="advertPopup" style="display:none; position:fixed; bottom:20px; right:20px; width:300px; background:#fff; border:1px solid #ccc; box-shadow:0 0 10px rgba(0,0,0,0.3); padding:15px; z-index:9999;">

        <span style="float:right; cursor:pointer;" onclick="document.getElementById('advertPopup').style.display='none';">✖</span>

        <strong>🔥 Contact:</strong><br>

        <a href="$linkUrl" target="_blank" style="color:#0077cc; text-decoration:underline;">

            $linkText

        </a>

    </div>

    <script>

        setTimeout(function() {

            if (document.getElementById('advertPopup')) document.getElementById('advertPopup').style.display = 'block';

        }, $delay);

    </script>

HTML;

}
// End of reusable2.php

// Reusable3.php
function renderCaptchaIfNeeded(): void {
    if (!isset($_COOKIE['captcha_solved'])) {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['captcha_input'])) {
            if (isset($_SESSION['captcha_code']) && $_POST['captcha_input'] === $_SESSION['captcha_code']) {
                setcookie("captcha_solved", "true", time() + 86400, "/");
                echo '<div class="message success" style="background-color:#e6f9e6; border:1px solid #4CAF50; color:#2e7d32; padding:1rem; border-radius:6px; margin:1rem auto; max-width:400px; text-align:center;">';
                echo '<p>✅ CAPTCHA solved. Redirecting…</p></div>';
                echo "<script>setTimeout(() => window.location.href = window.location.pathname, 1000);</script>";
                return;
            } else {
                echo '<div class="message error" style="background-color:#ffe6e6; border:1px solid #f44336; color:#b71c1c; padding:1rem; border-radius:6px; margin:1rem auto; max-width:400px; text-align:center;">';
                echo '<p>❌ Incorrect CAPTCHA. Please try again.</p></div>';
            }
        }

        // Generate CAPTCHA code
        $captcha_code = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);
        $_SESSION['captcha_code'] = $captcha_code;

        // Styled CAPTCHA form
        echo '<div class="container home-container">';
        echo '<div class="card" style="max-width: 420px; margin: 2rem auto; padding: 1.5rem;">';
        echo '<h2 class="page-title" style="color:#2e7d32;">🔐 CAPTCHA Verification capital letters only</h2>';
        echo '<form method="post" class="form-group" style="display:flex; flex-direction:column; gap:1rem;">';
        echo '<p class="captcha-code" style="font-size:2rem; font-weight:bold; letter-spacing:6px; background:#e8f5e9; color:#1b5e20; padding:0.5rem 1rem; border-radius:4px; text-align:center;">' . $captcha_code . '</p>';
        echo '<input type="text" name="captcha_input" placeholder="Enter the code above" required style="padding:0.5rem; border:1px solid #ccc; border-radius:4px;">';
        echo '<button type="submit" class="btn btn-success" style="background-color:#4CAF50; color:white; padding:0.6rem 1.2rem; border:none; border-radius:4px; cursor:pointer;">✅ Verify</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';

        include_footer(); // optional
        exit;
    }
}
//renderCaptchaIfNeeded();

// Existing functions below...

if (!function_exists('grant_mining_bonus')) {
    function grant_mining_bonus($email) {
        global $conn;
        if (!$conn || !$email) return;

        $stmt = $conn->prepare("SELECT tokens_earned, mintme FROM workers WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->bind_result($tokens_earned, $mintme);
            if ($stmt->fetch()) {
                $stmt->close();

                if ($tokens_earned >= 0 && $tokens_earned < 1) {
                    $stmt1 = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + 10 WHERE email = ?");
                    if ($stmt1) {
                        $stmt1->bind_param('s', $email);
                        $stmt1->execute();
                        $stmt1->close();
                    }
                }

                if ($mintme >= 0 && $mintme < 1) {
                    $stmt2 = $conn->prepare("UPDATE workers SET mintme = mintme + 10 WHERE email = ?");
                    if ($stmt2) {
                        $stmt2->bind_param('s', $email);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }
            } else {
                $stmt->close();
            }
        }
    }
}

function render_worktoken_dashboard() {
?>
<section class="token-dashboard">
  <!-- WorkToken Card -->
  <div class="token-card">
    <img src="/images/worktoken-logo.png" alt="WorkToken Logo" style="width:120px; height:auto;">
    <h2>WorkToken (WTK)</h2>
    <p>WorkToken is CfCbazar’s dynamic utility token...</p>
    <ul>
      <li><strong>Contract:</strong> <a href="https://bscscan.com/token/0xecbD4E86EE8583c8681E2eE2644FC778848B237D" target="_blank">0xecbD4E86EE8583c8681E2eE2644FC778848B237D</a></li>
      <li><strong>Decimals:</strong> 18</li>
      <li><strong>Trading:</strong> <a href="https://cc.free.bg/workth/" target="_blank">CfCbazar dapp</a></li>
    </ul>
    <button onclick="addTokenWTK()">Add WTK to MetaMask</button>
    <script>
      async function addTokenWTK() {
        try {
          await ethereum.request({
            method: 'wallet_watchAsset',
            params: {
              type: 'ERC20',
              options: {
                address: '0xecbD4E86EE8583c8681E2eE2644FC778848B237D',
                symbol: 'WTK',
                decimals: 18,
                image: 'https://cfcbazar.42web.io/images/worktoken-logo.png',
              },
            },
          });
        } catch (error) {
          console.error('MetaMask WTK integration failed:', error);
        }
      }
    </script>
  </div>

  <!-- WorkTHR Card -->
  <div class="token-card">
    <img src="/images/workthr-logo.png" alt="WorkTHR Logo" style="width:120px; height:auto;">
    <h2>WorkTHR (WTHR)</h2>
    <p>WorkTHR is CfCbazar’s fixed-supply token...</p>
    <ul>
      <li><strong>Contract:</strong> <a href="https://bscscan.com/token/0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00" target="_blank">0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00</a></li>
      <li><strong>Decimals:</strong> 18</li>
      <li><strong>Total Supply:</strong> 999,999,999 WTHR</li>
      <li><strong>Trading:</strong> <a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=BNB" target="_blank">PancakeSwap</a></li>
    </ul>
    <button onclick="addTokenWTHR()">Add WTHR to MetaMask</button>
    <script>
      async function addTokenWTHR() {
        try {
          await ethereum.request({
            method: 'wallet_watchAsset',
            params: {
              type: 'ERC20',
              options: {
                address: '0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00',
                symbol: 'WorkTHR',
                decimals: 18,
                image: 'https://cfcbazar.42web.io/images/workthr-logo.png',
              },
            },
          });
        } catch (error) {
          console.error('MetaMask WTHR integration failed:', error);
        }
      }
    </script>
  </div>
</section>
<?php
}

if (!function_exists('render_withdraw_link')) {
    function render_withdraw_link() {
        echo '<a href="/w.php" class="link-card" aria-label="Withdraw WorkTokens/WorkTHR">💸 <span>Withdraw WorkTokens/WorkTHR</span></a>';
    }
}

if (!function_exists('render_workthr_teaser')) {
    function render_workthr_teaser() {
        echo '<a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=BNB" class="link-card" target="_blank" rel="noopener">🥞 <span>Trade WorkTHR on PancakeSwap</span></a>';
    }
}

if (!function_exists('render_worktoken_teaser')) {
    function render_worktoken_teaser() {
        echo '<a href="https://cc.free.bg/workth/" class="link-card" aria-label="Trade WorkTokens on our DApp">🧠 <span>Trade WorkTokens on our DApp</span></a>';
    }
}
// End of reusable3.php
/**
 * Tracks a page visit in the `pages` table.
 * - Increments visits count
 * - Updates last_referrer
 * - Inserts new record if page doesn't exist
 * - Includes basic bot/poll/throttle protection
 *
 * Call this function early in pages you want to track:
 * require_once __DIR__ . '/includes/reusable.php';
 * trackPageVisit(url);
 */
function trackVisit(string $slug): void
{
    global $conn;

    // Only track GET page loads
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }

    // Normalize slug
    $slug = trim($slug);
    $slug = mb_substr($slug, 0, 255);

    if ($slug === '') {
        $slug = 'index.php';
    }

    // Human-readable title
    $title = ucfirst(str_replace(['.php', '_', '-'], ['', ' ', ' '], $slug));

    // Path must match your DB format exactly
    $path = '/' . $slug;

    // Insert or update
    $stmt = $conn->prepare("
        INSERT INTO pages (title, slug, path, status, visits, created_at, updated_at)
        VALUES (?, ?, ?, 'published', 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE visits = visits + 1, updated_at = NOW()
    ");
    $stmt->bind_param('sss', $title, $slug, $path);
    $stmt->execute();
    $stmt->close();
}

// Usage
/*
<?php
require_once __DIR__ . '/includes/reusable.php';

// Optional: skip tracking on very specific pages
// if (strpos($_SERVER['SCRIPT_NAME'], 'admin.php') !== false) {
//     // don't track admin
// } else {
    trackVisit($conn);
// }

include_header();
include_menu();
// ... rest of page ...
*/

// Get user status
/*
0 not logged in
1 admin
2 moderator
3 contributor
4 vip
5 standard
*/
function getUserStatus(string $email): int
{
    // Use global database connection
    global $conn;

    // Empty email → no status
    if (empty($email)) {
        return 0;
    }

    // Query user status
    $stmt = $conn->prepare("SELECT status FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($status);
    $found = $stmt->fetch();
    $stmt->close();

    // Return status or 0 if not found
    return $found ? (int)$status : 0;
}

// usage on pages
/*
require_once "includes/reusable.php";

$status = getUserStatus();

if ($status === 0) {
    die("❌ Not logged in.");
} elseif ($status === 1) {
    echo "✅ Welcome, Admin!";
} elseif ($status === 2) {
    echo "👮 Hello, Moderator.";
} elseif ($status === 3) {
    echo "🛠️ Contributor access granted.";
} elseif ($status === 4) {
    echo "🌟 VIP access.";
} elseif ($status === 5) {
    echo "👤 Standard user.";
}
*/

// set cookie url
function setReturnUrlCookie(string $path, int $expireSeconds = 300): void {
    // Validate path: must be a local PHP file
    if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $path)) {
        setcookie('return_url', urlencode($path), time() + $expireSeconds, '/', '', false, true);
    }
}
/* usage 
require_once 'includes/reusable.php';

setReturnUrlCookie('/dashboard.php'); // sets cookie for 5 minutes
*/

// redirect to return_url cookie url if valid
function redirectToReturnUrl(string $fallback = '/index.php'): void {
    if (isset($_COOKIE['return_url'])) {
        $returnPath = urldecode($_COOKIE['return_url']);

        // Validate again to ensure it's a safe local PHP path
        if (preg_match('/^\/[a-zA-Z0-9\/._-]+\.php$/', $returnPath)) {
            // Clear the cookie after use
            setcookie('return_url', '', time() - 3600, '/', '', false, true);

            // Redirect
            header("Location: $returnPath");
            exit;
        }
    }

    // Fallback redirect
    header("Location: $fallback");
    exit;
}
/* usage
require_once 'includes/reusable.php';

// After successful login
redirectToReturnUrl(); // Redirects to cookie path or /default.php
*/

// Display pancake swap price
function render_token_price_tracker() {
  echo <<<'HTML'
  <style>
    .token-tracker-container {
      font-family: Arial, sans-serif;
      background: #f9f9f9;
      padding: 20px;
      text-align: center;
      max-width: 600px;
      margin: 0 auto;
    }
    .token-tracker-container h2 {
      color: #333;
      font-size: 1.6em;
      margin-bottom: 20px;
    }
    .price-box {
      margin: 10px auto;
      padding: 16px;
      font-size: 1.2em;
      font-weight: bold;
      color: #28a745;
      background: #fff;
      border: 2px solid #28a745;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      word-wrap: break-word;
      overflow-wrap: break-word;
      max-width: 90%;
      transition: box-shadow 0.3s ease;
    }
    .price-box:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .price-box.error {
      color: #cc0000;
      border-color: #cc0000;
    }
    @media screen and (max-width: 480px) {
      .token-tracker-container h2 {
        font-size: 1.3em;
      }
      .price-box {
        font-size: 1em;
        padding: 12px;
      }
    }
  </style>

  <div class="token-tracker-container">
    <h2>📈 Live Token Prices</h2>
    <div id="workthr-price" class="price-box">Loading WorkTHR → USDT...</div>
    <div id="wtk-price" class="price-box">Loading WTK → WorkTHR...</div>
  </div>

  <script type="module">
    async function trackTokenPrice(path, labelId, symbol, targetSymbol) {
      try {
        const { ethers } = await import('https://cdn.jsdelivr.net/npm/ethers@6.8.0/+esm');
        const provider = new ethers.JsonRpcProvider('https://bsc-dataseed.binance.org/');
        const router = new ethers.Contract(
          '0x10ED43C718714eb63d5aA57B78B54704E256024E',
          ['function getAmountsOut(uint amountIn, address[] calldata path) external view returns (uint[] memory amounts)'],
          provider
        );
        const inputAmount = ethers.parseUnits('1', 18);
        const amounts = await router.getAmountsOut(inputAmount, path);
        const price = ethers.formatUnits(amounts[amounts.length - 1], 18);
        const el = document.getElementById(labelId);
        el.textContent = `1 ${symbol} ≈ ${price} ${targetSymbol}`;
        el.classList.remove('error');
      } catch (err) {
        console.error(`${symbol} price fetch error:`, err);
        const el = document.getElementById(labelId);
        el.textContent = `Error fetching ${symbol} price`;
        el.classList.add('error');
      }
    }

    function refreshPrices() {
      trackTokenPrice(
        ['0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00', '0x55d398326f99059fF775485246999027B3197955'],
        'workthr-price',
        'WorkTHR',
        'USDT'
      );
      trackTokenPrice(
        ['0xecbD4E86EE8583c8681E2eE2644FC778848B237D', '0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00'],
        'wtk-price',
        'WTK',
        'WorkTHR'
      );
    }

    refreshPrices();
    setInterval(refreshPrices, 86400000);
  </script>
HTML;
}
/* usage
include 'includes/reusable.php';
render_token_price_tracker();
*/
// End Display pancake swap price

// Disable registration, enable maintenance
function checkSystemFlags(mysqli $conn) {
    $result = $conn->query("SELECT maintenance, disable_registration FROM settings WHERE id = 1 LIMIT 1");

    if (!$result || $result->num_rows === 0) {
        die("System configuration error.");
    }

    $settings = $result->fetch_assoc();

    if ((int)$settings['maintenance'] === 1) {
        header("HTTP/1.1 503 Service Unavailable");
        echo "<h1>Site Under Maintenance</h1><p>Please check back later.</p>";
        exit;
    }

    if ((int)$settings['disable_registration'] === 1) {
        echo "<p style='color:red;'>Registration is disabled by administrator.</p>";
        return false;
    }

    return true;
}
// Disable registration, enable maintenance end
/* Usage:
// $conn is your mysqli connection
require_once "includes/reusable.php"; 

if (!checkSystemFlags($conn)) {
    // Stop registration flow
    exit;
}

// Proceed with registration logic...
*/

// Redirect to main server
function redirectToCfCbazar42web() {
    header("Location: https://cfcbazar.22web.io");
    exit;
}
//redirectToCfCbazar42web();

function show_disabled_message(string $reason = 'maintenance'): void {
    include_header();
    include_menu();

    echo '<div class="container">';
    echo '<div class="disabled-message card" style="padding:2em; background:#fff3f3; border:1px solid #f5c2c2; border-radius:8px;">';
    echo '<h2>🚫 This Page Is Disabled</h2>';
    echo '<p>We’ve temporarily disabled this page due to <strong>' . htmlspecialchars($reason) . '</strong>.</p>';
    echo '<p>For more, please visit our <a href="/news.php" target="_blank">News Center</a>.</p>';
    echo '</div>';
    echo '</div>';

    include_footer();
    exit;
}
/* usage
show_disabled_message();
*/

// ===============================
// STAKING SYSTEM HELPERS
// ===============================

function toggleStaking(mysqli $conn, string $wallet, string $action): string {
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
        return "Invalid wallet address.";
    }

    $stmt = $conn->prepare("SELECT address FROM workers WHERE address = ?");
    $stmt->bind_param("s", $wallet);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return "Wallet not found.";

    if ($action === 'start') {
        $stmt = $conn->prepare("UPDATE workers SET stake_active = 1, stake_timestamp = NOW() WHERE address = ?");
    } elseif ($action === 'stop') {
        $stmt = $conn->prepare("UPDATE workers SET stake_active = 0 WHERE address = ?");
    } else {
        return "Invalid action.";
    }

    $stmt->bind_param("s", $wallet);
    $stmt->execute();
    return $action === 'start' ? "✅ Staking started." : "🛑 Staking stopped.";
}


function getWorkTokenStatus(mysqli $conn, string $wallet, string $selectedToken = 'WTK'): string {
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
        return "Invalid wallet address.";
    }

    $stmt = $conn->prepare("SELECT tokens_earned, mintme, last_ping, stake_active, stake_timestamp FROM workers WHERE address = ?");
    $stmt->bind_param("s", $wallet);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return "Wallet not found.";

    $user = $result->fetch_assoc();
    $now = time();

    // Validate last_ping
    $isMinerRunning = false;
    if (!empty($user['last_ping']) && is_string($user['last_ping'])) {
        $lastPing = strtotime($user['last_ping']);
        if ($lastPing !== false) {
            $isMinerRunning = ($now - $lastPing) < 120;
        }
    }

    // Determine balance field
    $balanceField = ($selectedToken === 'WTK') ? 'tokens_earned' : 'mintme';
    $balance = floatval($user[$balanceField]);
    $bonus = 0;

    // Validate staking conditions
    if (
        $user['stake_active'] &&
        $isMinerRunning &&
        !empty($user['stake_timestamp']) &&
        is_string($user['stake_timestamp'])
    ) {
        $lastStake = strtotime($user['stake_timestamp']);
        if ($lastStake !== false) {
            $minutesStaked = ($now - $lastStake) / 60;
            if ($minutesStaked >= 1) {
                $apr = 0.01;
                $bonus = $balance * ($apr / 525600) * $minutesStaked;
                $newBalance = $balance + $bonus;

                $update = $conn->prepare("UPDATE workers SET $balanceField = ?, stake_timestamp = ? WHERE address = ?");
                $update->bind_param("dss", $newBalance, date("Y-m-d H:i:s", $now), $wallet);
                $update->execute();

                $balance = $newBalance;

                // Optional: log staking reward
                // $log = $conn->prepare("INSERT INTO staking_history (wallet, token, bonus, timestamp) VALUES (?, ?, ?, NOW())");
                // $log->bind_param("ssd", $wallet, $selectedToken, $bonus);
                // $log->execute();
            }
        }
    }

    return sprintf(
        "Wallet: %s\nToken: %s\nBalance: %.6f%s",
        $wallet,
        $selectedToken,
        $balance,
        ($bonus > 0) ? " (includes staking bonus of +".number_format($bonus, 6).")" : ""
    );
}

function getStakingStatus(mysqli $conn, string $wallet): string {
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
        return "Invalid wallet address.";
    }

    $stmt = $conn->prepare("SELECT stake_active, stake_timestamp FROM workers WHERE address = ?");
    $stmt->bind_param("s", $wallet);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) return "Wallet not found.";

    $data = $result->fetch_assoc();
    $isActive = (int)$data['stake_active'] === 1;
    $timestamp = $data['stake_timestamp'] ?? null;

    if ($isActive && $timestamp) {
        return "✅ Staking is active since " . htmlspecialchars($timestamp);
    } elseif ($isActive) {
        return "✅ Staking is active (no timestamp recorded)";
    } else {
        return "❌ Staking is not active.";
    }
}


function displayServerStatus(): void {
    $servers = [
        'cfcbazar.42web.io',
        'cfcbazar.22web.org',
        'cfcbazar.ct.ws',
        'cfcbazar.iceiy.com'
    ];

    echo '<ul style="list-style:none;padding:0;">';

    foreach ($servers as $server) {
        $url = "https://$server";
        $isOnline = @fsockopen($server, 443, $errno, $errstr, 2) ? 'Online' : 'Offline';
        $color = $isOnline === 'Online' ? 'green' : 'red';

        echo "<li style='margin:8px 0;'>
                🔗 <a href='$url' target='_blank'>$server</a> 
                <span style='color:$color;font-weight:bold;'>$isOnline</span>
              </li>";
    }

    echo '</ul>';
}
// usage
// displayServerStatus();

function render_top_userbar() {
    global $conn;

    // Default values
    $email = 'none';
    $wtk = 0;
    $thr = 0;
    $statusLabel = 'Guest';

    if (isset($_SESSION['user_id'])) {

        $uid = (int)$_SESSION['user_id'];

        // Get email
        $stmt1 = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");

        if ($stmt1) {
            $stmt1->bind_param("i", $uid);
            $stmt1->execute();
            $res1 = $stmt1->get_result();

            if ($res1 && $res1->num_rows > 0) {

                $user = $res1->fetch_assoc();
                $email = $user['email'];

                // Get worker totals
                $stmt2 = $conn->prepare("
                    SELECT
                        COALESCE(SUM(tokens_earned), 0) AS wtk,
                        COALESCE(SUM(mintme), 0) AS thr
                    FROM workers
                    WHERE email = ?
                ");

                if ($stmt2) {
                    $stmt2->bind_param("s", $email);
                    $stmt2->execute();
                    $res2 = $stmt2->get_result();

                    if ($res2 && $res2->num_rows > 0) {
                        $data = $res2->fetch_assoc();
                        $wtk = (float)($data['wtk'] ?? 0);
                        $thr = (float)($data['thr'] ?? 0);
                    }

                    $stmt2->close();
                }

                // User status
                $status = getUserStatus($email);

                switch ($status) {
                    case 1:
                        $statusLabel = "Admin";
                        break;
                    case 2:
                        $statusLabel = "Moderator";
                        break;
                    case 3:
                        $statusLabel = "Contributor";
                        break;
                    case 4:
                        $statusLabel = "VIP";
                        break;
                    case 5:
                        $statusLabel = "User";
                        break;
                    default:
                        $statusLabel = "Guest";
                        break;
                }
            }

            $stmt1->close();
        }
    }

    echo '
    <div style="
        position:fixed;
        top:75px;
        left:0;
        right:0;
        background:#28a745;
        color:#fff;
        padding:8px 12px;
        z-index:100;
        border-bottom:1px solid rgba(0,0,0,.15);
        box-sizing:border-box;
        font-size:14px;
    ">

        <div style="
            display:flex;
            flex-direction:column;
            gap:6px;
        ">

<div style="overflow-wrap:anywhere;">
    <div>
        <strong>Email:</strong> '.htmlspecialchars($email, ENT_QUOTES, 'UTF-8').' |
        <strong>User:</strong> '.$statusLabel.'
    </div>

    <div>
        <strong>WTK:</strong> '.number_format($wtk, 2).' |
        <strong>WorkTHR:</strong> '.number_format($thr, 2).'
    </div>
</div>

            <div>
                <button
                    onclick="window.location.href=\'/miner/\'"
                    style="
                        padding:8px 14px;
                        cursor:pointer;
                    "
                >
                    Miner
                </button>
            </div>

        </div>

    </div>';
}
/* Usage
<?php
require_once __DIR__ . '/includes/reusable.php';
render_top_userbar();
?>
*/

function cfc_footer($githubUrl, $toolName = "Source Code") {
    echo '
    <footer class="footer">
        <div class="card" style="padding:25px; margin-top:40px; text-align:center;">
            <h3 style="font-size:1.8rem; margin-bottom:15px;">GitHub</h3>
            <p style="font-size:1.2rem; margin-bottom:20px;">
                View the full source code for this page on GitHub.
            </p>
            <a href="' . htmlspecialchars($githubUrl) . '" 
               target="_blank" 
               style="
                    display:inline-block;
                    padding:15px 25px;
                    font-size:1.3rem;
                    font-weight:bold;
                    background:linear-gradient(135deg,#28a745,#1e7e34);
                    color:#fff;
                    border-radius:10px;
                    text-decoration:none;
               ">
                ' . htmlspecialchars($toolName) . ' →
            </a>
        </div>
    </footer>
    ';
}
//Usage
/* cfc_footer(
    "https://github.com/YOUR_USERNAME/YOUR_REPO",
    "Message Tool Source Code"
);*/

/**
 * Checks whether a user is logged in.
 *
 * Retrieves the user's email from the active session.
 *
 * @param string|null $email Receives the logged-in email address.
 * @return bool True if logged in, otherwise false.
 */
function is_logged_in(?string &$email): bool
{
    session_check();

    $email = $_SESSION['email'] ?? null;

    return $email !== null;
}

function session_check(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Returns the current CSRF token.
 * Creates one if it doesn't already exist.
 *
 * @return string
 */
function csrf_token(): string
{
    session_check();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Logs out the current user.
 *
 * If the URL contains ?logout, the session is destroyed
 * and the user is redirected to the login page.
 *
 * @return void
 */
function logout_user(): void
{
    session_check();

    if (!isset($_GET['logout'])) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: login.php');
    exit;
}

/**
 * Loads the dashboard data for the logged-in user.
 *
 * @param string $email
 * @param string $current_address Receives the wallet address.
 * @param float  $mintme_balance  Receives the WorkTHR balance.
 *
 * @return bool True on success, false if the worker record was not found.
 */
function load_dashboard_data(
    string $email,
    string &$current_address,
    float &$mintme_balance
): bool
{
    global $conn;

    $current_address = "";
    $mintme_balance = 0;

    $stmt = $conn->prepare("
        SELECT address, mintme
        FROM workers
        WHERE email = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($current_address, $mintme_balance);

    $found = $stmt->fetch();

    $stmt->close();

    if (!$found) {
        $current_address = "";
        $mintme_balance = 0;
        return false;
    }

    return true;
}

/**
 * Saves the user's wallet address.
 *
 * @param string $email
 * @param string $current_address Receives the updated wallet address.
 *
 * @return string Status message. Empty string if no save was attempted.
 */
function save_wallet(
    string $email,
    string &$current_address
): string
{
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['wallet_address'])) {
        return "";
    }

    if (!hash_equals(csrf_token(), $_POST['csrf_token'] ?? '')) {
        return "Security error.";
    }

    $wallet_address = trim($_POST['wallet_address']);

    // Validate Ethereum/BSC address
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
        return "Invalid wallet address.";
    }

    $stmt = $conn->prepare("
        UPDATE workers
        SET address = ?
        WHERE email = ?
    ");

    if (!$stmt) {
        return "Database error.";
    }

    $stmt->bind_param("ss", $wallet_address, $email);

    if (!$stmt->execute()) {
        $stmt->close();
        return "Database error.";
    }

    $stmt->close();

    $current_address = $wallet_address;

    return "Wallet saved!";
}

/**
 * Purchases VIP status for the current user.
 *
 * @param string $email
 * @param float  $mintme_balance Receives the updated WorkTHR balance.
 * @param int    $status          Receives the updated user status.
 *
 * @return string Status message. Empty string if no purchase was attempted.
 */
function buy_vip(
    string $email,
    float &$mintme_balance,
    int &$status
): string
{
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['vip_buy'])) {
        return "";
    }

    if (!hash_equals(csrf_token(), $_POST['csrf_token'] ?? '')) {
        return "Security error.";
    }

    $conn->begin_transaction();

    try {

        // Lock user row
        $stmt = $conn->prepare("
            SELECT status
            FROM users
            WHERE email = ?
            FOR UPDATE
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($db_status);
        $stmt->fetch();
        $stmt->close();

        if ($db_status != 5) {
            throw new Exception("Already VIP or not eligible.");
        }

        // Lock worker balance
        $stmt = $conn->prepare("
            SELECT mintme
            FROM workers
            WHERE email = ?
            FOR UPDATE
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();
        $stmt->close();

        if ($balance === null) {
            throw new Exception("Worker account not found.");
        }

        if ($balance < 10) {
            throw new Exception("Not enough WorkTHR.");
        }

        // Deduct WorkTHR
        $stmt = $conn->prepare("
            UPDATE workers
            SET mintme = mintme - 10
            WHERE email = ?
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();

        // Upgrade user
        $stmt = $conn->prepare("
            UPDATE users
            SET status = 4
            WHERE email = ?
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        // Update page variables
        $mintme_balance = $balance - 10;
        $status = 4;

        return "VIP upgrade successful!";

    } catch (Exception $e) {

        $conn->rollback();

        return $e->getMessage();
    }
}

/**
 * Grants the mining bonus once every 60 seconds.
 *
 * @param string $email
 * @return void
 */
function grant_dashboard_bonus(string $email): void
{
    session_check();

    if (
        !isset($_SESSION['last_bonus_run']) ||
        (time() - $_SESSION['last_bonus_run']) > 60
    ) {

        require_once __DIR__ . '/../testapi.php';

        if (function_exists('grant_mining_bonus')) {
            grant_mining_bonus($email);
        }

        $_SESSION['last_bonus_run'] = time();
    }
}

function close_database(): void
{
    global $conn;

    if ($conn instanceof mysqli) {
        $conn->close();
        $conn = null;
    }
}


// -------------------------------
// DATABASE SAFETY CHECK
// -------------------------------
/**
 * Ensure the global database connection is available.
 */
function require_database_connection(): void
{
    global $conn;

    if ($conn instanceof mysqli && $conn->connect_errno === 0) {
        return;
    }

    http_response_code(500);

    die('
        <div style="
            color:#faa;
            background:#400;
            padding:20px;
            border-radius:8px;
            width:350px;
            margin:40px auto;
            text-align:center;
            font-family:Arial,sans-serif;
        ">
            <strong>Database Error</strong><br>
            Could not connect to the database.<br>
            Please check your configuration.
        </div>
    ');
}

// Rewards WorkTHR
function reward_miner_workthr($email, $shares)
{
    global $conn;

    if ($shares <= 0) {
        return false;
    }

    $reward_per_share = 0.01;

    $reward = $shares * $reward_per_share;


    $stmt = $conn->prepare(
        "UPDATE workers 
         SET mintme = mintme + ?
         WHERE email = ?"
    );

    $stmt->bind_param(
        "ds",
        $reward,
        $email
    );

    return $stmt->execute();
}

?>
