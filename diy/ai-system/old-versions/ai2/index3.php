<?php
// ============================================================================
// CfCbazar AI Agent
// File: /diy/ai/index.php
// ============================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// ============================================================================
// Load reusable functions
// ============================================================================

$reusablePath = __DIR__ . '/../../includes/reusable.php';

if (!file_exists($reusablePath)) {
    http_response_code(500);
    exit('Error: /includes/reusable.php library missing.');
}

require_once $reusablePath;


// ============================================================================
// SerpApi Raw Search Function
// ============================================================================

if (!function_exists('agent_get_search')) {
    /**
     * Executes an HTTP request to SerpApi and returns the raw decoded JSON response.
     *
     * @param string $query    Search terms
     * @param string $apiKey   SerpApi authentication key
     * @param string $location Geographic location for target results
     * @return array|null
     */
    function agent_get_search(string $query, string $apiKey, string $location = 'Austin, Texas, United States'): ?array
    {
        $params = [
            'q'             => $query,
            'location'      => $location,
            'hl'            => 'en',
            'gl'            => 'us',
            'google_domain' => 'google.com',
            'api_key'       => $apiKey
        ];

        $endpoint = 'https://serpapi.com/search.json?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'CfCbazar-AI-Agent/1.0'
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse === false || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($rawResponse, true);
        return is_array($data) ? $data : null;
    }
}


// ============================================================================
// Load AI Agent Skills
// ============================================================================

$pdfFunctionLoaded = function_exists('agent_generate_pdf');
$feedFunctionLoaded = function_exists('process_agent_feed_info');
$searchFunctionLoaded = function_exists('agent_get_search');


// ============================================================================
// System Initialization
// ============================================================================

if (function_exists('enforce_https')) {
    enforce_https();
}

if (function_exists('require_database_connection')) {
    require_database_connection();
}

if (function_exists('checkSystemFlags')) {
    checkSystemFlags();
}

if (function_exists('trackVisit')) {
    trackVisit('ai-agent');
}


// ============================================================================
// User / Session
// ============================================================================

if (function_exists('session_check')) {
    session_check();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$email = $_SESSION['email'] ?? null;
$csrf = function_exists('csrf_token') ? csrf_token() : '';


// ============================================================================
// Helper: HTML escaping
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


// ============================================================================
// Markdown → HTML (Supports Links, Bold, Italic, Code blocks, and Inline code)
// ============================================================================

if (!function_exists('ai_md')) {
    function ai_md($text)
    {
        $text = (string)$text;

        $text = htmlspecialchars(
            $text,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $text = preg_replace_callback(
            '/```(?:[a-z0-9_+-]+)?\r?\n?(.*?)```/s',
            function ($match) {
                return '<pre><code>' . trim($match[1]) . '</code></pre>';
            },
            $text
        );

        $text = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/i',
            function ($match) {
                return '<a href="' . $match[2] . '" target="_blank" rel="noopener noreferrer">' . $match[1] . '</a>';
            },
            $text
        );

        $text = preg_replace(
            '/\*\*(.*?)\*\*/s',
            '<strong>$1</strong>',
            $text
        );

        $text = preg_replace(
            '/(?<!\*)\*([^\*]+)\*(?!\*)/s',
            '<em>$1</em>',
            $text
        );

        $text = preg_replace(
            '/`([^`]+)`/',
            '<code>$1</code>',
            $text
        );

        return nl2br($text);
    }
}


// ============================================================================
// SerpApi Web Search Execution Handler
// ============================================================================

if (!function_exists('perform_agent_search')) {
    function perform_agent_search(string $query): array
    {
        $apiKey = defined('SERPAPI_KEY') ? SERPAPI_KEY : (defined('SERPAPI_API_KEY') ? SERPAPI_API_KEY : '');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'SerpApi API key is not configured in reusable.php.'
            ];
        }

        if (!function_exists('agent_get_search')) {
            return [
                'success' => false,
                'error' => 'agent_get_search function is missing.'
            ];
        }

        $rawResponse = agent_get_search($query, $apiKey);

        if (!$rawResponse || !is_array($rawResponse)) {
            return [
                'success' => false,
                'error' => 'Failed to fetch search results from SerpApi.'
            ];
        }

        // Filter and format key sections to conserve AI context token window
        $formattedResults = [];

        if (!empty($rawResponse['knowledge_graph'])) {
            $kg = $rawResponse['knowledge_graph'];
            $formattedResults['knowledge_graph'] = [
                'title' => $kg['title'] ?? '',
                'type' => $kg['type'] ?? '',
                'description' => $kg['description'] ?? ''
            ];
        }

        if (!empty($rawResponse['organic_results']) && is_array($rawResponse['organic_results'])) {
            $formattedResults['organic_results'] = [];
            foreach (array_slice($rawResponse['organic_results'], 0, 5) as $item) {
                $formattedResults['organic_results'][] = [
                    'title' => $item['title'] ?? '',
                    'snippet' => $item['snippet'] ?? '',
                    'link' => $item['link'] ?? ''
                ];
            }
        }

        if (!empty($rawResponse['local_results']['places']) && is_array($rawResponse['local_results']['places'])) {
            $formattedResults['local_results'] = [];
            foreach (array_slice($rawResponse['local_results']['places'], 0, 3) as $place) {
                $formattedResults['local_results'][] = [
                    'title' => $place['title'] ?? '',
                    'rating' => $place['rating'] ?? '',
                    'address' => $place['address'] ?? '',
                    'description' => $place['description'] ?? ''
                ];
            }
        }

        if (empty($formattedResults)) {
            return [
                'success' => true,
                'message' => 'Search completed, but no relevant organic or local results were found.',
                'results' => []
            ];
        }

        return [
            'success' => true,
            'results' => $formattedResults
        ];
    }
}


