<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . "/config.php");

// ✅ Access control
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

if (!$can_edit) {
    http_response_code(403);
    echo "<h2>Access Denied</h2><p>You are not authorized to use this tool.</p>";
    exit();
}

// ✅ Directory and file list
$help_dir = __DIR__;
$php_files = array_filter(scandir($help_dir), function($file) use ($help_dir) {
    return is_file($help_dir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'php' && $file !== basename(__FILE__);
});

// ✅ Handle new page creation
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = trim($_POST['title']);
    $content = $_POST['content'];

    if ($title && $content) {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($title));
        $filename = $help_dir . '/' . $slug . ".php";

        if (file_exists($filename)) {
            $message = "⚠️ File <code>$slug.php</code> already exists.";
        } else {
            $template = <<<PHP
<?php
// Auto-generated page: $title
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>$title</title>
</head>
<body>
<h1>$title</h1>
<?php
// --- Begin dynamic content ---
?>
$content
<?php
// --- End dynamic content ---
?>
</body>
</html>
PHP;
            if (file_put_contents($filename, $template)) {
                $message = "✅ Page <code>$slug.php</code> created successfully.";
                $php_files[] = $slug . ".php";
            } else {
                $message = "❌ Failed to write file.";
            }
        }
    } else {
        $message = "⚠️ Title and content are required.";
    }
}

// ✅ Handle file edit
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $file = basename($_POST['edit_file']);
    $new_content = $_POST['edit_content'];

    if (in_array($file, $php_files)) {
        if (file_put_contents($help_dir . '/' . $file, $new_content)) {
            $message = "✅ File <code>$file</code> updated successfully.";
        } else {
            $message = "❌ Failed to update file.";
        }
    }
}

// ✅ Load file for editing
$selected_file = $_GET['edit'] ?? null;
$edit_content = "";
if ($selected_file && in_array($selected_file, $php_files)) {
    $edit_content = file_get_contents($help_dir . '/' . $selected_file);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Page Generator & Editor | CfCbazar</title>
  <style>
    body { font-family: Arial; padding: 20px; max-width: 900px; margin: auto; background: #f4f4f4; }
    h2 { color: #333; }
    input, textarea, select, button {
      width: 100%; padding: 10px; margin: 10px 0;
      border-radius: 6px; border: 1px solid #ccc;
      font-family: monospace;
    }
    textarea { height: 300px; }
    .message { font-weight: bold; color: green; }
    .warning { color: red; font-weight: bold; }
  </style>
</head>
<body>

<h2>🛠️ CfCbazar Page Generator & Editor</h2>

<?php if ($message): ?>
<p class="<?php echo strpos($message, '✅') !== false ? 'message' : 'warning'; ?>"><?php echo $message; ?></p>
<?php endif; ?>

<!-- ✅ Create New Page -->
<form method="post">
  <input type="hidden" name="action" value="create">
  <input type="text" name="title" placeholder="Page Title (used for filename)" required>
  <textarea name="content" placeholder="Enter PHP and Markdown content here..." required></textarea>
  <button type="submit">Generate Page</button>
</form>

<!-- ✅ Edit Existing Page -->
<h3>Edit Existing Page</h3>
<form method="get">
  <select name="edit" onchange="this.form.submit()">
    <option value="">-- Select a page --</option>
    <?php foreach ($php_files as $file): ?>
      <option value="<?php echo htmlspecialchars($file); ?>" <?php if ($file === $selected_file) echo 'selected'; ?>>
        <?php echo htmlspecialchars($file); ?>
      </option>
    <?php endforeach; ?>
  </select>
</form>

<?php if ($selected_file): ?>
<form method="post">
  <input type="hidden" name="action" value="edit">
  <input type="hidden" name="edit_file" value="<?php echo htmlspecialchars($selected_file); ?>">
  <textarea name="edit_content"><?php echo htmlspecialchars($edit_content); ?></textarea>
  <button type="submit">Save Changes</button>
</form>
<?php endif; ?>

</body>
</html>