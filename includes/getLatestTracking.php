<?php
declare(strict_types=1);

function getLatestTracking(int $limit = 10): array {
    updateTrackingJson();
    return array_slice(getAllTracking($limit), 0, $limit);
}
