<?php
session_start();
include("config.php"); // $conn

$posts_file = "posts.json";
$posts = file_exists($posts_file) ? json_decode(file_get_contents($posts_file), true) : [];
if (!is_array($posts)) $posts = [];

$email = $_SESSION['email'] ?? null;
$can_post = false;
if ($email) {
    $stmt = $conn->prepare("SELECT status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if ($user && $user['status'] == 1) {
        $can_post = true;
    }
}

// ==== Handle new post ====
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($can_post && isset($_POST['title'], $_POST['content'], $_POST['action']) && $_POST['action'] === 'new') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        if ($title && $content) {
            $new_post = [
                "id" => time(),
                "title" => $title,
                "content" => $content,
                "author" => $email,
                "created_at" => date("c")
            ];
            array_unshift($posts, $new_post);
            file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $message = "✅ Announcement posted.";
        } else {
            $message = "⚠️ Title and content are required.";
        }
    }

    // ==== Handle edit ====
    if ($can_post && isset($_POST['action']) && $_POST['action'] === 'edit' && isset($_POST['id'], $_POST['title'], $_POST['content'])) {
        $id = intval($_POST['id']);
        foreach ($posts as &$post) {
            if ($post['id'] === $id) {
                $post['title'] = trim($_POST['title']);
                $post['content'] = trim($_POST['content']);
                break;
            }
        }
        file_put_contents($posts_file, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = "✅ Post updated.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Announcements | CfCbazar</title>
<meta name="description" content="Latest updates and announcements from CfCbazar. Stay informed and share news easily.">
<meta name="keywords" content="CfCbazar, announcements, updates, API, guides, storefronts">
<meta name="author" content="CfCbazar Team">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: Arial; padding: 20px; background: #f4f4f4; max-width: 800px; margin: auto; }
form { margin-bottom: 30px; }
input, textarea, button { width: 100%; padding: 10px; margin: 10px 0; border-radius: 6px; border: 1px solid #ccc; }
.post { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
.share { margin-top: 10px; }
.message { font-weight: bold; color: green; }
.edit-form { background: #eef; padding: 10px; margin-top: 10px; border-radius: 6px; }
</style>
</head>
<body>

<h1>📢 CfCbazar Announcements</h1>
<?php if ($message): ?>
<p class="message"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<?php if ($can_post): ?>
<form method="post">
    <input type="hidden" name="action" value="new">
    <input type="text" name="title" placeholder="Announcement Title" required>
    <textarea name="content" rows="5" placeholder="Write your announcement..." required></textarea>
    <button type="submit">Publish</button>
</form>
<?php endif; ?>

<h2>Recent Posts</h2>
<?php foreach ($posts as $post): ?>
<article class="post" id="post<?php echo $post['id']; ?>">
    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
    <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
    <small>Posted by <?php echo htmlspecialchars($post['author']); ?></small>
    <div class="share">
        <?php
        $url = urlencode("https://cfcbazar.ct.ws/announcement.php#post" . $post['id']);
        $text = urlencode($post['title']);
        ?>
        <a href="https://twitter.com/intent/tweet?url=<?php echo $url; ?>&text=<?php echo $text; ?>" target="_blank">Share on X</a> |
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $url; ?>" target="_blank">Facebook</a> |
        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $url; ?>&title=<?php echo $text; ?>" target="_blank">LinkedIn</a>
    </div>

    <?php if ($can_post && $post['author'] === $email): ?>
    <details class="edit-form">
        <summary>Edit this post</summary>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
            <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
            <textarea name="content" rows="4" required><?php echo htmlspecialchars($post['content']); ?></textarea>
            <button type="submit">Save Changes</button>
        </form>
    </details>
    <?php endif; ?>
</article>
<?php endforeach; ?>

</body>
</html>