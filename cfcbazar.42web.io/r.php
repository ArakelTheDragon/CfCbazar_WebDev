<?php
// r.php
ob_start();
session_start();
ini_set('log_errors', 1);
ini_set('error_log', '/home/vol17_1/infinityfree.com/if0_39103611/htdocs/error.log');
error_reporting(E_ALL);

require 'config.php';
require 'includes/reusable.php';

if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(500);
    echo "❌ Database connection error.";
    ob_end_flush();
    exit;
}

// Table checks
$check_table = mysqli_query($conn, "SHOW TABLES FROM if0_39103611_db1 LIKE 'click_logs'");
error_log(mysqli_num_rows($check_table) == 0
    ? "click_logs table does not exist"
    : "click_logs table exists");

$test_query = mysqli_query($conn, "SELECT 1 FROM if0_39103611_db1.click_logs LIMIT 1");
error_log($test_query === false
    ? "Cannot access click_logs table: " . mysqli_error($conn)
    : "click_logs table is accessible for SELECT");

if (isset($_GET['go'])) {
    $id = intval($_GET['go']);
    if ($id <= 0) {
        error_log("Invalid link ID: " . $_GET['go']);
        http_response_code(400);
        echo "❌ Invalid link ID.";
        ob_end_flush();
        exit;
    }

    $check_stmt = $conn->prepare("SELECT `long`, email FROM if0_39103611_db1.short_links WHERE id = ?");
    if (!$check_stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo "❌ Internal server error.";
        ob_end_flush();
        exit;
    }
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_stmt->bind_result($longUrl, $ownerEmail);

    if ($check_stmt->fetch()) {
        $check_stmt->close();

        if (!preg_match('#^https?://#i', $longUrl)) {
            $longUrl = 'http://' . $longUrl;
        }
        if (!filter_var($longUrl, FILTER_VALIDATE_URL)) {
            error_log("Invalid URL: $longUrl");
            http_response_code(400);
            echo "❌ Invalid redirect URL.";
            ob_end_flush();
            exit;
        }

        $update = $conn->prepare("UPDATE if0_39103611_db1.short_links SET clicks = clicks + 1 WHERE id = ?");
        if ($update) {
            $update->bind_param("i", $id);
            $update->execute();
            error_log($update->affected_rows === 0
                ? "No rows updated for id: $id"
                : "Updated clicks for id: $id");
            $update->close();
        }

        $ip         = mb_convert_encoding($_SERVER['REMOTE_ADDR'] ?? 'unknown', 'UTF-8', 'auto');
        $user_agent = mb_convert_encoding(substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255), 'UTF-8', 'auto');
        $referrer   = mb_convert_encoding(substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255), 'UTF-8', 'auto');
        $utm_source = mb_convert_encoding(substr($_GET['utm_source'] ?? '', 0, 255), 'UTF-8', 'auto');

        $platform = 'unknown';
        if (strpos($user_agent, 'Mobile') !== false || strpos($user_agent, 'Android') !== false || strpos($user_agent, 'iPhone') !== false) {
            $platform = 'mobile';
        } elseif (strpos($user_agent, 'Windows') !== false) {
            $platform = 'windows';
        } elseif (strpos($user_agent, 'Mac') !== false) {
            $platform = 'mac';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            $platform = 'linux';
        }
        $platform = mb_convert_encoding($platform, 'UTF-8', 'auto');

        error_log("Logging click: id=$id, ip=$ip, ua=$user_agent, ref=$referrer, platform=$platform, utm=$utm_source");

        $log_stmt = $conn->prepare("
            INSERT INTO if0_39103611_db1.click_logs
            (short_id, ip, user_agent, referrer, platform, utm_source, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if ($log_stmt) {
            $log_stmt->bind_param("isssss", $id, $ip, $user_agent, $referrer, $platform, $utm_source);
            if (!$log_stmt->execute()) {
                error_log("Full INSERT failed: " . $log_stmt->error);
                $minimal_stmt = $conn->prepare("
                    INSERT INTO if0_39103611_db1.click_logs
                    (short_id, ip, platform, created_at) VALUES (?, ?, ?, NOW())
                ");
                if ($minimal_stmt) {
                    $minimal_stmt->bind_param("iss", $id, $ip, $platform);
                    $minimal_stmt->execute();
                    $minimal_stmt->close();
                }
            }
            $log_stmt->close();
        }

        if (!empty($ownerEmail)) {
            $deduct = $conn->prepare("UPDATE if0_39103611_db1.workers SET tokens_earned = tokens_earned - 0.01 WHERE email = ?");
            if ($deduct) {
                $deduct->bind_param("s", $ownerEmail);
                $deduct->execute();
                $deduct->close();
            }
        }

        header("Location: " . $longUrl);
        ob_end_flush();
        exit;
    } else {
        $check_stmt->close();
        error_log("Short link not found for id: $id");
        http_response_code(404);
        echo "❌ Short link not found.";
        ob_end_flush();
        exit;
    }
}

// Page visit tracker
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = ($uri === '/' ? '/r.php' : $uri);
$upd  = $conn->prepare("UPDATE if0_39103611_db1.pages SET visits = visits + 1, updated_at = NOW() WHERE path = ?");
if ($upd) {
    $upd->bind_param('s', $path);
    $upd->execute();
    if ($upd->affected_rows === 0) {
        $slug = ltrim($path, '/');
        $slug = $slug === '' ? 'url-shortener' : $slug;
        $title = 'URL Shortener';
        $ins = $conn->prepare("
            INSERT INTO if0_39103611_db1.pages (title, slug, path, visits, created_at, updated_at)
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

// Link creation
$email = $_SESSION['email'] ?? null;
$shortened = '';
$error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['longurl'])) {
    $longUrl = trim($_POST['longurl']);
    if (!$email) {
        $error = "Please log in to create links.";
    } elseif (empty($longUrl)) {
        $error = "Please enter a URL.";
    } elseif (!filter_var($longUrl, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid URL.";
    } else {
        if (!preg_match('#^https?://#i', $longUrl)) {
            $longUrl = 'http://' . $longUrl;
        }
        $stmt = $conn->prepare("SELECT id FROM if0_39103611_db1.short_links WHERE `long` = ? AND email = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $longUrl, $email);
            $stmt->execute();
            $stmt->bind_result($existingId);
            if ($stmt->fetch()) {
                $id = $existingId;
                $stmt->close();
            } else {
                $stmt->close();
                $shortened = "https://cfcbazar.42web.io/r.php?go=";
                $stmt = $conn->prepare("INSERT INTO if0_39103611_db1.short_links (`long`, `short`, clicks, email) VALUES (?, ?, 0, ?)");
                if ($stmt) {
                    $stmt->bind_param("sss", $longUrl, $shortened, $email);
                    if ($stmt->execute()) {
                        $id = $stmt->insert_id;
                        $shortened = "https://cfcbazar.42web.io/r.php?go=$id";
                        $update = $conn->prepare("UPDATE if0_39103611_db1.short_links SET `short` = ? WHERE id = ?");
                        if ($update) {
                            $update->bind_param("si", $shortened, $id);
                            $update->execute();
                            $update->close();
                        }
                        $deduct = $conn->prepare("UPDATE if0_39103611_db1.workers SET tokens_earned = tokens_earned - 0.01 WHERE email = ?");
                        if ($deduct) {
                            $deduct->bind_param("s", $email);
                            $deduct->execute();
                            $deduct->close();
                        }
                    }
                    $stmt->close();
                }
            }
            if (!empty($id)) {
                $shortened = "https://cfcbazar.42web.io/r.php?go=$id";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<?php
$title = 'URL Shortener';
include_header();
include_menu();
?>
<main class="container">
    <section class="card">
        <form method="post" class="shorten-form">
            <div class="form-group">
                <label for="longurl">Enter Your URL:</label>
                <input type="text" name="longurl" id="longurl" placeholder="Enter full URL (e.g., https://example.com)" required />
            </div>
            <button type="submit" class="btn btn-primary">Shorten URL</button>
        </form>
        <?php if ($shortened): ?>
            <div class="message success">
                ✅ Short link created successfully:
                <a href="<?= htmlspecialchars($shortened) ?>" target="_blank"><?= htmlspecialchars($shortened) ?></a>
            </div>
            <div class="qr-code">
                <canvas id="qr-canvas" data-qr-value="<?= htmlspecialchars($shortened) ?>"></canvas>
                <button onclick="downloadQR()" class="btn btn-secondary">📥 Download QR</button>
            </div>
        <?php elseif ($error): ?>
            <div class="message error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </section>

    <?php if ($email): ?>
        <section class="card stats">
            <h2>📊 Your Short Link Stats</h2>
            <?php
            $conn->query("SET SESSION group_concat_max_len = 10000");
            $stmt = $conn->prepare("
                SELECT sl.id, sl.`long`, sl.clicks,
                       (SELECT ip FROM if0_39103611_db1.click_logs WHERE short_id = sl.id ORDER BY created_at DESC LIMIT 1) AS last_ip,
                       GROUP_CONCAT(DISTINCT cl.referrer ORDER BY cl.referrer) AS referrers,
                       GROUP_CONCAT(DISTINCT cl.platform ORDER BY cl.platform) AS platforms,
                       GROUP_CONCAT(DISTINCT cl.utm_source ORDER BY cl.utm_source) AS utm_sources
                FROM if0_39103611_db1.short_links sl
                LEFT JOIN if0_39103611_db1.click_logs cl ON sl.id = cl.short_id
                WHERE sl.email = ?
                GROUP BY sl.id
            ");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                        $short = "https://cfcbazar.42web.io/r.php?go=" . $row['id'];
            ?>
                        <div class="table-container">
                            <h3>🔗 Link Summary</h3>
                            <table>
                                <tr><th>Original URL</th><td><a href="<?= htmlspecialchars($row['long']) ?>" target="_blank"><?= htmlspecialchars($row['long']) ?></a></td></tr>
                                <tr><th>Short URL</th><td><a href="<?= htmlspecialchars($short) ?>" target="_blank"><?= htmlspecialchars($short) ?></a></td></tr>
                                <tr><th>Clicks</th><td><?= intval($row['clicks']) ?></td></tr>
                                <tr><th>Last IP</th><td><?= htmlspecialchars($row['last_ip'] ?? 'None') ?></td></tr>
                                <tr><th>Referrers</th><td style="white-space: normal; word-break: break-word;"><?= htmlspecialchars($row['referrers'] ?? 'None') ?></td></tr>
                                <tr><th>Platforms</th><td><?= htmlspecialchars($row['platforms'] ?? 'None') ?></td></tr>
                                <tr><th>UTM Sources</th><td><?= htmlspecialchars($row['utm_sources'] ?? 'None') ?></td></tr>
                            </table>
                        </div>

                        <div class="table-container" style=".table-container + .table-container { margin-top: 1.5rem; }">
                            <h3>Click Details</h3>
                            <?php
                            $detail_stmt = $conn->prepare("
                                SELECT ip, user_agent, referrer, platform, utm_source, created_at
                                FROM if0_39103611_db1.click_logs
                                WHERE short_id = ?
                                ORDER BY created_at DESC
                                LIMIT 100
                            ");
                            if ($detail_stmt) {
                                $detail_stmt->bind_param("i", $row['id']);
                                $detail_stmt->execute();
                                $detail_result = $detail_stmt->get_result();
                                if ($detail_result->num_rows > 0):
                            ?>
                                <table class="worker-stats-table">
                                    <thead>
                                        <tr>
                                            <th>IP</th>
                                            <th>User Agent</th>
                                            <th>Referrer</th>
                                            <th>Platform</th>
                                            <th>UTM Source</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($detail_row = $detail_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($detail_row['ip']) ?></td>
                                                <td><?= htmlspecialchars($detail_row['user_agent'] ?? 'None') ?></td>
                                                <td><?= htmlspecialchars($detail_row['referrer'] ?? 'None') ?></td>
                                                <td><?= htmlspecialchars($detail_row['platform']) ?></td>
                                                <td><?= htmlspecialchars($detail_row['utm_source'] ?? 'None') ?></td>
                                                <td><?= htmlspecialchars($detail_row['created_at']) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p>No clicks recorded for this link.</p>
                            <?php endif;
                                $detail_stmt->close();
                            }
                            ?>
                        </div>
                    <?php endwhile;
                else:
                    echo "<p class='no-links'>You haven't created any links yet.</p>";
                endif;
                $stmt->close();
            }
            ?>
        </section>
    <?php endif; ?>
</main>
<?php include_footer(); ?>
<script src="/js/qr.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
