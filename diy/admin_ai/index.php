<?php
declare(strict_types=1);
session_start();

/* ============================ CONFIG ============================ */
const SITE_ROOT  = '/var/www/html';
const BACKUP_DIR = '/var/www/html/diy/admin_ai/.ai_backups';
const API_URL          = 'https://cfcbazar.8bit.ca/diy/ai/index.php?api=1';
const DEFAULT_MODEL    = 'beam';
const MAX_PROMPT       = 95000;
const CLIENT_TIMEOUT   = 900;
const MAX_WORKERS      = 4;
const MAX_TARGET_WORDS = 6000;

/* ⚠️ STATIC PASSWORD — local-only use. Change before exposing. ⚠️ */
const STATIC_PASSWORD  = '123';

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

function listFiles(): array {
    $out = [];
    if (!is_dir(SITE_ROOT)) return $out;
    $filter = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator(SITE_ROOT, FilesystemIterator::SKIP_DOTS),
        function(SplFileInfo $c) {
            if ($c->isDir()) return !in_array($c->getFilename(), SKIP_DIRS, true);
            return true;
        }
    );
    foreach (new RecursiveIteratorIterator($filter) as $info) {
        /** @var SplFileInfo $info */
        if (!$info->isFile()) continue;
        $ext = strtolower($info->getExtension());
        if (!in_array($ext, ALLOWED_EXT, true)) continue;
        if (in_array(strtolower($info->getFilename()), BLOCKED_NAMES, true)) continue;
        $out[] = [
            'path'  => ltrim(substr($info->getPathname(), strlen(SITE_ROOT)), '/'),
            'size'  => $info->getSize(),
            'mtime' => date('Y-m-d H:i', $info->getMTime()),
        ];
    }
    usort($out, fn($a,$b) => strcmp($a['path'], $b['path']));
    return $out;
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

function makeBackup(string $file): string {
    if (!is_dir(BACKUP_DIR) && !mkdir(BACKUP_DIR, 0700, true) && !is_dir(BACKUP_DIR)) {
        throw new RuntimeException('Cannot create backup dir.');
    }
    $stamp = date('Ymd-His');
    $rel   = ltrim(substr($file, strlen(SITE_ROOT)), '/');
    $dest  = BACKUP_DIR.'/'.$stamp.'/'.$rel;
    $dir   = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create backup subdir.');
    }
    if (!copy($file, $dest)) throw new RuntimeException('Backup copy failed.');
    return $stamp.'/'.$rel;
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

/* --- login page --- */
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

/* --- authenticated API endpoints --- */

if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $f = safePath((string)($_GET['file'] ?? ''));
        jsonOut(['ok'=>true,'content'=>(string)file_get_contents($f),
                 'size'=>filesize($f),'mtime'=>date('c',(int)filemtime($f))]);
    } catch (Throwable $e) { fail($e->getMessage()); }
}

if ($action === 'ai' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    try {
        $f = safePath((string)($_POST['file'] ?? ''));
        $mode = ($_POST['mode'] ?? 'review') === 'rewrite' ? 'rewrite' : 'review';
        $model = (string)($_POST['model'] ?? DEFAULT_MODEL);
        $content = (string)file_get_contents($f);
        if (strlen($content) > MAX_PROMPT) fail('File too large for the API.');
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if ($ext === 'php' && $mode === 'rewrite' && empty($_POST['php_ok'])) {
            fail('PHP rewrite requires the "allow PHP" checkbox.');
        }
        $workers = ($ext === 'md') ? max(1, min(4, (int)($_POST['workers'] ?? 1))) : 1;
        $target  = max(300, min(4000, (int)(strlen($content)/6)));
        $rel     = ltrim(substr($f, strlen(SITE_ROOT)), '/');

        $prompt = $mode === 'review'
            ? "You are a senior web developer reviewing a {$ext} file for a small site.\n"
              ."Return a concise bullet list of concrete issues only — accessibility, SEO, performance, security, correctness, dead code. No praise. Do NOT return the file.\n\n"
              ."File: {$rel}\n```{$ext}\n{$content}\n```"
            : "You are a senior web developer. Rewrite this {$ext} file.\n"
              ."Hard rules:\n- Preserve every function, URL, form field, CSS selector, and JS behaviour.\n"
              ."- Do not add external dependencies.\n- Keep the same file type and structure; only improve quality.\n"
              ."- Return ONLY the complete file content. No markdown fences, no commentary, no heading, no \"Here is the file\". Start with the first line of the file.\n\n"
              ."File: {$rel}\n```{$ext}\n{$content}\n```";

        $data = callApi($model, $prompt, $target, $workers);
        $reply = $data['response'];
        $out = ['ok'=>true,'mode'=>$mode,'raw'=>$reply,'word_count'=>$data['word_count'] ?? null];
        if ($mode === 'rewrite') $out['proposed'] = extractBody($reply);
        jsonOut($out);
    } catch (Throwable $e) { fail($e->getMessage()); }
}

