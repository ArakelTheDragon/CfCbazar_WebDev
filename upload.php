<?php
declare(strict_types=1);

/**
 * PHP File Uploader with Path Selector + Permission Repair
 * ------------------------------------------------------------
 * Features:
 *   - Password login
 *   - Choose target folder: pick from dropdown OR type any path
 *   - Create folders that don't exist (with configurable mode)
 *   - Auto-fix permissions on folders that exist but aren't writable
 *   - Upload single or multiple files
 *   - Download, delete, list
 *
 * IMPORTANT:
 *   - The web-server user (e.g. www-data) must have permission to
 *     create folders and chmod inside BASE_DIR. If PHP cannot fix
 *     permissions, this tool will tell you exactly which shell
 *     commands to run.
 *   - Never allow .php / .phtml / .htaccess in ALLOWED_EXTENSIONS.
 * ------------------------------------------------------------
 */

// ============================================================
// CONFIGURATION
// ============================================================

/** Root jail — users may only browse/upload inside this. */
const BASE_DIR = __DIR__ . '/files';

/** Login password. Set '' to disable auth (NOT recommended). */
const UPLOAD_PASSWORD = 'change-me-please';

/** Max bytes per file. */
const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB

/** Allowed file extensions (lowercase, no dot). [] = allow all. */
const ALLOWED_EXTENSIONS = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',
    'pdf', 'txt', 'csv', 'md', 'json', 'xml', 'yaml', 'yml',
    'zip', 'gz', 'tar', '7z',
    'mp3', 'wav', 'mp4', 'webm', 'ogg',
    'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'ttf', 'otf', 'woff', 'woff2',
];

const ALLOW_MULTIPLE     = true;
const OVERWRITE_EXISTING = false;

/**
 * Permissions applied when creating new directories.
 * 0755 = owner rwx, group rx, others rx  (typical for web-accessible)
 * 0775 = owner/group rwx, others rx     (shared hosting, group-writable)
 * 0777 = everyone rwx                   (last resort — avoid on public servers)
 */
const NEW_DIR_MODE = 0775;

/** Permissions applied to uploaded files. */
const NEW_FILE_MODE = 0644;

/**
 * If true: when a target directory exists but isn't writable, the
 * uploader will try to chmod it to NEW_DIR_MODE before failing.
 * Set to false if you don't want PHP changing permissions.
 */
const AUTO_FIX_PERMISSIONS = true;

// ============================================================
// BOOTSTRAP
// ============================================================

session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Create BASE_DIR if missing — try progressively permissive modes.
if (!is_dir(BASE_DIR)) {
    foreach ([NEW_DIR_MODE, 0755, 0777] as $mode) {
        if (@mkdir(BASE_DIR, $mode, true)) {
            @chmod(BASE_DIR, $mode);
            break;
        }
    }
}

$baseReal = realpath(BASE_DIR);
if ($baseReal === false) {
    http_response_code(500);
    exit(
        "Cannot access BASE_DIR: " . htmlspecialchars(BASE_DIR) . "\n" .
        "Create it manually and give the web-server user write access, e.g.:\n" .
        "  mkdir -p " . escapeshellarg(BASE_DIR) . "\n" .
        "  chown www-data:www-data " . escapeshellarg(BASE_DIR) . "\n" .
        "  chmod 775 " . escapeshellarg(BASE_DIR)
    );
}

// ============================================================
// AUTH
// ============================================================

$authError = '';

