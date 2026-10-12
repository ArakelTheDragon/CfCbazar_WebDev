<?php
// orpg.php — TokenQuest ORPG main page

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Safe include for reusable.php and its functions
$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;

    if (function_exists('trackVisit')) {
        trackVisit("games-wtk");
    }

    // Set return URL for login redirect
    if (function_exists('setReturnUrlCookie')) {
        setReturnUrlCookie("/orpg.php", 300);
    }

    // Optional redirectToReturnUrl (currently disabled)
    if (function_exists('redirectToReturnUrl')) {
        // redirectToReturnUrl('/orpg.php');
    }

    // Check if captcha was passed in 24 hours
    if (function_exists('renderCaptchaIfNeeded')) {
        renderCaptchaIfNeeded();
    }

    // Render header
    if (function_exists('include_header')) {
        include_header();
    }

    // Render menu
    if (function_exists('include_menu')) {
        include_menu();
    }
} else {
    echo "reusable.php not found";
}

// 1) Require login
if (!isset($_SESSION['email'])) {
    $return_url = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /login.php?return_url={$return_url}");
    exit;
}

// normalize email to lower-case for DB lookups
$email = strtolower($_SESSION['email'] ?? '');

// 2) CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3) Math question generator
function genQuestion() {
    $nums = [rand(1,10), rand(1,10), rand(1,10), rand(1,10)];
    $ops  = ['+','-','*','/'];
    $op1  = $ops[array_rand($ops)];
    $op2  = $ops[array_rand($ops)];
    $op3  = $ops[array_rand($ops)];
    if ($op3 === '/' && $nums[3] === 0) $nums[3] = 1;
    $expr = "{$nums[0]}{$op1}{$nums[1]}{$op2}{$nums[2]}{$op3}{$nums[3]}";
    $ans  = eval("return $expr;"); // controlled inputs
    return ['expr'=>$expr,'answer'=>round($ans,2)];
}
if (!isset($_SESSION['question'])) $_SESSION['question'] = genQuestion();

// 4) Rate limiting (per IP)
$ip = $_SERVER['REMOTE_ADDR'];
$rate_file = __DIR__ . "/rate_limit_{$ip}.json";
$rate_limit_max = 10;
$rate_limit_window = 60;
$rate_data = file_exists($rate_file)
    ? json_decode(file_get_contents($rate_file), true)
    : ['count'=>0,'time'=>time()];
if (time() - ($rate_data['time'] ?? 0) > $rate_limit_window) {
    $rate_data = ['count'=>0,'time'=>time()];
}

$message = "";

