<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1) Start session for flash‐messages
session_start();

// 2) Load database connection
require 'config.php';

// 3) Page visit tracking (before any output)
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/index.php' : $uri);

// Attempt to increment visits; if no row exists, insert a new one
$upd = $conn->prepare("
    UPDATE pages
       SET visits = visits + 1, updated_at = NOW()
     WHERE path = ?
");
if ($upd) {
    $upd->bind_param('s', $path);
    $upd->execute();

    // If no page row was updated, insert a new record
    if ($upd->affected_rows === 0) {
        // Derive a slug and human‐readable title from the path
        $slug  = ltrim($path, '/');
        $slug  = $slug === '' ? 'index' : $slug;
        $title = ucfirst(str_replace(['-', '_'], ' ', $slug));

        $ins = $conn->prepare("
            INSERT INTO pages
              (title, slug, path, visits, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");
        if ($ins) {
            $ins->bind_param('sss', $title, $slug, $path);
            if (! $ins->execute()) {
                error_log("Visits INSERT failed: " . $ins->error);
            }
            $ins->close();
        } else {
            error_log("Visits INSERT prepare failed: " . $conn->error);
        }
    }
    $upd->close();
} else {
    error_log("Visits UPDATE prepare failed: " . $conn->error);
}

// 4) Post–Redirect–Get flash message logic
$message = '';
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

// 5) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title']   ?? '');
    $type    = $_POST['type']         ?? '';
    $region  = trim($_POST['region']  ?? '');
    $rate    = (float) $_POST['hourly_usd'];
    $hours   = (int)   $_POST['hours'];

    if (
        $title &&
        $region &&
        in_array($type, ['profession','product','service'], true) &&
        $rate  > 0 &&
        $hours > 0
    ) {
        // Calculate USD and WTK values
        $total_usd  = $rate * $hours;
        $hourly_wtk = intval($rate      * 10_000_000);
        $total_wtk  = intval($total_usd * 10_000_000);

        $ins = $conn->prepare("
            INSERT INTO work_value
              (title, type, region,
               hourly_usd, hourly_wtk,
               hours, total_usd, total_wtk,
               status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $ins->bind_param(
            "sssdiidd",
            $title, $type, $region,
            $rate, $hourly_wtk,
            $hours, $total_usd, $total_wtk
        );
        if ($ins->execute()) {
            $_SESSION['form_message'] = "✅ Submission sent successfully and is awaiting admin approval.";
        } else {
            $_SESSION['form_message'] = "❌ Failed to submit. Try again.";
            error_log("work_value INSERT failed: " . $ins->error);
        }
        $ins->close();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['form_message'] = "⚠️ Please complete all fields with valid values.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// 6) Load only approved entries
$query = $conn->query("
    SELECT *
      FROM work_value
     WHERE status = 'approved'
  ORDER BY type, title
");
if (! $query) {
    die("Database query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>💼 Work Value Directory | CfCbazar</title>
<meta name="description" content="Submit and explore real-world work values in USD and WTK. CfCbazar's Work Value Directory helps estimate fair compensation for professions, products, and services." />
<meta name="keywords" content="work value, profession rates, product pricing, service compensation, CfCbazar, WTK conversion, hourly rate calculator" />
<meta name="author" content="CfCbazar" />
<meta name="robots" content="index, follow" />

<!-- Open Graph -->
<meta property="og:title" content="💼 Work Value Directory | CfCbazar" />
<meta property="og:description" content="Submit and explore real-world work values in USD and WTK. Estimate fair compensation for professions, products, and services." />
<meta property="og:type" content="website" />
<meta property="og:url" content="https://cfcbazar.ct.ws/work_value.php" />
<meta property="og:image" content="https://cfcbazar.ct.ws/work-value-preview.png" />

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="💼 Work Value Directory | CfCbazar" />
<meta name="twitter:description" content="Explore and submit work values in USD and WTK. A free tool by CfCbazar." />
<meta name="twitter:image" content="https://cfcbazar.ct.ws/work-value-preview.png" />
  <title>Work Value Directory | CFCBazar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f5f8fb;
      color: #333;
      margin: 0;
      padding: 40px 20px;
    }
    h1 {
      color: #2a405f;
      text-align: center;
      margin: 30px 0 20px;
    }
    .container {
      max-width: 600px;
      margin: auto;
      background: white;
      border-radius: 8px;
      padding: 30px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    label {
      display: block;
      margin-top: 15px;
      font-weight: 500;
    }
    input, select {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border-radius: 4px;
      border: 1px solid #ccc;
    }
    button {
      margin-top: 20px;
      padding: 10px 18px;
      background-color: #2a405f;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    button:hover {
      background: #1a2e4d;
    }
    .message {
      margin-top: 20px;
      font-size: 14px;
      color: #555;
      text-align: center;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      max-width: 1000px;
      margin: 50px auto 0;
      background: white;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    th, td {
      padding: 14px 12px;
      border-bottom: 1px solid #ddd;
      text-align: left;
    }
    th {
      background: #2a405f;
      color: white;
      font-weight: 600;
    }
    td:nth-child(5),
    td:nth-child(8) {
      color: #008060;
      font-weight: bold;
    }
    caption {
      margin-bottom: 15px;
      font-size: 1.1em;
      font-weight: 500;
      color: #4a6587;
    }
    .footer {
      text-align: center;
      margin-top: 50px;
      font-size: 13px;
      color: #666;
    }
    .footer a {
      color: #2a405f;
      text-decoration: underline;
      margin: 0 4px;
    }
  </style>
</head>
<body>

  <h1>Submit a Work Value</h1>
  <div class="container">
    <form method="POST">
      <label for="title">Title</label>
      <input type="text" name="title" id="title" required />

      <label for="type">Type</label>
      <select name="type" id="type" required>
        <option value="profession">Profession</option>
        <option value="product">Product</option>
        <option value="service">Service</option>
      </select>

      <label for="region">Region</label>
      <input type="text" name="region" id="region" required />

      <label for="hourly_usd">Hourly Rate (USD)</label>
      <input type="number" step="0.01" name="hourly_usd" id="hourly_usd" required />

      <label for="hours">Total Estimated Hours</label>
      <input type="number" name="hours" id="hours" required />

      <button type="submit">Submit for Approval</button>
    </form>

    <?php if ($message): ?>
      <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
  </div>

  <h1>Work Value Table</h1>
  <table>
    <caption>Approved Professions, Products & Services with WTK Conversion</caption>
    <tr>
      <th>Title</th>
      <th>Type</th>
      <th>Region</th>
      <th>Hourly Rate (USD)</th>
      <th>Hourly Rate (WTK)</th>
      <th>Total Value (USD)</th>
      <th>Hours</th>
      <th>Total Value (WTK)</th>
    </tr>
    <?php while ($row = $query->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($row['title']) ?></td>
      <td><?= ucfirst($row['type']) ?></td>
      <td><?= htmlspecialchars($row['region']) ?></td>
      <td>$<?= number_format($row['hourly_usd'], 2) ?></td>
      <td><?= number_format($row['hourly_wtk']) ?> WTK</td>
      <td>$<?= number_format($row['total_usd'],   2) ?></td>
      <td><?= $row['hours'] ?></td>
      <td><?= number_format($row['total_wtk'])  ?> WTK</td>
    </tr>
    <?php endwhile; ?>
  </table>

  <div class="footer">
    By using this site, you agree to our 
    <a href="miner/terms.html">Terms & Conditions</a>, 
    <a href="miner/privacy.html">Privacy Policy</a>, and 
    <a href="miner/cookies.html">Cookie Policy</a>.
  </div>

</body>
</html>