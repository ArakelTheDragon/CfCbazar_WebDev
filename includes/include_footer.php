<?php
/**
 * CfCbazar Layout Helper Library
 * File: /includes/include_footer.php
 *
 * Renders the primary site footer markup, social/ecosystem links, 
 * contact details, global JavaScript scripts, menu height CSS variables, 
 * and closes document body/html tags.
 */

declare(strict_types=1);

if (!function_exists('include_footer')) {
    /**
     * Outputs site footer markup, ecosystem external links, dynamic UI fix scripts,
     * and closes the HTML document.
     *
     * @return void
     */
    function include_footer(): void
    {
        echo '<footer class="footer" style="padding:1em; background:#f8f8f8; text-align:center; font-size:0.95em;">';
        echo '<p>&copy; CfCbazar. All rights reserved.</p>';
        echo '<p><a href="/t.php">Privacy Policy</a> | <a href="/t.php">Terms</a></p>';
        echo '<p style="margin-top:1em;">📢 Follow us for official updates:</p>';
        echo '<ul class="social-links" style="list-style:none; padding:0; margin:0; display:flex; flex-wrap:wrap; justify-content:center; gap:0.5em;">';
        echo '<li><a href="https://x.com/workthrp" target="_blank" rel="noopener">🐦 WorkToken on X</a></li>';
        echo '<li><a href="https://x.com/cfcbazargroup" target="_blank" rel="noopener">🐦 CfCbazar Group on X</a></li>';
        echo '<li><a href="https://www.facebook.com/share/12J6NS1M2cY/" target="_blank" rel="noopener">📘 WorkToken on Facebook</a></li>';
        echo '<li><a href="https://www.facebook.com/share/1CshFfT6bG/" target="_blank" rel="noopener">📘 CfCbazar on Facebook</a></li>';
        echo '<li><a href="https://youtube.com/@worktoken?si=PtWNenpqAYYadD0V" target="_blank" rel="noopener">📺 WorkToken on YouTube</a></li>';
        echo '<li><a href="https://youtube.com/@cfcbazar?si=LkDTc8EPU1vr9MNR" target="_blank" rel="noopener">📺 CfCbazar on YouTube</a></li>';
        echo '<li><a href="https://www.tiktok.com/@worktoken?_t=ZN-90uYlCvmRks&_r=1" target="_blank" rel="noopener">🎵 WorkToken on TikTok</a></li>';
        echo '<li><a href="https://www.tiktok.com/@cfcbazar?_t=ZN-90uYo1jYz4A&_r=1" target="_blank" rel="noopener">🎵 CfCbazar on TikTok</a></li>';
        echo '<li><a href="https://github.com/ArakelTheDragon/CfCbazar-Tokens" target="_blank" rel="noopener">🧠 CfCbazar-Tokens on GitHub</a></li>';
        echo '<li><a href="https://pancakeswap.finance/swap?inputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D&outputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00" target="_blank" rel="noopener">💱 Trade WorkTHR/WTK on PancakeSwap</a></li>';
        echo '</ul>';
        echo '<p style="margin-top:1em;">📬 Contact us: <a href="mailto:cfcbazar@gmail.com">cfcbazar@gmail.com</a></p>';
        echo '</footer>';

        // Load main JS
        echo '<script src="/js/scripts.js" defer></script>';

        // Dynamic menu height fix (GLOBAL)
        echo '<script>
        document.addEventListener("DOMContentLoaded", () => {
            const menu = document.querySelector(".main-nav");
            if (menu) {
                document.documentElement.style.setProperty("--menu-height", menu.offsetHeight + "px");
            }
        });
        </script>';

        echo '</body></html>';
    }
}
