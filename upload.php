<?php
declare(strict_types=1);

/**
 * PHP File Uploader with Selectable Target Path
 * ------------------------------------------------------------
 * - Log in with a password.
 * - Browse or type a target directory (relative to BASE_DIR).
 * - Create new folders from the UI.
 * - Upload single or multiple files to the chosen path.
 * - Download or delete files.
 *
 * SECURITY WARNING:
 *   - Change UPLOAD_PASSWORD before deploying publicly.
 *   - Never allow uploading .php, .phtml, .phar, etc. unless you
 *     fully understand the risk (uploaded PHP = remote code execution).
 *   - Keep BASE_DIR outside the web root when possible, and serve
 *     files only through the ?download= handler below.
 * ------------------------------------------------------------
 */

// ============================================================
// CONFIGURATION
// ============================================================

/**
 * Root directory the user is allowed to browse and upload into.
 * All paths entered by the user are resolved *inside* this folder;
 * anything trying to escape it (via ".." or absolute paths) is rejected.
 */
const BASE_DIR = __DIR__ . '/files';

const UPLOAD_PASSWORD = '1';

const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20 MB

const ALLOWED_EXTENSIONS = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
    'pdf', 'txt', 'csv', 'md', 'json', 'xml',
    'zip', 'gz', 'tar',
    'mp3', 'mp4', 'webm', 'ogg',
    'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
];

const ALLOW_MULTIPLE    = true;
const OVERWRITE_EXISTING = false;

// ============================================================
// BOOTSTRAP
// ============================================================

session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if (!is_dir(BASE_DIR)) {
    if (!@mkdir(BASE_DIR, 0755, true) && !is_dir(BASE_DIR)) {
        http_response_code(500);
        exit('Base directory could not be created: ' . htmlspecialchars(BASE_DIR));
    }
}

$baseReal = realpath(BASE_DIR);
if ($baseReal === false) {
    http_response_code(500);
    exit('Base directory is not accessible.');
}

// ============================================================
// AUTHENTICATION
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
// PATH HANDLING
// ============================================================

/**
 * Normalize a user-supplied relative path.
 * Returns a clean relative path like "sub/dir" (no leading/trailing slash),
 * or '' for the base directory. Returns null if the path tries to escape
 * BASE_DIR.
 */
function normalizeRelativePath(string $raw): ?string
{
    // Convert backslashes, collapse slashes.
    $raw = str_replace('\\', '/', $raw);
    $raw = trim($raw);

    // Block null bytes just in case.
    if (str_contains($raw, "\0")) {
        return null;
    }

    // Split and process segments.
    $parts = [];
    foreach (explode('/', $raw) as $segment) {
        $segment = trim($segment);
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            // Going up cancels a previous segment, or fails if at root.
            if (empty($parts)) {
                return null;
            }
            array_pop($parts);
            continue;
        }
        // Reject weird characters in folder names.
        if (!preg_match('/^[A-Za-z0-9._\- ]+$/', $segment)) {
            return null;
        }
        $parts[] = $segment;
    }

    return implode('/', $parts);
}

/**
 * Resolve a relative path to an absolute path inside BASE_DIR.
 * Returns null if the resolved path is outside BASE_DIR.
 */
function resolveSafePath(string $relative): ?string
{
    global $baseReal;
    $relative = ltrim($relative, '/');
    $candidate = $baseReal . ($relative === '' ? '' : DIRECTORY_SEPARATOR . $relative);

    // realpath fails if path doesn't exist yet (e.g. a folder to be created).
    $check = realpath($candidate);
    if ($check === false) {
        // Walk up until an existing ancestor is found and validate that.
        $parent = dirname($candidate);
        while ($parent !== $baseReal && $parent !== '' && $parent !== DIRECTORY_SEPARATOR) {
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

    if ($check !== $baseReal && !str_starts_with($check, $baseReal . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $check;
}

/**
 * List subdirectories (relative paths) under BASE_DIR, for the dropdown.
 * Returns ['', 'sub', 'sub/deep', ...] with '' meaning the base.
 */
function listDirectories(string $baseReal, string $relative = ''): array
{
    $dirs = [$relative];
    $abs = $relative === '' ? $baseReal : $baseReal . DIRECTORY_SEPARATOR . $relative;
    if (!is_dir($abs)) {
        return $dirs;
    }
    foreach (scandir($abs) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $childAbs = $abs . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($childAbs)) {
            $childRel = $relative === '' ? $entry : $relative . '/' . $entry;
            $dirs = array_merge($dirs, listDirectories($baseReal, $childRel));
        }
    }
    return $dirs;
}

// ============================================================
// HELPERS
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
// HANDLE POST ACTIONS
// ============================================================

$results = [];
$flash   = '';

// --- Create folder ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_folder'])) {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $results[] = ['error' => 'Invalid CSRF token.'];
    } else {
        $parentRel = normalizeRelativePath((string)($_POST['parent_path'] ?? ''));
        $folderName = trim((string)$_POST['new_folder']);

        if ($parentRel === null) {
            $results[] = ['error' => 'Invalid parent path.'];
        } elseif ($folderName === '' || !preg_match('/^[A-Za-z0-9._\- ]+$/', $folderName)) {
            $results[] = ['error' => 'Invalid folder name (letters, numbers, . _ - and spaces only).'];
        } else {
            $newRel  = $parentRel === '' ? $folderName : $parentRel . '/' . $folderName;
            $abs     = resolveSafePath($newRel);
            if ($abs === null) {
                $results[] = ['error' => 'Folder path escapes base directory.'];
            } elseif (is_dir($abs)) {
                $results[] = ['error' => 'Folder already exists: ' . $newRel];
            } elseif (@mkdir($abs, 0755)) {
                $flash = 'Created folder: ' . $newRel;
            } else {
                $results[] = ['error' => 'Could not create folder: ' . $newRel];
            }
        }
    }
}

// --- Delete file ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $results[] = ['error' => 'Invalid CSRF token.'];
    } else {
        $dirRel  = normalizeRelativePath((string)($_POST['target_path'] ?? ''));
        $fileName = safeFilename((string)$_POST['delete_file']);

        if ($dirRel === null) {
            $results[] = ['error' => 'Invalid target path.'];
        } else {
            $dirAbs = resolveSafePath($dirRel);
            if ($dirAbs === null) {
                $results[] = ['error' => 'Path escapes base directory.'];
            } else {
                $fileAbs = $dirAbs . DIRECTORY_SEPARATOR . $fileName;
                $real    = realpath($fileAbs);
                if ($real && is_file($real) && str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) {
                    if (@unlink($real)) {
                        $flash = 'Deleted: ' . $fileName;
                    } else {
                        $results[] = ['error' => 'Could not delete: ' . $fileName];
                    }
                } else {
                    $results[] = ['error' => 'File not found: ' . $fileName];
                }
            }
        }
    }
}