// ============================================================================
// OpenRouter Error Formatter
// ============================================================================

function format_openrouter_error($response, $httpCode = 0)
{
    $parts = ['OpenRouter request failed.'];

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

        if (!empty($response['error']['metadata'])) {
            $metadata = $response['error']['metadata'];

            if (is_array($metadata)) {
                if (!empty($metadata['raw'])) {
                    $parts[] = 'Metadata raw: ' . $metadata['raw'];
                }

                if (isset($metadata['provider_name'])) {
                    $parts[] = 'Metadata provider_name: ' . $metadata['provider_name'];
                }

                if (isset($metadata['is_byok'])) {
                    $parts[] = 'Metadata is_byok: ' . ($metadata['is_byok'] ? 'true' : 'false');
                }
            }
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


// ============================================================================
// OpenRouter API Call
// ============================================================================

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
        [
            'type' => 'function',
            'function' => [
                'name' => 'google_search',
                'description' => 'Search the live web for current facts, real-time stock prices, business listings, or recent news.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query string to look up.'
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'agent_generate_pdf',
                'description' => 'Generate a PDF file from text content when the user explicitly requests a PDF.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => [
                            'type' => 'string',
                            'description' => 'The complete text content to place inside the PDF.'
                        ]
                    ],
                    'required' => ['content']
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'process_agent_feed_info',
                'description' => 'Process information for the CfCbazar AI Agent feed when the user explicitly asks to add, submit, send, or process information for the agent feed.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string',
                            'description' => 'The category of the information being submitted to the agent feed.'
                        ],
                        'infoData' => [
                            'type' => 'string',
                            'description' => 'The information that should be sent to and processed by the agent feed.'
                        ]
                    ],
                    'required' => ['category', 'infoData']
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
        return [
            'success' => false,
            'error' => 'Failed to encode OpenRouter request: ' . json_last_error_msg()
        ];
    }

    $ch = curl_init($url);

    if ($ch === false) {
        return [
            'success' => false,
            'error' => 'Unable to initialize OpenRouter connection.'
        ];
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
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($rawResponse === false) {
        return [
            'success' => false,
            'error' => 'OpenRouter connection failed.' . "\n\n" . 'cURL error: ' . $curlError . "\n\n" . 'HTTP status: ' . $httpCode
        ];
    }

    $decoded = json_decode($rawResponse, true);

    if (!is_array($decoded)) {
        return [
            'success' => false,
            'error' => 'OpenRouter returned invalid JSON.' . "\n\n" . 'HTTP status: ' . $httpCode . "\n\n" . 'Raw response: ' . $rawResponse
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'success' => false,
            'error' => format_openrouter_error($decoded, $httpCode),
            'http_code' => $httpCode,
            'raw' => $decoded
        ];
    }

    if (isset($decoded['error'])) {
        return [
            'success' => false,
            'error' => format_openrouter_error($decoded, $httpCode),
            'http_code' => $httpCode,
            'raw' => $decoded
        ];
    }

    return [
        'success' => true,
        'data' => $decoded
    ];
}


