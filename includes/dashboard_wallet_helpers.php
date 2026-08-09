<?php
/**
 * CfCbazar Dashboard & User Account Engine Library
 * File: /includes/dashboard_wallet_helpers.php
 *
 * Handles account loading, wallet saving, VIP upgrades with atomic transactions, 
 * and throttled bonus distributions.
 */

declare(strict_types=1);

if (!function_exists('load_dashboard_data')) {
    /**
     * Loads worker wallet address and token balances for an account email.
     */
    function load_dashboard_data(string $email, string &$current_address, float &$mintme_balance): bool
    {
        global $conn;

        $current_address = "";
        $mintme_balance = 0.0;

        if (!$conn) return false;

        $stmt = $conn->prepare("SELECT address, mintme FROM workers WHERE email = ? LIMIT 1");
        if (!$stmt) return false;

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($addr, $bal);

        if ($stmt->fetch()) {
            $current_address = $addr ?? "";
            $mintme_balance = (float)($bal ?? 0.0);
            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }
}

if (!function_exists('save_wallet')) {
    /**
     * Validates and saves an EVM wallet address to the database.
     */
    function save_wallet(string $email, string &$current_address): string
    {
        global $conn;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['wallet_address'])) {
            return "";
        }

        if (function_exists('csrf_token') && !hash_equals(csrf_token(), $_POST['csrf_token'] ?? '')) {
            return "Security error.";
        }

        $wallet_address = trim($_POST['wallet_address']);

        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
            return "Invalid wallet address.";
        }

        $stmt = $conn->prepare("UPDATE workers SET address = ? WHERE email = ?");
        if (!$stmt) return "Database error.";

        $stmt->bind_param("ss", $wallet_address, $email);
        if (!$stmt->execute()) {
            $stmt->close();
            return "Database error.";
        }

        $stmt->close();
        $current_address = $wallet_address;
        return "Wallet saved!";
    }
}

if (!function_exists('buy_vip')) {
    /**
     * Atomically processes a 10 WorkTHR VIP status purchase using row locks.
     */
    function buy_vip(string $email, float &$mintme_balance, int &$status): string
    {
        global $conn;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['vip_buy'])) {
            return "";
        }

        if (function_exists('csrf_token') && !hash_equals(csrf_token(), $_POST['csrf_token'] ?? '')) {
            return "Security error.";
        }

        $conn->begin_transaction();

        try {
            // Lock user row
            $stmt = $conn->prepare("SELECT status FROM users WHERE email = ? FOR UPDATE");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($db_status);
            $stmt->fetch();
            $stmt->close();

            if ($db_status !== 5) {
                throw new Exception("Already VIP or not eligible.");
            }

            // Lock worker balance row
            $stmt = $conn->prepare("SELECT mintme FROM workers WHERE email = ? FOR UPDATE");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($balance);
            $stmt->fetch();
            $stmt->close();

            if ($balance === null) {
                throw new Exception("Worker account not found.");
            }

            if ($balance < 10) {
                throw new Exception("Not enough WorkTHR.");
            }

            // Deduct WorkTHR
            $stmt = $conn->prepare("UPDATE workers SET mintme = mintme - 10 WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();

            // Upgrade user status to VIP (4)
            $stmt = $conn->prepare("UPDATE users SET status = 4 WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $mintme_balance = (float)($balance - 10);
            $status = 4;

            return "VIP upgrade successful!";

        } catch (Exception $e) {
            $conn->rollback();
            return $e->getMessage();
        }
    }
}

if (!function_exists('grant_dashboard_bonus')) {
    /**
     * Throttles mining bonus execution to once every 60 seconds per user session.
     */
    function grant_dashboard_bonus(string $email): void
    {
        if (function_exists('session_check')) {
            session_check();
        }

        if (!isset($_SESSION['last_bonus_run']) || (time() - $_SESSION['last_bonus_run']) > 60) {
            if (function_exists('grant_mining_bonus')) {
                grant_mining_bonus($email);
            }
            $_SESSION['last_bonus_run'] = time();
        }
    }
}
