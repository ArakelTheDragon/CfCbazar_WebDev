<?php
declare(strict_types=1);

function isInTransit(array $tracking): bool {
    updateTrackingJson();
    return ($tracking['status'] ?? '') === 'in_transit';
}