// ============================================================================
// Initialize Conversation
// ============================================================================

$systemMessage = [
    'role' => 'system',
    'content' => 'You are the CfCbazar AI Agent. Respond clearly, accurately and helpfully. ' .
                 'You can help with CfCbazar, programming, DIY projects, technical questions, writing and general questions. ' .
                 'When you need current facts, real-time stock prices, or web information, use the google_search tool. ' .
                 'When the user explicitly requests a PDF, use the agent_generate_pdf tool. After a PDF is generated, provide the exact download link. ' .
                 'When the user explicitly asks to add or process information for the feed, use process_agent_feed_info.'
];

if (!isset($_SESSION['messages']) || !is_array($_SESSION['messages']) || empty($_SESSION['messages'])) {
    $_SESSION['messages'] = [$systemMessage];
}


// ============================================================================
// Clean Old Tool History
// ============================================================================

function clean_ai_history()
{
    global $systemMessage;

    if (!isset($_SESSION['messages']) || !is_array($_SESSION['messages'])) {
        $_SESSION['messages'] = [$systemMessage];
        return;
    }

    $clean = [];
    $system = $_SESSION['messages'][0] ?? null;

    if (is_array($system) && ($system['role'] ?? '') === 'system') {
        $clean[] = $system;
    } else {
        $clean[] = $systemMessage;
    }

    foreach (array_slice($_SESSION['messages'], 1) as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = $message['role'] ?? '';

        if ($role === 'tool') {
            continue;
        }

        if ($role === 'assistant' && !empty($message['tool_calls'])) {
            continue;
        }

        if (in_array($role, ['system', 'user', 'assistant'], true)) {
            $clean[] = $message;
        }
    }

    $_SESSION['messages'] = $clean;
}

clean_ai_history();


// ============================================================================
// Limit History Window
// ============================================================================

$maxHistoryMessages = 20;

if (count($_SESSION['messages']) > ($maxHistoryMessages + 1)) {
    $system = $_SESSION['messages'][0];
    $recent = array_slice($_SESSION['messages'], -$maxHistoryMessages);
    $_SESSION['messages'] = array_merge([$system], $recent);
}


