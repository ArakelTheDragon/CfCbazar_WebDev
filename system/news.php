<?php
// /news.php

// --------------------------------------------------------------------------
// Load Core Library
// --------------------------------------------------------------------------
$reusablePath = __DIR__ . '/../includes/reusable.php';

if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    die("Error: /includes/reusable.php library missing.");
}

session_check();

// Standard security & analytics
enforce_https();
trackVisit('news');

// Check admin privileges
$status = getUserStatus(); // Uses active session email
$isAdmin = ($status === 1);

// Page Metadata & Headers
$title = "CfCbazar News & Updates";
$description = "Latest news and updates from CfCbazar including WorkToken and WorkTHR announcements, platform changes, and mining tips.";

include_header($title, $description);
include_menu();
//render_top_userbar();

// --------------------------------------------------------------------------
// Article Data Handling
// --------------------------------------------------------------------------
$jsonPath = $_SERVER['DOCUMENT_ROOT'] . '/system/news.json';
$articles = [];
$feedbackMessage = '';

// Load articles from JSON
if (file_exists($jsonPath)) {
    $jsonData = file_get_contents($jsonPath);
    $data = json_decode($jsonData, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $articles = $data['articles'] ?? [];
    } else {
        $feedbackMessage = '<div class="error">❌ JSON format error: ' . json_last_error_msg() . '</div>';
    }
} else {
    $feedbackMessage = '<div class="error">❌ news.json not found at ' . htmlspecialchars($jsonPath) . '</div>';
}

// Handle Admin POST Actions
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Edit existing article
    if (isset($_POST['article_id'], $_POST['edited_content'])) {
        $id = (int)$_POST['article_id'];
        $editedContent = trim($_POST['edited_content']);

        foreach ($articles as &$article) {
            if ($article['id'] === $id) {
                $article['content'] = $editedContent;
                break;
            }
        }
        unset($article);

        file_put_contents($jsonPath, json_encode(['articles' => $articles], JSON_PRETTY_PRINT));
        $feedbackMessage = '<div class="success">✅ Article updated successfully.</div>';
    }

    // Add new article
    if (isset($_POST['new_title'], $_POST['new_content'])) {
        $newTitle = trim($_POST['new_title']);
        $newContent = trim($_POST['new_content']);

        if ($newTitle !== '' && $newContent !== '') {
            $newId = empty($articles) ? 1 : max(array_column($articles, 'id')) + 1;
            $newArticle = [
                'id'      => $newId,
                'title'   => $newTitle,
                'content' => $newContent
            ];

            array_unshift($articles, $newArticle);
            file_put_contents($jsonPath, json_encode(['articles' => $articles], JSON_PRETTY_PRINT));
            $feedbackMessage = '<div class="success">✅ New article published successfully.</div>';
        }
    }
}
?>

<div class="container" style="max-width: 900px; margin-top: 20px;">
    <h1 class="page-title">📰 CfCbazar News & Updates</h1>

    <?= $feedbackMessage ?>

    <div class="card" style="margin-bottom: 1.5em;">
        <input type="text" id="searchBox" placeholder="🔍 Search articles..." oninput="filterArticles()" style="width: 100%; padding: 0.6em; font-size: 1em; box-sizing: border-box;">
    </div>

    <?php if ($isAdmin): ?>
        <div class="card" style="margin-bottom: 2em; padding: 1.2em;">
            <h2>➕ Add New Article</h2>
            <form method="post">
                <div class="input-group" style="margin-bottom: 0.8em;">
                    <input type="text" name="new_title" placeholder="Title" required style="width: 100%; padding: 0.5em; box-sizing: border-box;">
                </div>
                <div class="input-group" style="margin-bottom: 0.8em;">
                    <textarea name="new_content" rows="6" placeholder="Content (HTML allowed)" required style="width: 100%; padding: 0.5em; box-sizing: border-box;"></textarea>
                </div>
                <button type="submit" class="btn">📝 Publish Article</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (empty($articles)): ?>
        <div class="card">
            <p>No articles found.</p>
        </div>
    <?php else: ?>
        <?php foreach ($articles as $article): ?>
            <?php 
                $anchor = 'article-' . (int)$article['id']; 
                $shareUrl = 'https://cfcbazar.42web.io/news.php#' . $anchor;
            ?>
            <div class="news-item card article-block" style="margin-bottom: 1.5em; padding: 1.2em;">
                <a id="<?= $anchor ?>"></a>
                <h2><?= htmlspecialchars($article['title']) ?></h2>
                
                <div class="article-content" style="margin: 1em 0;">
                    <?= $article['content'] ?>
                </div>

                <div class="share-buttons" style="margin-top: 1em; font-size: 0.9em; opacity: 0.9;">
                    <span>🔗 Share:</span>
                    <a href="https://x.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($article['title']) ?>" target="_blank" rel="noopener noreferrer">🐦 X</a> |
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener noreferrer">📘 Facebook</a> |
                    <a href="https://t.me/share/url?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($article['title']) ?>" target="_blank" rel="noopener noreferrer">📨 Telegram</a>
                </div>

                <?php if ($isAdmin): ?>
                    <hr style="margin: 1.5em 0; border: 0; border-top: 1px solid #ccc;">
                    <details>
                        <summary style="cursor: pointer; font-weight: bold; color: #007bff;">✏️ Edit Article Content</summary>
                        <form method="post" style="margin-top: 1em;">
                            <input type="hidden" name="article_id" value="<?= (int)$article['id'] ?>">
                            <textarea name="edited_content" rows="5" style="width: 100%; padding: 0.5em; box-sizing: border-box;"><?= htmlspecialchars($article['content']) ?></textarea>
                            <button type="submit" class="btn" style="margin-top: 0.5em;">💾 Save Changes</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2>Need Help?</h2>
    <div class="contact card" style="margin-bottom: 2em;">
        <p>Email: <a href="mailto:CfCbazar@gmail.com">CfCbazar@gmail.com</a></p>
        <p>Website: <a href="https://cfcbazar.42web.io">CfCbazar.42web.io</a></p>
    </div>
</div>

<?php include_footer(); ?>

<script>
function filterArticles() {
    const query = document.getElementById('searchBox').value.toLowerCase();
    const articles = document.querySelectorAll('.article-block');

    articles.forEach(article => {
        const text = article.innerText.toLowerCase();
        article.style.display = text.includes(query) ? '' : 'none';
    });
}
</script>
