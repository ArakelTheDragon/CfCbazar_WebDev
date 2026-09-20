<?php
/**
 * ============================================================================
 * CfCbazar AI Agent — API + Graphical User Interface
 * File: /diy/ai/index.php
 *
 * PromptUnderstandingSkill is ALWAYS executed first.
 * The same file provides:
 *   - JSON API for POST requests
 *   - Browser-based chat GUI for normal GET requests
 *
 * reusable.php already loads config.php.
 * ============================================================================
 */

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!interface_exists('AISkillInterface')) {
    interface AISkillInterface
    {
        public function getKeywords(): array;
        public function getPriority(): int;
        public function execute(string $input, array &$memory);
    }
}

$reusableFile = __DIR__ . '/../../includes/reusable.php';
$skillsDir = __DIR__ . '/../../includes/skills';
$memoryFilePath = __DIR__ . '/local_memory.json';
$memoryTopicsDir = __DIR__ . '/memory_topics/';

function ai_json_response(array $response, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    exit;
}

function ai_save_memory(string $path, array $memory): bool
{
    $json = json_encode(
        $memory,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    return $json !== false
        && file_put_contents($path, $json, LOCK_EX) !== false;
}

function ai_extract_output(mixed $result): string
{
    if (is_string($result) || is_numeric($result)) {
        return trim((string)$result);
    }

    if (!is_array($result)) {
        return '';
    }

    foreach (['result', 'output', 'response', 'answer', 'content', 'message'] as $field) {
        if (array_key_exists($field, $result) && is_scalar($result[$field])) {
            return trim((string)$result[$field]);
        }
    }

    $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false ? $json : '';
}

function ai_load_memory(string $path): array
{
    $default = [
        'knowledge' => [],
        'history' => []
    ];

    if (!is_file($path)) {
        return $default;
    }

    $json = file_get_contents($path);

    if ($json === false || trim($json) === '') {
        return $default;
    }

    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        @copy($path, $path . '.corrupt.' . date('Ymd_His'));
        return $default;
    }

    $decoded['knowledge'] = isset($decoded['knowledge']) && is_array($decoded['knowledge'])
        ? $decoded['knowledge']
        : [];

    $decoded['history'] = isset($decoded['history']) && is_array($decoded['history'])
        ? $decoded['history']
        : [];

    return $decoded;
}

function ai_run_agent(
    string $prompt,
    string $skillsDir,
    string $memoryFilePath
): array {
    if (!class_exists('PromptUnderstandingSkill')) {
        throw new RuntimeException('PromptUnderstandingSkill is not available.');
    }

    $memory = ai_load_memory($memoryFilePath);

    $understandingSkill = new PromptUnderstandingSkill();

    /*
     * Mandatory first stage.
     */
    $intentResult = $understandingSkill->execute($prompt, $memory);

    $intent = is_array($intentResult)
        ? strtolower(trim((string)($intentResult['intent'] ?? 'general')))
        : 'general';

    $targetSkill = is_array($intentResult)
        ? trim((string)($intentResult['target_skill'] ?? $intentResult['skill'] ?? ''))
        : '';

    $confidence = is_array($intentResult)
        ? (float)($intentResult['confidence'] ?? 0.50)
        : 0.50;

    $registry = [];

    foreach ((glob($skillsDir . '/*.php') ?: []) as $skillFile) {
        if (is_file($skillFile)) {
            require_once $skillFile;
        }
    }

    foreach (get_declared_classes() as $className) {
        if ($className === 'PromptUnderstandingSkill' || !class_exists($className)) {
            continue;
        }

        try {
            $reflection = new ReflectionClass($className);

            if (
                !$reflection->implementsInterface('AISkillInterface')
                || $reflection->isAbstract()
                || $reflection->isInterface()
            ) {
                continue;
            }

            $registry[$className] = new $className();
        } catch (Throwable) {
            continue;
        }
    }

    $selectedSkill = null;

    if ($targetSkill !== '' && isset($registry[$targetSkill])) {
        $selectedSkill = $registry[$targetSkill];
    }

    if (
        $selectedSkill === null
        && isset($registry['KnowledgeSkill'])
        && in_array($intent, ['general', 'knowledge'], true)
    ) {
        $selectedSkill = $registry['KnowledgeSkill'];
        $targetSkill = 'KnowledgeSkill';
    }

    $output = '';
    $source = 'local';

    if ($selectedSkill !== null) {
        $output = ai_extract_output(
            $selectedSkill->execute($prompt, $memory)
        );

        if ($output !== '') {
            $source = 'skill_' . strtolower($targetSkill);
        }
    }

    /*
     * OpenRouter fallback.
     */
    if ($output === '') {
        $localContext = '';

        if (class_exists('KnowledgeSkill')) {
            try {
                $knowledgeSkill = new KnowledgeSkill();
                $localContext = ai_extract_output(
                    $knowledgeSkill->execute($prompt, $memory)
                );
            } catch (Throwable) {
                $localContext = '';
            }
        }

        $systemMessage =
            "You are the CfCbazar AI Agent. "
            . "Answer clearly, accurately, and directly. "
            . "Do not claim to have performed actions you did not perform.\n"
            . "Detected intent: {$intent}\n"
            . "Intent confidence: " . number_format(max(0, min(1, $confidence)), 2) . "\n";

        if ($localContext !== '') {
            $systemMessage .= "Relevant local context:\n{$localContext}\n";
        }

        $messages = [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $prompt]
        ];

        if (function_exists('call_openrouter')) {
            $apiResult = call_openrouter($messages);

            if (is_array($apiResult) && !empty($apiResult['success'])) {
                $output = trim((string)($apiResult['data']['choices'][0]['message']['content'] ?? ''));
                $source = 'openrouter';
            } else {
                $error = is_array($apiResult)
                    ? (string)($apiResult['error'] ?? 'Unknown OpenRouter error')
                    : 'Unknown OpenRouter response';

                $output = $localContext !== ''
                    ? $localContext
                    : 'OpenRouter error: ' . $error;

                $source = 'local_fallback';
            }
        } else {
            $output = $localContext !== ''
                ? $localContext
                : 'No remote AI provider is available. Use: remember [topic] is [information].';

            $source = 'local_fallback';
        }
    }

    $memory['history'][] = [
        'user' => $prompt,
        'bot' => $output,
        'intent' => $intent,
        'skill' => $targetSkill !== '' ? $targetSkill : null,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if (count($memory['history']) > 100) {
        $memory['history'] = array_slice($memory['history'], -100);
    }

    $saved = ai_save_memory($memoryFilePath, $memory);

    return [
        'success' => true,
        'prompt' => $prompt,
        'source' => $source,
        'intent' => $intent,
        'target_skill' => $targetSkill !== '' ? $targetSkill : null,
        'confidence' => $confidence,
        'output' => $output,
        'memory_updated' => $saved
    ];
}

