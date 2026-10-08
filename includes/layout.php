<?php
declare(strict_types=1);
/**
 * Consolidated Layout & UI Renderers
 * include_header, include_menu, include_footer, cfc_footer, popups, bars, etc.
 */

// ===== from include_header.php =====
/**
 * CfCbazar Layout Helper Library
 * File: /includes/include_header.php
 *
 * Generates and outputs the base HTML5 structure, meta tags, Open Graph / Twitter cards,
 * stylesheet references, CDN scripts, and initial body layout header.
 */

if (!function_exists('include_header')) {
    function include_header(
        ?string $title = null,
        ?string $additional = null,
        string $robots = 'index, follow'
    ): void {
        $title = $title ?? 'CfCbazar – A Producer of Smart Deals, DIY eGuides, Open-Source Tools, Web Games & Free Entertainment';

        $defaultDescription = 'CfCbazar offers DIY eGuides for cooking, budgeting, survival planning, electronics, PCB projects, open-source tools, smart deals, free browser games, music & TV entertainment. Discover practical digital tools made to save you time and money.';

        // Use the page-specific description when provided.
        $description = trim((string) $additional);
        if ($description === '') {
            $description = $defaultDescription;
        }

        $csrfToken = $_SESSION['csrf_token'] ?? '';

        // Build the canonical URL from the current request path.
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        // Prevent accidental duplicate slashes.
        $path = '/' . ltrim($path, '/');

        $canonicalUrl = 'https://cfcbazar.8bit.ca' . $path;

        $keywords = implode(', ', [
            'CfCbazar',
            'DIY eGuides',
            'cooking guides',
            'budget planners',
            'survival calculators',
            'open source tools',
            'PCB projects',
            'electronics circuits',
            'smart deals',
            'printable products',
            'free browser games',
            'free TV',
            'entertainment',
            'digital downloads',
            'online tools'
        ]);

        echo '<!doctype html>';
        echo '<html lang="en">';
        echo '<head>';

        echo '<meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';

        // Only output the CSRF meta value when a session already exists.
        // This avoids starting a session just for the public page header.
        if (session_status() === PHP_SESSION_ACTIVE && $csrfToken !== '') {
            echo '<meta name="csrf_token" content="' .
                htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';
        }

        echo '<title>' .
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
            '</title>';

        echo '<meta name="description" content="' .
            htmlspecialchars($description, ENT_QUOTES, 'UTF-8') .
            '">';

        echo '<meta name="robots" content="' .
            htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') .
            '">';

        echo '<meta name="author" content="CfCbazar">';

        // Canonical URL
        echo '<link rel="canonical" href="' .
            htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') .
            '">';

        // Open Graph
        echo '<meta property="og:site_name" content="CfCbazar">';
        echo '<meta property="og:title" content="' .
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
            '">';
        echo '<meta property="og:description" content="' .
            htmlspecialchars($description, ENT_QUOTES, 'UTF-8') .
            '">';
        echo '<meta property="og:type" content="website">';
        echo '<meta property="og:url" content="' .
            htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') .
            '">';
        echo '<meta property="og:image" content="https://cfcbazar.8bit.ca/assets/images/cfcbazar-banner.jpg">';

        // Twitter / X
        echo '<meta name="twitter:card" content="summary_large_image">';
        echo '<meta name="twitter:title" content="' .
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
            '">';
        echo '<meta name="twitter:description" content="' .
            htmlspecialchars($description, ENT_QUOTES, 'UTF-8') .
            '">';
        echo '<meta name="twitter:url" content="' .
            htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') .
            '">';
        echo '<meta name="twitter:image" content="https://cfcbazar.8bit.ca/assets/images/cfcbazar-banner.jpg">';

        // Main stylesheet
        echo '<link rel="stylesheet" href="/assets/css/styles.css">';

        // Libraries currently used by the site
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        echo '<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>';

        echo '</head>';
        echo '<body>';

        echo '<header class="header"></header>';
    }
}

// ===== from include_menu.php =====
/**
 * CfCbazar Layout Helper Library
 * File: /includes/include_menu.php
 *
 * Renders the primary navigation menu bar, handles public and external links,
 * and conditionally appends Login/Logout and Admin links based on user session and status.
 */


