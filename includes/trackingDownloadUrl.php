<?php
declare(strict_types=1);

function trackingDownloadUrl(string $tracking): string {
    updateTrackingJson();
    return '?download=' . urlencode($tracking);
}
