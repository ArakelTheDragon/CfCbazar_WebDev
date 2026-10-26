<?php
// compose_add.php — Add facts/pairs to data.jsonl and retrain memory
declare(strict_types=1);
require_once __DIR__ . '/compose.php';

const DATA_FILE      = __DIR__ . '/data.jsonl';
const MEMORY_DIR     = __DIR__ . '/memory';
const ADMIN_PASSWORD = 'changeme123';   // ← change this

session_start();
$authed = $_SESSION['authed'] ?? false;

if (isset($_POST['password'])) {
    if (hash_equals(ADMIN_PASSWORD, $_POST['password'])) {
        $_SESSION['authed'] = true;
        $authed = true;
    } else {
        $errors[] = 'Wrong password.';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: compose_add.php');
    exit;
}

$messages = [];
$errors   = [];

// ---------- recursive delete ----------
function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*') as $f) {
        is_dir($f) ? rrmdir($f) : unlink($f);
    }
    rmdir($dir);
}

// ---------- Handle form ----------
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk'])) {
    $bulk = trim($_POST['bulk']);
    $rebuild = !empty($_POST['rebuild']);

    if ($bulk === '' && !$rebuild) {
        $errors[] = 'Input was empty.';
    } else {
        // Parse submitted lines
        $newLines = [];
        $malformed = 0;

        if ($bulk !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $bulk) as $line) {
                $line = trim($line);
                if ($line === '') continue;

                if (strpos($line, '||') !== false) {
                    [$in, $ans] = array_map('trim', explode('||', $line, 2));
                    if ($in === '' || $ans === '') { $malformed++; continue; }
                    $newLines[] = $in . ' || ' . $ans;
                } else {
                    if (strlen($line) < 2) { $malformed++; continue; }
                    $newLines[] = $line;
                }
            }
        }

        // Load existing data for dedup (unless rebuild replaces all)
        $existing = [];
        if (!$rebuild && file_exists(DATA_FILE)) {
            foreach (file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
                $existing[strtolower(trim($l))] = true;
            }
        }

        $toAppend = [];
        $skipped = 0;
        foreach ($newLines as $line) {
            $key = strtolower($line);
            if (isset($existing[$key])) { $skipped++; continue; }
            $existing[$key] = true;
            $toAppend[] = $line;
        }

        // Append to data.jsonl
        if (!empty($toAppend)) {
            $append = implode("\n", $toAppend) . "\n";
            if (file_put_contents(DATA_FILE, $append, FILE_APPEND) === false) {
                $errors[] = '❌ Failed to write data.jsonl — check permissions (chmod 666 data.jsonl)';
            } else {
                $messages[] = "✅ Appended " . count($toAppend) . " line(s) to data.jsonl" .
                    ($skipped > 0 ? " (skipped $skipped duplicate(s))" : "") .
                    ($malformed > 0 ? " (skipped $malformed malformed)" : "");
            }
        } elseif ($skipped > 0) {
            $messages[] = "ℹ️ Nothing new — $skipped duplicate(s) skipped.";
        }

        // Retrain
        if (empty($errors)) {
            $t0 = microtime(true);
            try {
                if ($rebuild) {
                    // Full rebuild from data.jsonl
                    if (is_dir(MEMORY_DIR)) rrmdir(MEMORY_DIR);
                    $memory = new CompositionMemory(MEMORY_DIR);
                    $allLines = file_exists(DATA_FILE)
                        ? file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
                        : [];
                    foreach ($allLines as $l) {
                        $l = trim($l);
                        if ($l === '') continue;
                        if (strpos($l, '||') !== false) {
                            [$in, $ans] = array_map('trim', explode('||', $l, 2));
                            $memory->learn($in, $ans);
                        } else {
                            $memory->addFact($l);
                        }
                    }
                    $memory->saveNow();
                    $s = $memory->stats();
                    $messages[] = sprintf(
                        "🔨 Full rebuild done in %.2fs — Facts: %d, Learned: %d, Shards: %d",
                        microtime(true) - $t0, $s['facts'], $s['learned'], $s['factsShards']
                    );
                } else {
                    // Incremental: only add the new lines
                    $memory = new CompositionMemory(MEMORY_DIR);
                    $before = $memory->stats();
                    foreach ($toAppend as $line) {
                        if (strpos($line, '||') !== false) {
                            [$in, $ans] = array_map('trim', explode('||', $line, 2));
                            $memory->learn($in, $ans);
                        } else {
                            $memory->addFact($line);
                        }
                    }
                    $memory->saveNow();
                    $after = $memory->stats();
                    $messages[] = sprintf(
                        "🧠 Retrained in %.2fs — Facts: %d → %d, Learned: %d → %d",
                        microtime(true) - $t0,
                        $before['facts'], $after['facts'],
                        $before['learned'], $after['learned']
                    );
                }
            } catch (Throwable $e) {
                $errors[] = '❌ Training failed: ' . $e->getMessage();
            }
        }
    }
}

// ---------- Stats + current data ----------
$memory = new CompositionMemory(MEMORY_DIR);
$stats  = $memory->stats();

$current = file_exists(DATA_FILE)
    ? file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];
