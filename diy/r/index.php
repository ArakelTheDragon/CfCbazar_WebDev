<?php
// /diy/r/index.php
ob_start();
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

// Public by default — do NOT force login here
$NEED_SESSION = true;
$NEED_LOGIN   = true;
$NEED_CART    = false;
$NEED_ADMIN   = false;

enforce_https();
checkSystemFlags();
bootstrap(); // may start session if one already exists; does not require login

if (empty($conn)) {
    http_response_code(500);
    echo "Database connection error.";
    ob_end_flush();
    exit;
}

$SITE_BASE  = 'https://cfcbazar.8bit.ca';
$SHORT_PATH = '/diy/r/index.php';

// ============================================================
// PUBLIC redirect
// ============================================================
if (isset($_GET['go'])) {
    $id = intval($_GET['go']);
    if ($id <= 0) {
        http_response_code(400);
        echo "Invalid link ID.";
        ob_end_flush();
        exit;
    }

    $check_stmt = $conn->prepare("SELECT `long`, email FROM short_links WHERE id = ? LIMIT 1");
    if (!$check_stmt) {
        http_response_code(500);
        echo "Internal server error.";
        ob_end_flush();
        exit;
    }
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_stmt->bind_result($longUrl, $ownerEmail);
    if (!$check_stmt->fetch()) {
        $check_stmt->close();
        http_response_code(404);
        echo "Short link not found.";
        ob_end_flush();
        exit;
    }
    $check_stmt->close();

    if (!preg_match('#^https?://#i', $longUrl)) {
        $longUrl = 'http://' . $longUrl;
    }
    if (!filter_var($longUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo "Invalid redirect URL.";
        ob_end_flush();
        exit;
    }

    $update = $conn->prepare("UPDATE short_links SET clicks = clicks + 1 WHERE id = ?");
    if ($update) {
        $update->bind_param("i", $id);
        $update->execute();
        $update->close();
    }

    $ip         = mb_convert_encoding($_SERVER['REMOTE_ADDR'] ?? 'unknown', 'UTF-8', 'auto');
    $user_agent = mb_convert_encoding(substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255), 'UTF-8', 'auto');
    $referrer   = mb_convert_encoding(substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255), 'UTF-8', 'auto');
    $utm_source = mb_convert_encoding(substr($_GET['utm_source'] ?? '', 0, 255), 'UTF-8', 'auto');
    $platform = 'unknown';
    if (preg_match('/Mobile|Android|iPhone/i', $user_agent)) $platform = 'mobile';
    elseif (stripos($user_agent, 'Windows') !== false) $platform = 'windows';
    elseif (stripos($user_agent, 'Mac') !== false) $platform = 'mac';
    elseif (stripos($user_agent, 'Linux') !== false) $platform = 'linux';

    $log_stmt = $conn->prepare("
        INSERT INTO click_logs (short_id, ip, user_agent, referrer, platform, utm_source, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    if ($log_stmt) {
        $log_stmt->bind_param("isssss", $id, $ip, $user_agent, $referrer, $platform, $utm_source);
        $log_stmt->execute();
        $log_stmt->close();
    }

    if (!empty($ownerEmail)) {
        $deduct = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned - 0.01 WHERE email = ?");
        if ($deduct) {
            $deduct->bind_param("s", $ownerEmail);
            $deduct->execute();
            $deduct->close();
        }
    }

    header("Location: " . $longUrl);
    ob_end_flush();
    exit;
}

// ============================================================
// Page (public shell; create/stats only if logged in)
// ============================================================
$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['email']) || !empty($_SESSION['user_email']);
$email    = $_SESSION['email'] ?? ($_SESSION['user_email'] ?? null);
$shortened = '';
$error     = '';

if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['longurl'])) {
    $longUrl = trim($_POST['longurl']);
    if (!$email) {
        $error = "Please log in to create links.";
    } elseif ($longUrl === '') {
        $error = "Please enter a URL.";
    } else {
        if (!preg_match('#^https?://#i', $longUrl)) {
            $longUrl = 'http://' . $longUrl;
        }
        if (!filter_var($longUrl, FILTER_VALIDATE_URL)) {
            $error = "Please enter a valid URL.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM short_links WHERE `long` = ? AND email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $longUrl, $email);
                $stmt->execute();
                $stmt->bind_result($existingId);
                if ($stmt->fetch()) {
                    $id = (int)$existingId;
                    $stmt->close();
                    $shortened = $SITE_BASE . $SHORT_PATH . '?go=' . $id;
                } else {
                    $stmt->close();
                    $placeholder = $SITE_BASE . $SHORT_PATH . '?go=';
                    $ins = $conn->prepare("INSERT INTO short_links (`long`, `short`, clicks, email) VALUES (?, ?, 0, ?)");
                    if ($ins) {
                        $ins->bind_param("sss", $longUrl, $placeholder, $email);
                        if ($ins->execute()) {
                            $id = (int)$ins->insert_id;
                            $shortened = $SITE_BASE . $SHORT_PATH . '?go=' . $id;
                            $upd = $conn->prepare("UPDATE short_links SET `short` = ? WHERE id = ?");
                            if ($upd) {
                                $upd->bind_param("si", $shortened, $id);
                                $upd->execute();
                                $upd->close();
                            }
                        }
                        $ins->close();
                    }
                }
            }
        }
    }
}

