<?php

declare(strict_types=1);

// Temporary diagnostics — remove after fix
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}


require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/SpellCorrector.php';
require_once __DIR__ . '/core/MemoryStore.php';
require_once __DIR__ . '/core/ConversationStore.php';

// Per-browser conversation session (cookie)
$conversationSessionId = ConversationStore::resolveSessionId();

$response = '';
$prompt = '';
$promptOriginal = '';
$status = [];
$startTime = microtime(true);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $promptOriginal = trim((string)($_POST['prompt'] ?? ''));
    $prompt = $promptOriginal;

    if ($prompt === '') {
        $error = 'Please enter a question or message.';
    } else {
        try {
            // Light auto-correct using lexicon + known memory topic words
            $memory = new MemoryStore(__DIR__ . '/memory/memory.json');
            $extraWords = $memory->listTopics();
            $prompt = SpellCorrector::correct($prompt, $extraWords);

            $router = new Router();
            $response = trim($router->handle($prompt));

            if ($response === '') {
                $response = 'I could not generate a response. Please try again.';
            }

            $status = [
                'last_topic'       => $router->lastTopic ?? '',
                'last_entities'    => $router->lastEntities ?? [],
                'last_intent'      => $router->lastIntent ?? '',
                'question_type'    => $router->lastQuestionType ?? '',
                'response_time'    => microtime(true) - $startTime,
                'prompt_original'  => $promptOriginal,
                'prompt_corrected' => $prompt,
            ];
        } catch (Throwable $e) {
            // Do not expose internal PHP/API errors to the user.
            $error = 'The AI system could not complete the request. Please try again.';
        }
    }
}

