<?php
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/checkLevelUp.php
 *
 * Evaluates a worker's accumulated EXP against level progression thresholds,
 * continuously leveling up and deducting EXP until the remainder is below the threshold.
 */

declare(strict_types=1);

if (!function_exists('checkLevelUp')) {
    /**
     * Checks if a worker has enough experience points to level up, updating
     * their level and deducting spent experience in a loop until EXP is insufficient.
     *
     * @param string $email User's account email address
     * @return void
     */
    function checkLevelUp(string $email): void
    {
        global $conn;

        if (!$conn) {
            error_log("checkLevelUp: Database connection not available");
            return;
        }

        while (true) {
            $stmt = $conn->prepare("SELECT COALESCE(exp,0) AS exp, COALESCE(level,1) AS level FROM workers WHERE email = ? LIMIT 1");

            if (!$stmt) {
                error_log("checkLevelUp: Prepare failed: " . $conn->error);
                return;
            }

            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$row) {
                return;
            }

            $exp = (int)$row['exp'];
            $level = (int)$row['level'];
            $needed = $level * 100;

            if ($exp >= $needed) {
                $stmt2 = $conn->prepare("UPDATE workers SET level = level + 1, exp = exp - ? WHERE email = ?");

                if (!$stmt2) {
                    error_log("checkLevelUp: Prepare failed for update: " . $conn->error);
                    return;
                }

                $stmt2->bind_param('is', $needed, $email);
                $stmt2->execute();
                $stmt2->close();

                continue;
            }

            break;
        }
    }
}
