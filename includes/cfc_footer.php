<?php
/**
 * CfCbazar Footer & Branding Helper Library
 * File: /includes/cfc_footer.php
 *
 * Renders the GitHub repository footer block.
 */

declare(strict_types=1);

if (!function_exists('cfc_footer')) {
    /**
     * Renders a styled GitHub source code callout box.
     *
     * @param string $githubUrl GitHub repository or file URL
     * @param string $toolName Label for the button link
     * @return void
     */
    function cfc_footer(string $githubUrl, string $toolName = "Source Code"): void
    {
        $escapedUrl = htmlspecialchars($githubUrl, ENT_QUOTES, 'UTF-8');
        $escapedName = htmlspecialchars($toolName, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
        <footer class="footer">
            <div class="card" style="padding:25px; margin-top:40px; text-align:center;">
                <h3 style="font-size:1.8rem; margin-bottom:15px;">GitHub</h3>
                <p style="font-size:1.2rem; margin-bottom:20px;">
                    View the full source code for this page on GitHub.
                </p>
                <a href="{$escapedUrl}" target="_blank" rel="noopener noreferrer" style="display:inline-block; padding:15px 25px; font-size:1.3rem; font-weight:bold; background:linear-gradient(135deg,#28a745,#1e7e34); color:#fff; border-radius:10px; text-decoration:none;">
                    {$escapedName} &rarr;
                </a>
            </div>
        </footer>
        HTML;
    }
}
