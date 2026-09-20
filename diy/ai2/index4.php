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
// Load PDF generator
// ============================================================================

$pdfFunctionLoaded = false;

$pdfPaths = [
    __DIR__ . '/../../includes/agent_generate_pdf.php',
    __DIR__ . '/agent_generate_pdf.php'
];

foreach ($pdfPaths as $pdfPath) {
    if (file_exists($pdfPath)) {
        require_once $pdfPath;
        $pdfFunctionLoaded = function_exists('agent_generate_pdf');
        if ($pdfFunctionLoaded) {
            break;
        }
    }
}


// ============================================================================
// System
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

$csrf = function_exists('csrf_token')
    ? csrf_token()
    : '';


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
// Markdown → HTML
//
// Supports:
//   **bold**
//   *italic*
//   `code`
//   [text](https://example.com)
//
// HTML is escaped BEFORE Markdown is converted.
// This prevents AI-generated HTML from being executed.
// ============================================================================

if (!function_exists('ai_md')) {
    function ai_md($text)
    {
        $text = (string)$text;

        // Escape HTML FIRST.
        $text = htmlspecialchars(
            $text,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        // --------------------------------------------------------------------
        // Markdown links
        //
        // IMPORTANT:
        // This is done after htmlspecialchars() so the generated <a> tag
        // itself remains valid HTML while the original AI content is safe.
        // --------------------------------------------------------------------

        $text = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/i',
            function ($match) {

                $label = $match[1];
                $url   = $match[2];

                return '<a href="' .
                    $url .
                    '" target="_blank" rel="noopener noreferrer">' .
                    $label .
                    '</a>';
            },
            $text
        );

        // --------------------------------------------------------------------
        // Bold
        // --------------------------------------------------------------------

        $text = preg_replace(
            '/\*\*(.*?)\*\*/s',
            '<strong>$1</strong>',
            $text
        );

        // --------------------------------------------------------------------
        // Italic
        // --------------------------------------------------------------------

        $text = preg_replace(
            '/(?<!\*)\*([^\*]+)\*(?!\*)/s',
            '<em>$1</em>',
            $text
        );

        // --------------------------------------------------------------------
        // Inline code
        // --------------------------------------------------------------------

        $text = preg_replace(
            '/`([^`]+)`/',
            '<code>$1</code>',
            $text
        );

        // --------------------------------------------------------------------
        // Preserve line breaks
        // --------------------------------------------------------------------

        return nl2br($text);
    }
}


// ============================================================================
// OpenRouter Error Formatter
// ============================================================================

