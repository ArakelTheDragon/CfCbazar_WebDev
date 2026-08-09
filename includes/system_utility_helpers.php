<?php
/**
 * CfCbazar Utility, Database & Mining Helpers
 * File: /includes/system_utility_helpers.php
 *
 * Provides database connection safety checks, mining reward logic, page slug redirects, 
 * and HTTPS enforcement wrappers.
 */

declare(strict_types=1);

if (!function_exists('enforce_https')) {
    /**
     * Enforces HTTPS redirect when supported by the server environment.
     */
    function enforce_https(): void {
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            if (!headers_sent()) {
                header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
                exit();
            }
        }
    }
}

if (!function_exists('grant_mining_bonus')) {
    /**
     * Grants a default starter bonus of 10 WTK and 10 WorkTHR to accounts with low balances.
     */
    function grant_mining_bonus(string $email): void {
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

if (!function_exists('reward_miner_workthr')) {
    /**
     * Credits WorkTHR tokens based on submitted mining share counts.
     */
    function reward_miner_workthr(string $email, int $shares): bool {
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

if (!function_exists('rvPageRedirect')) {
    /**
     * Resolves and returns the page URL path for a given page slug.
     */
    function rvPageRedirect(string $slug, string $fallback = '#'): string {
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

if (!function_exists('close_database')) {
    /**
     * Safely closes the active MySQL database connection.
     */
    function close_database(): void {
        global $conn;
        if ($conn instanceof mysqli) {
            $conn->close();
            $conn = null;
        }
    }
}

if (!function_exists('require_database_connection')) {
    /**
     * Verifies active database connection or displays a fatal error UI.
     */
    function require_database_connection(): void {
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
