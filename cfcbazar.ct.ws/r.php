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

// Verify click_logs table exists
$check_table = mysqli_query($conn, "SHOW TABLES FROM if0_39103611_db1 LIKE 'click_logs'");
if (mysqli_num_rows($check_table) == 0) {
    error_log("click_logs table does not exist in database if0_39103611_db1 for user if0_39103611");
} else {
    error_log("click_logs table exists in database if0_39103611_db1 for user if0_39103611");
}

// Test click_logs accessibility
$test_query = mysqli_query($conn, "SELECT 1 FROM if0_39103611_db1.click_logs LIMIT 1");
if ($test_query === false) {
    error_log("Cannot access click_logs table: " . mysqli_error($conn));
} else {
    error_log("click_logs table is accessible for SELECT");
}

if (isset($_GET['go'])) {
    $id = intval($_GET['go']);
    if ($id <= 0) {
        error_log("Invalid link ID: " . $_GET['go']);
        http_response_code(400);
        echo "❌ Invalid link ID.";
        ob_end_flush();
        exit;
    }

    // Validate short_id exists in short_links
    $check_stmt = $conn->prepare("SELECT `long`, email FROM if0_39103611_db1.short_links WHERE id = ?");
    if ($check_stmt === false) {
        error_log("Prepare failed for short_links SELECT: " . $conn->error);
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
            error_log("Invalid URL for redirect: $longUrl");
            http_response_code(400);
            echo "❌ Invalid redirect URL.";
            ob_end_flush();
            exit;
        }

        // Update clicks
        $update = $conn->prepare("UPDATE if0_39103611_db1.short_links SET clicks = clicks + 1 WHERE id = ?");
        if ($update) {
            $update->bind_param("i", $id);
            $update->execute();
            if ($update->affected_rows === 0) {
                error_log("No rows updated for short_links clicks, id: $id");
            } else {
                error_log("Updated clicks for short_links, id: $id");
            }
            $update->close();
        } else {
            error_log("Failed to prepare UPDATE for short_links: " . $conn->error);
        }

        // Log click tracking data to click_logs
        $ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
        $referrer   = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255);
        $utm_source = substr($_GET['utm_source'] ?? '', 0, 255);

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

        // Sanitize inputs
        $ip         = mb_convert_encoding($ip, 'UTF-8', 'auto');
        $user_agent = mb_convert_encoding($user_agent, 'UTF-8', 'auto');
        $referrer   = mb_convert_encoding($referrer, 'UTF-8', 'auto');
        $utm_source = mb_convert_encoding($utm_source, 'UTF-8', 'auto');
        $platform   = mb_convert_encoding($platform, 'UTF-8', 'auto');

        error_log("Attempting to log click for short_id=$id, ip=$ip, ua=$user_agent, ref=$referrer, platform=$platform, utm=$utm_source");

        // Attempt full insert (with created_at)
        $log_stmt = $conn->prepare("
            INSERT INTO if0_39103611_db1.click_logs
            (short_id, ip, user_agent, referrer, platform, utm_source, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if ($log_stmt) {
            $log_stmt->bind_param("isssss", $id, $ip, $user_agent, $referrer, $platform, $utm_source);
            if ($log_stmt->execute()) {
                error_log("Successfully logged click for short_id=$id, ip=$ip");
            } else {
                error_log("Full INSERT failed: " . $log_stmt->error);
                // Fallback minimal insert
                $minimal_stmt = $conn->prepare("
                    INSERT INTO if0_39103611_db1.click_logs
                    (short_id, ip, platform, created_at) VALUES (?, ?, ?, NOW())
                ");
                if ($minimal_stmt) {
                    $minimal_stmt->bind_param("iss", $id, $ip, $platform);
                    if ($minimal_stmt->execute()) {
                        error_log("Fallback minimal INSERT succeeded for short_id=$id");
                    } else {
                        error_log("Fallback minimal INSERT failed: " . $minimal_stmt->error);
                    }
                    $minimal_stmt->close();
                }
            }
            $log_stmt->close();
        }

        // Deduct from workers
        if (!empty($ownerEmail)) {
            $deduct = $conn->prepare("UPDATE if0_39103611_db1.workers SET tokens_earned = tokens_earned - 0.01 WHERE email = ?");
            if ($deduct) {
                $deduct->bind_param("s", $ownerEmail);
                $deduct->execute();
                $deduct->close();
            } else {
                error_log("Failed to prepare UPDATE for workers: " . $conn->error);
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

// === Pages visits tracker ===
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

// === Link creation handler ===
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
                $shortened = "https://cfcbazar.ct.ws/r.php?go=";
                $stmt = $conn->prepare("INSERT INTO if0_39103611_db1.short_links (`long`, `short`, clicks, email) VALUES (?, ?, 0, ?)");
                if ($stmt) {
                    $stmt->bind_param("sss", $longUrl, $shortened, $email);
                    if ($stmt->execute()) {
                        $id = $stmt->insert_id;
                        $shortened = "https://cfcbazar.ct.ws/r.php?go=$id";
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
                $shortened = "https://cfcbazar.ct.ws/r.php?go=$id";
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
                        $short = "https://cfcbazar.ct.ws/r.php?go=" . $row['id'];
            ?>
                        <div class="url-box">
                            <p><strong>Original URL:</strong> <?= htmlspecialchars($row['long']) ?></p>
                            <p><strong>Short URL:</strong> <a href="<?= htmlspecialchars($short) ?>" target="_blank"><?= htmlspecialchars($short) ?></a></p>
                            <p><strong>Clicks:</strong> <?= intval($row['clicks']) ?></p>
                            <p><strong>Last IP:</strong> <?= htmlspecialchars($row['last_ip'] ?? 'None') ?></p>
                            <p><strong>Referrers:</strong> <?= htmlspecialchars($row['referrers'] ?? 'None') ?></p>
                            <p><strong>Platforms:</strong> <?= htmlspecialchars($row['platforms'] ?? 'None') ?></p>
                            <p><strong>UTM Sources:</strong> <?= htmlspecialchars($row['utm_sources'] ?? 'None') ?></p>
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
                                    <table class="table">
                                        <tr>
                                            <th>IP</th>
                                            <th>User Agent</th>
                                            <th>Referrer</th>
                                            <th>Platform</th>
                                            <th>UTM Source</th>
                                            <th>Time</th>
                                        </tr>
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
                                    </table>
                                <?php else: ?>
                                    <p>No clicks recorded for this link.</p>
                                <?php endif; ?>
                                <?php
                                $detail_stmt->close();
                            }
                            ?>
                        </div>
                    <?php
                    endwhile;
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