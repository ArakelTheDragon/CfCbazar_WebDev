<?php
declare(strict_types=1);

/**
 * Escapes HTML output to prevent XSS attacks.
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
