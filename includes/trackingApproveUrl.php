<?php
declare(strict_types=1);

function trackingApproveUrl(int $id): string {
    updateTrackingJson();
    return '?approve=' . $id;
}