if (!function_exists('include_menu')) {
    /**
     * Outputs the primary HTML navigation bar and mobile toggle button.
     *
     * @return void
     */
    function include_menu(): void
    {
        global $conn;

        $email = strtolower(trim($_SESSION['email'] ?? ''));
        $logged_in = !empty($email);
        $is_admin = false;

        // Check if the logged-in user is an administrator
        if ($logged_in) {
            $stmt = $conn->prepare("SELECT status FROM users WHERE email = ? LIMIT 1");

            if (!$stmt) {
                error_log("include_menu(): Prepare failed - " . $conn->error);
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();

                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $is_admin = ((int)$row['status'] === 1);
                }

                $stmt->close();
            }
        }

        // Menu items
        $menu = [
            ['🏠 Home', '/index.php', false],
            ['🔗 Smart Deals', 'https://www.facebook.com/groups/195994786555718/', true],
            ['🔧 DIY Tools', '/diy/index.php', true],
            ['🎮 Games', '/games/index.php', true],
            ['🎵 Music', 'https://youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu', true],
            ['⛏️ Proof Of Work Center', '/pow/', true],
            ['💰 WorkToken', '/worktoken/index.php', true],
            ['🚚 Visit Store', 'https://ebay.us/m/DM1tRs', true],
            ['📖 About WorkToken', 'https://github.com/ArakelTheDragon/CfCbazar-Tokens', true],
            ['❓ Help Center', '/help/', true],
            ['ℹ️ About Us', '/about.php', false],
            ['📜 Terms & Privacy', '/t.php', false],
            ['🍪 Cookies', '/c.php', false],
        ];

        echo '<nav class="main-nav">';
        echo '<button class="menu-toggle" aria-expanded="false" aria-label="Toggle navigation">☰ Menu</button>';
        echo '<ul class="nav-menu">';

        foreach ($menu as [$title, $url, $newTab]) {

            echo '<li><a href="' . htmlspecialchars($url) . '"';

            if ($newTab) {
                echo ' target="_blank" rel="noopener noreferrer"';
            }

            echo '>' . htmlspecialchars($title) . '</a></li>';
        }

        // Login / Logout
        if ($logged_in) {
            echo '<li><a href="/logout.php">🚪 Log Out</a></li>';
        } else {
            echo '<li><a href="/login.php">🔑 Sign In / Register</a></li>';
        }

        // Admin menu
        if ($is_admin) {
            echo '<li><a href="/admin.php">🛠️ Admin</a></li>';
        }

        echo '</ul>';
        echo '</nav>';
    }
}

// ===== from include_footer.php =====
/**
 * CfCbazar Layout Helper Library
 * File: /includes/include_footer.php
 *
 * Renders the primary site footer markup, social/ecosystem links, 
 * contact details, global JavaScript scripts, menu height CSS variables, 
 * and closes document body/html tags.
 */


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
        echo '<script src="/assets/js/scripts.js" defer></script>';

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

// ===== from cfc_footer.php =====
/**
 * CfCbazar Footer & Branding Helper Library
 * File: /includes/cfc_footer.php
 *
 * Renders the GitHub repository footer block.
 */


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

// ===== from showAdvertPopup.php =====
/**
 * CfCbazar UI & Advertisement Helper Library
 * File: /includes/showAdvertPopup.php
 *
 * Renders a delayed UI popup container with HTML and JavaScript encouraging 
 * users to report errors or request features via the contact page.
 */


if (!function_exists('showAdvertPopup')) {
    /**
     * Renders an advertisement/feedback popup widget with a timed delay.
     *
     * @return void
     */
    function showAdvertPopup(): void
    {
        $linkUrl = '/contact.php';
        $linkText = 'Click here to report en error/feature!';
        $delay = 3000;

        echo <<<HTML
<div id="advertPopup" style="display:none; position:fixed; bottom:20px; right:20px; width:300px; background:#fff; border:1px solid #ccc; box-shadow:0 0 10px rgba(0,0,0,0.3); padding:15px; z-index:9999;">
    <span style="float:right; cursor:pointer;" onclick="document.getElementById('advertPopup').style.display='none';">✖</span>
    <strong>🔥 Contact:</strong><br>
    <a href="{$linkUrl}" target="_blank" style="color:#0077cc; text-decoration:underline;">
        {$linkText}
    </a>
</div>
<script>
    setTimeout(function() {
        if (document.getElementById('advertPopup')) document.getElementById('advertPopup').style.display = 'block';
    }, {$delay});
</script>
HTML;
    }
}

// ===== from ui_teaser_renderers.php =====
/**
 * CfCbazar UI Teaser & Trade Card Helper Library
 * File: /includes/ui_teaser_renderers.php
 *
 * Renders reusable link cards and external DEX callout components.
 */


