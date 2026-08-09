<?php
declare(strict_types=1);

function trackingLookupUrl(string $tracking): string {
    updateTrackingJson();
    return '?track=' . urlencode($tracking);
}
