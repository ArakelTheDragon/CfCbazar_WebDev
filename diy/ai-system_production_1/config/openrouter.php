<?php

declare(strict_types=1);

/**
 * OpenRouter configuration for CfCbazar AI System.
 *
 * This file is configuration only. It does not make API requests.
 * The OpenRouter API key remains in the site's root config.php.
 */

$configPath = __DIR__ . '/../../../config.php';

if (!is_file($configPath)) {
    throw new RuntimeException('OpenRouter configuration error: root config.php was not found.');
}

require_once $configPath;

if (!isset($API_openrouter) || !is_string($API_openrouter) || trim($API_openrouter) === '') {
    throw new RuntimeException(
        'OpenRouter configuration error: API key was not loaded from config.php.'
    );
}

return [
    'api_key'  => trim($API_openrouter),
    'base_url' => 'https://openrouter.ai/api/v1/chat/completions',
    'model'    => 'openrouter/free',
];
