<?php
// ============================================================================
// CfCbazar AI Agent - File System Scanner Tool Handler
// File: /includes/agent_scan_files.php
// ============================================================================

if (!defined('ABSPATH') && !defined('CFCBAZAR_INIT')) {
    // Basic protection against direct file execution if needed
}

// Include underlying scanner functions if not already present
$scannerCorePath = __DIR__ . '/scan_files.php';
if (file_exists($scannerCorePath)) {
    require_once $scannerCorePath;
}

if (!function_exists('agent_scan_files')) {
    /**
     * Executes the directory scanning skill for the AI Agent.
     *
     * @param string|null $rootDir Path to scan. If null, defaults to document root.
     * @return array Structured array response containing status and file tree data.
     */
    function agent_scan_files($rootDir = null) {
        if (!function_exists('getFileSystemScanJson')) {
            return [
                'success' => false,
                'error'   => 'The core scanner function getFileSystemScanJson() is missing from scan_files.php.'
            ];
        }

        try {
            // Resolve targeted directory path
            $targetDir = !empty($rootDir) ? (string)$rootDir : ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);

            if (!is_dir($targetDir)) {
                return [
                    'success' => false,
                    'error'   => "Target directory does not exist or is not readable: {$targetDir}"
                ];
            }

            // Retrieve structured scan array
            $scanResult = getFileSystemScanJson($targetDir, true);

            return [
                'success' => true,
                'message' => 'Directory scan completed successfully.',
                'data'    => $scanResult
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error'   => 'File system scan exception: ' . $e->getMessage()
            ];
        }
    }
}