if (UPLOAD_PASSWORD !== '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (hash_equals(UPLOAD_PASSWORD, (string)$_POST['password'])) {
            $_SESSION['authed'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        $authError = 'Incorrect password.';
    }
    if (isset($_GET['logout'])) {
        unset($_SESSION['authed']);
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    if (empty($_SESSION['authed'])) {
        renderLoginPage($authError);
        exit;
    }
}

// ============================================================
// PATH UTILITIES
// ============================================================

/**
 * Normalize a user-supplied relative path.
 * Returns clean rel path ('sub/dir'), '' for root, or null on escape.
 */
function normalizeRelativePath(string $raw): ?string
{
    $raw = str_replace('\\', '/', $raw);
    $raw = trim($raw);

    if (str_contains($raw, "\0")) return null;

    $parts = [];
    foreach (explode('/', $raw) as $segment) {
        $segment = trim($segment);
        if ($segment === '' || $segment === '.') continue;
        if ($segment === '..') {
            if (empty($parts)) return null;
            array_pop($parts);
            continue;
        }
        // Allow letters, digits, dot, underscore, dash, space.
        if (!preg_match('/^[A-Za-z0-9._\- ]+$/', $segment)) return null;
        $parts[] = $segment;
    }
    return implode('/', $parts);
}

/**
 * Turn a relative path into an absolute path inside BASE_DIR,
 * verifying the result stays inside the jail even if it doesn't
 * exist yet.
 */
function resolveSafePath(string $relative): ?string
{
    global $baseReal;
    $relative = ltrim($relative, '/');
    $candidate = $baseReal . ($relative === '' ? '' : DIRECTORY_SEPARATOR . $relative);

    $real = realpath($candidate);
    if ($real !== false) {
        if ($real !== $baseReal && !str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $real;
    }

    // Walk up to first existing ancestor and check that.
    $parent = dirname($candidate);
    while ($parent !== '' && $parent !== DIRECTORY_SEPARATOR && $parent !== $baseReal) {
        $parentReal = realpath($parent);
        if ($parentReal !== false) {
            if ($parentReal !== $baseReal && !str_starts_with($parentReal, $baseReal . DIRECTORY_SEPARATOR)) {
                return null;
            }
            break;
        }
        $parent = dirname($parent);
    }
    return $candidate;
}

/**
 * Recursively list all subdirectories as relative paths.
 * Returns e.g. ['', 'images', 'images/2026', 'docs'].
 */
function listDirectories(string $baseReal, string $relative = ''): array
{
    $out = [$relative];
    $abs = $relative === '' ? $baseReal : $baseReal . DIRECTORY_SEPARATOR . $relative;
    if (!is_dir($abs)) return $out;

    foreach (scandir($abs) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $childAbs = $abs . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($childAbs)) {
            $childRel = $relative === '' ? $entry : $relative . '/' . $entry;
            $out = array_merge($out, listDirectories($baseReal, $childRel));
        }
    }
    return $out;
}

/**
 * Ensure a directory exists AND is writable, creating or chmod-ing
 * as needed. Returns [ok, message].
 */
function ensureWritableDirectory(string $absPath, bool $createIfMissing = true): array
{
    global $baseReal;

    // Safety: must remain inside base.
    $check = realpath($absPath);
    if ($check !== false) {
        if ($check !== $baseReal && !str_starts_with($check, $baseReal . DIRECTORY_SEPARATOR)) {
            return [false, 'Path escapes base directory.'];
        }
        $absPath = $check;
    }

    // 1) Create if missing.
    if (!is_dir($absPath)) {
        if (!$createIfMissing) {
            return [false, 'Directory does not exist and creation is disabled.'];
        }
        if (!@mkdir($absPath, NEW_DIR_MODE, true)) {
            $err = error_get_last()['message'] ?? 'unknown error';
            return [false, "Could not create directory: {$err}"];
        }
        @chmod($absPath, NEW_DIR_MODE);
    }

    // 2) Writable?
    if (is_writable($absPath)) {
        return [true, 'Directory is writable.'];
    }

    // 3) Try to fix permissions.
    if (AUTO_FIX_PERMISSIONS) {
        @chmod($absPath, NEW_DIR_MODE);
        clearstatcache(true, $absPath);
        if (is_writable($absPath)) {
            return [true, 'Permissions repaired on ' . $absPath];
        }
    }

    // 4) Still not writable — report precisely what to fix.
    $owner = @fileowner($absPath);
    $ownerName = function_exists('posix_getpwuid') && $owner !== false
        ? (posix_getpwuid($owner)['name'] ?? (string)$owner)
        : (string)$owner;
    $runningAs = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
        ? (posix_getpwuid(posix_geteuid())['name'] ?? (string)posix_geteuid())
        : (function_exists('get_current_user') ? get_current_user() : 'unknown');

    return [false,
        "Directory exists but is not writable by PHP. " .
        "Owner: {$ownerName}, PHP runs as: {$runningAs}. " .
        "Fix with: chown -R {$runningAs} " . escapeshellarg($absPath) . " && chmod -R " . decoct(NEW_DIR_MODE) . " " . escapeshellarg($absPath)
    ];
}

// ============================================================
// FILENAME / EXTENSION HELPERS
// ============================================================

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, 2) . ' ' . $units[$i];
}

