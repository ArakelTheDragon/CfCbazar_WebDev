<?php
/**
 * CfCbazar UI Navigation Helper Library
 * File: /includes/render_top_userbar.php
 *
 * Renders the sticky top status bar displaying email, user role,
 * and live WTK / WorkTHR balances.
 */

declare(strict_types=1);

if (!function_exists('render_top_userbar')) {
    /**
     * Render the fixed top user status bar.
     *
     * @return void
     */
    function render_top_userbar(): void
    {
        global $conn;

        $email = 'none';
        $wtk = 0.0;
        $thr = 0.0;
        $statusLabel = 'Guest';

        if (isset($_SESSION['user_id']) && $conn instanceof mysqli) {
            $uid = (int)$_SESSION['user_id'];

            $stmt1 = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            if ($stmt1) {
                $stmt1->bind_param("i", $uid);
                $stmt1->execute();
                $res1 = $stmt1->get_result();

                if ($res1 && $res1->num_rows > 0) {
                    $user = $res1->fetch_assoc();
                    $email = $user['email'] ?? 'none';

                    // Fetch aggregated worker token totals
                    $stmt2 = $conn->prepare("
                        SELECT 
                            COALESCE(SUM(tokens_earned), 0) AS wtk, 
                            COALESCE(SUM(mintme), 0) AS thr 
                        FROM workers 
                        WHERE email = ?
                    ");

                    if ($stmt2) {
                        $stmt2->bind_param("s", $email);
                        $stmt2->execute();
                        $res2 = $stmt2->get_result();

                        if ($res2 && $res2->num_rows > 0) {
                            $data = $res2->fetch_assoc();
                            $wtk = (float)($data['wtk'] ?? 0);
                            $thr = (float)($data['thr'] ?? 0);
                        }
                        $stmt2->close();
                    }

                    if (function_exists('getUserStatus')) {
                        $status = getUserStatus($email);
                        $statusLabel = match ($status) {
                            1 => 'Admin',
                            2 => 'Moderator',
                            3 => 'Contributor',
                            4 => 'VIP',
                            5 => 'User',
                            default => 'Guest',
                        };
                    }
                }
                $stmt1->close();
            }
        }

        $escapedEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $formattedWtk = number_format($wtk, 2);
        $formattedThr = number_format($thr, 2);

        echo <<<HTML
        <div style="position:fixed; top:75px; left:0; right:0; background:#28a745; color:#fff; padding:8px 12px; z-index:100; border-bottom:1px solid rgba(0,0,0,.15); box-sizing:border-box; font-size:14px;">
            <div style="display:flex; flex-direction:column; gap:6px;">
                <div style="overflow-wrap:anywhere;">
                    <div>
                        <strong>Email:</strong> {$escapedEmail} | <strong>User:</strong> {$statusLabel}
                    </div>
                    <div>
                        <strong>WTK:</strong> {$formattedWtk} | <strong>WorkTHR:</strong> {$formattedThr}
                    </div>
                </div>
                <div>
                    <button onclick="window.location.href='/miner/'" style="padding:8px 14px; cursor:pointer;">Miner</button>
                </div>
            </div>
        </div>
        HTML;
    }
}