/*
 * Load reusable.php and all skills before handling API requests.
 */
if (!is_file($reusableFile)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ai_json_response([
            'success' => false,
            'error' => 'reusable.php was not found.',
            'path' => $reusableFile
        ], 500);
    }

    http_response_code(500);
    exit('CfCbazar AI configuration error: reusable.php was not found.');
}

require_once $reusableFile;

if (is_dir($skillsDir)) {
    foreach ((glob($skillsDir . '/*.php') ?: []) as $skillFile) {
        if (is_file($skillFile)) {
            require_once $skillFile;
        }
    }
}

if (!is_dir($memoryTopicsDir)) {
    @mkdir($memoryTopicsDir, 0755, true);
}

/*
 * JSON API mode.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $requestData = json_decode((string)$rawInput, true);

    if (!is_array($requestData)) {
        $requestData = $_POST;
    }

    $prompt = trim((string)($requestData['prompt'] ?? ''));

    if ($prompt === '') {
        ai_json_response([
            'success' => false,
            'error' => 'Empty prompt provided.'
        ], 400);
    }

    try {
        ai_json_response(
            ai_run_agent($prompt, $skillsDir, $memoryFilePath)
        );
    } catch (Throwable $exception) {
        ai_json_response([
            'success' => false,
            'error' => $exception->getMessage(),
            'debug' => [
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]
        ], 500);
    }
}

/*
 * GUI mode.
 */