function safeFilename(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
    $name = trim($name, '._');
    if ($name === '') $name = 'file_' . bin2hex(random_bytes(4));
    return $name;
}

function extensionOf(string $name): string
{
    return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

function isAllowedExtension(string $name): bool
{
    if (ALLOWED_EXTENSIONS === []) return true;
    return in_array(extensionOf($name), ALLOWED_EXTENSIONS, true);
}

function resolveTargetPath(string $dir, string $name): string
{
    $target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (OVERWRITE_EXISTING || !file_exists($target)) return $target;

    $ext  = pathinfo($name, PATHINFO_EXTENSION);
    $base = pathinfo($name, PATHINFO_FILENAME);
    $i = 1;
    do {
        $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR
                   . $base . '_' . $i++ . ($ext !== '' ? '.' . $ext : '');
    } while (file_exists($candidate));
    return $candidate;
}

// ============================================================
// ACTIONS
// ============================================================

$results = [];
$flash   = '';

// --- Create folder ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_folder'])) {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $results[] = ['error' => 'Invalid CSRF token.'];
    } else {
        $parentRel = normalizeRelativePath((string)($_POST['parent_path'] ?? ''));
        $name      = trim((string)$_POST['new_folder']);

        if ($parentRel === null) {
            $results[] = ['error' => 'Invalid parent path.'];
        } elseif ($name === '' || !preg_match('/^[A-Za-z0-9._\- ]+$/', $name)) {
            $results[] = ['error' => 'Invalid folder name.'];
        } else {
            $newRel = $parentRel === '' ? $name : $parentRel . '/' . $name;
            $abs    = resolveSafePath($newRel);
            if ($abs === null) {
                $results[] = ['error' => 'Path escapes base directory.'];
            } elseif (is_dir($abs)) {
                $results[] = ['error' => 'Folder already exists: ' . $newRel];
            } else {
                [$ok, $msg] = ensureWritableDirectory($abs, true);
                if ($ok) $flash = 'Created folder: ' . $newRel;
                else    $results[] = ['error' => $msg];
            }
        }
    }
}

// --- Delete file ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $results[] = ['error' => 'Invalid CSRF token.'];
    } else {
        $dirRel   = normalizeRelativePath((string)($_POST['target_path'] ?? ''));
        $fileName = safeFilename((string)$_POST['delete_file']);
        $dirAbs   = $dirRel === null ? null : resolveSafePath($dirRel);

        if ($dirAbs === null) {
            $results[] = ['error' => 'Invalid target path.'];
        } else {
            $fileAbs = $dirAbs . DIRECTORY_SEPARATOR . $fileName;
            $real    = realpath($fileAbs);
            if ($real && is_file($real) && str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) {
                if (@unlink($real)) $flash = 'Deleted: ' . $fileName;
                else $results[] = ['error' => 'Could not delete: ' . $fileName];
            } else {
                $results[] = ['error' => 'File not found: ' . $fileName];
            }
        }
    }
}

