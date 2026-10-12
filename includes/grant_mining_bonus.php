<?php
declare(strict_types=1);

/**
 * Grant a small mining / WorkTHR bonus to a logged-in user.
 * Called from /worktoken/index.php (rate-limited to once per 60s).
 *
 * @param string $email
 * @return bool
 */
function grant_mining_bonus(string $email): bool
{
    global $conn;

    if (empty($email) || !isset($conn) || !($conn instanceof mysqli)) {
        return false;
    }

    try {
        // Get current status and worker balance
        $stmt = $conn->prepare("SELECT u.status, w.mintme 
                                FROM users u 
                                LEFT JOIN workers w ON w.email = u.email 
                                WHERE u.email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($status, $mintme);
        $stmt->fetch();
        $stmt->close();

        if ($status === null) {
            return false;
        }

        // Bonus amount (VIP gets a bit more)
        $bonus = ($status == 4) ? 0.05 : 0.02;

        // Ensure worker row exists
        $stmt = $conn->prepare("INSERT INTO workers (email, mintme) 
                                VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE mintme = mintme + ?");
        $stmt->bind_param("sdd", $email, $bonus, $bonus);
        $stmt->execute();
        $stmt->close();

        // Optional log
        $stmt = $conn->prepare("INSERT INTO mining_bonus_log (email, amount, created_at) 
                                VALUES (?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sd", $email, $bonus);
            $stmt->execute();
            $stmt->close();
        }

        return true;
    } catch (Throwable $e) {
        error_log("grant_mining_bonus error: " . $e->getMessage());
        return false;
    }
}
