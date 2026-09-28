<?php

declare(strict_types=1);

/**
 * OpenRouter configuration for CfCbazar AI System.
 *
 * Site root config.php is at /config.php
 * This system lives at /diy/ai-system2_dev/
 */

$apiKey = '';

// 1. Environment variable
if (getenv('OPENROUTER_API_KEY') !== false) {
    $apiKey = trim((string) getenv('OPENROUTER_API_KEY'));
}

// 2. Site root and common relative locations
if ($apiKey === '') {
    $candidates = [
        // From /diy/ai-system2_dev/config/ → site root
        __DIR__ . '/../../../config.php',   // /diy/ai-system2_dev/config → ../../../ = site root
        __DIR__ . '/../../../../config.php',
        // Fallback other depths
        __DIR__ . '/../../config.php',
        __DIR__ . '/../config.php',
        __DIR__ . '/config.php',
        // Absolute-style from document root if available
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/config.php',
    ];

    foreach ($candidates as $configPath) {
        if ($configPath === '' || $configPath === '/config.php') {
            continue;
        }
        if (is_file($configPath)) {
            // Isolate variables so we don't pollute
            $API_openrouter = null;
            $openrouter_api_key = null;
            require $configPath;
            if (isset($API_openrouter) && is_string($API_openrouter) && trim($API_openrouter) !== '') {
                $apiKey = trim($API_openrouter);
                break;
            }
            if (isset($openrouter_api_key) && is_string($openrouter_api_key) && trim($openrouter_api_key) !== '') {
                $apiKey = trim($openrouter_api_key);
                break;
            }
        }
    }
}

// 3. Local override (never commit real keys)
if ($apiKey === '') {
    $local = __DIR__ . '/openrouter.local.php';
    if (is_file($local)) {
        $localConfig = require $local;
        if (is_array($localConfig) && !empty($localConfig['api_key'])) {
            $apiKey = trim((string) $localConfig['api_key']);
        }
    }
}

if ($apiKey === '') {
    throw new RuntimeException(
        'OpenRouter API key not found. Expected $API_openrouter in /config.php ' .
        '(site root). Current script dir: ' . __DIR__
    );
}

return [
    'api_key'  => $apiKey,
    'base_url' => 'https://openrouter.ai/api/v1/chat/completions',
    'model'    => 'openrouter/free',
];