if (!function_exists('render_withdraw_link')) {
    function render_withdraw_link(): void {
        echo '<a href="/w.php" class="link-card" aria-label="Withdraw WorkTokens/WorkTHR">💸 <span>Withdraw WorkTokens/WorkTHR</span></a>';
    }
}

if (!function_exists('render_workthr_teaser')) {
    function render_workthr_teaser(): void {
        echo '<a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=BNB" class="link-card" target="_blank" rel="noopener noreferrer">🥞 <span>Trade WorkTHR on PancakeSwap</span></a>';
    }
}

if (!function_exists('render_worktoken_teaser')) {
    function render_worktoken_teaser(): void {
        echo '<a href="https://cc.free.bg/workth/" class="link-card" aria-label="Trade WorkTokens on our DApp">🧠 <span>Trade WorkTokens on our DApp</span></a>';
    }
}

if (!function_exists('rfTradeWorkTokens')) {
    function rfTradeWorkTokens(): void {
        echo <<<HTML
        <section class="card">
            <h2>Trade WorkTokens</h2>
            <p>Trade WTK and WorkTHR using PancakeSwap.</p>
            <p>
                <a href="https://pancakeswap.finance/swap?inputCurrency=0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00&outputCurrency=0xecbD4E86EE8583c8681E2eE2644FC778848B237D" target="_blank" rel="noopener noreferrer">
                    Open PancakeSwap
                </a>
            </p>
        </section>
        HTML;
    }
}

// ===== from render_ecosystem_content.php =====
/**
 * CfCbazar UI Helper Library
 * File: /includes/render_ecosystem_content.php
 *
 * Renders the ecosystem section displaying token conversion mechanics, 
 * credit flow instructions, token chart canvas, and deposit QR code generator.
 */


if (!function_exists('render_ecosystem_content')) {
    /**
     * Outputs HTML section for the blockchain token ecosystem, credit conversion
     * rules, and deposit instructions with dynamic QR canvas.
     *
     * @return void
     */
    function render_ecosystem_content(): void
    {
        ?>
        <main class="ecosystem-section">
            <h1>💠 CfCbazar Blockchain Token Ecosystem & Credit Flow</h1>

            <div class="chart-container">
                <canvas id="tokenChart"></canvas>
            </div>

            <section class="ecosystem-flow">
                <h2>🔁 How CfCbazar Converts Blockchain Tokens into Platform Credits</h2>
                <ul>
                    <li><strong>Blockchain Reserve:</strong> Backs all platform credits</li>
                    <li><strong>Platform Credits:</strong> Used for games, features, and withdrawals</li>
                    <li><strong>To Get Credits:</strong> Send WorkTokens or BNB to <code class="token-address">0xFBd767f6454bCd07c959da2E48fD429531A1323A</code></li>
                    <li><strong>On Withdraw:</strong> You receive WorkTokens from <code class="token-address">0xFBd767f6454bCd07c959da2E48fD429531A1323A</code></li>
                </ul>
                <p>Learn more about <a href="/worktoken/index.php">WorkToken mechanics</a> or explore <a href="/games/index.php">CfCbazar games</a>.</p>
            </section>

            <div class="deposit-instructions">
                <canvas id="qr-canvas" data-qr-value="0xFBd767f6454bCd07c959da2E48fD429531A1323A"></canvas>
                <button onclick="downloadQR()">Download QR Code</button>
            </div>
        </main>
        <?php
    }
}

// ===== from render_device_table.php =====
/**
 * CfCbazar Mining & UI Helper Library
 * File: /includes/render_device_table.php
 *
 * Renders an HTML table listing active or inactive mining hardware devices along with 
 * last activity time, operational status, and dynamic deletion forms.
 */


if (!function_exists('render_device_table')) {
    /**
     * Outputs an HTML table of mining devices filtered by activity status.
     *
     * @param array $devices Array of device data rows
     * @param string $type Device type classification ('active' or 'inactive')
     * @return void
     */
    function render_device_table(array $devices, string $type): void
    {
        if (empty($devices)) {
            return;
        }

        $icon = ($type === 'active') ? '🟢' : '🔴';
        $class = ($type === 'active') ? 'active' : 'inactive';

        echo "<h4>$icon " . ucfirst($type) . " Devices</h4>";
        echo "<table class='device-table $class' role='grid' aria-label='$type Devices'>";
        echo "<thead><tr><th>MAC Address</th><th>Last Mine Time</th><th>Status</th><th>Action</th></tr></thead><tbody>";

        foreach ($devices as $device) {
            $mac = htmlspecialchars($device['mac_address']);
            $last_mine = htmlspecialchars($device['last_mine_time'] ?? 'Never');
            $status = $device['active'] ? '1' : '0';

            echo "
            <tr>
                <td>$mac</td>
                <td>$last_mine</td>
                <td>$status</td>
                <td>
                    <form method='POST' style='display:inline;'>
                        <input type='hidden' name='csrf_token' value='" . htmlspecialchars($_SESSION['csrf_token'] ?? '') . "'>
                        <input type='hidden' name='delete_mac' value='$mac'>
                        <button type='submit' class='delete-btn' onclick=\"return confirm('Delete device $mac?');\">🗑️ Delete</button>
                    </form>
                </td>
            </tr>";
        }

        echo "</tbody></table>";
    }
}

