<?php
// ============================================================================
// CfCbazar AI Agent
// File: /diy/ai/index.php
//
// Uses:
//     OpenRouter free model router: openrouter/free
//
// No PDF generation or tool/function calling.
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
// Session
// ============================================================================

if (function_exists('session_check')) {
    session_check();
}


// ============================================================================
// CSRF
// ============================================================================

$csrf = '';

if (function_exists('csrf_token')) {
    $csrf = (string) csrf_token();
}


// ============================================================================
// Helper: safe HTML escaping
// ============================================================================

if (!function_exists('ai_h')) {

    function ai_h($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}


// ============================================================================
// Helper: safe limited Markdown → HTML
//
// HTML is escaped BEFORE formatting is applied.
// Supported:
//     **bold**
//     *italic*
//     `code`
//
// No raw HTML is allowed.
// ============================================================================

if (!function_exists('ai_markdown')) {

    function ai_markdown($text)
    {
        $text = (string) $text;

        // Escape all HTML first.
        $text = htmlspecialchars(
            $text,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        // Inline code first.
        $text = preg_replace(
            '/`([^`]+)`/',
            '<code>$1</code>',
            $text
        );

        // Bold.
        $text = preg_replace(
            '/\*\*(.*?)\*\*/s',
            '<strong>$1</strong>',
            $text
        );

        // Italic.
        $text = preg_replace(
            '/(?<!\*)\*([^*\n]+)\*(?!\*)/',
            '<em>$1</em>',
            $text
        );

        // Convert line breaks.
        return nl2br($text, false);
    }
}


// ============================================================================
// OpenRouter API
// ============================================================================

if (!function_exists('call_openrouter')) {

    function call_openrouter($messages)
    {
        global $API_openrouter;

        // --------------------------------------------------------------------
        // Check API key
        // --------------------------------------------------------------------

        if (empty($API_openrouter)) {

            return [
                'success' => false,
                'error' => 'OpenRouter API key is not configured.'
            ];
        }


        // --------------------------------------------------------------------
        // API URL
        // --------------------------------------------------------------------

        $url = 'https://openrouter.ai/api/v1/chat/completions';


        // --------------------------------------------------------------------
        // Model
        // --------------------------------------------------------------------

        $model = 'openrouter/free';


        // --------------------------------------------------------------------
        // Request payload
        // --------------------------------------------------------------------

        $payload = [
            'model' => $model,
            'messages' => $messages
        ];


        // --------------------------------------------------------------------
        // Encode JSON
        // --------------------------------------------------------------------

        $jsonPayload = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($jsonPayload === false) {

            return [
                'success' => false,
                'error' =>
                    'Failed to encode the OpenRouter request: ' .
                    json_last_error_msg()
            ];
        }


        // --------------------------------------------------------------------
        // cURL
        // --------------------------------------------------------------------

        $ch = curl_init($url);

        if ($ch === false) {

            return [
                'success' => false,
                'error' =>
                    'Unable to initialize the OpenRouter connection.'
            ];
        }


        $headers = [
            'Authorization: Bearer ' . $API_openrouter,
            'Content-Type: application/json',
            'HTTP-Referer: https://cfcbazar.42web.io',
            'X-Title: CfCbazar AI Agent'
        ];


        curl_setopt_array(
            $ch,
            [
                CURLOPT_POST => true,

                CURLOPT_POSTFIELDS => $jsonPayload,

                CURLOPT_HTTPHEADER => $headers,

                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_CONNECTTIMEOUT => 15,

                CURLOPT_TIMEOUT => 90,

                CURLOPT_SSL_VERIFYPEER => true,

                CURLOPT_SSL_VERIFYHOST => 2
            ]
        );


        $response = curl_exec($ch);

        $curlError = curl_error($ch);

        $httpCode = (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);


        // --------------------------------------------------------------------
        // cURL error
        // --------------------------------------------------------------------

        if ($response === false) {

            return [
                'success' => false,
                'error' =>
                    'OpenRouter connection failed.' .
                    ($curlError !== ''
                        ? ' cURL error: ' . $curlError
                        : ''),
                'http_code' => $httpCode
            ];
        }


        // --------------------------------------------------------------------
        // Decode response
        // --------------------------------------------------------------------

        $decoded = json_decode(
            $response,
            true
        );


        if (!is_array($decoded)) {

            return [
                'success' => false,
                'error' =>
                    'OpenRouter returned invalid JSON.' .
                    ' HTTP status: ' . $httpCode .
                    ' Response: ' . $response,
                'http_code' => $httpCode
            ];
        }


        // --------------------------------------------------------------------
        // HTTP/API error
        // --------------------------------------------------------------------

        if ($httpCode < 200 || $httpCode >= 300) {

            $errorMessage =
                $decoded['error']['message']
                ?? 'Unknown OpenRouter API error.';

            $errorCode =
                $decoded['error']['code']
                ?? '';

            $provider =
                $decoded['error']['metadata']['provider_name']
                ?? '';

            $rawProviderError =
                $decoded['error']['metadata']['raw']
                ?? '';


            $fullError =
                'OpenRouter returned HTTP ' .
                $httpCode .
                '. ' .
                $errorMessage;


            if ($errorCode !== '') {
                $fullError .=
                    ' Error code: ' . $errorCode;
            }


            if ($provider !== '') {
                $fullError .=
                    ' Provider: ' . $provider;
            }


            if ($rawProviderError !== '') {
                $fullError .=
                    ' Provider details: ' .
                    $rawProviderError;
            }


            return [
                'success' => false,
                'error' => $fullError,
                'http_code' => $httpCode,
                'response' => $decoded
            ];
        }


        // --------------------------------------------------------------------
        // Successful response
        // --------------------------------------------------------------------

        return [
            'success' => true,
            'data' => $decoded,
            'http_code' => $httpCode
        ];
    }
}


// ============================================================================
// Initialize chat history
// ============================================================================

if (
    !isset($_SESSION['messages']) ||
    !is_array($_SESSION['messages'])
) {

    $_SESSION['messages'] = [
        [
            'role' => 'system',
            'content' =>
                'You are the CfCbazar AI Agent. ' .
                'Respond clearly, accurately and helpfully. ' .
                'Keep answers practical and concise unless the user asks for detail.'
        ]
    ];
}


// ============================================================================
// Clean malformed old history
//
// This is useful if an earlier version of the AI agent stored tool messages.
// The current version does NOT use tools.
// ============================================================================

$cleanMessages = [];

foreach ($_SESSION['messages'] as $message) {

    if (!is_array($message)) {
        continue;
    }

    $role = $message['role'] ?? '';

    if (
        $role !== 'system' &&
        $role !== 'user' &&
        $role !== 'assistant'
    ) {
        continue;
    }

    if (!isset($message['content'])) {
        continue;
    }

    $cleanMessages[] = [
        'role' => $role,
        'content' => (string) $message['content']
    ];
}


// Ensure there is a system message.
if (
    empty($cleanMessages) ||
    ($cleanMessages[0]['role'] ?? '') !== 'system'
) {

    array_unshift(
        $cleanMessages,
        [
            'role' => 'system',
            'content' =>
                'You are the CfCbazar AI Agent. ' .
                'Respond clearly, accurately and helpfully. ' .
                'Keep answers practical and concise unless the user asks for detail.'
        ]
    );
}

$_SESSION['messages'] = $cleanMessages;


// ============================================================================
// Limit conversation history
//
// Keep the system message plus the latest 20 user/assistant messages.
// ============================================================================

$maxHistoryMessages = 20;

if (count($_SESSION['messages']) > ($maxHistoryMessages + 1)) {

    $systemMessage = $_SESSION['messages'][0];

    $recentMessages = array_slice(
        $_SESSION['messages'],
        -$maxHistoryMessages
    );

    $_SESSION['messages'] = array_merge(
        [$systemMessage],
        $recentMessages
    );
}


// ============================================================================
// AJAX HANDLER
//
// IMPORTANT:
// This must execute BEFORE include_header(), include_menu(), etc.
// Otherwise those functions may send HTML before the JSON response.
// ============================================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['ajax'])
) {

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate'
    );


    // ------------------------------------------------------------------------
    // CSRF validation
    // ------------------------------------------------------------------------

    $postedCsrf = (string) (
        $_POST['csrf'] ?? ''
    );


    if (
        $csrf !== '' &&
        (
            $postedCsrf === '' ||
            !hash_equals(
                $csrf,
                $postedCsrf
            )
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
    // Message
    // ------------------------------------------------------------------------

    $user = trim(
        (string) (
            $_POST['message'] ?? ''
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
    // Message length
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
    // Add user message
    // ------------------------------------------------------------------------

    $_SESSION['messages'][] = [
        'role' => 'user',
        'content' => $user
    ];


    // ------------------------------------------------------------------------
    // Call OpenRouter
    // ------------------------------------------------------------------------

    $result = call_openrouter(
        $_SESSION['messages']
    );


    // ------------------------------------------------------------------------
    // API failure
    // ------------------------------------------------------------------------

    if (
        empty($result['success'])
    ) {

        // Remove user message because it was not successfully processed.
        array_pop(
            $_SESSION['messages']
        );


        echo json_encode(
            [
                'success' => false,
                'error' =>
                    $result['error']
                    ?? 'OpenRouter request failed.',
                'http_code' =>
                    $result['http_code']
                    ?? 0
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }


    // ------------------------------------------------------------------------
    // Extract API response
    // ------------------------------------------------------------------------

    $responseData =
        $result['data']
        ?? [];


    $aiMessage =
        $responseData['choices'][0]['message']
        ?? null;


    if (!is_array($aiMessage)) {

        array_pop(
            $_SESSION['messages']
        );


        echo json_encode(
            [
                'success' => false,
                'error' =>
                    'OpenRouter returned an unexpected response structure.',
                'details' =>
                    json_encode(
                        $responseData,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    )
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }


    // ------------------------------------------------------------------------
    // Extract content
    // ------------------------------------------------------------------------

    $aiReply =
        $aiMessage['content']
        ?? '';


    // Some providers may return null/empty content.
    if (
        !is_string($aiReply) ||
        trim($aiReply) === ''
    ) {

        $finishReason =
            $responseData['choices'][0]['finish_reason']
            ?? 'unknown';


        echo json_encode(
            [
                'success' => false,
                'error' =>
                    'The AI returned an empty response.',
                'finish_reason' =>
                    $finishReason,
                'model' =>
                    $responseData['model']
                    ?? 'unknown',
                'response' =>
                    $responseData
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }


    // ------------------------------------------------------------------------
    // Store assistant response
    // ------------------------------------------------------------------------

    $_SESSION['messages'][] = [
        'role' => 'assistant',
        'content' => $aiReply
    ];


    // ------------------------------------------------------------------------
    // Re-limit history
    // ------------------------------------------------------------------------

    if (
        count($_SESSION['messages']) >
        ($maxHistoryMessages + 1)
    ) {

        $systemMessage =
            $_SESSION['messages'][0];

        $recentMessages =
            array_slice(
                $_SESSION['messages'],
                -$maxHistoryMessages
            );

        $_SESSION['messages'] =
            array_merge(
                [$systemMessage],
                $recentMessages
            );
    }


    // ------------------------------------------------------------------------
    // Return successful JSON response
    // ------------------------------------------------------------------------

    echo json_encode(
        [
            'success' => true,
            'reply' => $aiReply,
            'model' =>
                $responseData['model']
                ?? 'openrouter/free'
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


// ============================================================================
// Layout
// ============================================================================

$title = 'CfCbazar AI Agent';

include_header($title);
include_menu();
showAdvertPopup();
render_top_userbar();

?>

<link
    rel="stylesheet"
    href="/css/styles.css"
>

<style>

/* ==========================================================================
   CfCbazar AI Agent
   ========================================================================== */

.ai-chat-box {
    max-height: 500px;
    overflow-y: auto;
    padding: 5px;
}

.ai-message {
    margin-bottom: 12px;
    overflow-wrap: anywhere;
}

.ai-user-message {
    border-left: 4px solid var(--primary);
}

.ai-assistant-message {
    border-left: 4px solid var(--secondary);
}

.ai-input-row {
    display: flex;
    gap: 12px;
    align-items: stretch;
}

.ai-input-row input {
    flex: 1 1 auto;
    width: auto !important;
    min-width: 0;
}

.ai-input-row button {
    flex: 0 0 110px;
    width: 110px !important;
}

.ai-loading {
    display: none;

    margin: 15px auto;

    width: 40px;
    height: 40px;

    border: 5px solid #ccc;
    border-top-color: var(--primary);

    border-radius: 50%;

    animation: ai-spin .8s linear infinite;
}

@keyframes ai-spin {
    to {
        transform: rotate(360deg);
    }
}

.ai-error {
    display: none;
    margin-top: 15px;
    overflow-wrap: anywhere;
}

.ai-model {
    margin-top: 10px;
    font-size: .85rem;
    opacity: .75;
}

.ai-chat-box code {
    overflow-wrap: anywhere;
}

@media (max-width: 600px) {

    .ai-input-row {
        flex-direction: column;
    }

    .ai-input-row input,
    .ai-input-row button {
        width: 100% !important;
        flex-basis: auto;
    }

}

</style>


<!-- ==========================================================================
     MAIN
     ========================================================================== -->

<div class="container">

    <div class="card">

        <h1>CfCbazar AI Agent</h1>

        <div class="warning">

            This AI agent uses the
            <strong>OpenRouter free model router</strong>.

            Responses may vary depending on
            available free models, provider availability
            and API rate limits.

            Chat history is maintained only in the
            current PHP session.

        </div>


        <!-- ================================================================
             CHAT
             ================================================================ -->

        <div
            class="profile-box ai-chat-box"
            id="chatBox"
            aria-live="polite"
        >

            <?php foreach ($_SESSION['messages'] as $msg): ?>

                <?php
                $role =
                    $msg['role']
                    ?? '';

                $content =
                    $msg['content']
                    ?? '';
                ?>


                <?php if ($role === 'user'): ?>

                    <div class="info-card ai-message ai-user-message">

                        <strong>You:</strong><br>

                        <?= ai_markdown($content) ?>

                    </div>


                <?php elseif ($role === 'assistant'): ?>

                    <div class="card ai-message ai-assistant-message">

                        <strong>AI:</strong><br>

                        <?= ai_markdown($content) ?>

                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

        </div>


        <!-- ================================================================
             LOADING
             ================================================================ -->

        <div
            class="ai-loading"
            id="loadingSpinner"
            aria-label="AI is thinking"
            role="status"
        ></div>


        <!-- ================================================================
             ERROR
             ================================================================ -->

        <div
            class="error ai-error"
            id="aiError"
            role="alert"
        ></div>


        <!-- ================================================================
             FORM
             ================================================================ -->

        <form
            id="aiForm"
            class="mt-20"
        >

            <label for="message">
                Type your message:
            </label>

            <div class="ai-input-row">

                <input
                    type="text"
                    name="message"
                    id="message"
                    maxlength="10000"
                    autocomplete="off"
                    required
                    placeholder="Ask the CfCbazar AI Agent..."
                >

                <button
                    type="submit"
                    id="sendButton"
                >
                    Send
                </button>

            </div>


            <?php if ($csrf !== ''): ?>

                <input
                    type="hidden"
                    name="csrf"
                    id="csrf"
                    value="<?= ai_h($csrf) ?>"
                >

            <?php endif; ?>

        </form>


        <div class="ai-model">
            Model router: <strong>openrouter/free</strong>
        </div>

    </div>


    <!-- ================================================================
         SOURCE CODE
         ================================================================ -->

    <?php

    if (function_exists('cfc_footer')) {

        cfc_footer(
            'https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/ai',
            'Source Code'
        );
    }

    ?>

</div>


<script>
(function () {

    'use strict';


    // ========================================================================
    // DOM
    // ========================================================================

    const form =
        document.getElementById('aiForm');

    const messageInput =
        document.getElementById('message');

    const spinner =
        document.getElementById('loadingSpinner');

    const errorBox =
        document.getElementById('aiError');

    const chatBox =
        document.getElementById('chatBox');

    const sendButton =
        document.getElementById('sendButton');

    const csrfInput =
        document.getElementById('csrf');


    // ========================================================================
    // Scroll chat
    // ========================================================================

    function scrollChat() {

        if (chatBox) {

            chatBox.scrollTop =
                chatBox.scrollHeight;
        }
    }


    // ========================================================================
    // Show error
    // ========================================================================

    function showError(message) {

        if (!errorBox) {
            return;
        }

        errorBox.textContent =
            message;

        errorBox.style.display =
            'block';
    }


    // ========================================================================
    // Hide error
    // ========================================================================

    function hideError() {

        if (!errorBox) {
            return;
        }

        errorBox.textContent =
            '';

        errorBox.style.display =
            'none';
    }


    // ========================================================================
    // Submit
    // ========================================================================

    if (form) {

        form.addEventListener(
            'submit',
            async function (event) {

                event.preventDefault();

                hideError();


                const message =
                    messageInput.value.trim();


                if (!message) {
                    return;
                }


                spinner.style.display =
                    'block';

                sendButton.disabled =
                    true;


                const formData =
                    new FormData();


                formData.append(
                    'ajax',
                    '1'
                );


                formData.append(
                    'message',
                    message
                );


                if (csrfInput) {

                    formData.append(
                        'csrf',
                        csrfInput.value
                    );
                }


                try {

                    const response =
                        await fetch(
                            window.location.href,
                            {
                                method: 'POST',

                                body: formData,

                                credentials:
                                    'same-origin',

                                headers: {
                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                    'Accept':
                                        'application/json'
                                }
                            }
                        );


                    // --------------------------------------------------------
                    // HTTP error
                    // --------------------------------------------------------

                    if (!response.ok) {

                        const text =
                            await response.text();

                        throw new Error(
                            'Server returned HTTP ' +
                            response.status +
                            '. ' +
                            text
                        );
                    }


                    // --------------------------------------------------------
                    // JSON response
                    // --------------------------------------------------------

                    let data;

                    try {

                        data =
                            await response.json();

                    } catch (jsonError) {

                        throw new Error(
                            'The server returned an invalid JSON response.'
                        );
                    }


                    // --------------------------------------------------------
                    // API/application error
                    // --------------------------------------------------------

                    if (!data.success) {

                        let error =
                            data.error ||
                            'The AI request failed.';


                        if (
                            data.finish_reason
                        ) {

                            error +=
                                ' Finish reason: ' +
                                data.finish_reason;
                        }


                        throw new Error(error);
                    }


                    // --------------------------------------------------------
                    // Success
                    // --------------------------------------------------------

                    messageInput.value =
                        '';


                    // Reload so PHP renders the new
                    // message from the session.

                    window.location.reload();

                } catch (error) {

                    console.error(
                        'CfCbazar AI error:',
                        error
                    );


                    showError(
                        error.message ||
                        'An unexpected error occurred.'
                    );


                    spinner.style.display =
                        'none';

                    sendButton.disabled =
                        false;
                }

            }
        );
    }


    // ========================================================================
    // Initial scroll
    // ========================================================================

    window.addEventListener(
        'load',
        function () {

            scrollChat();

            if (messageInput) {
                messageInput.focus();
            }

        }
    );

})();
</script>


<?php

// ============================================================================
// Footer
// ============================================================================

include_footer();

?> 