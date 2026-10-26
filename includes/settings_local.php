<?php
/**
 * settings_local.php
 * Page-specific flags. Override the global defaults here.
 * bootstrap() reads these and starts session / loads auth only when needed.
 */

declare(strict_types=1);

// Public page defaults (no session, no login)
$NEED_SESSION = true;
$NEED_LOGIN   = false;
$NEED_CART    = false;
$NEED_ADMIN   = false;

// Example for a protected page:
// $NEED_SESSION = true;
// $NEED_LOGIN   = true;
