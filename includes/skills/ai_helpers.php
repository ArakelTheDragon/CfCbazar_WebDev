<?php
// ============================================================================
// CfCbazar AI Helpers & Utilities
// File: /includes/ai_helpers.php
// ============================================================================

if (!function_exists('ai_h')) {
    function ai_h($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('format_openrouter_error')) {
    function format_openrouter_error($response, $httpCode = 0)
    {
        $parts = [];
        $parts[] = 'OpenRouter request failed.';

        if ($httpCode > 0) {
            $parts[] = 'HTTP status: ' . $httpCode;
        }

        if (is_array($response)) {
            if (!empty($response['error']['message'])) {
                $parts[] = 'Message: ' . $response['error']['message'];
            }
            if (isset($response['error']['code']) && $response['error']['code'] !== '') {
                $parts[] = 'Error code: ' . $response['error']['code'];
            }
            if (!empty($response['error']['type'])) {
                $parts[] = 'Error type: ' . $response['error']['type'];
            }
            if (!empty($response['error']['param'])) {
                $parts[] = 'Parameter: ' . $response['error']['param'];
            }

            $json = json_encode(
                $response,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($json !== false) {
                $parts[] = 'Full API response: ' . $json;
            }
        }

        return implode("\n\n", $parts);
    }
}

if (!function_exists('clean_ai_history')) {
    function clean_ai_history($systemMessage)
    {
        if (!isset($_SESSION['messages']) || !is_array($_SESSION['messages'])) {
            $_SESSION['messages'] = [$systemMessage];
            return;
        }

        $clean = [];
        $system = $_SESSION['messages'][0] ?? $systemMessage;
        $clean[] = (is_array($system) && ($system['role'] ?? '') === 'system') ? $system : $systemMessage;

        foreach (array_slice($_SESSION['messages'], 1) as $message) {
            if (!is_array($message)) continue;
            $role = $message['role'] ?? '';
            if ($role === 'tool') continue;
            if ($role === 'assistant' && !empty($message['tool_calls'])) continue;
            if (in_array($role, ['system', 'user', 'assistant'], true)) {
                $clean[] = $message;
            }
        }
        $_SESSION['messages'] = $clean;
    }
}
