<?php
declare(strict_types=1);

function isPending(array $tracking): bool {
    updateTrackingJson();
    return ($tracking['status'] ?? '') === 'pending';
}
