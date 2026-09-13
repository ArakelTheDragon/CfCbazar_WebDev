<?php
/**
 * /includes/scan_files.php
 * File System Scanner Module
 * Modular functions for directory traversal, content analysis, and JSON tree mapping.
 * Guarded with function_exists checks to prevent redeclaration conflicts.
 */

if (!function_exists('analyzeFileContent')) {
    /**
     * Basic heuristic analyzer to estimate file purpose based on content structure.
     *
     * @param string $filePath
     * @param string $extension
     * @return string
     */
    function analyzeFileContent($filePath, $extension) {
        if (!in_array($extension, ['php', 'html', 'htm', 'js'])) {
            return "Static " . strtoupper($extension) . " asset or configuration file.";
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return "Unable to read file content.";
        }

        $purpose = [];

        // PHP indicators
        if ($extension === 'php') {
            if (preg_match('/class\s+(\w+)/i', $content, $matches)) {
                $purpose[] = "Defines class '" . $matches[1] . "'";
            }
            if (preg_match('/function\s+(\w+)/i', $content, $matches)) {
                $purpose[] = "Contains custom functions (e.g., " . $matches[1] . ")";
            }
            if (preg_match('/(SELECT|INSERT|UPDATE|DELETE|PDO|mysqli)/i', $content)) {
                $purpose[] = "Handles database operations";
            }
            if (preg_match('/(curl_exec|file_get_contents|fetch|api)/i', $content)) {
                $purpose[] = "Performs API/external HTTP requests";
            }
            if (preg_match('/(session_start|\$_SESSION)/i', $content)) {
                $purpose[] = "Manages user sessions/auth";
            }
            if (preg_match('/(\$_POST|\$_GET|\$_REQUEST)/i', $content)) {
                $purpose[] = "Processes form inputs or request parameters";
            }
            if (preg_match('/(header\(|json_encode)/i', $content)) {
                $purpose[] = "Outputs JSON or API response headers";
            }
        }

        // HTML / JS indicators
        if (in_array($extension, ['html', 'htm', 'js'])) {
            if (preg_match('/<form/i', $content)) {
                $purpose[] = "Contains HTML forms";
            }
            if (preg_match('/(fetch\(|\$.ajax|XMLHttpRequest)/i', $content)) {
                $purpose[] = "Executes AJAX requests";
            }
        }

        if (empty($purpose)) {
            return "General script or template file.";
        }

        return implode('; ', $purpose) . '.';
    }
}

if (!function_exists('scanDirectoryMap')) {
    /**
     * Recursively scans directory and builds a flat array or structured map.
     *
     * @param string|null $dir Target directory path
     * @param array|null $allowedExts File extensions to filter
     * @param array|null $ignoredDirs Directory names to skip
     * @param bool $nested Whether to return nested hierarchy (true) or flat list (false)
     * @return array
     */
    function scanDirectoryMap($dir = null, $allowedExts = null, $ignoredDirs = null, $nested = false) {
        $dir = $dir ?: ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
        $allowedExts = $allowedExts ?: ['php', 'html', 'htm', 'js', 'css', 'json', 'sql'];
        $ignoredDirs = $ignoredDirs ?: ['.git', 'node_modules', 'vendor', 'cache', 'logs'];

        $results = [];
        $items = @scandir($dir);

        if ($items === false) {
            return $results;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                if (in_array($item, $ignoredDirs)) {
                    continue;
                }
                $subResults = scanDirectoryMap($path, $allowedExts, $ignoredDirs, $nested);
                if ($nested) {
                    $results[$item] = [
                        'type' => 'directory',
                        'contents' => $subResults
                    ];
                } else {
                    $results = array_merge($results, $subResults);
                }
            } else {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if (in_array($extension, $allowedExts)) {
                    $fileData = [
                        'file_name'  => $item,
                        'path'       => $path,
                        'extension'  => $extension,
                        'size_bytes' => @filesize($path) ?: 0,
                        'purpose'    => analyzeFileContent($path, $extension)
                    ];

                    if ($nested) {
                        $results[$item] = $fileData;
                    } else {
                        $results[] = $fileData;
                    }
                }
            }
        }

        return $results;
    }
}

if (!function_exists('getFileSystemScanJson')) {
    /**
     * Generates structured JSON report of directory state.
     *
     * @param string|null $rootDir Target directory path
     * @param bool $returnArray Return array instead of JSON string
     * @return string|array
     */
    function getFileSystemScanJson($rootDir = null, $returnArray = false) {
        $rootDir = $rootDir ?: ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
        $scannedFiles = scanDirectoryMap($rootDir);

        $response = [
            'status'      => 'success',
            'total_files' => count($scannedFiles),
            'root_path'   => $rootDir,
            'files'       => $scannedFiles
        ];

        if ($returnArray) {
            return $response;
        }

        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
