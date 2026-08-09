<?php
/**
 * CfCbazar Mining & UI Helper Library
 * File: /includes/render_device_table.php
 *
 * Renders an HTML table listing active or inactive mining hardware devices along with 
 * last activity time, operational status, and dynamic deletion forms.
 */

declare(strict_types=1);

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
