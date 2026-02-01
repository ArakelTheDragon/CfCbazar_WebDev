<?php
if (session_status() === PHP_SESSION_NONE) session_start();

//include($_SERVER['DOCUMENT_ROOT'] . "/config.php");
require_once __DIR__ . '/../includes/reusable.php';

enforce_https();

$articles_file = __DIR__ . "/articles.json";
$articles = file_exists($articles_file) ? json_decode(file_get_contents($articles_file), true) : [];
if (!is_array($articles)) $articles = [];

$email = $_SESSION['email'] ?? null;
$can_edit = false;
if ($email) {
    $stmt = $conn->prepare("SELECT status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if ($user && $user['status'] == 1) {
        $can_edit = true;
    }
}

// ==== Handle new article ====
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($can_edit && isset($_POST['action']) && $_POST['action'] === 'new') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $link = trim($_POST['link']);
        if ($title && $content && $link) {
            $new_article = [
                "id" => time(),
                "title" => $title,
                "content" => $content,
                "link" => $link
            ];
            array_unshift($articles, $new_article);
            file_put_contents($articles_file, json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $message = "✅ Article added.";
        } else {
            $message = "⚠️ All fields are required.";
        }
    }

    // ==== Handle edit ====
    if ($can_edit && isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        foreach ($articles as &$article) {
            if ($article['id'] === $id) {
                $article['title'] = trim($_POST['title']);
                $article['content'] = trim($_POST['content']);
                $article['link'] = trim($_POST['link']);
                break;
            }
        }
        file_put_contents($articles_file, json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = "✅ Article updated.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CfCbazar Help Center | Guides, Troubleshooting & FAQs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Find answers, guides, and troubleshooting tips for CfCbazar digital storefronts, mining dashboards, and modular assets." />
  <meta name="keywords" content="CfCbazar help center, mining dashboard, eBay storefronts, troubleshooting guides, digital assets, modular guides" />
  <meta name="author" content="CfCbazar Team" />
  <meta name="robots" content="index, follow" />
  <meta property="og:title" content="CfCbazar Help Center" />
  <meta property="og:description" content="Explore help articles, guides, and FAQs for CfCbazar storefronts and mining tools." />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://cfcbazar.com/help" />
  <meta property="og:image" content="https://cfcbazar.com/assets/help-center-banner.png" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="CfCbazar Help Center" />
  <meta name="twitter:description" content="Support articles and guides for CfCbazar storefronts and mining dashboards." />
  <meta name="twitter:image" content="https://cfcbazar.com/assets/help-center-banner.png" />
  <link rel="icon" href="/favicon.ico" type="image/x-icon" />
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "CfCbazar Help Center",
    "url": "https://cfcbazar.com/help",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://cfcbazar.com/help?search={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }
  </script>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; background: #f7f9fc; padding: 20px; }
    h1 { text-align: center; color: #333; }
    #search-box { width: 100%; padding: 12px; font-size: 16px; margin: 20px auto; display: block; border: 1px solid #ccc; border-radius: 8px; max-width: 600px; }
    #articles { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
    .card { background: white; padding: 16px; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
    .card:hover { transform: scale(1.02); }
    .card h3 { margin-top: 0; color: #0077cc; }
    .card p { color: #555; }
    .card a { display: inline-block; margin-top: 8px; color: #0077cc; text-decoration: underline; }
    #status { text-align: center; font-weight: bold; margin-top: 10px; color: #555; }
    .edit-form { background: #eef; padding: 10px; margin-top: 10px; border-radius: 6px; }
    form input, form textarea, form button { width: 100%; padding: 10px; margin: 6px 0; border-radius: 6px; border: 1px solid #ccc; }
  </style>
</head>
<body>

<header>
  <h1>📘 CfCbazar Help Center</h1>
  <p style="text-align:center; max-width:600px; margin:0 auto; color:#666;">
    Explore guides, FAQs, and troubleshooting tips for CfCbazar storefronts, mining dashboards, and digital assets.
  </p>
</header>

<?php if ($can_edit): ?>
<?php if ($message): ?>
<p style="text-align:center; font-weight:bold; color:green;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<form method="post" style="max-width:600px; margin:20px auto;">
  <input type="hidden" name="action" value="new">
  <input type="text" name="title" placeholder="Article Title" required>
  <textarea name="content" rows="4" placeholder="Article Content" required></textarea>
  <input type="url" name="link" placeholder="Link to full guide" required>
  <button type="submit">Add Article</button>
</form>
<?php endif; ?>

<input type="search" id="search-box" placeholder="Search help articles..." aria-label="Search help articles" />
<div id="status" role="status"></div>
<main id="articles" aria-live="polite">
  <?php foreach ($articles as $article): ?>
  <article class="card" id="article<?php echo $article['id'] ?? ''; ?>">
    <h3><?php echo htmlspecialchars($article['title']); ?></h3>
    <p><?php echo htmlspecialchars($article['content']); ?></p>
    <a href="<?php echo htmlspecialchars($article['link']); ?>" target="_blank">Read more</a>

    <?php if ($can_edit): ?>
    <details class="edit-form">
      <summary>Edit this article</summary>
      <form method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?php echo $article['id'] ?? ''; ?>">
        <input type="text" name="title" value="<?php echo htmlspecialchars($article['title']); ?>" required>
        <textarea name="content" rows="3" required><?php echo htmlspecialchars($article['content']); ?></textarea>
        <input type="url" name="link" value="<?php echo htmlspecialchars($article['link']); ?>" required>
        <button type="submit">Save Changes</button>
      </form>
    </details>
    <?php endif; ?>
  </article>
  <?php endforeach; ?>
</main>

<script>
  const searchBox = document.getElementById('search-box');
  const articles = Array.from(document.querySelectorAll('.card'));
  const status = document.getElementById('status');

  searchBox.addEventListener('input', () => {
    const term = searchBox.value.trim().toLowerCase();
    let visibleCount = 0;

    articles.forEach(card => {
      const title = card.querySelector('h3').textContent.toLowerCase();
      const content = card.querySelector('p').textContent.toLowerCase();
      const match = title.includes(term) || content.includes(term);
      card.style.display = match ? 'block' : 'none';
      if (match) visibleCount++;
    });

    status.textContent = term
      ? `${visibleCount} article${visibleCount !== 1 ? 's' : ''} found.`
      : '';
  });
</script>