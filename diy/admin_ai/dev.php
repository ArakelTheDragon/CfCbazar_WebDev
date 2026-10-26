<?php
declare(strict_types=1);
session_start();

/* ============================ CONFIG ============================ */
const SITE_ROOT        = '/var/www/html';
const BACKUP_DIR       = '/var/www/html/diy/admin_ai/.ai_backups';
const API_URL          = 'https://cfcbazar.8bit.ca/diy/ai/index.php?api=1';
const DEFAULT_MODEL    = 'beam';
const MAX_PROMPT       = 95000;
const CLIENT_TIMEOUT   = 900;
const MAX_WORKERS      = 4;
const MAX_TARGET_WORDS = 6000;

/* ⚠️ STATIC PASSWORD — local-only use. Change before exposing. ⚠️ */
const STATIC_PASSWORD  = '123';

const MAX_REVIEW_STORE = 4000;   // truncate review responses stored in temp.json
const MAX_RUNS_KEPT    = 20;     // keep last N runs

const ALLOWED_EXT   = ['html','htm','css','js','php','md','txt','json','svg'];
const BLOCKED_NAMES = ['config.php','secrets.php','.env','.htaccess','.htpasswd',
                       'wp-config.php','credentials.json','id_rsa','id_ed25519'];
const SKIP_DIRS     = ['.git','node_modules','vendor','uploads','.ai_backups'];

/* ============================ HELPERS ============================ */

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function checkCsrf(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        jsonOut(['ok'=>false,'error'=>'Invalid CSRF token.'], 403);
    }
}
function jsonOut(array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $code = 400): never { jsonOut(['ok'=>false,'error'=>$msg], $code); }

function safePath(string $rel): string {
    if ($rel === '' || str_contains($rel, "\0") || str_contains($rel, '..')) {
        throw new RuntimeException('Invalid path.');
    }
    $rel = ltrim($rel, '/\\');
    $full = SITE_ROOT . '/' . $rel;
    $real = realpath($full);
    if ($real === false) throw new RuntimeException('File not found.');
    if (!str_starts_with($real, SITE_ROOT . '/')) throw new RuntimeException('Outside site root.');
    if (!is_file($real)) throw new RuntimeException('Not a file.');
    if (in_array(strtolower(basename($real)), BLOCKED_NAMES, true)) throw new RuntimeException('File is blocked.');
    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) throw new RuntimeException('File type not allowed.');
    return $real;
}

