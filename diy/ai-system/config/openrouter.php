<?php

declare(strict_types=1);

/**
 * CfCbazar AI System - OpenRouter configuration
 *
 * Location:
 * /diy/ai-system/config/openrouter.php
 *
 * Secrets:
 * /includes/secrets.php
 *
 * This file does NOT load the site's main config.php.
 * The AI system only needs the OpenRouter credential.
 */

$apiKey = '';

/*
 * 1. Environment variable
 */
$envKey = getenv('OPENROUTER_API_KEY');

if ($envKey !== false && trim((string)$envKey) !== '') {
    $apiKey = trim((string)$envKey);
}

/*
 * 2. Site secrets.php
 *
 * Current structure:
 *
 * /htdocs/
 * ├── includes/
 * │   └── secrets.php
 * └── diy/
 *     └── ai-system/
 *         └── config/
 *             └── openrouter.php
 *
 * Therefore:
 *
 * __DIR__ . '/../../../includes/secrets.php'
 */
if ($apiKey === '') {

    $secretsPath = __DIR__ . '/../../../includes/secrets.php';

    if (is_file($secretsPath) && is_readable($secretsPath)) {

        require_once $secretsPath;

        if (
            defined('OPENROUTER_API_KEY') &&
            is_string(OPENROUTER_API_KEY) &&
            trim(OPENROUTER_API_KEY) !== ''
        ) {
            $apiKey = trim(OPENROUTER_API_KEY);
        }
    }
}

/*
 * 3. Optional local override
 *
 * Useful for development without modifying secrets.php.
 */
if ($apiKey === '') {

    $local = __DIR__ . '/openrouter.local.php';

    if (is_file($local)) {

        $localConfig = require $local;

        if (
            is_array($localConfig) &&
            isset($localConfig['api_key']) &&
            is_string($localConfig['api_key']) &&
            trim($localConfig['api_key']) !== ''
        ) {
            $apiKey = trim($localConfig['api_key']);
        }
    }
}

/*
 * 4. Fail clearly if no key was found.
 */
if ($apiKey === '') {

    throw new RuntimeException(
        'OpenRouter API key not found. ' .
        'Expected OPENROUTER_API_KEY in /includes/secrets.php. ' .
        'Checked: ' . __DIR__ . '/../../../includes/secrets.php'
    );
}

/*
 * OpenRouter configuration.
 */
return [
    'api_key'  => $apiKey,
    'base_url' => 'https://openrouter.ai/api/v1/chat/completions',
    'model'    => 'openrouter/free',
];
