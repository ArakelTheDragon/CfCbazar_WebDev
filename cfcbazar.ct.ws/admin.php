<?php
// admin.php — Unified Admin Panel with SEO and Visit Tracking
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';

// Visit tracking
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

$upd = $conn->prepare("UPDATE pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
  $upd->bind_param('s', $path);
  $upd->execute();
  if ($upd->affected_rows === 0) {
    $slug  = ltrim($path, '/');
    $slug  = $slug === '' ? 'index' : $slug;
    $title = 'Admin Portal';
    $ins = $conn->prepare("
      INSERT INTO pages (title, slug, path, visits, created_at, updated_at)
      VALUES (?, ?, ?, 1, NOW(), NOW())
    ");
    if ($ins) {
      $ins->bind_param('sss', $title, $slug, $path);
      $ins->execute();
      $ins->close();
    }
  }
  $upd->close();
}

// Access control
if (!isset($_SESSION['email'])) {
  header("Location: login.php");
  exit();
}

$email = trim(strtolower($_SESSION['email']));
$stmt = $conn->prepare("SELECT email, status FROM users WHERE LOWER(email) = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$hasAccess = $user && (int)$user['status'] === 1;

// Handle token update
if ($hasAccess && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'], $_POST['tokens'])) {
  $targetEmail = trim($_POST['email']);
  $tokens = (float)$_POST['tokens'];
  $update = $conn->prepare("UPDATE workers SET tokens_earned = ? WHERE email = ?");
  $update->bind_param("ds", $tokens, $targetEmail);
  $update->execute();
  $update->close();
}

// Handle work_value update
if ($hasAccess && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_work_value'])) {
  $id     = (int) $_POST['id'];
  $title  = trim($_POST['title']);
  $type   = $_POST['type'];
  $region = trim($_POST['region']);
  $usd    = (float) $_POST['hourly_usd'];
  $hours  = (int) $_POST['hours'];
  $status = $_POST['status'];

  if ($title && $region && in_array($type, ['profession','product','service'], true) && $usd > 0 && $hours > 0) {
    $total_usd  = $usd * $hours;
    $hourly_wtk = intval($usd * 10_000_000);
    $total_wtk  = intval($total_usd * 10_000_000);

    $stmt = $conn->prepare("
      UPDATE `work_value` SET
        `title` = ?, `type` = ?, `region` = ?,
        `hourly_usd` = ?, `hourly_wtk` = ?,
        `hours` = ?, `total_usd` = ?, `total_wtk` = ?,
        `status` = ?
      WHERE `id` = ?
    ");
    if ($stmt) {
      $stmt->bind_param(
        "sssdiiddsi",
        $title, $type, $region,
        $usd, $hourly_wtk,
        $hours, $total_usd, $total_wtk,
        $status, $id
      );
      $stmt->execute();
      $stmt->close();
    } else {
      error_log("work_value update prepare failed: " . $conn->error);
    }
  }
}

/* Numbers management (numbers.json) */
$numbersFile = __DIR__ . '/numbers.json';
$numbersData = [];
if (file_exists($numbersFile)) {
  $json = file_get_contents($numbersFile);
  $decoded = json_decode($json, true);
  $numbersData = is_array($decoded) ? $decoded : [];
}

if ($hasAccess && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_numbers'])) {
  // Delete entry
  if (isset($_POST['delete_index'])) {
    $idx = (int)$_POST['delete_index'];
    if (isset($numbersData[$idx])) {
      array_splice($numbersData, $idx, 1);
    }
  }
  // Add or Update entry
  if (isset($_POST['number'], $_POST['associated_with'])) {
    $entry = [
      'number' => trim($_POST['number']),
      'associated_with' => trim($_POST['associated_with'])
    ];
    if (isset($_POST['edit_index']) && $_POST['edit_index'] !== '') {
      $ei = (int)$_POST['edit_index'];
      $numbersData[$ei] = $entry;
    } else {
      $numbersData[] = $entry;
    }
  }
  // Save JSON and redirect
  file_put_contents($numbersFile, json_encode(array_values($numbersData), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Portal | CfCbazar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Admin dashboard for CfCbazar. Manage users, workers, site activity, work values, and numbers directory. Restricted to authorized administrators." />
  <meta name="keywords" content="admin panel, CfCbazar, user management, token updates, dashboard, work value, numbers directory" />
  <meta name="author" content="CfCbazar" />
  <meta name="robots" content="noindex, nofollow" />
  <meta property="og:title" content="Admin Portal | CfCbazar" />
  <meta property="og:description" content="Secure admin dashboard for CfCbazar. Manage users, workers, site activity, work values, and numbers directory." />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://cfcbazar.ct.ws/admin.php" />
  <meta property="og:image" content="https://cfcbazar.ct.ws/assets/admin-preview.png" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Admin Portal | CfCbazar" />
  <meta name="twitter:description" content="Secure admin dashboard for CfCbazar. Manage users, workers, site activity, work values, and numbers directory." />
  <meta name="twitter:image" content="https://cfcbazar.ct.ws/assets/admin-preview.png" />
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
    #loader {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: #fff; display: flex; justify-content: center; align-items: center;
      z-index: 9999;
    }
    .spinner {
      border: 6px solid #eee; border-top: 6px solid #3498db;
      border-radius: 50%; width: 60px; height: 60px;
      animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    #content { display: none; }
    table {
      width: 100%; border-collapse: collapse; background: #fff; margin-top: 20px;
    }
    th, td {
      padding: 10px; border: 1px solid #ccc; text-align: center;
    }
    form input, form select, form button {
      padding: 8px; margin: 4px; width: 100%;
    }
    form button {
      background: #3498db; color: white; border: none; cursor: pointer;
    }
    h2, h3 { color: #333; margin-top: 40px; }
    .inline-forms form { display: inline-block; margin: 0 4px; vertical-align: middle; }
  </style>
  <script>
    window.onload = () => {
      setTimeout(() => {
        document.getElementById("loader").style.display = "none";
        document.getElementById("content").style.display = "block";
      }, 300);
    };
  </script>
</head>
<body>
<div id="loader"><div class="spinner"></div></div>

<div id="content">
  <?php if (!$hasAccess): ?>
    <h2 style="color: red;">Access Denied: Admins only!</h2>
  <?php else: ?>
    <h2>Welcome, <?= htmlspecialchars($user['email']) ?> 👋</h2>

    <h3>User Directory</h3>
    <table>
      <tr><th>ID</th><th>Email</th><th>Status</th></tr>
      <?php
      $users = $conn->query("SELECT id, email, status FROM users");
      while ($row = $users->fetch_assoc()):
      ?>
      <tr>
        <td><?= htmlspecialchars((string)$row['id']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= htmlspecialchars((string)$row['status']) ?></td>
      </tr>
      <?php endwhile; ?>
    </table>

    <h3>Update Tokens Earned</h3>
    <form method="POST">
      <input type="email" name="email" placeholder="Worker email" required><br>
      <input type="number" step="0.001" name="tokens" placeholder="Tokens earned" required><br>
      <button type="submit">Update</button>
    </form>

    <h3>All Workers</h3>
    <table>
      <tr>
        <th>ID</th><th>Worker Name</th><th>Email</th><th>HR2 (H/s)</th><th>Tokens Earned</th><th>Address</th>
      </tr>
      <?php
      $workers = $conn->query("SELECT id, worker_name, email, hr2, tokens_earned, address FROM workers");
      while ($w = $workers->fetch_assoc()):
      ?>
      <tr>
        <td><?= htmlspecialchars((string)$w['id']) ?></td>
        <td><?= htmlspecialchars($w['worker_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($w['email'] ?? '') ?></td>
        <td><?= htmlspecialchars((string)($w['hr2'] ?? '')) ?></td>
        <td><?= number_format((float)($w['tokens_earned'] ?? 0), 3) ?></td>
        <td><?= htmlspecialchars($w['address'] ?? 'N/A') ?></td>
      </tr>
      <?php endwhile; ?>
    </table>

    <h3>Site Stats</h3>
    <?php
    $res = $conn->query("SELECT SUM(visits) AS total FROM pages");
    $total = $res->fetch_assoc()['total'] ?? 0;
    echo "<p>Total Page Visits: <strong>" . number_format((int)$total) . "</strong></p>";
    ?>

    <h3>Page Visit Breakdown</h3>
    <table>
      <tr><th>Title</th><th>Path</th><th>Visits</th></tr>
      <?php
      $pages = $conn->query("SELECT title, path, visits FROM pages ORDER BY visits DESC");
      while ($row = $pages->fetch_assoc()):
      ?>
      <tr>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['path']) ?></td>
        <td><?= number_format((int)$row['visits']) ?></td>
      </tr>
      <?php endwhile; ?>
    </table>

    <h3>Edit Work Value Entries</h3>
    <table>
      <tr>
        <th>ID</th><th>Title</th><th>Type</th><th>Region</th>
        <th>Hourly USD</th><th>Hours</th><th>Status</th><th>Action</th>
      </tr>
      <?php
      $entries = $conn->query("SELECT * FROM work_value ORDER BY id DESC");
      while ($row = $entries->fetch_assoc()):
      ?>
      <tr>
        <form method="POST">
          <td><?= (int)$row['id'] ?></td>
          <td><input type="text" name="title" value="<?= htmlspecialchars($row['title']) ?>" required></td>
          <td>
            <select name="type">
              <option value="profession" <?= $row['type'] === 'profession' ? 'selected' : '' ?>>Profession</option>
              <option value="product" <?= $row['type'] === 'product' ? 'selected' : '' ?>>Product</option>
              <option value="service" <?= $row['type'] === 'service' ? 'selected' : '' ?>>Service</option>
            </select>
          </td>
          <td><input type="text" name="region" value="<?= htmlspecialchars($row['region']) ?>" required></td>
          <td><input type="number" step="0.01" name="hourly_usd" value="<?= htmlspecialchars((string)$row['hourly_usd']) ?>" required></td>
          <td><input type="number" name="hours" value="<?= htmlspecialchars((string)$row['hours']) ?>" required></td>
          <td>
            <select name="status">
              <option value="pending" <?= $row['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="approved" <?= $row['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
            </select>
          </td>
          <td>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button type="submit" name="update_work_value">Save</button>
          </td>
        </form>
      </tr>
      <?php endwhile; ?>
    </table>

    <h3>Manage Numbers Directory</h3>

    <!-- Add new entry -->
    <form method="POST" style="margin-bottom:20px;">
      <input type="hidden" name="manage_numbers" value="1">
      <input type="text" name="number" placeholder="Phone number" required>
      <input type="text" name="associated_with" placeholder="Associated with" required>
      <button type="submit">Add Number</button>
    </form>

    <!-- Existing entries -->
    <table>
      <tr><th>#</th><th>Number</th><th>Associated With</th><th>Actions</th></tr>
      <?php foreach ($numbersData as $i => $entry): ?>
      <tr>
        <td><?= (int)$i ?></td>
        <td><?= htmlspecialchars($entry['number'] ?? '') ?></td>
        <td><?= htmlspecialchars($entry['associated_with'] ?? '') ?></td>
        <td class="inline-forms">
          <!-- Edit -->
          <form method="POST">
            <input type="hidden" name="manage_numbers" value="1">
            <input type="hidden" name="edit_index" value="<?= (int)$i ?>">
            <input type="text" name="number" value="<?= htmlspecialchars($entry['number'] ?? '') ?>" required>
            <input type="text" name="associated_with" value="<?= htmlspecialchars($entry['associated_with'] ?? '') ?>" required>
            <button type="submit">Save</button>
          </form>
          <!-- Delete -->
          <form method="POST" onsubmit="return confirm('Delete this entry?');">
            <input type="hidden" name="manage_numbers" value="1">
            <input type="hidden" name="delete_index" value="<?= (int)$i ?>">
            <button type="submit" style="background:#e74c3c;">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>

  <?php endif; ?>
</div>
</body>
</html>