// ===== from renderCaptchaIfNeeded.php =====
/**
 * CfCbazar Auth & Security Helper Library
 * File: /includes/renderCaptchaIfNeeded.php
 *
 * Checks if the user has solved the session/cookie CAPTCHA.
 * Handles CAPTCHA verification, cookie persistence, and renders 
 * the CAPTCHA form UI when verification is required.
 */


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

// ===== from render_token_price_tracker.php =====
/**
 * CfCbazar Crypto & Price Tracker Helper Library
 * File: /includes/render_token_price_tracker.php
 *
 * Renders a live PancakeSwap V2 price tracker widget using ethers.js to fetch
 * real-time exchange rates for WorkTHR/USDT and WTK/WorkTHR pairs directly from BSC.
 */


if (!function_exists('render_token_price_tracker')) {
    /**
     * Renders the token price tracker container CSS, HTML, and client-side JS module.
     *
     * @return void
     */
    function render_token_price_tracker(): void
    {
        echo <<<'HTML'
  <style>
    .token-tracker-container {
      font-family: Arial, sans-serif;
      background: #f9f9f9;
      padding: 20px;
      text-align: center;
      max-width: 600px;
      margin: 0 auto;
    }
    .token-tracker-container h2 {
      color: #333;
      font-size: 1.6em;
      margin-bottom: 20px;
    }
    .price-box {
      margin: 10px auto;
      padding: 16px;
      font-size: 1.2em;
      font-weight: bold;
      color: #28a745;
      background: #fff;
      border: 2px solid #28a745;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      word-wrap: break-word;
      overflow-wrap: break-word;
      max-width: 90%;
      transition: box-shadow 0.3s ease;
    }
    .price-box:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .price-box.error {
      color: #cc0000;
      border-color: #cc0000;
    }
    @media screen and (max-width: 480px) {
      .token-tracker-container h2 {
        font-size: 1.3em;
      }
      .price-box {
        font-size: 1em;
        padding: 12px;
      }
    }
  </style>

  <div class="token-tracker-container">
    <h2>📈 Live Token Prices</h2>
    <div id="workthr-price" class="price-box">Loading WorkTHR → USDT...</div>
    <div id="wtk-price" class="price-box">Loading WTK → WorkTHR...</div>
  </div>

  <script type="module">
    async function trackTokenPrice(path, labelId, symbol, targetSymbol) {
      try {
        const { ethers } = await import('https://cdn.jsdelivr.net/npm/ethers@6.8.0/+esm');
        const provider = new ethers.JsonRpcProvider('https://bsc-dataseed.binance.org/');
        const router = new ethers.Contract(
          '0x10ED43C718714eb63d5aA57B78B54704E256024E',
          ['function getAmountsOut(uint amountIn, address[] calldata path) external view returns (uint[] memory amounts)'],
          provider
        );
        const inputAmount = ethers.parseUnits('1', 18);
        const amounts = await router.getAmountsOut(inputAmount, path);
        const price = ethers.formatUnits(amounts[amounts.length - 1], 18);
        const el = document.getElementById(labelId);
        el.textContent = `1 ${symbol} ≈ ${price} ${targetSymbol}`;
        el.classList.remove('error');
      } catch (err) {
        console.error(`${symbol} price fetch error:`, err);
        const el = document.getElementById(labelId);
        el.textContent = `Error fetching ${symbol} price`;
        el.classList.add('error');
      }
    }

    function refreshPrices() {
      trackTokenPrice(
        ['0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00', '0x55d398326f99059fF775485246999027B3197955'],
        'workthr-price',
        'WorkTHR',
        'USDT'
      );
      trackTokenPrice(
        ['0xecbD4E86EE8583c8681E2eE2644FC778848B237D', '0xffc4f8Bde970D87f324AefB584961DDB0fbb4F00'],
        'wtk-price',
        'WTK',
        'WorkTHR'
      );
    }

    refreshPrices();
    setInterval(refreshPrices, 86400000);
  </script>
HTML;
    }
}

