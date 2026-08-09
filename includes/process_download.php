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
     * --------------------------------------------------------------------------
     * Download Handler
     * --------------------------------------------------------------------------
     *
     * Handles both:
     *
     * GET  ?download=xxxxx
     * -> Display email form
     *
     * POST ?download=xxxxx
     * -> Validate CSRF
     * -> Save downloader email
     * -> Redirect to download URL
     *
     * This function exits automatically when processing completes.
     * --------------------------------------------------------------------------
     *
     * @return void
     */
    function process_download(): void
    {
        if (!isset($_GET['download'])) {
            return;
        }

        $track = trim($_GET['download']);

        /**
         * --------------------------------------------------------------
         * First request (GET)
         * --------------------------------------------------------------
         */
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

            include_header("Download Digital Product");
            include_menu();
            render_top_userbar();

            ?>
            <main class="container">

                <section class="card">

                    <h2>Digital Download</h2>

                    <p>
                        Enter your email address to continue.
                    </p>

                    <form method="post">

                        <label>Email Address</label>

                        <input
                            type="email"
                            name="email_downloader"
                            required
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                        >

                        <button type="submit">
                            Continue
                        </button>

                    </form>

                </section>

            </main>
            <?php

            include_footer();

            close_database();

            exit;
        }

        /**
         * --------------------------------------------------------------
         * POST
         * --------------------------------------------------------------
         */

        validateTrackingCSRF();

        $emailDownloader = trim(
            $_POST['email_downloader'] ?? ''
        );

        if ($emailDownloader === '') {
            exit('Email address is required.');
        }

        $record = getDownloadRecord($track);

        if (!$record) {
            exit('Tracking number not found or awaiting approval.');
        }

        markDownloadDelivered(
            (int)$record['id'],
            $emailDownloader
        );

        header(
            'Location: ' . $record['download_link']
        );

        exit;
    }
}
