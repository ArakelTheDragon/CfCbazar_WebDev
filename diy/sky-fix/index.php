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

$NEED_SESSION = true;
$NEED_LOGIN   = false;
$NEED_CART    = false;
$NEED_ADMIN   = false;

$url        = "/diy/sky-fix/index.php";
$parent_url = "/help";
$title      = "CfCbazar Sky Fix Center";
$template   = "Generic";
$meta_title = "CfCbazar Sky Fix Help | Guides, Troubleshooting & FAQs";
$meta_desc  = "Find answers, guides, and troubleshooting tips for Sky TV and BB.";

// Absolute URL for canonical / share links
$full_url   = build_full_url($url);

setReturnUrl($url);

// ------------------------
// System
// ------------------------
enforce_https();
checkSystemFlags();
if (bootstrap() !== 0) {
    exit(1);
}
// -----------------------------------------------------------------------------
// Article summary helper — collapses whitespace, cuts on word boundary.
// -----------------------------------------------------------------------------
if (!function_exists('sky_fix_summary')) {
    function sky_fix_summary(string $text, int $limit = 180): string
    {
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }

        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($len <= $limit) {
            return $text;
        }

        $cut = function_exists('mb_substr')
            ? mb_substr($text, 0, $limit, 'UTF-8')
            : substr($text, 0, $limit);

        $space = function_exists('mb_strrpos')
            ? mb_strrpos($cut, ' ', 0, 'UTF-8')
            : strrpos($cut, ' ');

        if ($space !== false && $space > (int)($limit * 0.55)) {
            $cut = function_exists('mb_substr')
                ? mb_substr($cut, 0, $space, 'UTF-8')
                : substr($cut, 0, $space);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:!-") . '…';
    }
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

<main class="content">
    <div class="container">

        <div class="help-hero">
            <h1>📘 CfCbazar Sky Fix Center</h1>
            <p>Find answers, guides, and troubleshooting tips for Sky TV and BB.</p>
            <?php if (!empty($articles)): ?>
                <span class="help-count">
                    <?= count($articles) ?> article<?= count($articles) === 1 ? '' : 's' ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($is_admin): ?>
            <div class="help-admin-bar">
                <a href="/diy/sky-fix/sky-fix.php">✏️ Admin: manage articles</a>
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
                <p class="help-empty">No Sky Fix articles yet.</p>
            <?php else: ?>
                <?php foreach ($articles as $article):
                    $titleRaw   = (string)($article['title'] ?? '');
                    $contentRaw = (string)($article['content'] ?? '');
                    $link       = trim((string)($article['link'] ?? ''));

                    if ($link === '') {
                        $href = '';
                    } elseif (preg_match('#^https?://#i', $link)) {
                        $href = $link;
                    } else {
                        $href = '/' . ltrim($link, '/');
                    }

                    $hasLink = $href !== '';
                    $displayContent = $hasLink
                        ? sky_fix_summary($contentRaw, 180)
                        : $contentRaw;

                    $searchBlob = mb_strtolower($titleRaw . ' ' . $contentRaw, 'UTF-8');
                ?>
                    <article
                        class="help-card"
                        id="article<?= htmlspecialchars((string)($article['id'] ?? '')) ?>"
                        data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <h3><?= htmlspecialchars($titleRaw) ?></h3>

                        <?php if ($displayContent !== ''): ?>
                            <p class="help-card-summary"><?= htmlspecialchars($displayContent) ?></p>
                        <?php endif; ?>

                        <?php if ($hasLink): ?>
                            <a
                                href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                                class="help-card-link"
                                target="_blank"
                                rel="noopener"
                            >
                                Read more <span class="arrow" aria-hidden="true">→</span>
                            </a>
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
    const container = document.getElementById('help-articles');
    const status    = document.getElementById('help-status');
    const cards     = Array.from(container.querySelectorAll('.help-card'));

    const index = cards.map((card, i) => ({
        card,
        originalIndex: i,
        title: (card.querySelector('h3')?.textContent || '').toLowerCase(),
        text:  (card.dataset.search
                    || card.textContent
                    || '').toLowerCase()
    }));

    function reset() {
        index
            .slice()
            .sort((a, b) => a.originalIndex - b.originalIndex)
            .forEach(item => {
                item.card.style.display = '';
                container.appendChild(item.card);
            });
        status.textContent = '';
    }

    searchBox.addEventListener('input', () => {
        const words = [...new Set(
            searchBox.value.trim().toLowerCase().split(/\s+/).filter(Boolean)
        )];

        if (words.length === 0) {
            reset();
            return;
        }

        const scored = index.map(item => {
            let score = 0;
            for (const w of words) {
                if (item.title.includes(w)) score += 2;
                else if (item.text.includes(w)) score += 1;
            }
            return { ...item, score };
        });

        const matches = scored
            .filter(item => item.score > 0)
            .sort((a, b) =>
                b.score - a.score ||
                a.originalIndex - b.originalIndex
            );

        scored.forEach(item => { item.card.style.display = 'none'; });
        matches.forEach(item => {
            item.card.style.display = '';
            container.appendChild(item.card);
        });

        status.textContent = matches.length
            ? `${matches.length} article${matches.length === 1 ? '' : 's'} found.`
            : 'No articles found.';
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