function format_openrouter_error($response, $httpCode = 0)
{
    $parts = [];

    $parts[] = 'OpenRouter request failed.';

    if ($httpCode > 0) {
        $parts[] = 'HTTP status: ' . $httpCode;
    }

    if (is_array($response)) {

        if (!empty($response['error']['message'])) {
            $parts[] =
                'Message: ' .
                $response['error']['message'];
        }

        if (
            isset($response['error']['code']) &&
            $response['error']['code'] !== ''
        ) {
            $parts[] =
                'Error code: ' .
                $response['error']['code'];
        }

        if (!empty($response['error']['type'])) {
            $parts[] =
                'Error type: ' .
                $response['error']['type'];
        }

        if (!empty($response['error']['param'])) {
            $parts[] =
                'Parameter: ' .
                $response['error']['param'];
        }

        if (!empty($response['error']['metadata'])) {

            $metadata = $response['error']['metadata'];

            if (is_array($metadata)) {

                if (!empty($metadata['raw'])) {
                    $parts[] =
                        'Metadata raw: ' .
                        $metadata['raw'];
                }

                if (isset($metadata['provider_name'])) {
                    $parts[] =
                        'Metadata provider_name: ' .
                        $metadata['provider_name'];
                }

                if (isset($metadata['is_byok'])) {
                    $parts[] =
                        'Metadata is_byok: ' .
                        ($metadata['is_byok']
                            ? 'true'
                            : 'false');
                }
            }
        }

        $json = json_encode(
            $response,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($json !== false) {
            $parts[] =
                'Full API response: ' .
                $json;
        }
    }

    return implode("\n\n", $parts);
}


// ============================================================================
// OpenRouter API
// ============================================================================

function call_openrouter($messages)
{
    global $API_openrouter;

    if (empty($API_openrouter)) {

        return [
            'success' => false,
            'error' =>
                'OpenRouter API key is not configured.'
        ];
    }


    $url =
        'https://openrouter.ai/api/v1/chat/completions';


    // ------------------------------------------------------------------------
    // Model
    //
    // If CFCBAZAR_AI_MODEL is defined in config.php, that value is used.
    //
    // Otherwise use OpenRouter's free router.
    // ------------------------------------------------------------------------

    $model =
        defined('CFCBAZAR_AI_MODEL')
            ? CFCBAZAR_AI_MODEL
            : 'openrouter/free';


    // ------------------------------------------------------------------------
    // PDF tool
    // ------------------------------------------------------------------------

    $tools = [

        [
            'type' => 'function',

            'function' => [

                'name' =>
                    'agent_generate_pdf',

                'description' =>
                    'Generate a PDF file from text content when the user explicitly requests a PDF.',

                'parameters' => [

                    'type' => 'object',

                    'properties' => [

                        'content' => [

                            'type' => 'string',

                            'description' =>
                                'The complete text content to place inside the PDF.'
                        ]
                    ],

                    'required' => [
                        'content'
                    ]
                ]
            ]
        ]
    ];


    // ------------------------------------------------------------------------
    // Payload
    // ------------------------------------------------------------------------

    $payload = [

        'model' =>
            $model,

        'messages' =>
            $messages,

        'tools' =>
            $tools
    ];


    $jsonPayload =
        json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );


    if ($jsonPayload === false) {

        return [
            'success' => false,
            'error' =>
                'Failed to encode OpenRouter request: ' .
                json_last_error_msg()
        ];
    }


    // ------------------------------------------------------------------------
    // cURL
    // ------------------------------------------------------------------------

    $ch = curl_init($url);

    if ($ch === false) {

        return [
            'success' => false,
            'error' =>
                'Unable to initialize OpenRouter connection.'
        ];
    }


    $headers = [

        'Authorization: Bearer ' .
            $API_openrouter,

        'Content-Type: application/json',

        'Accept: application/json',

        'HTTP-Referer: https://cfcbazar.42web.io',

        'X-Title: CfCbazar AI Agent'
    ];


    curl_setopt_array(
        $ch,
        [

            CURLOPT_POST =>
                true,

            CURLOPT_POSTFIELDS =>
                $jsonPayload,

            CURLOPT_HTTPHEADER =>
                $headers,

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_CONNECTTIMEOUT =>
                20,

            CURLOPT_TIMEOUT =>
                120,

            CURLOPT_SSL_VERIFYPEER =>
                true,

            CURLOPT_SSL_VERIFYHOST =>
                2
        ]
    );


    $rawResponse =
        curl_exec($ch);


    $curlError =
        curl_error($ch);


    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    // ------------------------------------------------------------------------
    // cURL error
    // ------------------------------------------------------------------------

    if ($rawResponse === false) {

        return [
            'success' => false,
            'error' =>
                'OpenRouter connection failed.' .
                "\n\n" .
                'cURL error: ' .
                $curlError .
                "\n\n" .
                'HTTP status: ' .
                $httpCode
        ];
    }


    // ------------------------------------------------------------------------
    // Decode JSON
    // ------------------------------------------------------------------------

    $decoded =
        json_decode(
            $rawResponse,
            true
        );


    if (!is_array($decoded)) {

        return [
            'success' => false,
            'error' =>
                'OpenRouter returned invalid JSON.' .
                "\n\n" .
                'HTTP status: ' .
                $httpCode .
                "\n\n" .
                'Raw response: ' .
                $rawResponse
        ];
    }


    // ------------------------------------------------------------------------
    // HTTP error
    // ------------------------------------------------------------------------

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {

        return [
            'success' => false,

            'error' =>
                format_openrouter_error(
                    $decoded,
                    $httpCode
                ),

            'http_code' =>
                $httpCode,

            'raw' =>
                $decoded
        ];
    }


    // ------------------------------------------------------------------------
    // API-level error
    // ------------------------------------------------------------------------

    if (isset($decoded['error'])) {

        return [
            'success' => false,

            'error' =>
                format_openrouter_error(
                    $decoded,
                    $httpCode
                ),

            'http_code' =>
                $httpCode,

            'raw' =>
                $decoded
        ];
    }


    return [
        'success' => true,
        'data' => $decoded
    ];
}


