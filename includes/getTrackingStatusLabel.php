<?php
/**
 * CfCbazar Tracking Helper Library
 * File: /includes/getTrackingStatusLabel.php
 *
 * Returns formatted status badges/labels based on tracking status keys.
 */

declare(strict_types=1);

if (!function_exists('getTrackingStatusLabel')) {
    /**
     * Converts raw tracking status into styled HTML/text labels.
     *
     * @param string $status Current tracking status ('pending', 'in_transit', 'delivered', etc.)
     * @return string Formatted status label
     */
    function getTrackingStatusLabel(string $status): string
    {
        $statusKey = strtolower(trim($status));

        return match ($statusKey) {
            'pending' => '<span style="color:#ffc107;font-weight:bold;">⏳ Pending Approval</span>',
            'in_transit', 'approved' => '<span style="color:#17a2b8;font-weight:bold;">🚚 In Transit</span>',
            'delivered', 'completed' => '<span style="color:#28a745;font-weight:bold;">✅ Delivered</span>',
            'cancelled', 'rejected' => '<span style="color:#dc3545;font-weight:bold;">❌ Cancelled</span>',
            default => '<span style="color:#6c757d;font-weight:bold;">' . htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') . '</span>',
        };
    }
}
