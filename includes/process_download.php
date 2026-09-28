<?php
/**
 * CfCbazar Tracking & Download Helper Library
 * File: /includes/process_download.php
 *
 * Handles GET and POST processing for digital product download requests.
 */

declare(strict_types=1);

if (!function_exists('process_download')) {
    /**
     * GET  ?download=xxxxx  → show email form
     * POST ?download=xxxxx  → validate CSRF, save email, redirect to file
     */
    function process_download(): void
    {
        if (!isset($_GET['download'])) {
            return;
        }

        $track = trim((string) $_GET['download']);

        // ------------------------------------------------------------------
        // GET – show form
        // ------------------------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

            include_header('Download Digital Product');
            include_menu();
            if (function_exists('render_top_userbar')) {
                render_top_userbar();
            }

            // Ensure CSRF token exists before rendering the form
            $token = function_exists('csrf_token')
                ? csrf_token()
                : ($_SESSION['csrf_token'] ?? '');

            ?>
            <main class="container">
                <section class="card">
                    <h2>Digital Download</h2>
                    <p>Enter your email address to continue.</p>

                    <form method="post" action="">
                        <label for="email_downloader">Email Address</label>
                        <input
                            type="email"
                            id="email_downloader"
                            name="email_downloader"
                            required
                            autocomplete="email"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
                        >

                        <button type="submit">Continue</button>
                    </form>
                </section>
            </main>
            <?php

            include_footer();
            close_database();
            exit;
        }

        // ------------------------------------------------------------------
        // POST – validate & deliver
        // ------------------------------------------------------------------
        validateTrackingCSRF();

        $emailDownloader = trim((string) ($_POST['email_downloader'] ?? ''));

        if ($emailDownloader === '' || !filter_var($emailDownloader, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            exit('A valid email address is required.');
        }

        $record = getDownloadRecord($track);

        if (!$record) {
            http_response_code(404);
            exit('Tracking number not found or awaiting approval.');
        }

        markDownloadDelivered((int) $record['id'], $emailDownloader);

        header('Location: ' . $record['download_link']);
        exit;
    }
}