// --- Upload ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $results[] = ['error' => 'Invalid CSRF token.'];
    } else {
        $targetRel = normalizeRelativePath((string)($_POST['target_path'] ?? ''));
        if ($targetRel === null) {
            $results[] = ['error' => 'Invalid target path.'];
        } else {
            $targetAbs = resolveSafePath($targetRel);
            if ($targetAbs === null) {
                $results[] = ['error' => 'Target path escapes base directory.'];
            } else {
                // This is the permission-aware step: create-or-repair.
                [$ok, $msg] = ensureWritableDirectory($targetAbs, true);
                if (!$ok) {
                    $results[] = ['error' => $msg];
                } else {
                    if ($msg !== 'Directory is writable.') {
                        $flash = $msg;
                    }

                    $files = $_FILES['files'];
                    $count = is_array($files['name']) ? count($files['name']) : 1;

                    for ($i = 0; $i < $count; $i++) {
                        $name = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
                        $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                        $size = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];
                        $err  = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];

                        if ($err === UPLOAD_ERR_NO_FILE) continue;
                        if ($err !== UPLOAD_ERR_OK) {
                            $results[] = ['name' => $name, 'error' => 'Upload error code: ' . $err];
                            continue;
                        }
                        if ($size > MAX_FILE_SIZE) {
                            $results[] = ['name' => $name, 'error' => 'Exceeds max size (' . formatBytes(MAX_FILE_SIZE) . ').'];
                            continue;
                        }
                        $clean = safeFilename((string)$name);
                        if (!isAllowedExtension($clean)) {
                            $results[] = ['name' => $name, 'error' => 'File type not allowed.'];
                            continue;
                        }
                        if (!is_uploaded_file($tmp)) {
                            $results[] = ['name' => $name, 'error' => 'Not a valid uploaded file.'];
                            continue;
                        }

                        $target = resolveTargetPath($targetAbs, $clean);
                        if (@move_uploaded_file($tmp, $target)) {
                            @chmod($target, NEW_FILE_MODE);
                            $rel = $targetRel === '' ? basename($target) : $targetRel . '/' . basename($target);
                            $results[] = [
                                'name' => basename($target),
                                'ok'   => true,
                                'size' => formatBytes((int)$size),
                                'path' => $rel,
                                'url'  => '?download=' . rawurlencode($rel),
                            ];
                        } else {
                            $err = error_get_last()['message'] ?? 'unknown';
                            $results[] = ['name' => $name, 'error' => 'Could not move uploaded file: ' . $err];
                        }
                    }
                }
            }
        }
    }
}

