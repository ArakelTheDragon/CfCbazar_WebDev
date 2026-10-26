<?php
// /diy/sky-fix/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$reusablePath = __DIR__ . '/../../includes/reusable.php';
if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

require_once __DIR__ . '/../../includes/settings_global.php';
require_once __DIR__ . '/../../includes/settings_local.php';

$NEED_SESSION = true;
$NEED_LOGIN   = false;
$NEED_CART    = false;
$NEED_ADMIN   = false;

enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}

$articles_file = __DIR__ . '/articles.json';
$articles = file_exists($articles_file)
    ? json_decode(file_get_contents($articles_file), true)
    : [];
if (!is_array($articles)) {
    $articles = [];
}

$email = $_SESSION['email'] ?? null;
$is_admin = false;
if ($email) {
    $stmt = $conn->prepare("SELECT status FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if ((int)$row['status'] === 1) {
                $is_admin = true;
            }
        }
        $stmt->close();
    }
}

$url        = "/diy/sky-fix/index.php";
$parent_url = "/help";
$title      = "CfCbazar Sky Fix Center";
$template   = "Generic";
$meta_title = "CfCbazar Sky Fix Help | Guides, Troubleshooting & FAQs";
$meta_desc  = "Find answers, guides, and troubleshooting tips for Sky TV and BB.";

trackVisit(
    slug:      $url,
    parentUrl: $parent_url,
    title:     $title,
    template:  $template,
    metaTitle: $meta_title,
    metaDesc:  $meta_desc
);

render_header($title);
?>

<style>
.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.help-card {
    background: #fff;
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    transition: transform 0.15s ease;
}
.help-card:hover { transform: translateY(-2px); }
.help-card h3 { margin: 0 0 8px 0; color: #0077cc; font-size: 1.05rem; }
.help-card p { color: #555; margin: 0 0 10px 0; font-size: 0.92rem; line-height: 1.5; }
.help-card a { display: inline-block; color: #0077cc; text-decoration: underline; font-size: 0.9rem; }
.help-search {
    width: 100%;
    max-width: 600px;
    padding: 12px;
    font-size: 16px;
    margin: 20px auto;
    display: block;
    border: 1px solid #ccc;
    border-radius: 8px;
}
.help-status { text-align: center; font-weight: 600; margin-top: 10px; color: #555; }
.help-admin-bar {
    text-align: center;
    margin: 10px auto 0;
    font-size: 0.85rem;
}
.help-admin-bar a {
    color: #0077cc;
    text-decoration: none;
    padding: 6px 14px;
    border: 1px solid #0077cc;
    border-radius: 6px;
}
.help-admin-bar a:hover { background: #0077cc; color: #fff; }
</style>

<main class="content">
    <div class="container">

        <h1 style="text-align:center;">📘 CfCbazar Sky Fix Center</h1>
        <p style="text-align:center;max-width:600px;margin:0 auto;color:#666;">
            Find answers, guides, and troubleshooting tips for Sky TV and BB.
        </p>

        <?php if ($is_admin): ?>
            <div class="help-admin-bar">
                <a href="/diy/sky-fix/admin.php">✏️ Admin: manage articles</a>
            </div>
        <?php endif; ?>

        <input
            type="search"
            id="help-search"
            class="help-search"
            placeholder="Search Sky Fix articles…"
            aria-label="Search Sky Fix articles"
        />

        <div id="help-status" class="help-status" role="status"></div>

        <div id="help-articles" class="help-grid" aria-live="polite">
            <?php if (empty($articles)): ?>
                <p style="text-align:center;color:#777;">No Sky Fix articles yet.</p>
            <?php else: ?>
                <?php foreach ($articles as $article):
                    $link = trim((string)($article['link'] ?? ''));

                    if ($link === '') {
                        $href = '';
                    } elseif (preg_match('#^https?://#i', $link)) {
                        $href = $link;
                    } else {
                        $href = '/' . ltrim($link, '/');
                    }
                ?>
                    <article class="help-card" id="article<?= htmlspecialchars((string)($article['id'] ?? '')) ?>">
                        <h3><?= htmlspecialchars($article['title'] ?? '') ?></h3>
                        <p><?= htmlspecialchars($article['content'] ?? '') ?></p>
                        <?php if ($href !== ''): ?>
                            <a href="<?= htmlspecialchars($href) ?>" target="_blank" rel="noopener">Read more →</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
(function () {
    'use strict';
    const searchBox = document.getElementById('help-search');
    const cards     = Array.from(document.querySelectorAll('.help-card'));
    const status    = document.getElementById('help-status');

    searchBox.addEventListener('input', () => {
        const term = searchBox.value.trim().toLowerCase();
        let visible = 0;

        cards.forEach(card => {
            const title   = (card.querySelector('h3')?.textContent || '').toLowerCase();
            const content = (card.querySelector('p')?.textContent  || '').toLowerCase();
            const match   = title.includes(term) || content.includes(term);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        status.textContent = term
            ? `${visible} article${visible === 1 ? '' : 's'} found.`
            : '';
    });
})();
</script>

<?php
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/sky-fix",
    "Sky Fix Center Source Code"
);
include_footer();
close_database();
?>