$url        = "/diy/r/index.php";
$parent_url = "/diy";
$title      = "URL Shortener";
$template   = "Generic";
$meta_title = "URL Shortener – CfCbazar";
$meta_desc  = "Create short links with click tracking.";

trackVisit(
    slug: $url, parentUrl: $parent_url, title: $title,
    template: $template, metaTitle: $meta_title, metaDesc: $meta_desc
);

render_header($title);
?>
<main class="content">
  <div class="container">
    <section class="card">
      <h1>URL Shortener</h1>

      <?php if (!$loggedIn): ?>
        <p>Create short links and track clicks.</p>
        <p><a class="btn btn-primary" href="/login.php?return=<?= urlencode($SHORT_PATH) ?>">Log in to create links</a></p>
      <?php else: ?>
        <form method="post" class="shorten-form">
          <div class="form-group">
            <label for="longurl">Enter Your URL:</label>
            <input type="text" name="longurl" id="longurl" placeholder="https://example.com" required>
          </div>
          <button type="submit" class="btn btn-primary">Shorten URL</button>
        </form>
        <?php if ($shortened): ?>
          <div class="message success" style="margin-top:1rem;">
            Short link:
            <a href="<?= htmlspecialchars($shortened) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($shortened) ?></a>
          </div>
        <?php elseif ($error): ?>
          <div class="message error" style="margin-top:1rem;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if ($loggedIn && $email): ?>
    <section class="card stats">
      <h2>Your Short Link Stats</h2>
      <?php
        $conn->query("SET SESSION group_concat_max_len = 10000");
        $stmt = $conn->prepare("
          SELECT sl.id, sl.`long`, sl.clicks,
            (SELECT ip FROM click_logs WHERE short_id = sl.id ORDER BY created_at DESC LIMIT 1) AS last_ip,
            GROUP_CONCAT(DISTINCT cl.referrer) AS referrers,
            GROUP_CONCAT(DISTINCT cl.platform) AS platforms,
            GROUP_CONCAT(DISTINCT cl.utm_source) AS utm_sources
          FROM short_links sl
          LEFT JOIN click_logs cl ON sl.id = cl.short_id
          WHERE sl.email = ?
          GROUP BY sl.id
        ");
        if ($stmt) {
          $stmt->bind_param("s", $email);
          $stmt->execute();
          $result = $stmt->get_result();
          if ($result && $result->num_rows > 0) {
            echo '<ul class="link-list">';
            while ($row = $result->fetch_assoc()) {
              $short  = $SITE_BASE . $SHORT_PATH . '?go=' . $row['id'];
              $label  = parse_url($row['long'], PHP_URL_HOST) ?: $row['long'];
              $clicks = (int)$row['clicks'];
              echo '<li><details class="link-item"><summary>';
              echo '<span>'.htmlspecialchars($label).'</span> ';
              echo '<span class="link-badge">'.$clicks.' clicks</span> ';
              echo '<span style="word-break:break-all;">'.htmlspecialchars($short).'</span>';
              echo '</summary><div class="link-body"><table>';
              echo '<tr><th>Original</th><td><a href="'.htmlspecialchars($row['long']).'" target="_blank" rel="noopener">'.htmlspecialchars($row['long']).'</a></td></tr>';
              echo '<tr><th>Short</th><td><a href="'.htmlspecialchars($short).'" target="_blank" rel="noopener">'.htmlspecialchars($short).'</a></td></tr>';
              echo '<tr><th>Clicks</th><td>'.$clicks.'</td></tr>';
              echo '<tr><th>Last IP</th><td>'.htmlspecialchars($row['last_ip'] ?? 'None').'</td></tr>';
              echo '<tr><th>Referrers</th><td>'.htmlspecialchars($row['referrers'] ?? 'None').'</td></tr>';
              echo '<tr><th>Platforms</th><td>'.htmlspecialchars($row['platforms'] ?? 'None').'</td></tr>';
              echo '<tr><th>UTM</th><td>'.htmlspecialchars($row['utm_sources'] ?? 'None').'</td></tr>';
              echo '</table></div></details></li>';
            }
            echo '</ul>';
          } else {
            echo "<p class='no-links'>You haven't created any links yet.</p>";
          }
          $stmt->close();
        }
      ?>
    </section>
    <?php endif; ?>
  </div>
</main>
<?php
cfc_footer(
  "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/r/index.php",
  "URL Shortener Source Code"
);
include_footer();
close_database();
ob_end_flush();
?>