// --- Upload files ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $results[] = ['error' => 'Invalid CSRF token.'];
    } else {
        $targetRel = normalizeRelativePath((string)($_POST['target_path'] ?? ''));
        if ($targetRel === null) {
            $results[] = ['error' => 'Invalid target path.'];
        } else {
            $targetAbs = resolveSafePath($targetRel);
            if ($targetAbs === null) {
                $results[] = ['error' => 'Target path escapes base directory.'];
            } elseif (!is_dir($targetAbs)) {
                if (!@mkdir($targetAbs, 0755, true)) {
                    $results[] = ['error' => 'Target folder does not exist and could not be created.'];
                }
            }

            if (is_dir($targetAbs)) {
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
                        @chmod($target, 0644);
                        $rel = $targetRel === '' ? basename($target) : $targetRel . '/' . basename($target);
                        $results[] = [
                            'name' => basename($target),
                            'ok'   => true,
                            'size' => formatBytes((int)$size),
                            'path' => $rel,
                            'url'  => '?download=' . rawurlencode($rel),
                        ];
                    } else {
                        $results[] = ['name' => $name, 'error' => 'Could not move uploaded file.'];
                    }
                }
            }
        }
    }
}

// ============================================================
// HANDLE DOWNLOAD
// ============================================================

if (isset($_GET['download'])) {
    $rel = normalizeRelativePath((string)$_GET['download']);
    if ($rel === null || $rel === '') {
        http_response_code(404);
        exit('File not found.');
    }
    $abs = resolveSafePath($rel);
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
// CURRENT FOLDER LISTING
// ============================================================

$currentRel = '';
if (isset($_GET['dir'])) {
    $maybe = normalizeRelativePath((string)$_GET['dir']);
    if ($maybe !== null) {
        $currentRel = $maybe;
    }
}

$currentAbs = resolveSafePath($currentRel);
if ($currentAbs === null || !is_dir($currentAbs)) {
    $currentRel = '';
    $currentAbs = $baseReal;
}

// Available folders (for dropdowns).
$allDirs = listDirectories($baseReal, '');

// Files in the current folder.
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

// Breadcrumb parts
$breadcrumbs = [];
if ($currentRel !== '') {
    $acc = '';
    foreach (explode('/', $currentRel) as $seg) {
        $acc = $acc === '' ? $seg : $acc . '/' . $seg;
        $breadcrumbs[] = ['name' => $seg, 'rel' => $acc];
    }
}

// ============================================================
// RENDER
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
    <p class="muted">Upload to any folder under <code><?= htmlspecialchars(BASE_DIR) ?></code></p>

    <?php if ($flash !== ''): ?>
        <div class="flash"><?= htmlspecialchars($flash) ?></div>
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
                <label class="field" for="target_path">Or type a path (relative to root)</label>
                <input type="text" id="target_path" name="target_path"
                       value="<?= htmlspecialchars($currentRel) ?>"
                       placeholder="e.g. images/2026 or leave empty for root">
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

    <!-- Browse current folder -->
    <div class="card">
        <h2>📂 Browsing: <code>/<?= htmlspecialchars($currentRel) ?></code></h2>
        <div class="breadcrumbs">
            <a href="?dir=">root</a>
            <?php foreach ($breadcrumbs as $bc): ?>
                / <a href="?dir=<?= rawurlencode($bc['rel']) ?>"><?= htmlspecialchars($bc['name']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($files)): ?>
            <p class="muted">No files here yet.</p>
        <?php else: ?>
            <table>
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

    <p class="muted" style="text-align:center;">
        Base folder: <code><?= htmlspecialchars(BASE_DIR) ?></code>
    </p>
</div>

<script>
    const drop = document.getElementById('drop');
    const input = document.getElementById('fileInput');
    const list = document.getElementById('fileList');
    const select = document.getElementById('target_select');
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

    // Selecting from dropdown fills the text input.
    select.addEventListener('change', () => {
        pathInput.value = select.value;
    });
</script>
</body>
</html>
