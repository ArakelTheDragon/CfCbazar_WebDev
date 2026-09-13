<?php
// ============================================================================
// OpenRouter API Client
// File: /includes/agent_openrouter.php
// ============================================================================

if (!function_exists('call_openrouter')) {
    function call_openrouter($messages)
    {
        global $API_openrouter;

        if (empty($API_openrouter)) {
            return [
                'success' => false,
                'error' => 'OpenRouter API key is not configured.'
            ];
        }

        $url = 'https://openrouter.ai/api/v1/chat/completions';
        $model = defined('CFCBAZAR_AI_MODEL') ? CFCBAZAR_AI_MODEL : 'openrouter/free';

        $tools = [
            ['type' => 'openrouter:web_search', 'parameters' => ['max_results' => 5]],
            ['type' => 'openrouter:web_fetch', 'parameters' => ['max_content_tokens' => 50000]],
            ['type' => 'openrouter:advisor'],
            ['type' => 'openrouter:subagent'],
            ['type' => 'openrouter:fusion'],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'agent_scan_files',
                    'description' => 'Scan local server directories to retrieve structured JSON file/directory details.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'root_dir' => ['type' => 'string', 'description' => 'Optional root directory path to scan.']
                        ]
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'agent_generate_image',
                    'description' => 'Generate a custom GD image banner with custom text, dimensions, and hex colors.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'The exact text string to render on the image banner.'],
                            'width' => ['type' => 'integer', 'description' => 'Width of image in pixels (default: 400).'],
                            'height' => ['type' => 'integer', 'description' => 'Height of image in pixels (default: 120).'],
                            'bg_color' => ['type' => 'string', 'description' => 'Hex color code for background e.g. #000000.'],
                            'text_color' => ['type' => 'string', 'description' => 'Hex color code for text e.g. #E90E5B.']
                        ],
                        'required' => ['text']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'agent_generate_pdf',
                    'description' => 'Generate a PDF file from text content when explicitly requested.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'content' => ['type' => 'string', 'description' => 'The complete text content to place inside the PDF.']
                        ],
                        'required' => ['content']
                    ]
                ]
            ]
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'tools' => $tools
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonPayload === false) {
            return ['success' => false, 'error' => 'Failed to encode OpenRouter request.'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['success' => false, 'error' => 'Unable to initialize OpenRouter connection.'];
        }

        $headers = [
            'Authorization: Bearer ' . $API_openrouter,
            'Content-Type: application/json',
            'Accept: application/json',
            'HTTP-Referer: https://cfcbazar.42web.io',
            'X-Title: CfCbazar AI Agent'
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $rawResponse = curl_exec($ch);
        $curlError   = curl_error($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse === false) {
            return ['success' => false, 'error' => 'OpenRouter connection failed. cURL error: ' . $curlError];
        }

        $decoded = json_decode($rawResponse, true);

        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'OpenRouter returned invalid JSON.'];
        }

        if ($httpCode < 200 || $httpCode >= 300 || isset($decoded['error'])) {
            return ['success' => false, 'error' => format_openrouter_error($decoded, $httpCode)];
        }

        return ['success' => true, 'data' => $decoded];
    }
}