$rawResponseJson = json_encode(
    $response,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($rawResponseJson === false) {
    $rawResponseJson = '""';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CfCbazar AI System - Beta GUI</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
    * { box-sizing: border-box; }

    body {
        background: #121212;
        color: #e0e0e0;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
    }

    .container {
        width: 95%;
        max-width: 900px;
        margin: 40px auto;
        background: #1e1e1e;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px #000;
    }

    h1 {
        text-align: center;
        color: #4fc3f7;
        margin-bottom: 20px;
    }

    textarea {
        width: 100%;
        min-height: 140px;
        background: #0f0f0f;
        color: #e0e0e0;
        border: 1px solid #333;
        border-radius: 6px;
        padding: 10px;
        font-size: 16px;
        resize: vertical;
    }

    button {
        background: #4fc3f7;
        color: #000;
        padding: 10px 16px;
        border: none;
        border-radius: 6px;
        font-size: 15px;
        cursor: pointer;
        font-weight: bold;
        transition: background 0.2s, opacity 0.2s;
    }

    button:hover { background: #81d4fa; }

    button:disabled {
        background: #333;
        color: #888;
        cursor: not-allowed;
    }

    #submit-btn {
        width: 100%;
        margin-top: 10px;
    }

    .loading-box {
        display: none;
        margin-top: 20px;
        background: #0f0f0f;
        padding: 16px 20px;
        border-radius: 6px;
        border: 1px solid #0284c7;
        color: #38bdf8;
        text-align: center;
        font-weight: bold;
        font-size: 16px;
    }

    .spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 3px solid rgba(56, 189, 248, 0.3);
        border-radius: 50%;
        border-top-color: #38bdf8;
        animation: spin 1s ease-in-out infinite;
        vertical-align: middle;
        margin-right: 10px;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    .error-box {
        margin-top: 20px;
        background: #241313;
        color: #ffb4b4;
        padding: 14px 16px;
        border: 1px solid #6b2525;
        border-radius: 6px;
    }

    .response-toolbar {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 20px;
    }

    .response-toolbar button,
    .code-copy-btn {
        font-size: 13px;
        padding: 7px 11px;
    }

    .response-box {
        margin-top: 8px;
        background: #0f0f0f;
        padding: 20px;
        border-radius: 6px;
        border: 1px solid #333;
        font-size: 15px;
        line-height: 1.6;
        overflow-wrap: anywhere;
    }

    .response-box h1,
    .response-box h2,
    .response-box h3,
    .response-box h4 {
        color: #4fc3f7;
        margin-top: 20px;
        margin-bottom: 10px;
        border-bottom: 1px solid #2a2a2a;
        padding-bottom: 4px;
        text-align: left;
    }

    .response-box h1:first-child,
    .response-box h2:first-child,
    .response-box h3:first-child { margin-top: 0; }

    .response-box p { margin: 0 0 12px 0; }

    .response-box code {
        background: #1a1a1a;
        color: #ffb74d;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: 'Courier New', Courier, monospace;
        font-size: 14px;
    }

    .code-wrapper {
        position: relative;
        margin: 15px 0;
    }

    .response-box pre {
        background: #050505;
        padding: 42px 14px 14px;
        border-radius: 6px;
        border: 1px solid #282828;
        overflow-x: auto;
        margin: 0;
        white-space: pre;
    }

    .response-box pre code {
        display: block;
        background: transparent;
        color: #81d4fa;
        padding: 0;
        white-space: pre;
        tab-size: 4;
    }

    .code-copy-btn {
        position: absolute;
        top: 7px;
        right: 7px;
        z-index: 2;
    }

    .response-box ul,
    .response-box ol {
        padding-left: 24px;
        margin-bottom: 12px;
    }

    .response-box li { margin-bottom: 6px; }

    .response-box blockquote {
        border-left: 4px solid #4fc3f7;
        margin: 12px 0;
        padding-left: 15px;
        color: #b0bec5;
        font-style: italic;
    }

    .response-box table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }

    .response-box th,
    .response-box td {
        border: 1px solid #333;
        padding: 8px 12px;
        text-align: left;
    }

    .response-box th {
        background: #1a1a1a;
        color: #4fc3f7;
    }

    .response-box hr {
        border: 0;
        height: 1px;
        background: #333;
        margin: 20px 0;
    }

    .status-panel {
        margin-top: 30px;
        background: #1e1e1e;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #333;
    }

    .status-panel h2 {
        color: #4fc3f7;
        margin-bottom: 15px;
        margin-top: 0;
    }

    .status-item {
        margin-bottom: 8px;
        font-size: 15px;
    }

    .copy-status {
        min-height: 18px;
        margin-top: 6px;
        color: #81d4fa;
        font-size: 12px;
        text-align: right;
    }
</style>
</head>
<body>

<div class="container">
    <h1>CfCbazar AI System - Beta Test GUI</h1>

    <form id="ai-form" method="POST">
    <input type="hidden" name="conversation_session" value="<?php echo htmlspecialchars($conversationSessionId ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <textarea id="prompt-input" name="prompt" placeholder="Type your prompt here..." required><?php echo htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <button type="submit" id="submit-btn">Run AI</button>
    </form>

    <div id="loading-box" class="loading-box">
        <span class="spinner"></span> Working... Generating AI response...
    </div>

    <?php if ($error !== ''): ?>
        <div class="error-box" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($response !== ''): ?>
        <div class="response-toolbar">
            <button type="button" id="copy-response-btn">Copy response</button>
        </div>

        <div id="response-box" class="response-box"></div>
        <div id="copy-status" class="copy-status" aria-live="polite"></div>

        <script>
        (function () {
            const rawResponse = <?php echo $rawResponseJson; ?>;
            const responseBox = document.getElementById('response-box');
            const copyResponseButton = document.getElementById('copy-response-btn');
            const copyStatus = document.getElementById('copy-status');

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function copyText(text, statusElement) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function () {
                        statusElement.textContent = 'Copied.';
                    }).catch(function () {
                        fallbackCopy(text, statusElement);
                    });
                    return;
                }

                fallbackCopy(text, statusElement);
            }

            function fallbackCopy(text, statusElement) {
                const area = document.createElement('textarea');
                area.value = text;
                area.style.position = 'fixed';
                area.style.left = '-9999px';
                area.style.top = '0';
                document.body.appendChild(area);
                area.focus();
                area.select();

                try {
                    const successful = document.execCommand('copy');
                    statusElement.textContent = successful ? 'Copied.' : 'Copy failed.';
                } catch (error) {
                    statusElement.textContent = 'Copy failed.';
                }

                document.body.removeChild(area);
            }

            function addCodeCopyButtons() {
                responseBox.querySelectorAll('pre').forEach(function (pre) {
                    if (pre.parentElement && pre.parentElement.classList.contains('code-wrapper')) {
                        return;
                    }

                    const wrapper = document.createElement('div');
                    wrapper.className = 'code-wrapper';
                    pre.parentNode.insertBefore(wrapper, pre);
                    wrapper.appendChild(pre);

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'code-copy-btn';
                    button.textContent = 'Copy code';

                    button.addEventListener('click', function () {
                        const code = pre.querySelector('code');
                        copyText(code ? code.textContent : pre.textContent, button);
                        button.textContent = 'Copied';
                        window.setTimeout(function () {
                            button.textContent = 'Copy code';
                        }, 1500);
                    });

                    wrapper.appendChild(button);
                });
            }

            if (typeof marked !== 'undefined') {
                const renderer = new marked.Renderer();

                // Raw HTML returned by the model must be displayed as text.
                // Fenced ```html blocks are still rendered normally by marked.
                renderer.html = function (token) {
                    const rawHtml = typeof token === 'string'
                        ? token
                        : (token && (token.raw || token.text)) || '';
                    return '<pre><code>' + escapeHtml(rawHtml) + '</code></pre>';
                };

                marked.setOptions({
                    breaks: true,
                    gfm: true,
                    renderer: renderer
                });

                responseBox.innerHTML = marked.parse(rawResponse);
                addCodeCopyButtons();
            } else {
                responseBox.textContent = rawResponse;
            }

            copyResponseButton.addEventListener('click', function () {
                copyText(rawResponse, copyStatus);
                copyResponseButton.textContent = 'Copied';
                window.setTimeout(function () {
                    copyResponseButton.textContent = 'Copy response';
                }, 1500);
            });
        }());
        </script>
    <?php endif; ?>

    <?php if (!empty($status)): ?>
        <div class="status-panel">
            <h2>System Status</h2>
            <div class="status-item"><strong>Last Topic:</strong> <?php echo htmlspecialchars((string)($status['last_topic'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="status-item"><strong>Last Entities:</strong> <?php echo htmlspecialchars(implode(', ', (array)($status['last_entities'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="status-item"><strong>Intent:</strong> <?php echo htmlspecialchars((string)($status['last_intent'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="status-item"><strong>Question Type:</strong> <?php echo htmlspecialchars((string)($status['question_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="status-item"><strong>Response Time:</strong> <?php echo number_format((float)$status['response_time'], 4); ?> sec</div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const form = document.getElementById('ai-form');
    const btn = document.getElementById('submit-btn');
    const loadingBox = document.getElementById('loading-box');
    const existingResponse = document.getElementById('response-box');

    form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.innerText = 'Working...';
        loadingBox.style.display = 'block';

        if (existingResponse) {
            existingResponse.style.opacity = '0.3';
        }
    });
}());
</script>

</body>
</html>