// --- Download ---
if (isset($_GET['download'])) {
    $rel = normalizeRelativePath((string)$_GET['download']);
    $abs = ($rel === null || $rel === '') ? null : resolveSafePath($rel);
    if ($abs === null || !is_file($abs)) {
        http_response_code(404);
        exit('File not found.');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
    header('Content-Length: ' . filesize($abs));
    readfile($abs);
    exit;
}

// ============================================================
// CURRENT LISTING
// ============================================================

$currentRel = '';
if (isset($_GET['dir'])) {
    $maybe = normalizeRelativePath((string)$_GET['dir']);
    if ($maybe !== null) $currentRel = $maybe;
}
$currentAbs = resolveSafePath($currentRel);
if ($currentAbs === null || !is_dir($currentAbs)) {
    $currentRel = '';
    $currentAbs = $baseReal;
}

$allDirs = listDirectories($baseReal, '');

// Diagnose the currently-browsed folder so the UI can show the status.
$currentWritable = is_writable($currentAbs);
$currentOwner = @fileowner($currentAbs);
$currentOwnerName = (function_exists('posix_getpwuid') && $currentOwner !== false)
    ? (posix_getpwuid($currentOwner)['name'] ?? (string)$currentOwner)
    : (string)$currentOwner;
$phpUser = (function_exists('posix_getpwuid') && function_exists('posix_geteuid'))
    ? (posix_getpwuid(posix_geteuid())['name'] ?? (string)posix_geteuid())
    : (function_exists('get_current_user') ? get_current_user() : 'unknown');

// Files listing
$files = [];
foreach (scandir($currentAbs) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $full = $currentAbs . DIRECTORY_SEPARATOR . $entry;
    if (is_file($full)) {
        $files[] = [
            'name' => $entry,
            'size' => formatBytes((int)filesize($full)),
            'time' => date('Y-m-d H:i:s', (int)filemtime($full)),
        ];
    }
}
usort($files, fn($a, $b) => strcmp($b['time'], $a['time']));

// Breadcrumbs
$breadcrumbs = [];
if ($currentRel !== '') {
    $acc = '';
    foreach (explode('/', $currentRel) as $seg) {
        $acc = $acc === '' ? $seg : $acc . '/' . $seg;
        $breadcrumbs[] = ['name' => $seg, 'rel' => $acc];
    }
}

// ============================================================
// RENDER — LOGIN
// ============================================================

function renderLoginPage(string $error): void
{
    ?>
    <!doctype html>
    <html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Uploader — Sign in</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#f1f5f9; margin:0;
               display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .box { background:#fff; padding:32px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08);
               width:100%; max-width:360px; }
        h1 { margin:0 0 20px; font-size:1.4rem; }
        input[type=password] { width:100%; padding:10px 12px; border:1px solid #cbd5e1;
                               border-radius:8px; font-size:1rem; box-sizing:border-box; }
        button { width:100%; margin-top:16px; padding:10px 12px; border:0; border-radius:8px;
                 background:#2563eb; color:#fff; font-weight:600; font-size:1rem; cursor:pointer; }
        .err { color:#b91c1c; margin-top:12px; font-size:.9rem; }
    </style></head><body>
        <form class="box" method="post">
            <h1>🔒 Uploader</h1>
            <input type="password" name="password" placeholder="Password" autofocus required>
            <button type="submit">Sign in</button>
            <?php if ($error !== ''): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        </form>
    </body></html>
    <?php
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>File Uploader</title>
<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, Segoe UI, sans-serif;
           background:#f1f5f9; color:#0f172a; margin:0; padding:32px 16px; }
    .container { max-width: 900px; margin: 0 auto; }
    h1 { font-size: 1.6rem; margin: 0 0 8px; }
    h2 { font-size: 1.1rem; margin: 0 0 12px; }
    .card { background:#fff; border-radius:12px; padding:22px;
            box-shadow: 0 10px 30px rgba(0,0,0,.06); margin-bottom:20px; }
    .muted { color:#64748b; font-size:.9rem; }
    label.field { display:block; font-weight:600; font-size:.88rem; margin-bottom:6px; color:#334155; }
    select, input[type=text] {
        width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px;
        font-size:.95rem; background:#fff;
    }
    select:focus, input[type=text]:focus { outline:none; border-color:#2563eb; }
    .row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
    .row > div { flex:1 1 220px; }
    .drop { border:2px dashed #cbd5e1; border-radius:12px; padding:26px; text-align:center;
            background:#f8fafc; transition: border-color .15s, background .15s; margin-top:14px; }
    .drop.dragover { border-color:#2563eb; background:#eff6ff; }
    .drop input[type=file] { display:none; }
    .drop label { cursor:pointer; display:block; }
    .drop .big { font-size:1.05rem; font-weight:600; margin-bottom:4px; }
    .drop .small { color:#64748b; font-size:.85rem; }
    .btn { display:inline-block; border:0; border-radius:8px; padding:9px 18px;
           background:#2563eb; color:#fff; font-weight:600; font-size:.9rem; cursor:pointer; }
    .btn:hover { background:#1d4ed8; }
    .btn.secondary { background:#e2e8f0; color:#0f172a; }
    .btn.secondary:hover { background:#cbd5e1; }
    .btn.danger { background:#dc2626; }
    .btn.danger:hover { background:#b91c1c; }
    .filelist { margin-top:14px; font-size:.9rem; color:#334155; text-align:left; }
    .filelist ul { margin:6px 0 0; padding-left:20px; }
    .result { padding:10px 14px; border-radius:8px; margin-bottom:8px; font-size:.9rem; }
    .result.ok { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
    .result.err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .flash { padding:10px 14px; border-radius:8px; margin-bottom:12px; font-size:.9rem;
             background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    table { width:100%; border-collapse: collapse; font-size:.9rem; }
    th, td { text-align:left; padding:8px 10px; border-bottom:1px solid #e2e8f0; }
    th { background:#f8fafc; font-weight:600; color:#334155; }
    .actions { white-space:nowrap; }
    .actions form { display:inline; }
    .logout { float:right; font-size:.85rem; color:#64748b; text-decoration:none; }
    .logout:hover { color:#2563eb; }
    code { background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:.85rem; }
    .breadcrumbs { margin: 0 0 12px; font-size:.9rem; color:#475569; }
    .breadcrumbs a { color:#2563eb; text-decoration:none; }
    .breadcrumbs a:hover { text-decoration:underline; }
    .toolbar { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
    .status { font-size:.85rem; padding:6px 10px; border-radius:6px; display:inline-block; margin-top:6px; }
    .status.ok  { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
    .status.bad { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
</style>
</head>
<body>
<div class="container">
    <h1>
        📤 File Uploader
        <?php if (UPLOAD_PASSWORD !== ''): ?>
            <a class="logout" href="?logout=1">Sign out</a>
        <?php endif; ?>
    </h1>
    <p class="muted">Root: <code><?= htmlspecialchars(BASE_DIR) ?></code></p>

    <?php if ($flash !== ''): ?>
        <div class="flash">ℹ️ <?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
        <div class="card">
            <?php foreach ($results as $r): ?>
                <?php if (!empty($r['ok'])): ?>
                    <div class="result ok">
                        ✅ <strong><?= htmlspecialchars($r['name']) ?></strong>
                        (<?= htmlspecialchars($r['size']) ?>) → <code><?= htmlspecialchars($r['path']) ?></code>
                        · <a href="<?= htmlspecialchars($r['url']) ?>">Download</a>
                    </div>
                <?php else: ?>
                    <div class="result err">
                        ❌ <strong><?= htmlspecialchars($r['name'] ?? '') ?></strong>
                        — <?= htmlspecialchars($r['error']) ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Upload form -->
    <form class="card" method="post" enctype="multipart/form-data" id="uploadForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

        <h2>1 · Choose target folder</h2>

        <div class="row">
            <div>
                <label class="field" for="target_select">Pick an existing folder</label>
                <select id="target_select">
                    <?php foreach ($allDirs as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>"
                                <?= $d === $currentRel ? 'selected' : '' ?>>
                            <?= $d === '' ? '/ (root)' : '/' . htmlspecialchars($d) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="field" for="target_path">Or type a path (will be created if missing)</label>
                <input type="text" id="target_path" name="target_path"
                       value="<?= htmlspecialchars($currentRel) ?>"
                       placeholder="e.g. images/2026/march — leave empty for root">
            </div>
        </div>

        <h2 style="margin-top:22px;">2 · Select file(s)</h2>

        <div class="drop" id="drop">
            <input type="file" name="files[]" id="fileInput" <?= ALLOW_MULTIPLE ? 'multiple' : '' ?>>
            <label for="fileInput">
                <div class="big">Drop files here or click to browse</div>
                <div class="small">
                    Max <?= htmlspecialchars(formatBytes(MAX_FILE_SIZE)) ?> per file
                    <?php if (ALLOWED_EXTENSIONS !== []): ?>
                        · Allowed: <?= htmlspecialchars(implode(', ', ALLOWED_EXTENSIONS)) ?>
                    <?php endif; ?>
                </div>
            </label>
            <div class="filelist" id="fileList"></div>
        </div>

        <div class="toolbar">
            <button type="submit" class="btn">⬆ Upload</button>
            <button type="reset" class="btn secondary" id="clearBtn">Clear</button>
        </div>
    </form>

    <!-- Create folder -->
    <form class="card" method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        <input type="hidden" name="parent_path" value="<?= htmlspecialchars($currentRel) ?>">
        <h2>📁 Create a new folder</h2>
        <p class="muted" style="margin-top:0;">
            Will be created inside: <code>/<?= htmlspecialchars($currentRel) ?><?= $currentRel === '' ? '' : '/' ?></code>
        </p>
        <div class="row">
            <div>
                <label class="field" for="new_folder">Folder name</label>
                <input type="text" id="new_folder" name="new_folder" placeholder="my-folder" required>
            </div>
            <div style="flex:0 0 auto;">
                <button type="submit" class="btn">Create</button>
            </div>
        </div>
    </form>

    <!-- Browse -->
    <div class="card">
        <h2>📂 Browsing: <code>/<?= htmlspecialchars($currentRel) ?></code></h2>

        <div class="breadcrumbs">
            <a href="?dir=">root</a>
            <?php foreach ($breadcrumbs as $bc): ?>
                / <a href="?dir=<?= rawurlencode($bc['rel']) ?>"><?= htmlspecialchars($bc['name']) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="status <?= $currentWritable ? 'ok' : 'bad' ?>">
            <?php if ($currentWritable): ?>
                ✅ Writable by PHP (user: <?= htmlspecialchars($phpUser) ?>)
            <?php else: ?>
                ⚠️ NOT writable — owner: <?= htmlspecialchars($currentOwnerName) ?>,
                PHP runs as: <?= htmlspecialchars($phpUser) ?>.
                <?php if (AUTO_FIX_PERMISSIONS): ?>
                    Upload will attempt to chmod it to <?= decoct(NEW_DIR_MODE) ?>.
                <?php else: ?>
                    Fix manually: <code>chown <?= htmlspecialchars($phpUser) ?> <?= htmlspecialchars($currentAbs) ?> &amp;&amp; chmod <?= decoct(NEW_DIR_MODE) ?> <?= htmlspecialchars($currentAbs) ?></code>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (empty($files)): ?>
            <p class="muted" style="margin-top:14px;">No files here yet.</p>
        <?php else: ?>
            <table style="margin-top:14px;">
                <thead>
                    <tr><th>Name</th><th>Size</th><th>Uploaded</th><th class="actions">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($files as $f):
                    $rel = $currentRel === '' ? $f['name'] : $currentRel . '/' . $f['name'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($f['name']) ?></td>
                        <td><?= htmlspecialchars($f['size']) ?></td>
                        <td><?= htmlspecialchars($f['time']) ?></td>
                        <td class="actions">
                            <a href="?download=<?= rawurlencode($rel) ?>">Download</a>
                            &nbsp;·&nbsp;
                            <form method="post" onsubmit="return confirm('Delete <?= htmlspecialchars($f['name'], ENT_QUOTES) ?>?');" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                <input type="hidden" name="target_path" value="<?= htmlspecialchars($currentRel) ?>">
                                <input type="hidden" name="delete_file" value="<?= htmlspecialchars($f['name']) ?>">
                                <button type="submit" class="btn danger" style="padding:4px 10px; font-size:.8rem;">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    const drop      = document.getElementById('drop');
    const input     = document.getElementById('fileInput');
    const list      = document.getElementById('fileList');
    const select    = document.getElementById('target_select');
    const pathInput = document.getElementById('target_path');

    function refreshList() {
        if (!input.files || !input.files.length) { list.innerHTML = ''; return; }
        const items = [...input.files].map(f =>
            `<li>${f.name} <em style="color:#64748b">(${(f.size/1024).toFixed(1)} KB)</em></li>`
        ).join('');
        list.innerHTML = `<strong>${input.files.length} file(s) selected:</strong><ul>${items}</ul>`;
    }
    input.addEventListener('change', refreshList);

    ['dragenter','dragover'].forEach(ev =>
        drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('dragover'); })
    );
    ['dragleave','drop'].forEach(ev =>
        drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('dragover'); })
    );
    drop.addEventListener('drop', e => {
        if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; refreshList(); }
    });

    document.getElementById('clearBtn').addEventListener('click', () => {
        input.value = ''; refreshList();
    });

    select.addEventListener('change', () => { pathInput.value = select.value; });
</script>
</body>
</html>
