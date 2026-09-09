<?php
/**
 * CfCbazar Utility, Database & Mining Helpers
 * File: /includes/system_utility_helpers.php
 *
 * Provides database connection safety checks, mining reward logic, page slug redirects, 
 * and HTTPS enforcement wrappers.
 */

declare(strict_types=1);

/* ============================================================
   HTTPS ENFORCEMENT WITH MAINTENANCE FALLBACK
   ============================================================ */
if (!function_exists('enforce_https')) {

    function enforce_https(): void
    {
        global $conn;

        try {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $uri  = $_SERVER['REQUEST_URI'] ?? '/';

            // Check if request is already using HTTPS
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

            if ($isHttps) {
                return;
            }

            // Verify host is present and headers are not yet sent
            if (empty($host) || headers_sent()) {
                throw new RuntimeException("HTTPS redirect failed: Headers already sent or invalid host.");
            }

            // Redirect to HTTPS
            header("Location: https://{$host}{$uri}", true, 301);
            exit;

        } catch (Throwable $e) {
            // Error encountered: Set maintenance = 1 in the settings database table
            if ($conn instanceof mysqli && $conn->connect_errno === 0) {
                $stmt = $conn->prepare("UPDATE settings SET maintenance = 1");
                if ($stmt) {
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $conn->query("UPDATE settings SET maintenance = 1");
                }
            }
        }
    }
}

/* ============================================================
   MINING BONUS
   ============================================================ */
if (!function_exists('grant_mining_bonus')) {

    function grant_mining_bonus(string $email): void
    {
        global $conn;
        if (!$conn || empty($email)) return;

        $stmt = $conn->prepare("SELECT tokens_earned, mintme FROM workers WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->bind_result($tokens_earned, $mintme);

            if ($stmt->fetch()) {
                $stmt->close();

                if ($tokens_earned >= 0 && $tokens_earned < 1) {
                    $u1 = $conn->prepare("UPDATE workers SET tokens_earned = tokens_earned + 10 WHERE email = ?");
                    if ($u1) {
                        $u1->bind_param('s', $email);
                        $u1->execute();
                        $u1->close();
                    }
                }

                if ($mintme >= 0 && $mintme < 1) {
                    $u2 = $conn->prepare("UPDATE workers SET mintme = mintme + 10 WHERE email = ?");
                    if ($u2) {
                        $u2->bind_param('s', $email);
                        $u2->execute();
                        $u2->close();
                    }
                }
            } else {
                $stmt->close();
            }
        }
    }
}

/* ============================================================
   MINING REWARD
   ============================================================ */
if (!function_exists('reward_miner_workthr')) {

    function reward_miner_workthr(string $email, int $shares): bool
    {
        global $conn;
        if ($shares <= 0 || !$conn) return false;

        $reward_per_share = 0.01;
        $reward = $shares * $reward_per_share;

        $stmt = $conn->prepare("UPDATE workers SET mintme = mintme + ? WHERE email = ?");
        if (!$stmt) return false;

        $stmt->bind_param("ds", $reward, $email);
        $res = $stmt->execute();
        $stmt->close();

        return $res;
    }
}

/* ============================================================
   PAGE REDIRECT
   ============================================================ */
if (!function_exists('rvPageRedirect')) {

    function rvPageRedirect(string $slug, string $fallback = '#'): string
    {
        global $conn;

        if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
            return $fallback;
        }

        $stmt = $conn->prepare("SELECT path FROM pages WHERE slug = ? AND status = 'published' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                return $row['path'];
            }
            $stmt->close();
        }

        return $fallback;
    }
}

/* ============================================================
   CLOSE DATABASE
   ============================================================ */
if (!function_exists('close_database')) {

    function close_database(): void
    {
        global $conn;
        if ($conn instanceof mysqli) {
            $conn->close();
            $conn = null;
        }
    }
}

/* ============================================================
   REQUIRE DATABASE CONNECTION
   ============================================================ */
if (!function_exists('require_database_connection')) {

    function require_database_connection(): void
    {
        global $conn;

        if ($conn instanceof mysqli && $conn->connect_errno === 0) {
            return;
        }

        http_response_code(500);
        die('
            <div style="color:#faa; background:#400; padding:20px; border-radius:8px; width:350px; margin:40px auto; text-align:center; font-family:Arial,sans-serif;">
                <strong>Database Error</strong><br>
                Could not connect to the database.<br>
                Please check your configuration.
            </div>
        ');
    }
}

/* ============================================================
   getUserStatus() WITH AUTO-RECOVERY
   ============================================================ */
if (!function_exists('getUserStatus')) {

    function getUserStatus(?string $email = null): int
    {
        global $conn;

        if ($email instanceof mysqli) {
            $email = $_SESSION['email'] ?? null;
        }

        if ($email === null || $email === '') {
            return 0;
        }

        $stmt = $conn->prepare("SELECT status FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) return 0;

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($status);
        $found = $stmt->fetch();
        $stmt->close();

        return $found ? (int)$status : 0;
    }
}