// ============================================================================
// Initialize conversation
// ============================================================================

$systemMessage = [

    'role' =>
        'system',

    'content' =>
        'You are the CfCbazar AI Agent. ' .
        'Respond clearly, accurately and helpfully. ' .
        'You can help with CfCbazar, programming, DIY projects, ' .
        'technical questions, writing and general questions. ' .

        'When the user explicitly requests a PDF, use the ' .
        'agent_generate_pdf tool. ' .

        'After a PDF is generated, clearly tell the user that the PDF ' .
        'was created and provide the download link returned by the tool. ' .

        'Do not invent, alter or replace the PDF URL returned by the tool. ' .

        'Only use the PDF generation tool when the user requests a PDF ' .
        'or when generating a PDF is clearly appropriate.'
];


if (
    !isset($_SESSION['messages']) ||
    !is_array($_SESSION['messages']) ||
    empty($_SESSION['messages'])
) {

    $_SESSION['messages'] = [
        $systemMessage
    ];
}


// ============================================================================
// Clean old tool history
// ============================================================================

function clean_ai_history()
{
    global $systemMessage;

    if (
        !isset($_SESSION['messages']) ||
        !is_array($_SESSION['messages'])
    ) {
        $_SESSION['messages'] = [
            $systemMessage
        ];

        return;
    }

    $clean = [];

    $system =
        $_SESSION['messages'][0]
        ?? null;

    if (
        is_array($system) &&
        ($system['role'] ?? '') === 'system'
    ) {
        $clean[] = $system;
    } else {
        $clean[] = $systemMessage;
    }


    foreach (
        array_slice(
            $_SESSION['messages'],
            1
        ) as $message
    ) {

        if (!is_array($message)) {
            continue;
        }

        $role =
            $message['role']
            ?? '';


        // Remove old tool results.
        if ($role === 'tool') {
            continue;
        }


        // Remove incomplete old assistant tool calls.
        if (
            $role === 'assistant' &&
            !empty($message['tool_calls'])
        ) {
            continue;
        }


        if (
            $role === 'system' ||
            $role === 'user' ||
            $role === 'assistant'
        ) {
            $clean[] = $message;
        }
    }


    $_SESSION['messages'] = $clean;
}


// Run cleanup immediately.
clean_ai_history();


// ============================================================================
// Limit history
// ============================================================================

$maxHistoryMessages = 20;

if (
    count($_SESSION['messages']) >
    ($maxHistoryMessages + 1)
) {

    $system =
        $_SESSION['messages'][0];

    $recent =
        array_slice(
            $_SESSION['messages'],
            -$maxHistoryMessages
        );

    $_SESSION['messages'] =
        array_merge(
            [$system],
            $recent
        );
}


