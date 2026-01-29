<?php
// index.php — lists all files and folders from the current directory down

header('Content-Type: text/plain');

function listFiles($dir, $prefix = '') {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        echo $prefix . $item . (is_dir($path) ? "/\n" : "\n");
        if (is_dir($path)) {
            listFiles($path, $prefix . '  ');
        }
    }
}

// Start from the current directory
listFiles(__DIR__);