$pageTitle = 'CfCbazar AI Agent';
if (function_exists('include_header')) {
    include_header($pageTitle);
}
if (function_exists('include_menu')) {
    include_menu();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        .cfcbazar-ai {
            width: min(100% - 24px, 980px);
            margin: 24px auto;
            font-family: Arial, sans-serif;
        }

        .ai-panel {
            border: 1px solid #d9dfe7;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 8px 28px rgba(0,0,0,.08);
        }

        .ai-header {
            padding: 22px;
            background: linear-gradient(135deg, #202b3c, #405875);
            color: #fff;
        }

        .ai-header h1 {
            margin: 0 0 8px;
            font-size: 1.55rem;
        }

        .ai-header p {
            margin: 0;
            opacity: .88;
        }

        .ai-status {
            padding: 10px 16px;
            font-size: .85rem;
            background: #f2f5f8;
            color: #4d5967;
            border-bottom: 1px solid #e1e6ec;
        }

        .ai-messages {
            min-height: 340px;
            max-height: 62vh;
            overflow-y: auto;
            padding: 18px;
            background: #f8fafc;
        }

        .ai-message {
            max-width: 88%;
            margin: 0 0 16px;
            padding: 12px 15px;
            border-radius: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .ai-message.user {
            margin-left: auto;
            background: #dcecff;
            color: #17324f;
            border-bottom-right-radius: 4px;
        }

        .ai-message.bot {
            margin-right: auto;
            background: #fff;
            color: #202a35;
            border: 1px solid #e1e6ec;
            border-bottom-left-radius: 4px;
        }

        .ai-meta {
            margin-top: 8px;
            font-size: .75rem;
            opacity: .65;
        }

        .ai-form {
            padding: 16px;
            border-top: 1px solid #e1e6ec;
            background: #fff;
        }

        .ai-form textarea {
            width: 100%;
            min-height: 85px;
            resize: vertical;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #bcc7d4;
            border-radius: 10px;
            font: inherit;
        }

        .ai-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .ai-button {
            border: 0;
            border-radius: 9px;
            padding: 11px 17px;
            cursor: pointer;
            font-weight: 600;
        }

        .ai-button.primary {
            background: #2d6cdf;
            color: #fff;
        }

        .ai-button.secondary {
            background: #e8edf3;
            color: #26384c;
        }

        .ai-button:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .ai-examples {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .ai-example {
            border: 1px solid #ccd6e2;
            background: #f7f9fb;
            border-radius: 20px;
            padding: 7px 10px;
            cursor: pointer;
            font-size: .8rem;
        }

        @media (max-width: 600px) {
            .ai-message {
                max-width: 96%;
            }
        }
    </style>
</head>
<body>
<main class="cfcbazar-ai">
    <section class="ai-panel" aria-label="CfCbazar AI Agent">
        <header class="ai-header">
            <h1>CfCbazar AI Agent</h1>
            <p>Local memory, skill routing, and OpenRouter fallback.</p>
        </header>

        <div class="ai-status" id="ai-status" role="status">
            Ready. Prompt understanding runs first for every message.
        </div>

        <div class="ai-messages" id="ai-messages" aria-live="polite">
            <div class="ai-message bot">
                <strong>CfCbazar AI:</strong>

                Ask a question, request a calculation, diagnose a problem, summarize text, or teach me using:
                remember [topic] is [information]
            </div>
        </div>

        <form class="ai-form" id="ai-form">
            <label for="ai-prompt"><strong>Your message</strong></label>
            <textarea
                id="ai-prompt"
                name="prompt"
                placeholder="Type your message..."
                required
                maxlength="12000"
            ></textarea>

            <div class="ai-actions">
                <button class="ai-button primary" id="ai-send" type="submit">
                    Send
                </button>
                <button class="ai-button secondary" id="ai-clear" type="button">
                    Clear chat
                </button>
            </div>

            <div class="ai-examples" aria-label="Example prompts">
                <button class="ai-example" type="button" data-prompt="Calculate 8000 / 255">Calculate</button>
                <button class="ai-example" type="button" data-prompt="Check the system status">Diagnostics</button>
                <button class="ai-example" type="button" data-prompt="Summarize the following text:">Summarize</button>
                <button class="ai-example" type="button" data-prompt="What do you know about CfCbazar?">Knowledge</button>
            </div>
        </form>
    </section>
</main>

<script>
(function () {
    'use strict';

    const form = document.getElementById('ai-form');
    const promptInput = document.getElementById('ai-prompt');
    const sendButton = document.getElementById('ai-send');
    const clearButton = document.getElementById('ai-clear');
    const messages = document.getElementById('ai-messages');
    const status = document.getElementById('ai-status');

    function addMessage(type, text, meta) {
        const wrapper = document.createElement('div');
        wrapper.className = 'ai-message ' + type;

        const content = document.createElement('div');
        content.textContent = text;
        wrapper.appendChild(content);

        if (meta) {
            const metadata = document.createElement('div');
            metadata.className = 'ai-meta';
            metadata.textContent = meta;
            wrapper.appendChild(metadata);
        }

        messages.appendChild(wrapper);
        messages.scrollTop = messages.scrollHeight;
    }

    function setBusy(busy) {
        sendButton.disabled = busy;
        promptInput.disabled = busy;
        status.textContent = busy
            ? 'Processing: understanding prompt and selecting a route...'
            : 'Ready. Prompt understanding runs first for every message.';
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const prompt = promptInput.value.trim();

        if (!prompt) {
            return;
        }

        addMessage('user', prompt);
        promptInput.value = '';
        setBusy(true);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ prompt: prompt })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'The AI request failed.');
            }

            const metadata = [
                data.source ? 'Source: ' + data.source : '',
                data.intent ? 'Intent: ' + data.intent : '',
                data.target_skill ? 'Skill: ' + data.target_skill : ''
            ].filter(Boolean).join(' | ');

            addMessage('bot', data.output || 'No response was returned.', metadata);
            status.textContent = 'Completed. Prompt understanding was executed first.';

        } catch (error) {
            addMessage('bot', 'Error: ' + error.message);
            status.textContent = 'Request failed.';
        } finally {
            setBusy(false);
            promptInput.focus();
        }
    });

    clearButton.addEventListener('click', function () {
        messages.innerHTML = '';
        addMessage('bot', 'Chat cleared. Your saved server-side memory was not deleted.');
        status.textContent = 'Chat cleared.';
    });

    document.querySelectorAll('.ai-example').forEach(function (button) {
        button.addEventListener('click', function () {
            promptInput.value = button.dataset.prompt || '';
            promptInput.focus();
        });
    });

    promptInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
})();
</script>

<?php
if (function_exists('include_footer')) {
    include_footer();
}
?>
