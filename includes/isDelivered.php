<?php
declare(strict_types=1);

function isDelivered(array $tracking): bool {
    updateTrackingJson();
    return ($tracking['status'] ?? '') === 'delivered';
}
