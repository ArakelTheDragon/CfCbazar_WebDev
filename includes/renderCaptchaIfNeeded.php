<?php
/**
 * CfCbazar Auth & Security Helper Library
 * File: /includes/renderCaptchaIfNeeded.php
 *
 * Checks if the user has solved the session/cookie CAPTCHA.
 * Handles CAPTCHA verification, cookie persistence, and renders 
 * the CAPTCHA form UI when verification is required.
 */

declare(strict_types=1);

if (!function_exists('renderCaptchaIfNeeded')) {
    /**
     * Renders a CAPTCHA challenge if not already solved by the user.
     *
     * @return void
     */
    function renderCaptchaIfNeeded(): void
    {
        if (!isset($_COOKIE['captcha_solved'])) {
            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['captcha_input'])) {
                if (isset($_SESSION['captcha_code']) && $_POST['captcha_input'] === $_SESSION['captcha_code']) {
                    setcookie("captcha_solved", "true", time() + 86400, "/");
                    echo '<div class="message success" style="background-color:#e6f9e6; border:1px solid #4CAF50; color:#2e7d32; padding:1rem; border-radius:6px; margin:1rem auto; max-width:400px; text-align:center;">';
                    echo '<p>✅ CAPTCHA solved. Redirecting…</p></div>';
                    echo "<script>setTimeout(() => window.location.href = window.location.pathname, 1000);</script>";
                    return;
                } else {
                    echo '<div class="message error" style="background-color:#ffe6e6; border:1px solid #f44336; color:#b71c1c; padding:1rem; border-radius:6px; margin:1rem auto; max-width:400px; text-align:center;">';
                    echo '<p>❌ Incorrect CAPTCHA. Please try again.</p></div>';
                }
            }

            // Generate CAPTCHA code
            $captcha_code = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);
            $_SESSION['captcha_code'] = $captcha_code;

            // Styled CAPTCHA form
            echo '<div class="container home-container">';
            echo '<div class="card" style="max-width: 420px; margin: 2rem auto; padding: 1.5rem;">';
            echo '<h2 class="page-title" style="color:#2e7d32;">🔐 CAPTCHA Verification capital letters only</h2>';
            echo '<form method="post" class="form-group" style="display:flex; flex-direction:column; gap:1rem;">';
            echo '<p class="captcha-code" style="font-size:2rem; font-weight:bold; letter-spacing:6px; background:#e8f5e9; color:#1b5e20; padding:0.5rem 1rem; border-radius:4px; text-align:center;">' . htmlspecialchars($captcha_code, ENT_QUOTES) . '</p>';
            echo '<input type="text" name="captcha_input" placeholder="Enter the code above" required style="padding:0.5rem; border:1px solid #ccc; border-radius:4px;">';
            echo '<button type="submit" class="btn btn-success" style="background-color:#4CAF50; color:white; padding:0.6rem 1.2rem; border:none; border-radius:4px; cursor:pointer;">✅ Verify</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';

            if (function_exists('include_footer')) {
                include_footer();
            }

            exit();
        }
    }
}
