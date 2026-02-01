<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Call token.php (which is in parent directory)
$response = include(dirname(__DIR__) . '/token.php');

if ($response === 0) {
    echo "Token refreshed.";
} elseif ($response === 1) {
    echo "Token is still valid.";
} else {
    echo "Error: token.php did not return a valid response.";
}