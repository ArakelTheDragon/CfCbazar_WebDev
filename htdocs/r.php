<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php'; // make sure this defines $conn

$email = $_SESSION['username'] ?? null;

// Handle redirection
if (isset($_GET['go'])) {
    $id = intval($_GET['go']);

    $stmt = $conn->prepare("SELECT `long`, `email` FROM short_links WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($longUrl, $ownerEmail);

    if ($stmt->fetch()) {
        $stmt->close();

        if (!preg_match('#^https?://#i', $longUrl)) {
            $longUrl = 'http://' . $longUrl;
        }

        $update = $conn->prepare("UPDATE short_links SET clicks = clicks + 1 WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();
        $update->close();

        if (!empty($ownerEmail)) {
            $deduct = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned - 0.01 WHERE email = ?");
            $deduct->bind_param("s", $ownerEmail);
            $deduct->execute();
            $deduct->close();
        }

        header("Location: $longUrl");
        exit;
    } else {
        echo "❌ Short link not found.";
        exit;
    }
}

// Handle form submission
$shortened = '';
$error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['longurl'])) {
    $longUrl = trim($_POST['longurl']);

    if (!$email) {
        $error = "Please log in to create links.";
    } elseif (empty($longUrl)) {
        $error = "Please enter a URL.";
    } else {
        if (!preg_match('#^https?://#i', $longUrl)) {
            $longUrl = 'http://' . $longUrl;
        }

        $stmt = $conn->prepare("SELECT id FROM short_links WHERE `long` = ? AND email = ?");
        $stmt->bind_param("ss", $longUrl, $email);
        $stmt->execute();
        $stmt->bind_result($existingId);
        if ($stmt->fetch()) {
            $id = $existingId;
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO short_links (`long`, clicks, email) VALUES (?, 0, ?)");
            $stmt->bind_param("ss", $longUrl, $email);
            if ($stmt->execute()) {
                $id = $stmt->insert_id;

                $deduct = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned - 0.01 WHERE email = ?");
                $deduct->bind_param("s", $email);
                $deduct->execute();
                $deduct->close();
            } else {
                $error = "Could not create link.";
            }
            $stmt->close();
        }

        if (!empty($id)) {
            $shortened = "https://cfcbazar.ct.ws/r.php?go=$id";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>🔗 URL Shortener</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: sans-serif; text-align: center; background: #f5f5f5; padding: 2rem; }
    input[type=text] { width: 90%; max-width: 400px; padding: 10px; }
    input[type=submit], button { padding: 10px 20px; margin-top: 10px; font-size: 1em; }
    .link { margin-top: 20px; font-size: 18px; word-break: break-word; }
    .error { color: red; margin-top: 1rem; }
    canvas { margin-top: 20px; }
    .stats { text-align: left; max-width: 600px; margin: 2rem auto 0; background: white; padding: 1rem; border-radius: 8px; }
    .url-box { margin-bottom: 1rem; word-break: break-word; }
  </style>
</head>
<body>

<h2>🔗 Create a Short Link</h2>
<p>Each link creation and visit deducts <strong>0.01 WorkTokens</strong></p>

<form method="post">
    <input type="text" name="longurl" placeholder="Enter full URL..." required />
    <br>
    <input type="submit" value="Shorten URL" />
</form>

<?php if ($shortened): ?>
  <div class="link">
    ✅ Your short link:<br>
    <a href="<?= htmlspecialchars($shortened) ?>" target="_blank"><?= htmlspecialchars($shortened) ?></a><br><br>
    <canvas id="qr-canvas"></canvas><br>
    <button onclick="downloadQR()">📥 Download QR</button>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/qrious/dist/qrious.min.js"></script>
  <script>
    const canvas = document.getElementById('qr-canvas');
    new QRious({ element: canvas, size: 250, value: <?= json_encode($shortened) ?> });
    function downloadQR() {
        const a = document.createElement('a');
        a.download = 'qr-code.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
    }
  </script>
<?php elseif ($error): ?>
  <div class="error">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($email): ?>
  <div class="stats">
    <h3>📊 Your Short Link Stats</h3>
    <?php
      $stmt = $conn->prepare("SELECT id, `long`, clicks FROM short_links WHERE email = ?");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
          $short = "https://cfcbazar.ct.ws/r.php?go=" . $row['id'];
    ?>
      <div class="url-box">
        🔗 <strong><?= htmlspecialchars($row['long']) ?></strong><br>
        ➡️ <a href="<?= htmlspecialchars($short) ?>" target="_blank"><?= htmlspecialchars($short) ?></a><br>
        👁️ Clicks: <?= intval($row['clicks']) ?>
      </div>
    <?php
        endwhile;
      else:
        echo "<p>You haven't created any links yet.</p>";
      endif;
      $stmt->close();
    ?>
  </div>
<?php endif; ?>

</body>
</html>