if ($action === 'apply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    try {
        $f   = safePath((string)($_POST['file'] ?? ''));
        $new = (string)($_POST['content'] ?? '');
        if ($new === '') fail('Empty content.');
        $backup = makeBackup($f);
        file_put_contents($f, rtrim($new, "\n")."\n");
        $err = validateFile($f);
        if ($err !== null) {
            copy(BACKUP_DIR.'/'.$backup, $f);
            fail("Validation failed, backup restored: {$err}");
        }
        jsonOut(['ok'=>true,'backup'=>$backup]);
    } catch (Throwable $e) { fail($e->getMessage()); }
}

if ($action === 'backups' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $list = [];
    if (is_dir(BACKUP_DIR)) {
        foreach (glob(BACKUP_DIR.'/*', GLOB_ONLYDIR) ?: [] as $stampDir) {
            $stamp = basename($stampDir);
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stampDir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $info) {
                if (!$info->isFile()) continue;
                $list[] = [
                    'backup' => $stamp.'/'.substr($info->getPathname(), strlen($stampDir)+1),
                    'size'   => $info->getSize(),
                    'mtime'  => date('Y-m-d H:i:s', $info->getMTime()),
                ];
            }
        }
    }
    usort($list, fn($a,$b) => strcmp($b['backup'], $a['backup']));
    jsonOut(['ok'=>true,'backups'=>$list]);
}

if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    try {
        $b = (string)($_POST['backup'] ?? '');
        if (!preg_match('#^[0-9]{8}-[0-9]{6}/[\w./-]+$#', $b) || str_contains($b,'..')) {
            fail('Invalid backup path.');
        }
        $src = BACKUP_DIR.'/'.$b;
        if (!is_file($src)) fail('Backup not found.');
        $rel = substr($b, strpos($b,'/')+1);
        $dst = safePath($rel);
        makeBackup($dst);
        copy($src, $dst);
        jsonOut(['ok'=>true]);
    } catch (Throwable $e) { fail($e->getMessage()); }
}