// ============================================================================
// AJAX HANDLER
// ============================================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['ajax'])
) {

    header(
        'Content-Type: application/json; charset=utf-8'
    );


    // ------------------------------------------------------------------------
    // CSRF
    // ------------------------------------------------------------------------

    $postedCsrf =
        (string)(
            $_POST['csrf']
            ?? ''
        );


    if (
        $csrf !== '' &&
        !hash_equals(
            $csrf,
            $postedCsrf
        )
    ) {

        echo json_encode(
            [
                'success' => false,

                'error' =>
                    'Security validation failed. Please reload the page.'
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }


    // ------------------------------------------------------------------------
    // User message
    // ------------------------------------------------------------------------

    $user =
        trim(
            (string)(
                $_POST['message']
                ?? ''
            )
        );


    if ($user === '') {

        echo json_encode(
            [
                'success' => false,

                'error' =>
                    'Please enter a message.'
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }


    // ------------------------------------------------------------------------
    // Maximum message size
    // ------------------------------------------------------------------------

    if (
        function_exists('mb_strlen') &&
        mb_strlen($user, 'UTF-8') > 10000
    ) {

        echo json_encode(
            [
                'success' => false,

                'error' =>
                    'Message is too long. Maximum length is 10,000 characters.'
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }


    // ------------------------------------------------------------------------
    // Clean history again
    // ------------------------------------------------------------------------

    clean_ai_history();


    // ------------------------------------------------------------------------
    // Add user message
    // ------------------------------------------------------------------------

    $_SESSION['messages'][] = [

        'role' =>
            'user',

        'content' =>
            $user
    ];


    // ------------------------------------------------------------------------
    // FIRST API REQUEST
    // ------------------------------------------------------------------------

    $response =
        call_openrouter(
            $_SESSION['messages']
        );


    if (
        empty($response['success'])
    ) {

        array_pop(
            $_SESSION['messages']
        );

        echo json_encode(
            [
                'success' => false,

                'error' =>
                    $response['error']
                    ?? 'Unknown OpenRouter error.'
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }


    $apiData =
        $response['data']
        ?? [];


    $msg =
        $apiData['choices'][0]['message']
        ?? null;


    if (!is_array($msg)) {

        echo json_encode(
            [
                'success' => false,

                'error' =>
                    'OpenRouter returned an unexpected response.' .
                    "\n\n" .
                    'Full API response: ' .
                    json_encode(
                        $apiData,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    )
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }


    // =========================================================================
    // TOOL CALL
    // =========================================================================

    $pdfUrl = null;


    if (
        !empty($msg['tool_calls']) &&
        is_array($msg['tool_calls'])
    ) {

        // ---------------------------------------------------------------------
        // CRITICAL:
        //
        // Store the COMPLETE assistant message FIRST.
        //
        // OpenRouter requires the assistant tool_calls message to immediately
        // precede the corresponding tool response.
        // ---------------------------------------------------------------------

        $_SESSION['messages'][] = $msg;


        foreach (
            $msg['tool_calls']
            as $toolCall
        ) {

            $toolCallId =
                $toolCall['id']
                ?? '';


            $functionName =
                $toolCall['function']['name']
                ?? '';


            $arguments =
                $toolCall['function']['arguments']
                ?? '{}';


            $toolResult = [
                'success' => false,
                'error' =>
                    'Unknown tool.'
            ];


            // -----------------------------------------------------------------
            // Decode tool arguments
            // -----------------------------------------------------------------

            $args =
                json_decode(
                    $arguments,
                    true
                );


            if (!is_array($args)) {

                $toolResult = [
                    'success' => false,
                    'error' =>
                        'Invalid PDF tool arguments: ' .
                        json_last_error_msg()
                ];
            }


            // -----------------------------------------------------------------
            // PDF generator
            // -----------------------------------------------------------------

            elseif (
                $functionName ===
                'agent_generate_pdf'
            ) {

                if (
                    !function_exists(
                        'agent_generate_pdf'
                    )
                ) {

                    $toolResult = [
                        'success' => false,
                        'error' =>
                            'PDF generator function is not available on the server.'
                    ];

                } else {

                    $content =
                        (string)(
                            $args['content']
                            ?? ''
                        );


                    if (
                        trim($content) === ''
                    ) {

                        $toolResult = [
                            'success' => false,
                            'error' =>
                                'PDF content was empty.'
                        ];

                    } else {

                        try {

                            $generatedUrl =
                                agent_generate_pdf(
                                    $content
                                );


                            if (
                                !is_string(
                                    $generatedUrl
                                ) ||
                                trim(
                                    $generatedUrl
                                ) === ''
                            ) {

                                throw new RuntimeException(
                                    'PDF generator returned an empty URL.'
                                );
                            }


                            // -------------------------------------------------
                            // Store the actual server-generated URL.
                            // Never trust a URL invented by the AI.
                            // -------------------------------------------------

                            $pdfUrl =
                                $generatedUrl;


                            $toolResult = [
                                'success' => true,

                                'url' =>
                                    $generatedUrl,

                                'message' =>
                                    'PDF successfully generated. ' .
                                    'Use this exact URL when giving the user ' .
                                    'the download link: ' .
                                    $generatedUrl
                            ];

                        } catch (
                            Throwable $e
                        ) {

                            $toolResult = [
                                'success' => false,

                                'error' =>
                                    'PDF generation failed: ' .
                                    $e->getMessage()
                            ];
                        }
                    }
                }
            }


            // -----------------------------------------------------------------
            // Store matching tool result
            // -----------------------------------------------------------------

            $_SESSION['messages'][] = [

                'role' =>
                    'tool',

                'tool_call_id' =>
                    $toolCallId,

                'content' =>
                    json_encode(
                        $toolResult,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    )
            ];
        }


        // ---------------------------------------------------------------------
        // SECOND API REQUEST
        //
        // This allows the AI to respond naturally after the PDF is created.
        // ---------------------------------------------------------------------

        $secondResponse =
            call_openrouter(
                $_SESSION['messages']
            );


        if (
            empty($secondResponse['success'])
        ) {

            // We still have the actual PDF URL.
            // Give it directly to the browser even if the second AI request
            // fails.

            $errorText =
                $secondResponse['error']
                ?? 'Unknown OpenRouter error after PDF generation.';


            $fallbackReply =
                $pdfUrl
                    ? 'The PDF has been successfully created.'
                    : 'The requested tool could not be completed.';


            echo json_encode(
                [
                    'success' => true,

                    'reply' =>
                        $fallbackReply,

                    'pdf_url' =>
                        $pdfUrl,

                    'warning' =>
                        $errorText
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            exit;
        }


        $secondData =
            $secondResponse['data']
            ?? [];


        $secondMsg =
            $secondData['choices'][0]['message']
            ?? null;


        if (!is_array($secondMsg)) {

            $fallbackReply =
                $pdfUrl
                    ? 'The PDF has been successfully created.'
                    : 'OpenRouter returned an unexpected response.';


            echo json_encode(
                [
                    'success' => true,

                    'reply' =>
                        $fallbackReply,

                    'pdf_url' =>
                        $pdfUrl,

                    'warning' =>
                        'OpenRouter returned an unexpected second response.'
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            exit;
        }


        // Store normal assistant response.
        $_SESSION['messages'][] =
            $secondMsg;


        $assistantContent =
            (string)(
                $secondMsg['content']
                ?? ''
            );


        if (
            trim($assistantContent) === ''
        ) {

            $assistantContent =
                $pdfUrl
                    ? 'The PDF has been successfully created.'
                    : 'The AI returned an empty response.';
        }


        // ---------------------------------------------------------------------
        // Return actual PDF URL separately.
        //
        // This is the important fix. The browser receives the real URL from
        // PHP rather than relying on the AI to format the link.
        // ---------------------------------------------------------------------

        echo json_encode(
            [
                'success' => true,

                'reply' =>
                    $assistantContent,

                'pdf_url' =>
                    $pdfUrl
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }


    // =========================================================================
    // NORMAL AI RESPONSE — NO TOOL
    // =========================================================================

    $_SESSION['messages'][] =
        $msg;


    $assistantContent =
        (string)(
            $msg['content']
            ?? ''
        );


    if (
        trim($assistantContent) === ''
    ) {

        $assistantContent =
            'The AI returned an empty response.';
    }


    echo json_encode(
        [
            'success' => true,

            'reply' =>
                $assistantContent,

            'pdf_url' =>
                null
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


// ============================================================================
// Page
// ============================================================================

$pageTitle =
    'CfCbazar AI Agent';

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
/* ==========================================================================
   CfCbazar AI Agent
   ========================================================================== */

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
    to {
        transform: rotate(360deg);
    }
}

.ai-error {
    white-space: pre-wrap;
    color: #b00020;
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

        <h1>
            CfCbazar AI Agent
        </h1>

        <p>
            Ask questions, get help, or request a PDF.
        </p>

    </div>


    <div
        id="aiChatBox"
        class="ai-chat-box"
    >

        <div class="ai-message ai-message-ai">

            <span class="ai-message-label">
                CfCbazar AI
            </span>

            How can I help you?

        </div>

    </div>


    <div
        id="aiLoading"
        class="ai-loading"
    >

        <span class="ai-loading-spinner"></span>

        Thinking...

    </div>


    <form
        id="aiForm"
        class="ai-input-row"
    >

        <textarea
            id="aiMessage"
            name="message"
            placeholder="Type your message..."
            maxlength="10000"
            required
        ></textarea>


        <button
            type="submit"
            id="aiSend"
        >
            Send
        </button>

    </form>

</div>


<script>
(function () {

    'use strict';


    const form =
        document.getElementById('aiForm');

    const input =
        document.getElementById('aiMessage');

    const chat =
        document.getElementById('aiChatBox');

    const loading =
        document.getElementById('aiLoading');

    const sendButton =
        document.getElementById('aiSend');


    const csrf =
        <?php echo json_encode($csrf); ?>;


    // ------------------------------------------------------------------------
    // Escape HTML
    // ------------------------------------------------------------------------

    function escapeHtml(value) {

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }


    // ------------------------------------------------------------------------
    // Basic client-side Markdown renderer
    //
    // Server-side rendering is also used for security. This client renderer
    // is mainly for displaying the response returned by AJAX.
    // ------------------------------------------------------------------------

    function renderMarkdown(text) {

        let value =
            escapeHtml(text);


        // Markdown links.
        value =
            value.replace(
                /\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/gi,
                function (
                    match,
                    label,
                    url
                ) {

                    return '<a href="' +
                        url +
                        '" target="_blank" rel="noopener noreferrer">' +
                        label +
                        '</a>';
                }
            );


        // Bold.
        value =
            value.replace(
                /\*\*(.*?)\*\*/gs,
                '<strong>$1</strong>'
            );


        // Italic.
        value =
            value.replace(
                /(?<!\*)\*([^\*]+)\*(?!\*)/gs,
                '<em>$1</em>'
            );


        // Inline code.
        value =
            value.replace(
                /`([^`]+)`/g,
                '<code>$1</code>'
            );


        return value.replace(
            /\n/g,
            '<br>'
        );
    }


    // ------------------------------------------------------------------------
    // Add message
    // ------------------------------------------------------------------------

    function addMessage(
        type,
        text
    ) {

        const div =
            document.createElement('div');

        div.className =
            'ai-message ' +
            (
                type === 'user'
                    ? 'ai-message-user'
                    : 'ai-message-ai'
            );


        const label =
            document.createElement('span');

        label.className =
            'ai-message-label';

        label.textContent =
            type === 'user'
                ? 'You'
                : 'CfCbazar AI';


        div.appendChild(label);


        const content =
            document.createElement('div');

        if (type === 'user') {

            content.textContent =
                text;

        } else {

            content.innerHTML =
                renderMarkdown(text);
        }


        div.appendChild(content);

        chat.appendChild(div);

        chat.scrollTop =
            chat.scrollHeight;


        return div;
    }


    // ------------------------------------------------------------------------
    // Add PDF download button
    // ------------------------------------------------------------------------

    function addPdfLink(pdfUrl) {

        if (!pdfUrl) {
            return;
        }


        // Only allow HTTPS HTTP URLs.
        if (
            !/^https?:\/\//i.test(pdfUrl)
        ) {
            return;
        }


        const div =
            document.createElement('div');

        div.className =
            'ai-message ai-message-ai';


        const label =
            document.createElement('span');

        label.className =
            'ai-message-label';

        label.textContent =
            'PDF';


        div.appendChild(label);


        const link =
            document.createElement('a');

        link.href =
            pdfUrl;

        link.target =
            '_blank';

        link.rel =
            'noopener noreferrer';

        link.className =
            'ai-pdf-download';

        link.textContent =
            'Download PDF, Deleted after';


        div.appendChild(link);

        chat.appendChild(div);

        chat.scrollTop =
            chat.scrollHeight;
    }


    // ------------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------------

    form.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            const message =
                input.value.trim();


            if (!message) {
                return;
            }


            addMessage(
                'user',
                message
            );


            input.value = '';


            loading.style.display =
                'block';

            sendButton.disabled =
                true;

            input.disabled =
                true;


            try {

                const body =
                    new URLSearchParams();


                body.append(
                    'ajax',
                    '1'
                );


                body.append(
                    'csrf',
                    csrf
                );


                body.append(
                    'message',
                    message
                );


                const response =
                    await fetch(
                        window.location.href,
                        {
                            method: 'POST',

                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'X-Requested-With':
                                    'XMLHttpRequest'
                            },

                            body: body.toString()
                        }
                    );


                const rawText =
                    await response.text();


                let data;


                try {

                    data =
                        JSON.parse(
                            rawText
                        );

                } catch (jsonError) {

                    throw new Error(
                        'The server returned an invalid response.\n\n' +
                        rawText
                    );
                }


                if (
                    !response.ok ||
                    !data.success
                ) {

                    throw new Error(
                        data.error ||
                        'The AI request failed.'
                    );
                }


                // ------------------------------------------------------------
                // Normal AI response
                // ------------------------------------------------------------

                if (data.reply) {

                    addMessage(
                        'ai',
                        data.reply
                    );
                }


                // ------------------------------------------------------------
                // IMPORTANT:
                //
                // The PDF URL comes directly from PHP, not from the AI.
                //
                // This guarantees a clickable link even if the AI returns
                // plain text instead of proper Markdown.
                // ------------------------------------------------------------

                if (data.pdf_url) {

                    addPdfLink(
                        data.pdf_url
                    );
                }


                // ------------------------------------------------------------
                // Optional warning
                // ------------------------------------------------------------

                if (data.warning) {

                    addMessage(
                        'ai',
                        'Warning:\n\n' +
                        data.warning
                    );
                }


            } catch (error) {

                addMessage(
                    'ai',
                    'Error:\n\n' +
                    error.message
                );

            } finally {

                loading.style.display =
                    'none';

                sendButton.disabled =
                    false;

                input.disabled =
                    false;

                input.focus();
            }

        }
    );


    // ------------------------------------------------------------------------
    // Enter = send
    // Shift+Enter = new line
    // ------------------------------------------------------------------------

    input.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Enter' &&
                !event.shiftKey
            ) {

                event.preventDefault();

                form.requestSubmit();
            }
        }
    );

})();
</script>


<?php

// ============================================================================
// Footer
// ============================================================================

if (function_exists('cfc_footer')) {

    cfc_footer(
        'https://github.com/',
        'CfCbazar AI Agent'
    );
}

include_footer();

?>
