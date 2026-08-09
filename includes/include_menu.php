<?php
/**
 * CfCbazar Layout Helper Library
 * File: /includes/include_menu.php
 *
 * Renders the primary navigation menu bar, handles public and external links,
 * and conditionally appends Login/Logout and Admin links based on user session and status.
 */

declare(strict_types=1);

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
