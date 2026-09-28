<?php

/**
 * index.php
 *
 * Entry point for your modular AI system.
 * Loads the Router, receives a prompt, and prints the final response.
 */

require_once __DIR__ . '/core/Router.php';

// Get user prompt (CLI or GET parameter)
$prompt = "";

// CLI usage: php index.php "your prompt here"
if (php_sapi_name() === 'cli') {
    $prompt = $argv[1] ?? "";
}

// Web usage: index.php?prompt=Hello
if (isset($_GET['prompt'])) {
    $prompt = $_GET['prompt'];
}

// If no prompt provided → show message
if (trim($prompt) === "") {
    echo "No prompt provided.\n";
    exit;
}

// Initialize Router
$router = new Router();

// Process prompt through the AI pipeline
$response = $router->handle($prompt);

// Output final response
echo $response . "\n";