/* --- main UI --- */
$files    = listFiles();
$csrfTok  = csrf();
$selInit  = isset($_GET['file']) ? (string)$_GET['file'] : '';
$models   = ['beam','gemma4:31b','gpt-oss:120b','gpt-oss:20b','nemotron-3-nano:30b','nemotron-3-super','nemotron-3-ultra'];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8">
<title>AI Web Builder</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,sans-serif;background:#0f172a;color:#f8fafc;height:100vh;display:flex;flex-direction:column}
header{background:#1e293b;border-bottom:1px solid #334155;padding:10px 16px;display:flex;gap:12px;align-items:center}
header h1{font-size:1rem;margin:0;color:#38bdf8}
header .sp{flex:1}
header .warn{color:#f87171;font-size:.8rem;font-weight:600}
header a{color:#94a3b8;font-size:.85rem;text-decoration:none}
main{flex:1;display:flex;min-height:0}
aside{width:280px;background:#111c33;border-right:1px solid #334155;overflow:auto;padding:8px}
aside h2{font-size:.75rem;color:#94a3b8;text-transform:uppercase;padding:6px 8px;margin:0}
.file{display:block;padding:6px 10px;border-radius:6px;color:#cbd5e1;text-decoration:none;font-size:.85rem;font-family:monospace;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.file:hover{background:#1e293b}
.file.active{background:#0284c7;color:#fff}
.file .sz{color:#64748b;font-size:.75rem;margin-left:6px}
section{flex:1;display:flex;flex-direction:column;min-width:0;padding:12px;gap:10px}
.bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.bar label{font-size:.85rem;color:#94a3b8;display:flex;gap:6px;align-items:center}
button{padding:8px 14px;border:0;border-radius:8px;background:#0284c7;color:#fff;font-weight:600;cursor:pointer;font-size:.9rem}
button:hover:not(:disabled){background:#0369a1}
button:disabled{background:#475569;cursor:not-allowed}
button.ghost{background:#334155;color:#cbd5e1}
button.ghost:hover:not(:disabled){background:#475569}
button.apply{background:#059669}
button.apply:hover:not(:disabled){background:#047857}
select,input[type=checkbox]{background:#0f172a;color:#f8fafc;border:1px solid #334155;border-radius:6px;padding:6px 8px;font-size:.85rem}
.editors{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:10px;min-height:0}
.pane{display:flex;flex-direction:column;min-height:0}
.pane h3{font-size:.8rem;color:#94a3b8;margin:0 0 6px;text-transform:uppercase}
.pane textarea{flex:1;width:100%;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#e2e8f0;padding:10px;font:13px/1.5 ui-monospace,Consolas,monospace;resize:none;overflow:auto}
.pane textarea.proposed{border-color:#059669}
#status{font-size:.85rem;color:#94a3b8;min-height:1.2em}
#status.err{color:#f87171}
#status.ok{color:#34d399}
.toast{position:fixed;bottom:20px;right:20px;background:#059669;color:#fff;padding:10px 16px;border-radius:8px;opacity:0;transition:opacity .3s;pointer-events:none}
.toast.show{opacity:1}
.toast.err{background:#dc2626}
</style></head>
<body>
<header>
  <h1>AI Web Builder</h1>
  <span class="warn">⚠ static password — local only</span>
  <span class="sp"></span>
  <a href="?action=logout">Log out</a>
</header>
<main>
  <aside>
    <h2>Files (<?= count($files) ?>)</h2>
    <?php foreach ($files as $f): ?>
      <a class="file" data-path="<?= htmlspecialchars($f['path'], ENT_QUOTES) ?>">
        <?= htmlspecialchars($f['path']) ?><span class="sz"><?= number_format($f['size']/1024,1) ?>K</span>
      </a>
    <?php endforeach; ?>
  </aside>
  <section>
    <div class="bar">
      <select id="model">
        <?php foreach ($models as $m): ?>
          <option value="<?= $m ?>"<?= $m===DEFAULT_MODEL?' selected':'' ?>><?= $m ?></option>
        <?php endforeach; ?>
      </select>
      <button class="ghost" id="btnReview" disabled>Review</button>
      <button class="ghost" id="btnRewrite" disabled>Rewrite</button>
      <label><input type="checkbox" id="phpOk"> allow PHP rewrite</label>
      <span style="flex:1"></span>
      <button class="apply" id="btnApply" disabled>Apply proposed</button>
    </div>
    <div class="editors">
      <div class="pane">
        <h3>Current</h3>
        <textarea id="current" readonly placeholder="Select a file on the left."></textarea>
      </div>
      <div class="pane">
        <h3>Proposed / review</h3>
        <textarea id="proposed" class="proposed" placeholder="Review output or proposed rewrite appears here."></textarea>
      </div>
    </div>
    <div id="status"></div>
  </section>
</main>
<div class="toast" id="toast"></div>

<script>
"use strict";
const CSRF = <?= json_encode($csrfTok) ?>;

const $ = id => document.getElementById(id);
const current = $('current'), proposed = $('proposed'), status = $('status');
const btnReview = $('btnReview'), btnRewrite = $('btnRewrite'), btnApply = $('btnApply');
const model = $('model'), phpOk = $('phpOk'), toast = $('toast');

let activePath = null;
let activeMode = null;

function setStatus(msg, kind) {
  status.textContent = msg || '';
  status.className = kind || '';
}
function showToast(msg, isErr) {
  toast.textContent = msg;
  toast.className = 'toast show' + (isErr ? ' err' : '');
  setTimeout(() => toast.className = 'toast', 2500);
}
function setBusy(b) {
  btnReview.disabled = b || !activePath;
  btnRewrite.disabled = b || !activePath;
  btnApply.disabled = b || activeMode !== 'rewrite' || !proposed.value.trim();
}
async function api(action, form) {
  const body = new URLSearchParams(form);
  body.set('csrf', CSRF);
  const r = await fetch('?action=' + action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  });
  const j = await r.json().catch(() => ({ ok: false, error: 'Bad JSON from server (HTTP ' + r.status + ')' }));
  if (!j.ok) throw new Error(j.error || 'Request failed');
  return j;
}

document.querySelectorAll('.file').forEach(el => {
  el.addEventListener('click', async () => {
    document.querySelectorAll('.file').forEach(x => x.classList.remove('active'));
    el.classList.add('active');
    activePath = el.dataset.path;
    activeMode = null;
    proposed.value = '';
    current.value = 'Loading…';
    setBusy(true);
    try {
      const r = await fetch('?action=read&file=' + encodeURIComponent(activePath));
      const j = await r.json();
      if (!j.ok) throw new Error(j.error);
      current.value = j.content;
      setStatus('Loaded ' + activePath + ' (' + j.size + ' bytes)');
    } catch (e) {
      current.value = '';
      setStatus(e.message, 'err');
    } finally {
      setBusy(false);
    }
  });
});

async function run(mode) {
  if (!activePath) return;
  setBusy(true);
  setStatus(mode === 'review' ? 'Reviewing… (may take minutes)' : 'Rewriting… (may take minutes)');
  proposed.value = '';
  const form = { file: activePath, mode, model: model.value, workers: '1' };
  if (phpOk.checked) form.php_ok = '1';
  try {
    const j = await api('ai', form);
    activeMode = mode;
    if (mode === 'review') {
      proposed.value = j.raw || '';
      setStatus('Review complete' + (j.word_count ? ' (' + j.word_count + ' words)' : ''));
    } else {
      proposed.value = j.proposed || '';
      setStatus('Rewrite ready — review, then Apply.');
    }
  } catch (e) {
    setStatus(e.message, 'err');
  } finally {
    setBusy(false);
  }
}
btnReview.addEventListener('click', () => run('review'));
btnRewrite.addEventListener('click', () => run('rewrite'));

btnApply.addEventListener('click', async () => {
  if (!activePath || activeMode !== 'rewrite' || !proposed.value.trim()) return;
  if (!confirm('Overwrite ' + activePath + ' with the proposed content?\n\nA backup will be created first.')) return;
  setBusy(true);
  setStatus('Writing…');
  try {
    const j = await api('apply', { file: activePath, content: proposed.value });
    current.value = proposed.value;
    setStatus('Written. Backup: ' + j.backup, 'ok');
    showToast('Saved', false);
  } catch (e) {
    setStatus(e.message, 'err');
    showToast(e.message, true);
  } finally {
    setBusy(false);
  }
});

proposed.addEventListener('input', () => {
  btnApply.disabled = !activePath || activeMode !== 'rewrite' || !proposed.value.trim();
});

setBusy(true);
<?php if ($selInit !== ''): ?>
document.querySelectorAll('.file').forEach(el => {
  if (el.dataset.path === <?= json_encode($selInit) ?>) el.click();
});
<?php endif; ?>
</script>
</body></html>