function callApi(string $model, string $prompt, int $targetWords, int $workers): array {
    $payload = json_encode([
        'model'=>$model, 'prompt'=>$prompt,
        'target_words'=>max(150, min(MAX_TARGET_WORDS,$targetWords)),
        'workers'=>max(1, min(MAX_WORKERS,$workers)),
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $ch = curl_init(API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT=>15, CURLOPT_TIMEOUT=>CLIENT_TIMEOUT,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException("Network: $err");
    $data = json_decode((string)$body, true);
    if (!is_array($data)) throw new RuntimeException("Non-JSON (HTTP $code)");
    if (isset($data['error'])) throw new RuntimeException("API: {$data['error']}");
    if (!isset($data['response']) || !is_string($data['response'])) {
        throw new RuntimeException('Missing "response"');
    }
    return $data;
}

function extractBody(string $reply): string {
    $t = trim($reply);
    if (preg_match('/^```[a-zA-Z0-9_+-]*\s*\n(.*?)\n```\s*$/s', $t, $m)) {
        return rtrim(ltrim($m[1], "\n"));
    }
    if (str_contains($t, '```')) {
        $p = explode('```', $t);
        if (count($p) >= 3) {
            $b = $p[1];
            $nl = strpos($b, "\n");
            if ($nl !== false) $b = substr($b, $nl+1);
            return rtrim(ltrim($b, "\n"));
        }
    }
    return $t;
}

function validateFile(string $file): ?string {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        $out=[]; $code=0;
        exec('php -l '.escapeshellarg($file).' 2>&1', $out, $code);
        if ($code !== 0) return implode("\n", $out);
    }
    if ($ext === 'json') {
        json_decode((string)file_get_contents($file), true);
        if (json_last_error() !== JSON_ERROR_NONE) return 'JSON invalid: '.json_last_error_msg();
    }
    return null;
}

/* ------------------------- backup + temp store ------------------------- */

function ensureBackupDir(): void {
    if (!is_dir(BACKUP_DIR) && !@mkdir(BACKUP_DIR, 0700, true) && !is_dir(BACKUP_DIR)) {
        throw new RuntimeException('Cannot create backup dir.');
    }
}

function makeBackup(string $file, ?string $stamp = null): string {
    ensureBackupDir();
    $stamp = $stamp ?? date('Ymd-His');
    $rel   = ltrim(substr($file, strlen(SITE_ROOT)), '/');
    $dest  = BACKUP_DIR.'/'.$stamp.'/'.$rel;
    $dir   = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create backup subdir.');
    }
    if (!copy($file, $dest)) throw new RuntimeException('Backup copy failed.');
    return $stamp.'/'.$rel;
}

function tempJsonPath(): string {
    return BACKUP_DIR . '/temp.json';
}

function loadRuns(): array {
    ensureBackupDir();
    $p = tempJsonPath();
    if (!is_file($p)) return ['runs' => []];
    $d = json_decode((string)file_get_contents($p), true);
    if (!is_array($d) || !isset($d['runs']) || !is_array($d['runs'])) {
        return ['runs' => []];
    }
    return $d;
}

function saveRuns(array $data): void {
    ensureBackupDir();
    $p   = tempJsonPath();
    $tmp = $p . '.tmp.' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp,
            json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            LOCK_EX) === false) {
        throw new RuntimeException('Cannot write temp.json');
    }
    if (!rename($tmp, $p)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot replace temp.json');
    }
}

function addRun(array $run): void {
    $d = loadRuns();
    array_unshift($d['runs'], $run);
    $d['runs'] = array_slice($d['runs'], 0, MAX_RUNS_KEPT);
    saveRuns($d);
}

function updateRun(string $id, array $patch): void {
    $d = loadRuns();
    foreach ($d['runs'] as &$r) {
        if (($r['id'] ?? '') === $id) {
            $r = array_merge($r, $patch);
            break;
        }
    }
    unset($r);
    saveRuns($d);
}

function getRun(string $id): ?array {
    $d = loadRuns();
    foreach ($d['runs'] as $r) {
        if (($r['id'] ?? '') === $id) return $r;
    }
    return null;
}

/* ------------------------- file walking + filter ------------------------- */

function matchesFilter(string $rel, string $filter): bool {
    if ($filter === '') return true;
    foreach (array_map('trim', explode(',', $filter)) as $p) {
        if ($p === '') continue;
        $target = str_contains($p, '/') ? $rel : basename($rel);
        if (fnmatch($p, $target, FNM_CASEFOLD)) return true;
    }
    return false;
}

function walkForJob(string $dir, string $filter, array &$out): void {
    $entries = @scandir($dir);
    if ($entries === false) return;
    foreach ($entries as $name) {
        if ($name === '.' || $name === '..') continue;
        $full = rtrim($dir, '/') . '/' . $name;
        if (is_link($full)) continue;
        if (is_dir($full)) {
            if (in_array($name, SKIP_DIRS, true)) continue;
            walkForJob($full, $filter, $out);
            continue;
        }
        if (!is_file($full)) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXT, true)) continue;
        if (in_array(strtolower($name), BLOCKED_NAMES, true)) continue;
        $rel = ltrim(substr($full, strlen(SITE_ROOT)), '/');
        if (!matchesFilter($rel, $filter)) continue;
        $out[] = $full;
    }
}

