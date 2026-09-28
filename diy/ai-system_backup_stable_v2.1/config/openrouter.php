<?php

require __DIR__ . '/../../../config.php';

if (!isset($API_openrouter) || trim($API_openrouter) === '') {
    die("ERROR: OpenRouter API key not loaded. 
Make sure /config.php contains: \$API_openrouter = 'your-key-here';");
}

return [
    "api_key" => $API_openrouter,
    "base_url" => "https://openrouter.ai/api/v1/chat/completions",
    "model" => "openrouter/free",
];