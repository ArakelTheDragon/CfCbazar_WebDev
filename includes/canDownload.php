<?php
declare(strict_types=1);

function canDownload(array $tracking): bool {
    updateTrackingJson();
    return isset($tracking['status']) && $tracking['status'] !== 'pending';
}
