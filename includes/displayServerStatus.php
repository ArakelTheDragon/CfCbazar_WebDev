<?php
/**
 * CfCbazar Server Monitor & UI Helper Library
 * File: /includes/displayServerStatus.php
 *
 * Pings configured network hosts on HTTPS port 443 via socket connection
 * and renders an HTML status list indicating live online/offline network status.
 */

declare(strict_types=1);

if (!function_exists('displayServerStatus')) {
    /**
     * Checks availability of monitored CfCbazar mirror domains and displays an HTML list with status indicators.
     *
     * @return void
     */
    function displayServerStatus(): void
    {
        $servers = [
            'cfcbazar.42web.io',
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
