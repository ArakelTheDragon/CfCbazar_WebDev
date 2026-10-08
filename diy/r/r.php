<?php
/**
 * CfCbazar URL Shortener
 * File: /diy/r/index.php
 *
 * Short format: https://cfcbazar.42web.io/r.php?go={id}
 * Optional: campaign, launch_time, description
 * UI: expandable list per link with stats
 */

declare(strict_types=1);

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (!is_readable($reusablePath)) {
    http_response_code(500);
    exit('Error: /includes/reusable.php library missing.');
}
require_once $reusablePath;

enforce_https();
require_database_connection();
session_check();

$email = null;
$is_logged_in = is_logged_in($email);

function cfc_valid_http_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower($parts['scheme']);
    return $scheme === 'http' || $scheme === 'https';
}

function cfc_normalize_url(string $url): string
{
    $url = trim($url);
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

$baseShort = 'https://cfcbazar.42web.io/r.php?go=';

$shortened = '';
$error = '';
$successMeta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['longurl'])) {
    $token = $_POST['csrf_token'] ?? '';
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        $error = 'Invalid CSRF token. Please reload and try again.';
    } elseif (!$is_logged_in || !$email) {
        $error = 'Please log in to create links.';
    } else {
        $longUrl = cfc_normalize_url((string) $_POST['longurl']);
        $campaign = trim((string) ($_POST['campaign'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $launchRaw = trim((string) ($_POST['launch_time'] ?? ''));

        $campaign = $campaign !== '' ? mb_substr($campaign, 0, 100) : null;
        $description = $description !== '' ? mb_substr($description, 0, 2000) : null;

        $launchTime = null;
        if ($launchRaw !== '') {
            $ts = strtotime(str_replace('T', ' ', $launchRaw));
            if ($ts === false) {
                $error = 'Invalid launch time.';
            } else {
                $launchTime = date('Y-m-d H:i:s', $ts);
            }
        }

        if ($error === '') {
            if ($longUrl === '') {
                $error = 'Please enter a URL.';
            } elseif (!cfc_valid_http_url($longUrl)) {
                $error = 'Please enter a valid http(s) URL.';
            } else {
                $id = 0;
                $ins = $conn->prepare('
                    INSERT INTO short_links (`long`, `short`, clicks, email, campaign, launch_time, description)
                    VALUES (?, ?, 0, ?, ?, ?, ?)
                ');
                if ($ins) {
                    $placeholder = '';
                    $ins->bind_param(
                        'ssssss',
                        $longUrl,
                        $placeholder,
                        $email,
                        $campaign,
                        $launchTime,
                        $description
                    );
                    if ($ins->execute()) {
                        $id = (int) $ins->insert_id;
                        $short = $baseShort . $id;
                        $upd = $conn->prepare('UPDATE short_links SET `short` = ? WHERE id = ?');
                        if ($upd) {
                            $upd->bind_param('si', $short, $id);
                            $upd->execute();
                            $upd->close();
                        }
                        $deduct = $conn->prepare('
                            UPDATE workers
                            SET tokens_earned = tokens_earned - 0.01
                            WHERE email = ? AND tokens_earned >= 0.01
                        ');
                        if ($deduct) {
                            $deduct->bind_param('s', $email);
                            $deduct->execute();
                            $deduct->close();
                        }
                    } else {
                        $error = 'Could not create link. Ensure columns campaign, launch_time, description exist on short_links.';
                    }
                    $ins->close();
                } else {
                    $error = 'Database prepare failed. Add campaign, launch_time, description columns if missing.';
                }

                if ($id > 0 && $error === '') {
                    $shortened = $baseShort . $id;
                    $successMeta = [
                        'campaign' => $campaign,
                        'launch_time' => $launchTime,
                        'description' => $description,
                    ];
                } elseif ($error === '') {
                    $error = 'Could not create short link.';
                }
            }
        }
    }
}

// Load links for expandable list
$links = [];
if ($email) {
    $stmt = $conn->prepare('
        SELECT id, `long`, clicks, campaign, launch_time, description, `short`
        FROM short_links
        WHERE email = ?
        ORDER BY id DESC
        LIMIT 100
    ');
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $links[] = $row;
            }
        }
        $stmt->close();
    }
}

if (function_exists('trackVisit')) {
    trackVisit('diy-url-shortener');
}

$title = 'URL Shortener – CfCbazar';
include_header($title);
include_menu();
if (function_exists('showAdvertPopup')) {
    showAdvertPopup();
}
if (function_exists('render_top_userbar')) {
    render_top_userbar();
}

$csrf = function_exists('csrf_token') ? csrf_token() : ($_SESSION['csrf_token'] ?? '');
?>
<style>
.cfc-link-list { list-style: none; padding: 0; margin: 0; }
.cfc-link-item {
  border: 1px solid #e0e0e0;
  border-radius: 10px;
  margin-bottom: 0.75rem;
  background: #fff;
  overflow: hidden;
}
.cfc-link-item summary {
  cursor: pointer;
  padding: 0.9rem 1rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1rem;
  align-items: center;
  font-weight: 600;
  list-style: none;
  background: #f7faf8;
}
.cfc-link-item summary::-webkit-details-marker { display: none; }
.cfc-link-item summary::before {
  content: '▸';
  display: inline-block;
  margin-right: 0.4rem;
  transition: transform .15s;
  color: #28a745;
}
.cfc-link-item[open] summary::before { transform: rotate(90deg); }
.cfc-link-item[open] summary { border-bottom: 1px solid #e8e8e8; }
.cfc-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  background: #e8f5e9;
  color: #1b5e20;
}
.cfc-link-body { padding: 1rem; }
.cfc-link-body table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.cfc-link-body th, .cfc-link-body td {
  text-align: left;
  padding: 0.4rem 0.5rem;
  border-bottom: 1px solid #f0f0f0;
  vertical-align: top;
  word-break: break-word;
}
.cfc-link-body th { width: 8rem; color: #555; }
.cfc-clicks-table { margin-top: 0.75rem; font-size: 0.8rem; }
.cfc-clicks-table th { background: #f5f5f5; }
.shorten-form .form-group { margin-bottom: 0.85rem; }
.shorten-form label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
.shorten-form input, .shorten-form textarea {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  padding: 0.5rem 0.65rem;
  border: 1px solid #ccc;
  border-radius: 6px;
}
</style>

<main class="container">
    <section class="card">
        <h1>URL Shortener</h1>
        <p>Short links use <code>https://cfcbazar.42web.io/r.php?go=ID</code>. Add optional campaign, launch time, and description — expand a link below to see full stats.</p>

        <form method="post" class="shorten-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-group">
                <label for="longurl">Destination URL *</label>
                <input type="url" name="longurl" id="longurl" placeholder="https://example.com" required>
            </div>
            <div class="form-group">
                <label for="campaign">Campaign (optional)</label>
                <input type="text" name="campaign" id="campaign" maxlength="100" placeholder="e.g. spring-sale-2026">
            </div>
            <div class="form-group">
                <label for="launch_time">Launch time (optional)</label>
                <input type="datetime-local" name="launch_time" id="launch_time">
            </div>
            <div class="form-group">
                <label for="description">Description (optional)</label>
                <textarea name="description" id="description" rows="3" maxlength="2000" placeholder="Notes for this link"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Shorten URL</button>
        </form>

        <?php if ($shortened): ?>
            <div class="message success" style="margin-top:1rem;">
                Short link created:
                <a href="<?= e($shortened) ?>" target="_blank" rel="noopener"><?= e($shortened) ?></a>
                <?php if ($successMeta): ?>
                    <ul style="margin-top:0.5rem;">
                        <?php if (!empty($successMeta['campaign'])): ?>
                            <li><strong>Campaign:</strong> <?= e($successMeta['campaign']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($successMeta['launch_time'])): ?>
                            <li><strong>Launch:</strong> <?= e($successMeta['launch_time']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($successMeta['description'])): ?>
                            <li><strong>Description:</strong> <?= e($successMeta['description']) ?></li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="qr-code">
                <canvas id="qr-canvas" data-qr-value="<?= e($shortened) ?>"></canvas>
                <button type="button" onclick="downloadQR()" class="btn btn-secondary">Download QR</button>
            </div>
        <?php elseif ($error): ?>
            <div class="message error" style="margin-top:1rem;"><?= e($error) ?></div>
        <?php endif; ?>
    </section>

    <?php if ($email): ?>
        <section class="card">
            <h2>Your links</h2>
            <?php if (empty($links)): ?>
                <p>No short links yet.</p>
            <?php else: ?>
                <ul class="cfc-link-list">
                <?php foreach ($links as $row):
                    $id = (int) $row['id'];
                    $short = $baseShort . $id;
                    $label = $row['campaign'] ?: (parse_url($row['long'], PHP_URL_HOST) ?: 'Link');
                    $clicks = (int) $row['clicks'];
                ?>
                    <li>
                        <details class="cfc-link-item">
                            <summary>
                                <span><?= e($label) ?></span>
                                <span class="cfc-badge"><?= $clicks ?> click<?= $clicks === 1 ? '' : 's' ?></span>
                                <span style="font-weight:500;font-size:0.85rem;color:#666;word-break:break-all;"><?= e($short) ?></span>
                            </summary>
                            <div class="cfc-link-body">
                                <table>
                                    <tr><th>Original</th><td><a href="<?= e($row['long']) ?>" target="_blank" rel="noopener"><?= e($row['long']) ?></a></td></tr>
                                    <tr><th>Short</th><td><a href="<?= e($short) ?>" target="_blank" rel="noopener"><?= e($short) ?></a></td></tr>
                                    <tr><th>Clicks</th><td><?= $clicks ?></td></tr>
                                    <tr><th>Campaign</th><td><?= e($row['campaign'] ?? '—') ?></td></tr>
                                    <tr><th>Launch time</th><td><?= e($row['launch_time'] ?? '—') ?></td></tr>
                                    <tr><th>Description</th><td><?= nl2br(e($row['description'] ?? '—')) ?></td></tr>
                                </table>

                                <h3 style="margin:1rem 0 0.4rem;font-size:1rem;">Recent clicks</h3>
                                <?php
                                $detail = $conn->prepare('
                                    SELECT ip, user_agent, referrer, platform, utm_source, created_at
                                    FROM click_logs WHERE short_id = ?
                                    ORDER BY created_at DESC LIMIT 30
                                ');
                                if ($detail) {
                                    $detail->bind_param('i', $id);
                                    $detail->execute();
                                    $dr = $detail->get_result();
                                    if ($dr && $dr->num_rows > 0):
                                ?>
                                <div style="overflow-x:auto;">
                                <table class="cfc-clicks-table worker-stats-table">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>IP</th>
                                            <th>Platform</th>
                                            <th>Referrer</th>
                                            <th>UTM</th>
                                            <th>User agent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($d = $dr->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= e($d['created_at'] ?? '') ?></td>
                                            <td><?= e($d['ip'] ?? '') ?></td>
                                            <td><?= e($d['platform'] ?? '') ?></td>
                                            <td><?= e($d['referrer'] ?? '') ?></td>
                                            <td><?= e($d['utm_source'] ?? '') ?></td>
                                            <td><?= e(mb_substr((string)($d['user_agent'] ?? ''), 0, 80)) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                                </div>
                                <?php else: ?>
                                    <p style="color:#666;font-size:0.9rem;">No clicks recorded yet.</p>
                                <?php
                                    endif;
                                    $detail->close();
                                }
                                ?>
                            </div>
                        </details>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="card">
            <p><a href="/login.php">Log in</a> to create and manage short links.</p>
        </section>
    <?php endif; ?>
</main>
<?php
if (function_exists('cfc_footer')) {
    cfc_footer('https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/r', 'URL Shortener Source');
}
include_footer();
?>
<script src="/assets/js/qr.js"></script>
<script src="/js/qr.js"></script>
<?php
close_database();