$totalData = count($current);
$shownData = array_slice($current, -25);   // show last 25
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Add Data — Compose Memory</title>
<style>
    body { font-family: system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; color: #222; }
    h1, h2 { border-bottom: 1px solid #eee; padding-bottom: .3rem; }
    textarea { width: 100%; min-height: 200px; padding: .6rem; font-family: monospace; font-size: .95rem; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
    input[type=password], input[type=text] { padding: .5rem; font-size: 1rem; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }
    button, a.btn { padding: .55rem 1.2rem; font-size: 1rem; border: 1px solid #333; background: #333; color: white; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
    a.btn { background: #555; border-color: #555; }
    a.btn.red { background: #a33; border-color: #a33; }
    .msg { background: #e8f5e9; border-left: 4px solid #43a047; padding: .6rem 1rem; border-radius: 4px; margin: .5rem 0; font-family: monospace; }
    .err { background: #ffebee; border-left: 4px solid #e53935; padding: .6rem 1rem; border-radius: 4px; margin: .5rem 0; }
    .hint { font-size: .85rem; color: #555; margin: .3rem 0; }
    .stats { background: #f0f7ff; padding: .8rem 1rem; border-radius: 6px; margin: 1rem 0; font-family: monospace; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
    th, td { text-align: left; padding: .35rem .55rem; border-bottom: 1px solid #eee; font-size: .9rem; vertical-align: top; }
    td code { background: #f2f2f2; padding: .05rem .3rem; border-radius: 3px; word-break: break-all; }
    .badge { display: inline-block; padding: .1rem .5rem; border-radius: 4px; font-size: .8rem; font-weight: 600; }
    .badge.fact { background: #e3f2fd; color: #0d47a1; }
    .badge.pair { background: #f3e5f5; color: #4a148c; }
    .preview { font-family: monospace; background: #fafafa; padding: .8rem 1rem; border-radius: 6px; white-space: pre-wrap; margin: 1rem 0; font-size: .9rem; }
    .toolbar { display: flex; gap: .5rem; flex-wrap: wrap; margin: 1rem 0; }
    label.chk { font-size: .9rem; color: #555; display: inline-flex; align-items: center; gap: .35rem; }
</style>
</head>
<body>

<h1>Add Data — Compose Memory</h1>

<div class="toolbar">
    <a class="btn" href="compose_web.php">← Ask (web)</a>
    <?php if ($authed): ?>
        <a class="btn red" href="?logout=1">Logout</a>
    <?php endif; ?>
</div>

<?php foreach ($messages as $m): ?>
    <div class="msg"><?= htmlspecialchars($m) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $e): ?>
    <div class="err"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<?php if (!$authed): ?>

    <h2>Login</h2>
    <form method="post">
        <label>Password</label>
        <input type="password" name="password" autofocus>
        <p style="margin-top:1rem"><button type="submit">Enter</button></p>
    </form>
    <p class="hint">Edit <code>ADMIN_PASSWORD</code> in <code>compose_add.php</code> before deploying.</p>

<?php else: ?>

    <div class="stats">
        Facts: <strong><?= $stats['facts'] ?></strong> ·
        Learned: <strong><?= $stats['learned'] ?></strong> ·
        Fact shards: <strong><?= $stats['factsShards'] ?></strong> ·
        Learned shards: <strong><?= $stats['learnedShards'] ?></strong>
    </div>

    <h2>Add new facts / pairs</h2>
    <p class="hint">
        One item per line.<br>
        &bull; Plain sentence → stored as <strong>fact</strong>: <code>water is a liquid essential for life</code><br>
        &bull; <code>input || answer</code> → stored as <strong>learned pair</strong> (exact match): <code>what is water || water is a liquid essential for life</code>
    </p>

    <form method="post">
        <textarea name="bulk" placeholder="what is beer || beer is an alcoholic beverage made from grain
beer is an alcoholic beverage made from grain
how do i brew tea || boil water and steep tea leaves
what is coffee || coffee is a brewed drink made from roasted beans" autofocus><?= htmlspecialchars($_POST['bulk'] ?? '') ?></textarea>

        <p><label class="chk">
            <input type="checkbox" name="rebuild" value="1">
            Full rebuild from <code>data.jsonl</code> (slower, wipes memory first)
        </label></p>

        <button type="submit">Add &amp; Retrain</button>
    </form>

    <h2>Live Preview</h2>
    <div id="preview" class="preview">(nothing to preview)</div>

    <script>
    const ta = document.querySelector('textarea[name=bulk]');
    const out = document.getElementById('preview');
    function render() {
        const lines = ta.value.split(/\r?\n/);
        let valid = 0, malformed = 0;
        let html = '';
        for (const raw of lines) {
            const line = raw.trim();
            if (!line) continue;
            if (line.includes('||')) {
                const [a, b] = line.split('||').map(x => x.trim());
                if (!a || !b) { html += '⚠ PAIR (malformed): ' + line + '\n'; malformed++; }
                else { html += '✓ PAIR  ' + a + '  →  ' + b + '\n'; valid++; }
            } else if (line.length >= 2) {
                html += '✓ FACT  ' + line + '\n';
                valid++;
            } else {
                html += '⚠ too short: ' + line + '\n';
                malformed++;
            }
        }
        out.textContent = (html || '(nothing to preview)') +
            '\n\n— Summary: ' + valid + ' valid, ' + malformed + ' malformed —';
    }
    ta.addEventListener('input', render);
    render();
    </script>

    <h2>data.jsonl — last 25 of <?= $totalData ?> line(s)</h2>
    <?php if ($totalData === 0): ?>
        <p class="hint">No data yet.</p>
    <?php else: ?>
    <table>
        <tr><th style="width:70px">Type</th><th>Content</th></tr>
        <?php foreach ($shownData as $line): ?>
            <?php $isPair = strpos($line, '||') !== false; ?>
            <tr>
                <td><span class="badge <?= $isPair ? 'pair' : 'fact' ?>"><?= $isPair ? 'PAIR' : 'FACT' ?></span></td>
                <td><code><?= htmlspecialchars($line) ?></code></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>