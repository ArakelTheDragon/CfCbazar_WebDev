<?php

spl_autoload_register(function ($class) {
    $prefix = 'Phpml\\';
    $baseDir = __DIR__ . '/Phpml/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