function listFilesMatching(string $filter): array {
    $out = [];
    if (!is_dir(SITE_ROOT)) return $out;
    walkForJob(SITE_ROOT, $filter, $out);
    sort($out);
    return $out;
}

/* ------------------------- stream emitter ------------------------- */

function emit(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
    if (ob_get_level() > 0) @ob_flush();
    @flush();
}

/* ============================ ROUTING ============================ */

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* --- login --- */
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $pw = (string)($_POST['password'] ?? '');
    if (hash_equals(STATIC_PASSWORD, $pw)) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: ?'); exit;
    }
    $loginError = 'Wrong password.';
}
if ($action === 'logout') {
    $_SESSION = []; session_destroy();
    header('Location: ?'); exit;
}

if (empty($_SESSION['logged_in'])) {
    $err = htmlspecialchars($loginError ?? '', ENT_QUOTES);
    $tok = htmlspecialchars(csrf(), ENT_QUOTES);
    ?><!doctype html><html><head><meta charset="utf-8">
<title>AI Web Builder · Login</title>
<style>
body{font-family:system-ui,sans-serif;background:#0f172a;color:#f8fafc;display:grid;place-items:center;min-height:100vh;margin:0}
.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;width:340px}
h1{font-size:1.1rem;margin:0 0 20px;color:#38bdf8}
input{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#f8fafc;box-sizing:border-box}
button{margin-top:14px;width:100%;padding:10px;border:0;border-radius:8px;background:#0284c7;color:#fff;font-weight:600;cursor:pointer}
button:hover{background:#0369a1}
.err{color:#f87171;font-size:.85rem;margin-top:10px}
</style></head><body>
<form class="card" method="post" action="?action=login">
  <h1>AI Web Builder</h1>
  <input type="hidden" name="csrf" value="<?= $tok ?>">
  <input type="password" name="password" placeholder="Password" autofocus autocomplete="off">
  <button type="submit">Log in</button>
  <?php if ($err): ?><div class="err"><?= $err ?></div><?php endif; ?>
</form></body></html><?php
    exit;
}

/* --- run: streams NDJSON progress + final summary --- */

if ($action === 'run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    session_write_close();

    $prompt = trim((string)($_POST['prompt'] ?? ''));
    $filter = trim((string)($_POST['filter'] ?? ''));
    $model  = (string)($_POST['model'] ?? DEFAULT_MODEL);
    $mode   = ($_POST['mode'] ?? 'rewrite') === 'review' ? 'review' : 'rewrite';
    $phpOk  = !empty($_POST['php_ok']);

    if ($prompt === '') {
        header('Content-Type: application/x-ndjson');
        emit(['type'=>'error','error'=>'Prompt is required.']);
        exit;
    }

    ignore_user_abort(true);
    set_time_limit(0);
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    while (ob_get_level() > 0) ob_end_flush();
    ob_implicit_flush(true);

    $runId = bin2hex(random_bytes(6));
    $startedAt = date('c');

    $run = [
        'id'         => $runId,
        'started_at' => $startedAt,
        'finished_at'=> null,
        'status'     => 'running',
        'prompt'     => $prompt,
        'filter'     => $filter,
        'model'      => $model,
        'mode'       => $mode,
        'files'      => [],
        'summary'    => null,
    ];
    addRun($run);

    $files = listFilesMatching($filter);
    $total = count($files);

    emit(['type'=>'start', 'run_id'=>$runId, 'total'=>$total]);

    $stats = ['total'=>$total, 'applied'=>0, 'reviewed'=>0,
              'unchanged'=>0, 'skipped'=>0, 'failed'=>0];

    foreach ($files as $idx => $file) {
        $rel = ltrim(substr($file, strlen(SITE_ROOT)), '/');
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $pos = $idx + 1;

        if ($ext === 'php' && $mode === 'rewrite' && !$phpOk) {
            $stats['skipped']++;
            $run['files'][] = ['path'=>$rel, 'status'=>'skipped', 'reason'=>'php needs allow'];
            updateRun($runId, ['files'=>$run['files']]);
            emit(['type'=>'file','path'=>$rel,'status'=>'skipped',
                  'reason'=>'php needs allow','pos'=>$pos,'total'=>$total]);
            continue;
        }

        $content = (string)file_get_contents($file);
        if (strlen($content) > MAX_PROMPT) {
            $stats['skipped']++;
            $run['files'][] = ['path'=>$rel, 'status'=>'skipped', 'reason'=>'too large'];
            updateRun($runId, ['files'=>$run['files']]);
            emit(['type'=>'file','path'=>$rel,'status'=>'skipped',
                  'reason'=>'too large','pos'=>$pos,'total'=>$total]);
            continue;
        }

        emit(['type'=>'progress','path'=>$rel,'pos'=>$pos,'total'=>$total]);

        try {
            $workers = ($ext === 'md') ? 2 : 1;
            $target  = max(300, min(4000, (int)(strlen($content)/6)));

            if ($mode === 'review') {
                $fullPrompt =
                    "USER INSTRUCTION:\n{$prompt}\n\n"
                  . "Review this {$ext} file for a small site. Return a concise bullet list "
                  . "of concrete issues only. No praise. Do NOT return the file.\n\n"
                  . "File: {$rel}\n```{$ext}\n{$content}\n```";
                $data = callApi($model, $fullPrompt, $target, $workers);
                $raw  = (string)$data['response'];
                $stored = strlen($raw) > MAX_REVIEW_STORE
                        ? substr($raw, 0, MAX_REVIEW_STORE) . "\n… (truncated)"
                        : $raw;
                $stats['reviewed']++;
                $run['files'][] = ['path'=>$rel, 'status'=>'reviewed', 'raw'=>$stored];
                updateRun($runId, ['files'=>$run['files']]);
                emit(['type'=>'file','path'=>$rel,'status'=>'reviewed','pos'=>$pos,'total'=>$total]);
            } else {
                $fullPrompt =
                    "USER INSTRUCTION:\n{$prompt}\n\n"
                  . "Apply the instruction to the file below and return ONLY the complete file content.\n"
                  . "Hard rules:\n- Preserve every function, URL, form field, CSS selector, JS behaviour.\n"
                  . "- No new external dependencies.\n- No markdown fences, no commentary, no heading. "
                  . "Start with the first line of the file.\n\n"
                  . "File: {$rel}\n```{$ext}\n{$content}\n```";
                $data = callApi($model, $fullPrompt, $target, $workers);
                $new  = extractBody((string)$data['response']);

                if (trim($new) === trim($content)) {
                    $stats['unchanged']++;
                    $run['files'][] = ['path'=>$rel, 'status'=>'unchanged'];
                    updateRun($runId, ['files'=>$run['files']]);
                    emit(['type'=>'file','path'=>$rel,'status'=>'unchanged','pos'=>$pos,'total'=>$total]);
                } else {
                    $backup = makeBackup($file, $runId);
                    file_put_contents($file, rtrim($new, "\n")."\n");
                    $err = validateFile($file);
                    if ($err !== null) {
                        copy(BACKUP_DIR.'/'.$backup, $file);
                        $stats['failed']++;
                        $run['files'][] = ['path'=>$rel, 'status'=>'failed',
                                           'error'=>$err, 'backup'=>$backup];
                        updateRun($runId, ['files'=>$run['files']]);
                        emit(['type'=>'file','path'=>$rel,'status'=>'failed',
                              'error'=>$err,'pos'=>$pos,'total'=>$total]);
                    } else {
                        $stats['applied']++;
                        $run['files'][] = ['path'=>$rel, 'status'=>'applied', 'backup'=>$backup];
                        updateRun($runId, ['files'=>$run['files']]);
                        emit(['type'=>'file','path'=>$rel,'status'=>'applied',
                              'backup'=>$backup,'pos'=>$pos,'total'=>$total]);
                    }
                }
            }
        } catch (Throwable $e) {
            $stats['failed']++;
            $run['files'][] = ['path'=>$rel, 'status'=>'failed', 'error'=>$e->getMessage()];
            updateRun($runId, ['files'=>$run['files']]);
            emit(['type'=>'file','path'=>$rel,'status'=>'failed',
                  'error'=>$e->getMessage(),'pos'=>$pos,'total'=>$total]);
        }
    }

    $run['finished_at'] = date('c');
    $run['status']      = 'complete';
    $run['summary']     = $stats;
    updateRun($runId, $run);

    emit(['type'=>'done', 'run_id'=>$runId, 'summary'=>$stats]);
    exit;
}

/* --- list runs --- */
if ($action === 'runs' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $d = loadRuns();
    $out = [];
    foreach ($d['runs'] as $r) {
        $out[] = [
            'id'         => $r['id'] ?? '',
            'started_at' => $r['started_at'] ?? '',
            'finished_at'=> $r['finished_at'] ?? null,
            'status'     => $r['status'] ?? '',
            'mode'       => $r['mode'] ?? '',
            'model'      => $r['model'] ?? '',
            'prompt'     => mb_substr((string)($r['prompt'] ?? ''), 0, 120),
            'summary'    => $r['summary'] ?? null,
        ];
    }
    jsonOut(['ok'=>true, 'runs'=>$out]);
}

/* --- get one run --- */
if ($action === 'run_get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (string)($_GET['id'] ?? '');
    $r = getRun($id);
    if ($r === null) fail('Run not found.', 404);
    jsonOut(['ok'=>true, 'run'=>$r]);
}

/* ============================ UI ============================ */

$csrfTok = csrf();
$models  = ['beam','gemma4:31b','gpt-oss:120b','gpt-oss:20b',
            'nemotron-3-nano:30b','nemotron-3-super','nemotron-3-ultra'];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8">
<title>AI Web Builder · Batch</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,sans-serif;background:#0f172a;color:#f8fafc;min-height:100vh}
header{background:#1e293b;border-bottom:1px solid #334155;padding:10px 16px;display:flex;gap:12px;align-items:center}
header h1{font-size:1rem;margin:0;color:#38bdf8}
header .warn{color:#f87171;font-size:.8rem;font-weight:600}
header .sp{flex:1}
header a{color:#94a3b8;font-size:.85rem;text-decoration:none}
main{max-width:1100px;margin:0 auto;padding:16px;display:flex;flex-direction:column;gap:14px}
section{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:16px}
section h2{font-size:.9rem;margin:0 0 10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em}
textarea,input[type=text],select{background:#0f172a;color:#f8fafc;border:1px solid #334155;border-radius:8px;padding:10px 12px;font-size:.95rem;width:100%;outline:none;font-family:inherit}
textarea{min-height:110px;resize:vertical}
textarea:focus,input:focus,select:focus{border-color:#38bdf8}
.options{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:10px}
.options select,.options input[type=text]{width:auto}
.options label{display:flex;gap:6px;align-items:center;font-size:.85rem;color:#94a3b8}
.options input[type=text]{min-width:260px}
button{padding:9px 18px;border:0;border-radius:8px;background:#0284c7;color:#fff;font-weight:600;cursor:pointer;font-size:.9rem}
button:hover:not(:disabled){background:#0369a1}
button:disabled{background:#475569;cursor:not-allowed}
button.ghost{background:#334155;color:#cbd5e1}
button.ghost:hover:not(:disabled){background:#475569}
#progressPanel{display:none}
#progressPanel.on{display:block}
.pRow{display:flex;gap:10px;align-items:center;font-size:.9rem;color:#cbd5e1;font-family:monospace}
.pBar{height:6px;background:#334155;border-radius:3px;overflow:hidden;margin:8px 0 12px}
.pBarFill{height:100%;background:#0284c7;width:0%;transition:width .3s}
#pLog{max-height:180px;overflow:auto;background:#0f172a;border:1px solid #334155;border-radius:6px;padding:8px 10px;font:12px/1.5 ui-monospace,Consolas,monospace;color:#94a3b8}
#pLog div{padding:2px 0}
.ok{color:#34d399}
.err{color:#f87171}
.muted{color:#64748b}
#resultPanel{display:none}
#resultPanel.on{display:block}
#summaryGrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(90px,1fr));gap:8px;margin-bottom:12px}
.sumBox{background:#0f172a;border:1px solid #334155;border-radius:8px;padding:8px 10px}
.sumBox .k{font-size:.7rem;color:#94a3b8;text-transform:uppercase}
.sumBox .v{font-size:1.2rem;font-weight:600}
.sumBox.applied .v{color:#34d399}
.sumBox.reviewed .v{color:#38bdf8}
.sumBox.unchanged .v{color:#cbd5e1}
.sumBox.skipped .v{color:#facc15}
.sumBox.failed .v{color:#f87171}
#fileTable{width:100%;border-collapse:collapse;font-size:.87rem}
#fileTable th,#fileTable td{padding:6px 8px;border-bottom:1px solid #334155;text-align:left;vertical-align:top}
#fileTable th{color:#94a3b8;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em}
#fileTable td.path{font-family:monospace;color:#cbd5e1}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.72rem;font-weight:600;text-transform:uppercase}
.badge.applied{background:#064e3b;color:#34d399}
.badge.reviewed{background:#0c4a6e;color:#38bdf8}
.badge.unchanged{background:#1e293b;color:#cbd5e1}
.badge.skipped{background:#422006;color:#facc15}
.badge.failed{background:#450a0a;color:#f87171}
.badge.running{background:#1e3a8a;color:#93c5fd}
details summary{cursor:pointer;color:#94a3b8;font-size:.8rem}
details pre{margin:6px 0 0;background:#0f172a;border:1px solid #334155;border-radius:6px;padding:8px;font-size:.78rem;color:#e2e8f0;overflow:auto;max-height:220px;white-space:pre-wrap}
#historyList{display:flex;flex-direction:column;gap:6px}
.runItem{display:flex;gap:10px;align-items:center;padding:8px 10px;border:1px solid #334155;border-radius:8px;background:#0f172a;cursor:pointer}
.runItem:hover{border-color:#38bdf8}
.runItem .id{font-family:monospace;color:#38bdf8;font-size:.82rem}
.runItem .prompt{flex:1;color:#cbd5e1;font-size:.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.runItem .when{color:#64748b;font-size:.75rem}
.runItem .s{margin-left:auto;font-size:.75rem;color:#94a3b8}
</style></head>
<body>
<header>
  <h1>AI Web Builder · Batch</h1>
  <span class="warn">⚠ static password — local only</span>
  <span class="sp"></span>
  <a href="?action=logout">Log out</a>
</header>
<main>

<section>
  <h2>What should the AI do?</h2>
  <textarea id="prompt" placeholder="e.g. Fix accessibility issues across the site. Preserve behaviour, do not add dependencies."></textarea>
  <div class="options">
    <select id="model">
      <?php foreach ($models as $m): ?>
        <option value="<?= $m ?>"<?= $m===DEFAULT_MODEL?' selected':'' ?>><?= $m ?></option>
      <?php endforeach; ?>
    </select>
    <select id="mode">
      <option value="rewrite" selected>Rewrite &amp; apply</option>
      <option value="review">Review only</option>
    </select>
    <input type="text" id="filter" placeholder="filter (optional): *.php  |  diy/ai/*  |  blank = all">
    <label><input type="checkbox" id="phpOk"> allow PHP rewrite</label>
    <button id="runBtn">Run</button>
  </div>
</section>

<section id="progressPanel">
  <h2>Progress</h2>
  <div class="pRow"><span id="pLabel">Idle</span></div>
  <div class="pBar"><div class="pBarFill" id="pBarFill"></div></div>
  <div id="pLog"></div>
</section>

<section id="resultPanel">
  <h2>Result <span class="muted" id="rId"></span></h2>
  <div id="summaryGrid"></div>
  <table id="fileTable">
    <thead><tr><th>File</th><th>Status</th><th>Detail</th></tr></thead>
    <tbody id="fileTbody"></tbody>
  </table>
</section>

<section>
  <h2>Recent runs</h2>
  <div id="historyList"><span class="muted">Loading…</span></div>
</section>

</main>

<script>
"use strict";
const CSRF = <?= json_encode($csrfTok) ?>;

const $ = id => document.getElementById(id);
const promptEl = $('prompt'), modelEl = $('model'), modeEl = $('mode'),
      filterEl = $('filter'), phpOkEl = $('phpOk'), runBtn = $('runBtn');
const progressPanel = $('progressPanel'), pLabel = $('pLabel'), pBarFill = $('pBarFill'), pLog = $('pLog');
const resultPanel = $('resultPanel'), rId = $('rId'), summaryGrid = $('summaryGrid'), fileTbody = $('fileTbody');
const historyList = $('historyList');

const summaryOrder = ['total','applied','reviewed','unchanged','skipped','failed'];

function logLine(txt, cls) {
  const d = document.createElement('div');
  if (cls) d.className = cls;
  d.textContent = txt;
  pLog.appendChild(d);
  pLog.scrollTop = pLog.scrollHeight;
}

function clearProgress() {
  pLog.innerHTML = '';
  pLabel.textContent = 'Starting…';
  pBarFill.style.width = '0%';
  progressPanel.classList.add('on');
  resultPanel.classList.remove('on');
}

function setBusy(b) { runBtn.disabled = b; }

function badge(status) {
  return '<span class="badge ' + status + '">' + status + '</span>';
}

async function runJob() {
  const prompt = promptEl.value.trim();
  if (!prompt) { alert('Enter a prompt first.'); promptEl.focus(); return; }

  setBusy(true);
  clearProgress();
  logLine('Sending run…');

  const body = new URLSearchParams({
    csrf: CSRF,
    prompt,
    filter: filterEl.value.trim(),
    model: modelEl.value,
    mode: modeEl.value
  });
  if (phpOkEl.checked) body.set('php_ok', '1');

  let currentRunId = null;
  let files = [];
  let total = 0;

  try {
    const res = await fetch('?action=run', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    });

    if (!res.body) throw new Error('Streaming not supported by this browser.');
    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buf = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      buf += decoder.decode(value, { stream: true });
      let i;
      while ((i = buf.indexOf('\n')) >= 0) {
        const line = buf.slice(0, i).trim();
        buf = buf.slice(i + 1);
        if (!line) continue;
        let evt;
        try { evt = JSON.parse(line); } catch { continue; }
        handleEvent(evt);
      }
    }

    function handleEvent(e) {
      if (e.type === 'error') {
        logLine('Error: ' + e.error, 'err');
        pLabel.textContent = 'Error';
        return;
      }
      if (e.type === 'start') {
        currentRunId = e.run_id;
        total = e.total;
        rId.textContent = '· ' + e.run_id;
        pLabel.textContent = 'Run ' + e.run_id + ' · ' + total + ' file(s)';
        logLine('Found ' + total + ' file(s) matching filter.', 'muted');
        return;
      }
      if (e.type === 'progress') {
        pLabel.textContent = 'Processing ' + e.pos + '/' + e.total + ' · ' + e.path;
        pBarFill.style.width = (100 * (e.pos - 1) / e.total).toFixed(1) + '%';
        logLine('… ' + e.path, 'muted');
        return;
      }
      if (e.type === 'file') {
        files.push({ path: e.path, status: e.status, error: e.error,
                     backup: e.backup, raw: e.raw, reason: e.reason });
        pBarFill.style.width = (100 * e.pos / e.total).toFixed(1) + '%';
        const line = e.pos + '/' + e.total + '  ' + e.status + '  ' + e.path
                    + (e.error ? '  — ' + e.error.split('\n')[0] : '');
        logLine(line, e.status === 'failed' ? 'err'
                     : e.status === 'applied' ? 'ok' : '');
        return;
      }
      if (e.type === 'done') {
        pBarFill.style.width = '100%';
        pLabel.textContent = 'Complete — ' + formatSummary(e.summary);
        renderResult(e.run_id, files, e.summary);
        refreshHistory();
        return;
      }
    }
  } catch (err) {
    logLine('Fatal: ' + (err.message || err), 'err');
    pLabel.textContent = 'Aborted';
  } finally {
    setBusy(false);
  }
}

function formatSummary(s) {
  if (!s) return '';
  return ['applied','reviewed','unchanged','skipped','failed']
    .filter(k => s[k])
    .map(k => s[k] + ' ' + k).join(', ') || 'nothing done';
}

function renderResult(runId, files, summary) {
  resultPanel.classList.add('on');
  rId.textContent = '· ' + runId;
  summaryGrid.innerHTML = summaryOrder.map(k => {
    const v = summary?.[k] ?? 0;
    return '<div class="sumBox ' + k + '"><div class="k">' + k + '</div><div class="v">' + v + '</div></div>';
  }).join('');
  fileTbody.innerHTML = files.map((f, i) => {
    let detail = '';
    if (f.status === 'failed' && f.error) {
      detail = '<details><summary>error</summary><pre>' + esc(f.error) + '</pre></details>';
    } else if (f.status === 'applied' && f.backup) {
      detail = '<span class="muted">backup: ' + esc(f.backup) + '</span>';
    } else if (f.status === 'reviewed' && f.raw) {
      detail = '<details><summary>review</summary><pre>' + esc(f.raw) + '</pre></details>';
    } else if (f.status === 'skipped' && f.reason) {
      detail = '<span class="muted">' + esc(f.reason) + '</span>';
    }
    return '<tr><td class="path">' + esc(f.path) + '</td>'
         + '<td>' + badge(f.status) + '</td>'
         + '<td>' + detail + '</td></tr>';
  }).join('');
}

function esc(s) {
  return String(s)
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
}

async function refreshHistory() {
  try {
    const r = await fetch('?action=runs');
    const j = await r.json();
    if (!j.ok) throw new Error(j.error);
    if (!j.runs.length) { historyList.innerHTML = '<span class="muted">No runs yet.</span>'; return; }
    historyList.innerHTML = j.runs.map(run => {
      const s = run.summary;
      const sum = s ? [s.applied && s.applied+'✓', s.failed && s.failed+'✗', s.skipped && s.skipped+'⊘']
                          .filter(Boolean).join(' ') : run.status;
      return '<div class="runItem" data-id="' + esc(run.id) + '">'
           + '<span class="id">' + esc(run.id) + '</span>'
           + '<span class="prompt">' + esc(run.prompt) + '</span>'
           + '<span class="s">' + esc(sum) + '</span>'
           + '<span class="when">' + esc(run.started_at) + '</span>'
           + '</div>';
    }).join('');
    historyList.querySelectorAll('.runItem').forEach(el => {
      el.addEventListener('click', () => loadRun(el.dataset.id));
    });
  } catch (e) {
    historyList.innerHTML = '<span class="err">' + esc(e.message) + '</span>';
  }
}

async function loadRun(id) {
  try {
    const r = await fetch('?action=run_get&id=' + encodeURIComponent(id));
    const j = await r.json();
    if (!j.ok) throw new Error(j.error);
    renderResult(j.run.id, j.run.files || [], j.run.summary || {});
    progressPanel.classList.add('on');
    pLabel.textContent = 'Loaded run ' + j.run.id;
    pLog.innerHTML = '';
  } catch (e) {
    alert('Could not load run: ' + e.message);
  }
}

runBtn.addEventListener('click', runJob);
refreshHistory();
</script>
</body></html>