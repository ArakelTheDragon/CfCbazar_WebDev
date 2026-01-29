<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "includes/reusable.php";

$status = getUserStatus($conn);
$isAdmin = ($status === 1);

$title = "CfCbazar News & Updates";
$description = "Latest news and updates from CfCbazar including WorkToken and WorkTHR announcements, platform changes, and mining tips.";

include_header();
include_menu();

$jsonPath = $_SERVER['DOCUMENT_ROOT'] . '/news.json';
$articles = [];

// Load articles from JSON
if (file_exists($jsonPath)) {
    $jsonData = file_get_contents($jsonPath);
    $data = json_decode($jsonData, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $articles = $data['articles'] ?? [];
    } else {
        echo '<div class="error">❌ JSON format error: ' . json_last_error_msg() . '</div>';
    }
} else {
    echo '<div class="error">❌ news.json not found at ' . htmlspecialchars($jsonPath) . '</div>';
}

// Handle admin actions
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Edit existing article
    if (isset($_POST['article_id'], $_POST['edited_content'])) {
        $id = (int)$_POST['article_id'];
        foreach ($articles as &$article) {
            if ($article['id'] === $id) {
                $article['content'] = $_POST['edited_content'];
                break;
            }
        }
        file_put_contents($jsonPath, json_encode(['articles' => $articles], JSON_PRETTY_PRINT));
        echo '<div class="success">✅ Article updated.</div>';
    }

    // Add new article
    if (isset($_POST['new_title'], $_POST['new_content'])) {
        $newTitle = trim($_POST['new_title']);
        $newContent = trim($_POST['new_content']);
        if ($newTitle && $newContent) {
            $newId = empty($articles) ? 1 : max(array_column($articles, 'id')) + 1;
            $newArticle = [
                'id' => $newId,
                'title' => $newTitle,
                'content' => $newContent
            ];
            array_unshift($articles, $newArticle);
            file_put_contents($jsonPath, json_encode(['articles' => $articles], JSON_PRETTY_PRINT));
            echo '<div class="success">✅ New article added.</div>';
        }
    }
}
?>

<div class="container">
  <h1 class="page-title">📰 CfCbazar News & Updates</h1>

  <!-- Search Box -->
  <div class="card" style="margin-bottom:1em;">
    <input type="text" id="searchBox" placeholder="🔍 Search articles..." oninput="filterArticles()" style="width:100%; padding:0.5em; font-size:1em;">
  </div>

  <?php if ($isAdmin): ?>
    <div class="card" style="margin-bottom:2em; padding:1em;">
      <h2>➕ Add New Article</h2>
      <form method="post">
        <input type="text" name="new_title" placeholder="Title" required style="width:100%; margin-bottom:0.5em;">
        <textarea name="new_content" rows="6" placeholder="Content (HTML allowed)" required style="width:100%;"></textarea>
        <button type="submit">📝 Publish</button>
      </form>
    </div>
  <?php endif; ?>

  <?php foreach ($articles as $article): ?>
    <?php $anchor = 'article-' . $article['id']; ?>
    <?php $shareUrl = 'https://cfcbazar.42web.io/news.php#' . $anchor; ?>
    <div class="news-item card article-block">
      <a id="<?= $anchor ?>"></a>
      <div class="date">📅</div>
      <h2><?= htmlspecialchars($article['title']) ?></h2>
      <p><?= $article['content'] ?></p>

      <!-- Social Sharing Buttons -->
      <div class="share-buttons" style="margin-top:1em;">
        <span>🔗 Share:</span>
        <a href="https://x.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($article['title']) ?>" target="_blank" rel="noopener">🐦 X</a> |
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener">📘 Facebook</a> |
        <a href="https://t.me/share/url?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($article['title']) ?>" target="_blank" rel="noopener">📨 Telegram</a>
      </div>

      <?php if ($isAdmin): ?>
        <form method="post" class="form-group" style="margin-top:1em;">
          <input type="hidden" name="article_id" value="<?= $article['id'] ?>">
          <textarea name="edited_content" rows="5" style="width:100%;"><?= htmlspecialchars_decode($article['content']) ?></textarea>
          <button type="submit">💾 Save Changes</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <h2>Need Help?</h2>
  <div class="contact card">
    <p>Email: <a href="mailto:CfCbazar@gmail.com">CfCbazar@gmail.com</a></p>
    <p>Website: <a href="https://cfcbazar.42web.io">CfCbazar.42web.io</a></p>
  </div>
</div>

<?php include_footer(); ?>

<!-- Search Filter Script -->
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
