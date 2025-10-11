<?php
// orpg.php — TokenQuest ORPG main page
require 'includes/reusable.php';

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
    $ans  = eval("return $expr;"); // safe: controlled inputs
    return ['expr'=>$expr,'answer'=>round($ans,2)];
}
if (!isset($_SESSION['question'])) $_SESSION['question'] = genQuestion();

// 4) Rate limiting (per IP)
$ip = $_SERVER['REMOTE_ADDR'];
$rate_file = __DIR__ . "/rate_limit_{$ip}.json";
$rate_limit_max = 10;
$rate_limit_window = 60;
$rate_data = file_exists($rate_file) ? json_decode(file_get_contents($rate_file), true) : ['count'=>0,'time'=>time()];
if (time() - ($rate_data['time'] ?? 0) > $rate_limit_window) $rate_data = ['count'=>0,'time'=>time()];

$message = "";

// 5) Handle submission and cheats
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($rate_data['count'] ?? 0) >= $rate_limit_max) {
        $message = "Rate limit exceeded. Try again later.";
    } else {
        $rate_data['count'] = ($rate_data['count'] ?? 0) + 1;
        $rate_data['time'] = time();
        file_put_contents($rate_file, json_encode($rate_data));

        if (isset($_POST['answer'], $_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $input_raw = $_POST['answer'];
            // ensure number
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

            $tokenReward = 0.00001;
            $correctAnswer = $_SESSION['question']['answer'];

            // === Cheat codes (all numeric):
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
                // bump level by 99
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
                    // award small token + xp + random gear boost
                    addTokens($email, $tokenReward);
                    addExp($email, 10);
                    checkLevelUp($email);
                    upgradeRandomGear($email, rand(1,5));
                    $message = sprintf("✅ Correct! +10 XP, +%.5f WT.", $tokenReward);
                } else {
                    // penalty: subtract tokenReward safely
                    $currentTokens = (float)($user['tokens_earned'] ?? 0.0);
                    $newTokens = max(0, $currentTokens - $tokenReward);
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
$sync = syncQuestsAchievementsAndRewards($email);
$quests = $sync['quests'] ?? [];
$achievements = $sync['achievements'] ?? [];

// 7) Reload player stats after updates
$user = getWorkerStats($email);
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

// Render
include_header();
include_menu();
?>

<main class="container">
    <h1>🎲 TokenQuest ORPG</h1>

    <section class="card">
        <h2>👤 Player Stats</h2>
        <p><strong>Level:</strong> <?= htmlspecialchars($level) ?></p>
        <p><strong>XP:</strong> <?= htmlspecialchars($xp) ?></p>
        <p><strong>Tokens:</strong> <?= htmlspecialchars(number_format($tokens, 8)) ?> WT</p>

        <ul>
            <?php foreach ($gear as $slot => $item): ?>
                <li><strong><?= htmlspecialchars(ucwords(str_replace('_',' ',$slot))) ?>:</strong> <?= htmlspecialchars($item ?: '—') ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if (!empty($message)): ?>
        <div class="<?= strpos($message,'✅') === 0 ? 'success' : 'error' ?>"><?= $message ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>🧮 Solve the Challenge</h2>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <p><strong><?= htmlspecialchars($_SESSION['question']['expr']) ?> = ?</strong></p>
            <input type="number" step="0.01" name="answer" required>
            <button type="submit">Submit</button>
        </form>
        <p style="font-size:.9rem;color:#666">Cheat codes are a 4 digit code, if you guess it you can get +99 all gear, +99 level, +990xp and other rewards.</p>
    </section>

    <section class="card">
        <h2>📜 Active Quests</h2>
        <?php if (!empty($quests)): ?>
            <ul>
                <?php foreach ($quests as $q): ?><li><?= htmlspecialchars($q) ?></li><?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No active quests.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>🏆 Achievements</h2>
        <?php if (!empty($achievements)): ?>
            <ul>
                <?php foreach ($achievements as $a): ?><li><?= htmlspecialchars($a) ?></li><?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No achievements yet.</p>
        <?php endif; ?>
    </section>
</main>

<?php include_footer(); ?>