// ===== from displayServerStatus.php =====
/**
 * CfCbazar Server Monitor & UI Helper Library
 * File: /includes/displayServerStatus.php
 *
 * Pings configured network hosts on HTTPS port 443 via socket connection
 * and renders an HTML status list indicating live online/offline network status.
 */


if (!function_exists('displayServerStatus')) {
    /**
     * Checks availability of monitored CfCbazar mirror domains and displays an HTML list with status indicators.
     *
     * @return void
     */
    function displayServerStatus(): void
    {
        $servers = [
            'cfcbazar.8bit.ca',
            'cfcbazar.22web.org',
            'cfcbazar.ct.ws',
            'cfcbazar.iceiy.com'
        ];

        echo '<ul style="list-style:none;padding:0;">';

        foreach ($servers as $server) {
            $url = "https://" . htmlspecialchars($server, ENT_QUOTES, 'UTF-8');
            
            // Check socket connection with a 2-second timeout
            $socket = @fsockopen($server, 443, $errno, $errstr, 2);
            $isOnline = $socket !== false;

            if ($socket) {
                fclose($socket);
            }

            $statusText = $isOnline ? 'Online' : 'Offline';
            $color = $isOnline ? 'green' : 'red';

            echo "<li style='margin:8px 0;'>
                    🔗 <a href='{$url}' target='_blank'>" . htmlspecialchars($server, ENT_QUOTES, 'UTF-8') . "</a> 
                    <span style='color:{$color};font-weight:bold;'>{$statusText}</span>
                  </li>";
        }

        echo '</ul>';
    }
}

// Stub for render_top_userbar (original file was missing from archive)
if (!function_exists('render_top_userbar')) {
    function render_top_userbar(): void {
        // minimal placeholder – replace with real implementation when available
        return;
    }
}
if (!function_exists('render_worktoken_dashboard')) {
    function render_worktoken_dashboard(): void { return; }
}
if (!function_exists('show_disabled_message')) {
    /**
     * Render a full styled disabled-page notice and stop execution.
     *
     * @param string $reason
     * @return void
     */
    function show_disabled_message(string $reason = 'maintenance'): void
    {
        if (function_exists('include_header')) {
            include_header('Page Disabled – CfCbazar');
        }
        if (function_exists('include_menu')) {
            include_menu();
        }
        if (function_exists('render_top_userbar')) {
            render_top_userbar();
        }

        $escapedReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        ?>
        <main class="container">
            <div class="disabled-message card" style="
                padding: 2.5rem;
                background: #fff3f3;
                border: 1px solid #f5c2c2;
                border-radius: 12px;
                margin: 3rem auto;
                max-width: 620px;
                text-align: center;
                box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            ">
                <h2 style="margin-top:0; color:#c0392b;">🚫 This Page Is Disabled</h2>
                <p>We’ve temporarily disabled this page due to <strong><?= $escapedReason ?></strong>.</p>
                <p>For the latest updates, please visit our 
                    <a href="/system/news.php" target="_blank" rel="noopener">News Center</a>.
                </p>
            </div>
        </main>
        <?php

        if (function_exists('include_footer')) {
            include_footer();
        }

        exit;
    }
}

// ===== from frVersion.php =====
if (!function_exists('frVersion')) {
    /**
     * Outputs invisible text on the page for version tracking / debugging.
     *
     * @param string $version    The version string (e.g., "1.2.3")
     * @param string $additional Optional additional text to hide
     * @param bool   $asComment  If true, wraps in HTML comment. If false, uses hidden span.
     * @return void
     */
    function frVersion(string $version = '', string $additional = '', bool $asComment = false): void
    {
        $version    = htmlspecialchars($version, ENT_QUOTES, 'UTF-8');
        $additional = htmlspecialchars($additional, ENT_QUOTES, 'UTF-8');
        $additional = str_replace('--', '—', $additional);

        $text = trim($version . ' ' . $additional);

        if ($text === '') {
            return;
        }

        if ($asComment) {
            echo "\n<!-- frVersion: {$text} -->\n";
        } else {
            echo '<span style="display:none !important; visibility:hidden; position:absolute; '
               . 'left:-9999px; width:0; height:0; overflow:hidden;" '
               . 'aria-hidden="true" data-frversion="' . $version . '">'
               . $text
               . '</span>' . "\n";
        }
    }
}
