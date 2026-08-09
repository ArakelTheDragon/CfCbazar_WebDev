<?php
/**
 * CfCbazar Worker & Mining Helper Library
 * File: /includes/addTokens.php
 *
 * Increments the total tokens earned by a specific worker in the database.
 */

declare(strict_types=1);

if (!function_exists('addTokens')) {
    /**
     * Increments the tokens_earned field for a given user email.
     *
     * @param string $email User's account email address
     * @param float $amount Amount of tokens to add
     * @return void
     */
    function addTokens(string $email, float $amount): void
    {
        global $conn;

        if (!$conn) {
            error_log("addTokens: Database connection not available");
            return;
        }

        $stmt = $conn->prepare("UPDATE workers SET tokens_earned = COALESCE(tokens_earned,0) + ? WHERE email = ?");

        if (!$stmt) {
            error_log("addTokens: Prepare failed: " . $conn->error);
            return;
        }

        $stmt->bind_param('ds', $amount, $email);
        $stmt->execute();
        $stmt->close();
    }
}
