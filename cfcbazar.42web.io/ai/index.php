<?php

ini_set('display_errors', 0);
error_reporting(0);

function scanFiles($dir, $pattern) {

    // Folders to skip (prevents 500 errors)
    $exclude = [
        'vendor', 'tmp', 'logs', 'mail', 'cgi-bin',
        'sessions', 'node_modules', 'ai', '.git'
    ];

    $directory = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);

    // Filter out excluded folders
    $filter = new RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) use ($exclude) {
        if ($iterator->hasChildren()) {
            return !in_array($current->getFilename(), $exclude);
        }
        return true;
    });

    $rii = new RecursiveIteratorIterator($filter);
    $results = [];

    foreach ($rii as $file) {
        if ($file->isDir()) continue;

        $path = $file->getPathname();

        // Only scan code files
        if (!preg_match('/\.(php|js|html|css)$/i', $path)) continue;

        // Safely read file
        $lines = @file($path);
        if (!$lines) continue;

        foreach ($lines as $num => $line) {
            if (stripos($line, $pattern) !== false) {
                $results[] = [
                    "file" => $path,
                    "line" => $num + 1,
                    "snippet" => trim($line)
                ];
            }
        }
    }

    return $results;
}

$query = $_GET['q'] ?? '';

if ($query) {

    // Scan the entire website root
    $root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

    $matches = scanFiles($root, $query);

    header('Content-Type: application/json');
    echo json_encode($matches, JSON_PRETTY_PRINT);

} else {
    echo "Usage: index.php?q=functionName";
}

