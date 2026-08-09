<?php
declare(strict_types=1);

/**
 * Wrapper/Alias to locate a tracking entry by tracking number.
 */
function findTracking(string $trackingNumber): ?array {
    if (function_exists('getTrackingRecord')) {
        return getTrackingRecord($trackingNumber);
    }
    return null;
}
