<?php

declare(strict_types=1);

/**
 * OpenRouter configuration.
 * Reads $API_openrouter from site-root /config.php
 * (config.php loads includes/secrets.php and maps OPENROUTER_API_KEY).
 */

$apiKey = '';

// 1. Environment variable
if (getenv('OPENROUTER_API_KEY') !== false) {
    $apiKey = trim((string) getenv('OPENROUTER_API_KEY'));
}

// 2. Site-root config.php (and common relative locations)
if ($apiKey === '') {
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    $candidates = array_values(array_unique(array_filter([
        $docRoot !== '' ? $docRoot . '/config.php' : null,
        // From /diy/ai-system3_dev/config → site root
        __DIR__ . '/../../../config.php',
        __DIR__ . '/../../../../config.php',
        __DIR__ . '/../../config.php',
        __DIR__ . '/../config.php',
        dirname(__DIR__, 3) . '/config.php',
        dirname(__DIR__, 4) . '/config.php',
    ])));

    foreach ($candidates as $configPath) {
        if ($configPath === '' || !is_file($configPath)) {
            continue;
        }

        $API_openrouter = null;
        $openrouter_api_key = null;

        try {
            require $configPath;
        } catch (Throwable $e) {
            continue;
        }

        if (isset($API_openrouter) && is_string($API_openrouter) && trim($API_openrouter) !== '') {
            $apiKey = trim($API_openrouter);
            break;
        }
        if (isset($openrouter_api_key) && is_string($openrouter_api_key) && trim($openrouter_api_key) !== '') {
            $apiKey = trim($openrouter_api_key);
            break;
        }
        // secrets define style if config exposed it
        if (defined('OPENROUTER_API_KEY')) {
            $val = constant('OPENROUTER_API_KEY');
            if (is_string($val) && trim($val) !== '') {
                $apiKey = trim($val);
                break;
            }
        }
    }
}

// 3. Local override (optional)
if ($apiKey === '') {
    $local = __DIR__ . '/openrouter.local.php';
    if (is_file($local)) {
        $localConfig = require $local;
        if (is_array($localConfig) && !empty($localConfig['api_key'])) {
            $apiKey = trim((string) $localConfig['api_key']);
        }
    }
}

$apiKey = preg_replace('/\s+/', '', $apiKey ?? '') ?? '';

if ($apiKey === '') {
    throw new RuntimeException(
        'OpenRouter API key not found. Expected $API_openrouter in /config.php '
        . '(from OPENROUTER_API_KEY in includes/secrets.php). Config dir: ' . __DIR__
    );
}

return [
    'api_key'  => $apiKey,
    'base_url' => 'https://openrouter.ai/api/v1/chat/completions',
    'model'    => 'openrouter/free',
    'referer'  => (isset($_SERVER['HTTP_HOST'])
        ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'])
        : 'https://cfcbazar.42web.io'),
    'title'    => 'CfCbazar AI System',
];
