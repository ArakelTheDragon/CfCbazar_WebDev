<?php
// /diy/sky-fix/admin.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (!is_file($reusablePath)) {
    http_response_code(500);
    exit('Error: /includes/reusable.php library missing.');
}
require_once $reusablePath;
require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';

$NEED_SESSION = true;
$NEED_LOGIN   = false; // Login is not required for this page.
$NEED_CART    = false;
$NEED_ADMIN   = false;

enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

/* Login is intentionally not required for this page. */

$articlesFile = __DIR__ . '/articles.json';
$articleDir   = __DIR__;
$reserved     = ['index', 'admin', 'articles'];

if (empty($_SESSION['skyfix_csrf']) || !is_string($_SESSION['skyfix_csrf'])) {
    $_SESSION['skyfix_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['skyfix_csrf'];

function sf_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sf_verify_csrf(): void
{
    $expected = $_SESSION['skyfix_csrf'] ?? '';
    $submitted = $_POST['csrf'] ?? '';
    if (!is_string($expected) || $expected === '' || !is_string($submitted) || !hash_equals($expected, $submitted)) {
        http_response_code(400);
        exit('Invalid CSRF token. Reload the page and try again.');
    }
}

function sf_slugify(string $value): string
{
    $value = strtolower(trim($value));
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function sf_valid_slug(string $slug, array $reserved): bool
{
    return $slug !== ''
        && strlen($slug) <= 80
        && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1
        && !in_array($slug, $reserved, true);
}

/** Only permit local absolute paths or ordinary HTTP(S) URLs for optional links. */
function sf_validate_link(string $link): bool
{
    $link = trim($link);
    if ($link === '') {
        return true;
    }
    if (preg_match('/[\x00-\x20\\]/', $link)) {
        return false;
    }
    if (str_starts_with($link, '//')) {
        return false;
    }
    if (str_starts_with($link, '/')) {
        return !str_starts_with($link, '/\\');
    }
    $parts = parse_url($link);
    return is_array($parts)
        && isset($parts['scheme'], $parts['host'])
        && in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true);
}

/**
 * Metadata is stored as a base64 JSON marker in generated PHP files. This
 * avoids executing article files just to read metadata and supports safe parsing.
 */
function sf_read_meta(string $file): ?array
{
    if (!is_file($file) || !is_readable($file)) {
        return null;
    }
    $handle = @fopen($file, 'rb');
    if ($handle === false) {
        return null;
    }
    $line = fgets($handle);
    // The marker is written on line 2, immediately after the opening PHP tag.
    if ($line !== "<?php\n") {
        fclose($handle);
        return null;
    }
    $line = fgets($handle);
    fclose($handle);
    if (!is_string($line) || !preg_match('/^\/\/ SKYFIX_META_BASE64:([A-Za-z0-9+\/=]+)\s*$/', trim($line), $match)) {
        return null;
    }
    $json = base64_decode($match[1], true);
    if ($json === false) {
        return null;
    }
    try {
        $meta = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($meta) || !isset($meta['title'], $meta['content'])) {
        return null;
    }
    $meta['_file'] = basename($file, '.php');
    return $meta;
}

/** Return metadata for generated article pages only, not arbitrary PHP files. */
function sf_scan_articles(string $dir, array $reserved): array
{
    $articles = [];
    foreach (glob($dir . '/*.php') ?: [] as $file) {
        $slug = basename($file, '.php');
        if (!sf_valid_slug($slug, $reserved)) {
            continue;
        }
        $meta = sf_read_meta($file);
        if ($meta !== null) {
            $meta['slug'] = $slug;
            $articles[$slug] = $meta;
        }
    }
    uasort($articles, static function (array $a, array $b): int {
        return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });
    return $articles;
}

/** Atomically write an article page, including metadata that can be parsed safely. */
function sf_write_article(string $path, array $meta): bool
{
    try {
        $json = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return false;
    }
    $encodedMeta = base64_encode($json);
    $title = (string)($meta['title'] ?? '');
    $content = (string)($meta['content'] ?? '');
    $url = '/diy/sky-fix/' . basename($path, '.php') . '.php';

    // Export values as PHP literals so arbitrary content cannot break PHP syntax.
    $titleLiteral = var_export($title, true);
    $contentLiteral = var_export($content, true);
    $urlLiteral = var_export($url, true);

    $source = "<?php\n"
        . '// SKYFIX_META_BASE64:' . $encodedMeta . "\n"
        . "// Auto-generated by /diy/sky-fix/admin.php.\n"
        . '$articleTitle = ' . $titleLiteral . ";\n"
        . '$articleContent = ' . $contentLiteral . ";\n"
        . '$articleUrl = ' . $urlLiteral . ";\n"
        . <<<'PHP'

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (!is_file($reusablePath)) {
    http_response_code(500);
    exit('Site library missing.');
}
require_once $reusablePath;
require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';

$NEED_SESSION = true;
$NEED_LOGIN = false;
$NEED_CART = false;
$NEED_ADMIN = false;
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

$url = $articleUrl;
$parent_url = '/diy/sky-fix/';
$title = $articleTitle;
$template = 'Generic';
$meta_title = $articleTitle . ' — CfCbazar Sky Fix';
$plainDescription = trim(preg_replace('/\s+/', ' ', $articleContent) ?? $articleContent);
$meta_desc = function_exists('mb_substr') ? mb_substr($plainDescription, 0, 160) : substr($plainDescription, 0, 160);

if (function_exists('trackVisit')) {
    trackVisit($url, $parent_url, $title, $template, $meta_title, $meta_desc);
}
render_header($title);
echo '<main class="content"><div class="container">';
echo '<h1>' . htmlspecialchars($articleTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>';
echo '<div class="card">' . nl2br(htmlspecialchars($articleContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div>';
echo '<p style="margin-top:20px"><a href="/diy/sky-fix/index.php">&larr; Back to Sky Fix Center</a></p>';
echo '</div></main>';
if (function_exists('cfc_footer')) {
    cfc_footer('https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/sky-fix', $articleTitle . ' Source Code');
}
include_footer();
close_database();
PHP;

    $directory = dirname($path);
    $temp = @tempnam($directory, '.skyfix_');
    if ($temp === false) {
        return false;
    }
    $written = @file_put_contents($temp, $source, LOCK_EX);
    if ($written === false || $written !== strlen($source)) {
        @unlink($temp);
        return false;
    }
    @chmod($temp, 0644);
    if (!@rename($temp, $path)) {
        @unlink($temp);
        return false;
    }
    return true;
}

/** Save a JSON index atomically; report failures instead of silently ignoring them. */
function sf_save_index(string $file, array $articles): bool
{
    $json = json_encode(array_values($articles), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $temp = @tempnam(dirname($file), '.skyfix_idx_');
    if ($temp === false) {
        return false;
    }
    $written = @file_put_contents($temp, $json, LOCK_EX);
    if ($written === false || $written !== strlen($json)) {
        @unlink($temp);
        return false;
    }
    @chmod($temp, 0644);
    if (!@rename($temp, $file)) {
        @unlink($temp);
        return false;
    }
    return true;
}

/** Rebuild index from recognized generated pages; return false if saving fails. */
function sf_rebuild_index(string $dir, string $indexFile, array $reserved): bool
{
    $list = [];
    foreach (glob($dir . '/*.php') ?: [] as $file) {
        $slug = basename($file, '.php');
        if (!sf_valid_slug($slug, $reserved)) {
            continue;
        }
        $meta = sf_read_meta($file);
        if ($meta === null) {
            continue;
        }
        $list[] = [
            'id' => $meta['id'] ?? $slug,
            'title' => $meta['title'] ?? $slug,
            'content' => $meta['content'] ?? '',
            'link' => $meta['link'] ?? ('/diy/sky-fix/' . $slug . '.php'),
            'created' => $meta['created'] ?? '',
            'updated' => $meta['updated'] ?? '',
        ];
    }
    usort($list, static fn(array $a, array $b): int => strcasecmp((string)$a['title'], (string)$b['title']));
    return sf_save_index($indexFile, $list);
}

function sf_redirect_message(string $message): never
{
    header('Location: /diy/sky-fix/admin.php?ok=' . rawurlencode($message), true, 303);
    exit;
}

$flash = null;
$action = (string)($_POST['action'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    sf_verify_csrf();

    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $slug = sf_slugify((string)($_POST['slug'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $link = trim((string)($_POST['link'] ?? ''));

        if ($title === '' || $content === '') {
            $flash = ['type' => 'err', 'msg' => 'Title and content are required.'];
        } elseif (!sf_valid_slug($slug, $reserved)) {
            $flash = ['type' => 'err', 'msg' => 'Invalid or reserved slug. Use letters, numbers, and single hyphens only.'];
        } elseif (is_file($articleDir . '/' . $slug . '.php')) {
            $flash = ['type' => 'err', 'msg' => 'A file with that slug already exists. Choose another slug.'];
        } elseif (!sf_validate_link($link)) {
            $flash = ['type' => 'err', 'msg' => 'The optional link must be a local path or an HTTP(S) URL.'];
        } else {
            $now = gmdate('c');
            $meta = [
                'id' => $slug,
                'title' => $title,
                'content' => $content,
                'link' => $link !== '' ? $link : '/diy/sky-fix/' . $slug . '.php',
                'created' => $now,
                'updated' => $now,
            ];
            if (sf_write_article($articleDir . '/' . $slug . '.php', $meta)) {
                if (!sf_rebuild_index($articleDir, $articlesFile, $reserved)) {
                    $flash = ['type' => 'err', 'msg' => 'Article page was created, but articles.json could not be updated. Check directory permissions.'];
                } else {
                    sf_redirect_message('Created ' . $slug . '.php');
                }
            } else {
                $flash = ['type' => 'err', 'msg' => 'Failed to write article file. Check directory permissions.'];
            }
        }
    } elseif ($action === 'update') {
        $slug = sf_slugify((string)($_POST['slug'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $link = trim((string)($_POST['link'] ?? ''));
        $path = $articleDir . '/' . $slug . '.php';
        $old = sf_valid_slug($slug, $reserved) && is_file($path) ? sf_read_meta($path) : null;

        if ($old === null) {
            $flash = ['type' => 'err', 'msg' => 'Unknown article or this file is not a managed Sky Fix article.'];
        } elseif ($title === '' || $content === '') {
            $flash = ['type' => 'err', 'msg' => 'Title and content are required.'];
        } elseif (!sf_validate_link($link)) {
            $flash = ['type' => 'err', 'msg' => 'The optional link must be a local path or an HTTP(S) URL.'];
        } else {
            $meta = [
                'id' => $old['id'] ?? $slug,
                'title' => $title,
                'content' => $content,
                'link' => $link !== '' ? $link : '/diy/sky-fix/' . $slug . '.php',
                'created' => $old['created'] ?? gmdate('c'),
                'updated' => gmdate('c'),
            ];
            if (sf_write_article($path, $meta)) {
                if (!sf_rebuild_index($articleDir, $articlesFile, $reserved)) {
                    $flash = ['type' => 'err', 'msg' => 'Article page was updated, but articles.json could not be updated. Check directory permissions.'];
                } else {
                    sf_redirect_message('Updated ' . $slug . '.php');
                }
            } else {
                $flash = ['type' => 'err', 'msg' => 'Failed to update article file. Check directory permissions.'];
            }
        }
    } elseif ($action === 'delete') {
        $slug = sf_slugify((string)($_POST['slug'] ?? ''));
        $path = $articleDir . '/' . $slug . '.php';
        $meta = sf_valid_slug($slug, $reserved) && is_file($path) ? sf_read_meta($path) : null;

        if ($meta === null) {
            $flash = ['type' => 'err', 'msg' => 'Unknown article or this file is not a managed Sky Fix article.'];
        } elseif (!@unlink($path)) {
            $flash = ['type' => 'err', 'msg' => 'Could not delete the article page. Check directory permissions.'];
        } elseif (!sf_rebuild_index($articleDir, $articlesFile, $reserved)) {
            $flash = ['type' => 'err', 'msg' => 'The article page was deleted, but articles.json could not be updated. Check directory permissions.'];
        } else {
            sf_redirect_message('Deleted ' . $slug . '.php');
        }
    } else {
        $flash = ['type' => 'err', 'msg' => 'Unknown action.'];
    }
}

if (isset($_GET['ok'])) {
    $flash = ['type' => 'ok', 'msg' => (string)$_GET['ok']];
}

$editing = null;
$editSlug = sf_slugify((string)($_GET['edit'] ?? ''));
if (sf_valid_slug($editSlug, $reserved)) {
    $candidate = $articleDir . '/' . $editSlug . '.php';
    $editing = sf_read_meta($candidate);
    if ($editing !== null) {
        $editing['slug'] = $editSlug;
    }
}

$articles = sf_scan_articles($articleDir, $reserved);
render_header('Sky Fix — Admin');
?>
<style>
.sf-admin{max-width:960px;margin:0 auto}.sf-flash{padding:10px 14px;border-radius:8px;margin:14px 0;font-size:.92rem}.sf-flash.ok{background:#e6f7ec;border:1px solid #a3e0b6;color:#14532d}.sf-flash.err{background:#fdecec;border:1px solid #f5b5b5;color:#7f1d1d}.sf-panel{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:18px 0;box-shadow:0 2px 6px rgba(0,0,0,.05)}.sf-panel h2{margin:0 0 12px;color:#0077cc;font-size:1.1rem}.sf-row{display:flex;gap:12px;flex-wrap:wrap}.sf-row>*{flex:1 1 260px}.sf-field{display:flex;flex-direction:column;margin-bottom:12px}.sf-field label{font-size:.85rem;color:#555;margin-bottom:4px;font-weight:600}.sf-field input[type=text],.sf-field textarea{padding:10px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;width:100%;box-sizing:border-box}.sf-field textarea{min-height:160px;resize:vertical}.sf-hint{font-size:.78rem;color:#777;margin-top:4px}.sf-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}.sf-btn{display:inline-block;padding:9px 16px;border-radius:8px;border:1px solid #0077cc;background:#0077cc;color:#fff;text-decoration:none;cursor:pointer;font:inherit;font-weight:600}.sf-btn:hover{background:#005fa3}.sf-btn.secondary{background:#fff;color:#0077cc}.sf-btn.secondary:hover{background:#eef6ff}.sf-btn.danger{background:#b91c1c;border-color:#b91c1c}.sf-btn.danger:hover{background:#991b1b}.sf-table{width:100%;border-collapse:collapse;font-size:.9rem}.sf-table th,.sf-table td{text-align:left;padding:10px 8px;border-bottom:1px solid #eee;vertical-align:top}.sf-table th{color:#555;font-weight:600;font-size:.8rem;text-transform:uppercase;letter-spacing:.03em}.sf-table td form{display:inline}.sf-empty{text-align:center;color:#777;padding:20px}.sf-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.72rem;font-weight:700}.sf-badge.admin{background:#fff7d6;color:#7a5b00;border:1px solid #f1d873}.sf-table-wrap{overflow-x:auto}
</style>
<main class="content"><div class="container sf-admin">
    <h1>Sky Fix — Admin <span class="sf-badge admin">ADMIN</span></h1>
    <p style="color:#666">Create, edit, or delete Sky Fix articles. Managed articles are standalone PHP files in <code>/diy/sky-fix/</code>; <code>articles.json</code> is regenerated from managed article files.</p>

    <?php if ($flash !== null): ?>
        <div class="sf-flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= sf_h($flash['msg']) ?></div>
    <?php endif; ?>

    <section class="sf-panel">
        <h2><?= $editing !== null ? 'Edit article — ' . sf_h($editing['slug']) : 'Create new article' ?></h2>
        <form method="post" action="/diy/sky-fix/admin.php">
            <input type="hidden" name="csrf" value="<?= sf_h($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editing !== null ? 'update' : 'create' ?>">
            <div class="sf-row">
                <div class="sf-field">
                    <label for="title">Title</label>
                    <input id="title" name="title" type="text" maxlength="200" required value="<?= sf_h($editing['title'] ?? '') ?>">
                </div>
                <div class="sf-field">
                    <label for="slug">Slug (filename)</label>
                    <input id="slug" name="slug" type="text" maxlength="80" required value="<?= sf_h($editing['slug'] ?? '') ?>" <?= $editing !== null ? 'readonly' : '' ?> pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*">
                    <div class="sf-hint">Letters, numbers, and hyphens only. The file will be <code>&lt;slug&gt;.php</code>. Slugs are locked while editing.</div>
                </div>
            </div>
            <div class="sf-field">
                <label for="content">Summary / content</label>
                <textarea id="content" name="content" required><?= sf_h($editing['content'] ?? '') ?></textarea>
                <div class="sf-hint">Content is displayed as escaped text with line breaks; HTML is not executed.</div>
            </div>
            <div class="sf-field">
                <label for="link">Optional “Read more” link</label>
                <input id="link" name="link" type="text" maxlength="2048" placeholder="Leave blank to use the article page URL" value="<?= sf_h($editing['link'] ?? '') ?>">
                <div class="sf-hint">Only local paths and HTTP(S) URLs are accepted.</div>
            </div>
            <div class="sf-actions">
                <?php if ($editing !== null): ?>
                    <button class="sf-btn" type="submit">Save changes</button>
                    <a class="sf-btn secondary" href="/diy/sky-fix/admin.php">Cancel</a>
                <?php else: ?>
                    <button class="sf-btn" type="submit">Create article</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="sf-panel">
        <h2>Existing articles (<?= count($articles) ?>)</h2>
        <?php if ($articles === []): ?>
            <p class="sf-empty">No managed articles found. Use the form above to create one.</p>
        <?php else: ?>
            <div class="sf-table-wrap"><table class="sf-table">
                <thead><tr><th>Title</th><th>File</th><th>Updated</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($articles as $slug => $article): ?>
                    <?php
                    $preview = (string)($article['content'] ?? '');
                    if (function_exists('mb_strimwidth')) {
                        $preview = mb_strimwidth($preview, 0, 120, '…', 'UTF-8');
                    } else {
                        $preview = substr($preview, 0, 120) . (strlen($preview) > 120 ? '…' : '');
                    }
                    ?>
                    <tr>
                        <td><strong><?= sf_h($article['title'] ?? $slug) ?></strong><br><span style="color:#777;font-size:.82rem"><?= sf_h($preview) ?></span></td>
                        <td><code><?= sf_h($slug) ?>.php</code></td>
                        <td><?= sf_h($article['updated'] ?? '') ?></td>
                        <td>
                            <a class="sf-btn secondary" style="padding:6px 10px" href="/diy/sky-fix/admin.php?edit=<?= rawurlencode($slug) ?>">Edit</a>
                            <a class="sf-btn secondary" style="padding:6px 10px" href="/diy/sky-fix/<?= rawurlencode($slug) ?>.php" target="_blank" rel="noopener">View</a>
                            <form method="post" action="/diy/sky-fix/admin.php" onsubmit="return confirm('Delete this article file? This cannot be undone.');">
                                <input type="hidden" name="csrf" value="<?= sf_h($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="slug" value="<?= sf_h($slug) ?>">
                                <button class="sf-btn danger" style="padding:6px 10px" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <p style="text-align:center;margin:20px 0"><a href="/diy/sky-fix/index.php" class="sf-btn secondary">&larr; Back to Sky Fix Center</a></p>
</div></main>
<?php
include_footer();
close_database();