// ============================================================================
// AJAX HANDLER
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {

    // Suppress inline PHP display errors so warnings/notices don't corrupt JSON
    ini_set('display_errors', 0);

    // Clean and close all active output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    $postedCsrf = (string)($_POST['csrf'] ?? '');

    if ($csrf !== '' && !hash_equals($csrf, $postedCsrf)) {
        echo json_encode([
            'success' => false,
            'error' => 'Security validation failed. Please reload the page.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user = trim((string)($_POST['message'] ?? ''));

    if ($user === '') {
        echo json_encode([
            'success' => false,
            'error' => 'Please enter a message.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (function_exists('mb_strlen') && mb_strlen($user, 'UTF-8') > 10000) {
        echo json_encode([
            'success' => false,
            'error' => 'Message is too long. Maximum length is 10,000 characters.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    clean_ai_history();

    $_SESSION['messages'][] = [
        'role' => 'user',
        'content' => $user
    ];

    $response = call_openrouter($_SESSION['messages']);

    if (empty($response['success'])) {
        array_pop($_SESSION['messages']);
        echo json_encode([
            'success' => false,
            'error' => $response['error'] ?? 'Unknown OpenRouter error.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $apiData = $response['data'] ?? [];
    $msg = $apiData['choices'][0]['message'] ?? null;

    if (!is_array($msg)) {
        echo json_encode([
            'success' => false,
            'error' => 'OpenRouter returned an unexpected response format.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Handle Tool Execution
    $pdfUrl = null;
    $feedResult = null;
    $feedProcessed = false;

    if (!empty($msg['tool_calls']) && is_array($msg['tool_calls'])) {
        $_SESSION['messages'][] = $msg;

        foreach ($msg['tool_calls'] as $toolCall) {
            $toolCallId = $toolCall['id'] ?? '';
            $functionName = $toolCall['function']['name'] ?? '';
            $arguments = $toolCall['function']['arguments'] ?? '{}';
            $toolResult = ['success' => false, 'error' => 'Unknown tool.'];

            if ($toolCallId === '') {
                continue;
            }

            $args = json_decode($arguments, true);

            if (!is_array($args)) {
                $toolResult = ['success' => false, 'error' => 'Invalid tool arguments JSON.'];
            } elseif ($functionName === 'google_search') {
                $searchQuery = trim((string)($args['query'] ?? ''));

                if ($searchQuery === '') {
                    $toolResult = [
                        'success' => false,
                        'error' => 'Search query was empty.'
                    ];
                } else {
                    $toolResult = perform_agent_search($searchQuery);
                }
            } elseif ($functionName === 'agent_generate_pdf') {
                if (!function_exists('agent_generate_pdf')) {
                    $toolResult = ['success' => false, 'error' => 'PDF generator function not available on server.'];
                } else {
                    $content = (string)($args['content'] ?? '');
                    if (trim($content) === '') {
                        $toolResult = ['success' => false, 'error' => 'PDF content was empty.'];
                    } else {
                        try {
                            $generatedUrl = agent_generate_pdf($content);
                            if (!is_string($generatedUrl) || trim($generatedUrl) === '') {
                                throw new RuntimeException('PDF generator returned an empty URL.');
                            }
                            $pdfUrl = $generatedUrl;
                            $toolResult = [
                                'success' => true,
                                'url' => $generatedUrl,
                                'message' => 'PDF successfully generated: ' . $generatedUrl
                            ];
                        } catch (Throwable $e) {
                            $toolResult = ['success' => false, 'error' => 'PDF generation failed: ' . $e->getMessage()];
                        }
                    }
                }
            } elseif ($functionName === 'process_agent_feed_info') {
                if (!function_exists('process_agent_feed_info')) {
                    $toolResult = ['success' => false, 'error' => 'Agent feed function not available on server.'];
                } else {
                    $category = trim((string)($args['category'] ?? ''));
                    $infoData = trim((string)($args['infoData'] ?? ''));

                    if ($category === '' || $infoData === '') {
                        $toolResult = ['success' => false, 'error' => 'Missing category or info data for feed.'];
                    } else {
                        try {
                            $feedResult = process_agent_feed_info($category, $infoData);
                            if (is_array($feedResult)) {
                                $feedProcessed = (isset($feedResult['status']) && $feedResult['status'] === 'success');
                                $toolResult = $feedResult;
                            } else {
                                $toolResult = ['success' => false, 'error' => 'Invalid feed result format.'];
                            }
                        } catch (Throwable $e) {
                            $toolResult = ['success' => false, 'error' => 'Feed processing error: ' . $e->getMessage()];
                        }
                    }
                }
            }

            $_SESSION['messages'][] = [
                'role' => 'tool',
                'tool_call_id' => $toolCallId,
                'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ];
        }

        $secondResponse = call_openrouter($_SESSION['messages']);

        if (empty($secondResponse['success'])) {
            $fallbackReply = $pdfUrl ? 'The PDF has been generated.' : ($feedProcessed ? 'Feed information processed.' : 'Tool action executed.');
            echo json_encode([
                'success' => true,
                'reply' => $fallbackReply,
                'pdf_url' => $pdfUrl,
                'feed_result' => $feedResult,
                'warning' => $secondResponse['error'] ?? 'API error following tool execution.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $secondData = $secondResponse['data'] ?? [];
        $secondMsg = $secondData['choices'][0]['message'] ?? null;

        if (is_array($secondMsg)) {
            $_SESSION['messages'][] = $secondMsg;
            $assistantContent = (string)($secondMsg['content'] ?? '');
            if (trim($assistantContent) === '') {
                $assistantContent = $pdfUrl ? 'The PDF has been successfully created.' : 'Operation completed.';
            }

            echo json_encode([
                'success' => true,
                'reply' => $assistantContent,
                'pdf_url' => $pdfUrl,
                'feed_result' => $feedResult
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    // Standard Response without tools
    $_SESSION['messages'][] = $msg;
    $assistantContent = (string)($msg['content'] ?? '');

    if (trim($assistantContent) === '') {
        $assistantContent = 'The AI returned an empty response.';
    }

    echo json_encode([
        'success' => true,
        'reply' => $assistantContent,
        'pdf_url' => null,
        'feed_result' => null
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


// ============================================================================
// HTML Page Output
// ============================================================================

$pageTitle = 'CfCbazar AI Agent';

include_header($pageTitle);
include_menu();

if (function_exists('showAdvertPopup')) {
    showAdvertPopup();
}

if (function_exists('render_top_userbar')) {
    render_top_userbar();
}
?>

<style>
.ai-agent-wrapper {
    max-width: 1100px;
    margin: 30px auto;
    padding: 0 15px 40px;
}

.ai-agent-title {
    text-align: center;
    margin-bottom: 20px;
}

.ai-agent-title h1 {
    margin-bottom: 8px;
}

.ai-agent-title p {
    margin: 0;
    opacity: 0.8;
}

.ai-chat-box {
    width: 100%;
    max-height: 500px;
    overflow-y: auto;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(128,128,128,.35);
    background: rgba(128,128,128,.06);
}

.ai-message {
    margin-bottom: 18px;
    padding: 14px 16px;
    border-radius: 10px;
    line-height: 1.55;
    overflow-wrap: anywhere;
}

.ai-message-user {
    margin-left: 10%;
    background: rgba(0,123,255,.10);
}

.ai-message-ai {
    margin-right: 10%;
    background: rgba(128,128,128,.10);
}

.ai-message-label {
    display: block;
    font-weight: 700;
    margin-bottom: 7px;
}

.ai-message pre {
    background: #1e1e1e;
    color: #f8f8f2;
    padding: 12px;
    border-radius: 6px;
    overflow-x: auto;
    margin: 10px 0;
}

.ai-message pre code {
    background: transparent;
    color: inherit;
    padding: 0;
}

.ai-message code {
    display: inline-block;
    padding: 2px 5px;
    border-radius: 4px;
    background: rgba(0,0,0,.08);
    font-family: monospace;
}

.ai-message a {
    color: #0066cc;
    text-decoration: underline;
    font-weight: 700;
    cursor: pointer;
}

.ai-message a:hover {
    text-decoration: none;
}

.ai-pdf-download {
    display: inline-block;
    margin-top: 12px;
    padding: 10px 16px;
    border-radius: 7px;
    background: #0066cc;
    color: #fff !important;
    text-decoration: none !important;
    font-weight: 700;
}

.ai-pdf-download:hover {
    opacity: .88;
}

.ai-feed-status {
    display: inline-block;
    margin-top: 12px;
    padding: 10px 16px;
    border-radius: 7px;
    font-weight: 700;
    background: rgba(0,128,0,.10);
}

.ai-input-row {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    align-items: stretch;
}

.ai-input-row textarea {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 80px;
    resize: vertical;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid rgba(128,128,128,.4);
    font: inherit;
}

.ai-input-row button {
    flex: 0 0 120px;
    width: 120px;
    border: 0;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 700;
}

.ai-loading {
    display: none;
    text-align: center;
    margin: 15px 0;
}

.ai-loading-spinner {
    display: inline-block;
    width: 22px;
    height: 22px;
    border: 3px solid rgba(128,128,128,.35);
    border-top-color: currentColor;
    border-radius: 50%;
    animation: ai-spin .8s linear infinite;
    vertical-align: middle;
    margin-right: 8px;
}

@keyframes ai-spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 600px) {
    .ai-agent-wrapper {
        margin-top: 15px;
        padding-left: 10px;
        padding-right: 10px;
    }

    .ai-chat-box {
        max-height: 60vh;
        padding: 12px;
    }

    .ai-message-user,
    .ai-message-ai {
        margin-left: 0;
        margin-right: 0;
    }

    .ai-input-row {
        flex-direction: column;
    }

    .ai-input-row textarea,
    .ai-input-row button {
        width: 100%;
        flex-basis: auto;
    }
}
</style>

<div class="ai-agent-wrapper">
    <div class="ai-agent-title">
        <h1>CfCbazar AI Agent</h1>
        <p>Ask questions, get help, request a PDF, or submit information to the agent feed.</p>
    </div>

    <div id="aiChatBox" class="ai-chat-box">
        <div class="ai-message ai-message-ai">
            <span class="ai-message-label">CfCbazar AI</span>
            How can I help you?
        </div>
    </div>

    <div id="aiLoading" class="ai-loading">
        <span class="ai-loading-spinner"></span>
        Thinking...
    </div>

    <form id="aiForm" class="ai-input-row">
        <textarea
            id="aiMessage"
            name="message"
            placeholder="Type your message..."
            maxlength="10000"
            required
        ></textarea>
        <button type="submit" id="aiSend">Send</button>
    </form>
</div>

<script>
(function () {
    'use strict';

    const form = document.getElementById('aiForm');
    const input = document.getElementById('aiMessage');
    const chat = document.getElementById('aiChatBox');
    const loading = document.getElementById('aiLoading');
    const sendButton = document.getElementById('aiSend');
    const csrf = <?php echo json_encode($csrf); ?>;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderMarkdown(text) {
        let value = escapeHtml(text);

        value = value.replace(/```(?:[a-z0-9_+-]+)?\r?\n?(.*?)```/gs, function (match, code) {
            return '<pre><code>' + code.trim() + '</code></pre>';
        });

        value = value.replace(/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/gi, function (match, label, url) {
            return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
        });

        value = value.replace(/\*\*(.*?)\*\*/gs, '<strong>$1</strong>');

        value = value.replace(/(?<!\*)\*([^\*]+)\*(?!\*)/gs, '<em>$1</em>');

        value = value.replace(/`([^`]+)`/g, '<code>$1</code>');

        return value.replace(/\n/g, '<br>');
    }

    function addMessage(type, text) {
        const div = document.createElement('div');
        div.className = 'ai-message ' + (type === 'user' ? 'ai-message-user' : 'ai-message-ai');

        const label = document.createElement('span');
        label.className = 'ai-message-label';
        label.textContent = type === 'user' ? 'You' : 'CfCbazar AI';

        div.appendChild(label);

        const content = document.createElement('div');
        if (type === 'user') {
            content.textContent = text;
        } else {
            content.innerHTML = renderMarkdown(text);
        }

        div.appendChild(content);
        chat.appendChild(div);
        chat.scrollTop = chat.scrollHeight;

        return div;
    }

    function addPdfLink(pdfUrl) {
        if (!pdfUrl || !/^https?:\/\//i.test(pdfUrl)) {
            return;
        }

        const div = document.createElement('div');
        div.className = 'ai-message ai-message-ai';

        const label = document.createElement('span');
        label.className = 'ai-message-label';
        label.textContent = 'PDF';
        div.appendChild(label);

        const link = document.createElement('a');
        link.href = pdfUrl;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.className = 'ai-pdf-download';
        link.textContent = 'Download PDF';

        div.appendChild(link);
        chat.appendChild(div);
        chat.scrollTop = chat.scrollHeight;
    }

    function addFeedStatus(feedResult) {
        if (!feedResult || feedResult.status !== 'success') {
            return;
        }

        const div = document.createElement('div');
        div.className = 'ai-message ai-message-ai';

        const label = document.createElement('span');
        label.className = 'ai-message-label';
        label.textContent = 'Agent Feed';
        div.appendChild(label);

        const status = document.createElement('div');
        status.className = 'ai-feed-status';
        status.textContent = 'Feed information processed successfully.';

        div.appendChild(status);
        chat.appendChild(div);
        chat.scrollTop = chat.scrollHeight;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const message = input.value.trim();

        if (!message) {
            return;
        }

        addMessage('user', message);
        input.value = '';

        loading.style.display = 'block';
        sendButton.disabled = true;
        input.disabled = true;

        try {
            const body = new URLSearchParams();
            body.append('ajax', '1');
            body.append('csrf', csrf);
            body.append('message', message);

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });

            const rawText = await response.text();
            let data;

            try {
                // Extract valid JSON boundaries to ignore prepended/appended non-JSON text
                const jsonStart = rawText.indexOf('{');
                const jsonEnd = rawText.lastIndexOf('}');

                if (jsonStart === -1 || jsonEnd === -1) {
                    throw new Error('No valid JSON object found in response.');
                }

                const cleanJson = rawText.substring(jsonStart, jsonEnd + 1);
                data = JSON.parse(cleanJson);
            } catch (jsonError) {
                throw new Error('The server returned an invalid response.\n\n' + rawText);
            }

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'The AI request failed.');
            }

            if (data.reply) {
                addMessage('ai', data.reply);
            }

            if (data.pdf_url) {
                addPdfLink(data.pdf_url);
            }

            if (data.feed_result) {
                addFeedStatus(data.feed_result);
            }

            if (data.warning) {
                addMessage('ai', 'Warning:\n\n' + data.warning);
            }

        } catch (error) {
            addMessage('ai', 'Error:\n\n' + error.message);
        } finally {
            loading.style.display = 'none';
            sendButton.disabled = false;
            input.disabled = false;
            input.focus();
        }
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
})();
</script>

<?php
if (function_exists('cfc_footer')) {
    cfc_footer('https://github.com/', 'CfCbazar AI Agent');
}

include_footer();
?>