// 5) Handle submission and cheats
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($rate_data['count'] ?? 0) >= $rate_limit_max) {
        $message = "Rate limit exceeded. Try again later.";
    } else {
        $rate_data['count'] = ($rate_data['count'] ?? 0) + 1;
        $rate_data['time'] = time();
        file_put_contents($rate_file, json_encode($rate_data));

        if (isset($_POST['answer'], $_POST['csrf_token']) &&
            hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {

            $input_raw = $_POST['answer'];
            $input = round((float)$input_raw, 2);

            // fetch user snapshot
            $user = getWorkerStats($email);
            if (empty($user)) {
                // if no worker row exists, create seed row
                $ins = $conn->prepare("INSERT INTO workers (email, tokens_earned, exp, level) VALUES (?, 0, 0, 1)");
                $ins->bind_param('s', $email);
                $ins->execute();
                $ins->close();
                $user = getWorkerStats($email);
            }

            $tokenReward   = 0.00001;
            $correctAnswer = $_SESSION['question']['answer'];

            // Cheat codes (numeric):
            // 9879 -> +99 to all gear
            // 9878 -> +99 levels
            // 9877 -> +990 XP
            // 1337 -> +1 WT
            // 4242 -> +100 XP
            // 7777 -> +10 random gear
            if ($input === 9879.00) {
                upgradeAllGear($email, 99);
                $message = "Cheat 9879: +99 applied to all gear.";

            } elseif ($input === 9878.00) {
                $stmt = $conn->prepare("UPDATE workers SET level = COALESCE(level,1) + 99 WHERE email = ?");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $stmt->close();
                $message = "Cheat 9878: +99 levels applied.";

            } elseif ($input === 9877.00) {
                addExp($email, 990);
                checkLevelUp($email);
                $message = "Cheat 9877: +990 XP applied.";

            } elseif ($input === 1337.00) {
                addTokens($email, 1.0);
                $message = "Cheat 1337: +1.0 WT.";

            } elseif ($input === 4242.00) {
                addExp($email, 100);
                checkLevelUp($email);
                $message = "Cheat 4242: +100 XP.";

            } elseif ($input === 7777.00) {
                upgradeRandomGear($email, 10);
                $message = "Cheat 7777: +10 to a random gear slot.";

            } else {
                // normal answer processing
                $isCorrect = abs($input - $correctAnswer) < 0.01;
                if ($isCorrect) {
                    addTokens($email, $tokenReward);
                    addExp($email, 10);
                    checkLevelUp($email);
                    upgradeRandomGear($email, rand(1,5));
                    $message = sprintf("✅ Correct! +10 XP, +%.5f WT.", $tokenReward);
                } else {
                    $currentTokens = (float)($user['tokens_earned'] ?? 0.0);
                    $newTokens     = max(0, $currentTokens - $tokenReward);
                    $stmt = $conn->prepare("UPDATE workers SET tokens_earned = ? WHERE email = ?");
                    $stmt->bind_param('ds', $newTokens, $email);
                    $stmt->execute();
                    $stmt->close();
                    $message = "❌ Wrong answer. -{$tokenReward} WT.";
                }
            }

            // refresh question & csrf
            $_SESSION['question'] = genQuestion();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

// 6) Quests & achievements (sync + auto-claim)
$sync        = syncQuestsAchievementsAndRewards($email);
$quests      = $sync['quests'] ?? [];
$achievements = $sync['achievements'] ?? [];

// 7) Reload player stats after updates
$user   = getWorkerStats($email);
$tokens = (float)($user['tokens_earned'] ?? 0.0);
$xp     = (int)($user['exp'] ?? 0);
$level  = (int)($user['level'] ?? 1);
$gear   = [
    'helmet'        => $user['helmet'] ?? '',
    'armour'        => $user['armour'] ?? '',
    'weapon'        => $user['weapon'] ?? '',
    'second_weapon' => $user['second_weapon'] ?? '',
    'pants'         => $user['pants'] ?? '',
    'boots'         => $user['boots'] ?? '',
    'gloves'        => $user['gloves'] ?? ''
];
?>

<style>
html {
    overflow-anchor: none;
}

/* ORPG Layout */
.orpg-wrapper {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px 16px 30px;
}

.orpg-title {
    text-align: center;
    font-size: 2rem;
    margin-bottom: 20px;
}

/* Card */
.orpg-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 20px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
}

/* Stats Grid */
.orpg-stats-grid {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.orpg-stat {
    text-align: center;
    flex: 1 1 30%;
}

.orpg-stat .label {
    display: block;
    font-size: 0.9rem;
    color: #666;
}

.orpg-stat .value {
    font-size: 1.3rem;
    font-weight: bold;
    margin-top: 4px;
}

/* Gear */
.gear-title {
    margin-top: 15px;
    font-size: 1.2rem;
}

.gear-list {
    list-style: none;
    padding: 0;
    margin-top: 10px;
}

.gear-list li {
    padding: 6px 0;
    border-bottom: 1px solid #eee;
}

/* Messages */
.orpg-message {
    padding: 12px;
    border-radius: 10px;
    margin: 0 0 20px;
    text-align: center;
    font-weight: bold;
}

.orpg-message.success {
    background: #e6ffe6;
    color: #0a8a0a;
}

.orpg-message.error {
    background: #ffe6e6;
    color: #b30000;
}

/* Challenge */
.challenge-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.challenge-question {
    text-align: center;
    font-size: 1.3rem;
}

.challenge-input {
    padding: 10px;
    font-size: 1.1rem;
    border-radius: 8px;
    border: 1px solid #ccc;
    width: 100%;
    box-sizing: border-box;
}

.challenge-btn {
    padding: 12px;
    font-size: 1.1rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.challenge-btn:hover {
    background: #005fcc;
}

.cheat-hint {
    font-size: 0.85rem;
    color: #777;
    margin-top: 10px;
}

/* Lists */
.quest-list,
.achievement-list {
    list-style: none;
    padding: 0;
    margin: 8px 0 0;
}

.quest-list li,
.achievement-list li {
    padding: 6px 0;
    border-bottom: 1px solid #eee;
}

/* Mobile */
@media (max-width: 480px) {
    .orpg-title {
        font-size: 1.7rem;
    }

    .orpg-card {
        padding: 16px;
    }

    .challenge-input {
        font-size: 1rem;
    }

    .challenge-btn {
        font-size: 1rem;
    }
}
</style>

<main class="orpg-wrapper">
    <h1 class="orpg-title">🎲 TokenQuest ORPG</h1>

    <!-- PLAYER STATS -->
    <section class="orpg-card">
        <h2>👤 Player Stats</h2>

        <div class="orpg-stats-grid">
            <div class="orpg-stat">
                <span class="label">Level</span>
                <span class="value"><?= htmlspecialchars($level) ?></span>
            </div>
            <div class="orpg-stat">
                <span class="label">XP</span>
                <span class="value"><?= htmlspecialchars($xp) ?></span>
            </div>
            <div class="orpg-stat">
                <span class="label">Tokens</span>
                <span class="value"><?= htmlspecialchars(number_format($tokens, 8)) ?> WT</span>
            </div>
        </div>

        <h3 class="gear-title">🎒 Gear</h3>
        <ul class="gear-list">
            <?php foreach ($gear as $slot => $item): ?>
                <li>
                    <strong><?= htmlspecialchars(ucwords(str_replace('_',' ',$slot))) ?>:</strong>
                    <?= htmlspecialchars($item ?: '—') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- MESSAGE -->
    <?php if (!empty($message)): ?>
        <div class="orpg-message <?= strpos($message,'✅') === 0 ? 'success' : 'error' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- CHALLENGE -->
    <section class="orpg-card">
        <h2>🧮 Solve the Challenge</h2>

        <form method="post" autocomplete="off" class="challenge-form" style="scroll-margin-top:0;">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <p class="challenge-question">
                <strong><?= htmlspecialchars($_SESSION['question']['expr']) ?> = ?</strong>
            </p>

            <input type="number" step="0.01" name="answer" required class="challenge-input">

            <button type="submit" class="challenge-btn">Submit</button>
        </form>

        <p class="cheat-hint">
            Cheat codes are 4 digits. Guessing them gives gear boosts, XP, levels, and more.
        </p>
    </section>

    <!-- QUESTS -->
    <section class="orpg-card">
        <h2>📜 Active Quests</h2>
        <?php if (!empty($quests)): ?>
            <ul class="quest-list">
                <?php foreach ($quests as $q): ?>
                    <li><?= htmlspecialchars($q) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No active quests.</p>
        <?php endif; ?>
    </section>

    <!-- ACHIEVEMENTS -->
    <section class="orpg-card">
        <h2>🏆 Achievements</h2>
        <?php if (!empty($achievements)): ?>
            <ul class="achievement-list">
                <?php foreach ($achievements as $a): ?>
                    <li><?= htmlspecialchars($a) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No achievements yet.</p>
        <?php endif; ?>
    </section>
</main>

<?php
if (function_exists('include_footer')) {
    include_footer();
}
?>

