<?php

declare(strict_types=1);

/**
 * CfCbazar AI System — GUI entry.
 * Uses site-wide includes/reusable.php for header/menu when available.
 */

// Show errors early (blank screen diagnosis). Set to 0 after stable.
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// ---------------------------------------------------------------------------
// Site layout (optional). InfinityFree helpers often die()/exit with no output.
// Set $aiUseSiteLayout = true only after confirming header/menu work.
// ---------------------------------------------------------------------------
$aiUseSiteLayout = false; // SAFE DEFAULT — AI GUI always renders
$useSiteLayout = false;
$email = null;
$is_logged_in = false;
$csrf = '';
$siteRoot = '';
$siteBootstrapError = '';

$siteRootCandidates = [
    dirname(__DIR__, 2),
    dirname(__DIR__),
    dirname(__DIR__, 3),
    $_SERVER['DOCUMENT_ROOT'] ?? '',
];
foreach ($siteRootCandidates as $candidate) {
    $candidate = rtrim((string)$candidate, '/');
    if ($candidate !== '' && is_dir($candidate . '/includes') && is_readable($candidate . '/includes/reusable.php')) {
        $siteRoot = $candidate;
        break;
    }
}

if ($aiUseSiteLayout && $siteRoot !== '') {
    try {
        $syncPath = $siteRoot . '/system/sync.php';
        if (is_readable($syncPath)) {
            require_once $syncPath;
        }
        require_once $siteRoot . '/includes/reusable.php';

        // Never call helpers that may exit() the request on shared hosting
        // (require_database_connection / checkSystemFlags / enforce_https).

        if (function_exists('trackVisit')) {
            try { trackVisit('ai-system'); } catch (Throwable $e) { /* ignore */ }
        }
        if (function_exists('session_check')) {
            try { session_check(); } catch (Throwable $e) { /* ignore */ }
        }
        if (function_exists('is_logged_in')) {
            try { $is_logged_in = (bool) is_logged_in($email); } catch (Throwable $e) { $is_logged_in = false; }
        }
        if (function_exists('csrf_token')) {
            try { $csrf = (string) csrf_token(); } catch (Throwable $e) { $csrf = ''; }
        }

        $useSiteLayout = function_exists('include_header');
    } catch (Throwable $e) {
        $useSiteLayout = false;
        $siteBootstrapError = $e->getMessage();
        error_log('AI system site bootstrap failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// AI system
// ---------------------------------------------------------------------------
// AI system
// ---------------------------------------------------------------------------
try {
    require_once __DIR__ . '/core/Router.php';
    require_once __DIR__ . '/core/SpellCorrector.php';
    require_once __DIR__ . '/core/MemoryStore.php';
    require_once __DIR__ . '/core/ConversationStore.php';
    $conversationSessionId = ConversationStore::resolveSessionId();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "AI bootstrap error\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit;
}

$response = '';
$prompt = '';
$promptOriginal = '';
$status = [];
$startTime = microtime(true);
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $promptOriginal = trim((string)($_POST['prompt'] ?? ''));
    $prompt = $promptOriginal;

    if ($prompt === '') {
        $error = 'Please enter a question or message.';
    } else {
        try {
            $memory = new MemoryStore(__DIR__ . '/memory/memory.json');
            $featuresFile = __DIR__ . '/config/features.php';
            $features = is_file($featuresFile) ? (require $featuresFile) : [];
            $spellOn = !isset($features['spell_correct_enabled']) || !empty($features['spell_correct_enabled']);
            if ($spellOn) {
                $extraWords = $memory->listTopics();
                $prompt = SpellCorrector::correct($prompt, $extraWords);
            }

            $router = new Router();
            $response = trim($router->handle($prompt));

            if ($response === '') {
                $response = 'I could not generate a response. Please try again.';
            }

            $topicName = (string)($router->lastTopic ?? '');
            $topicFactCount = 0;
            if ($topicName !== '') {
                try {
                    $topicFactCount = count($memory->getTopicFacts($topicName));
                } catch (Throwable $e) {
                    $topicFactCount = 0;
                }
            }

            $memBytes = memory_get_usage(true);
            $memPeakBytes = memory_get_peak_usage(true);

            $status = [
                'last_topic'       => $topicName,
                'last_entities'    => $router->lastEntities ?? [],
                'last_intent'      => $router->lastIntent ?? '',
                'question_type'    => $router->lastQuestionType ?? '',
                'response_time'    => microtime(true) - $startTime,
                'prompt_original'  => $promptOriginal,
                'prompt_corrected' => $prompt,
                'topic_fact_count' => $topicFactCount,
                'memory_mb'        => round($memBytes / 1048576, 2),
                'memory_peak_mb'   => round($memPeakBytes / 1048576, 2),
            ];
        } catch (Throwable $e) {
            error_log('AI system error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $error = 'The AI system could not complete the request. Please try again.';
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                $error .= ' [' . $e->getMessage() . ']';
            }
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

$pageTitle = 'CfCbazar AI System';

// ---------------------------------------------------------------------------
// Layout: site header/menu when reusable is available
// ---------------------------------------------------------------------------
if ($useSiteLayout && function_exists('include_header')) {
    try {
        include_header($pageTitle);
        if (function_exists('include_menu')) {
            include_menu();
        }
        if (function_exists('showAdvertPopup')) {
            showAdvertPopup();
        }
        if (function_exists('render_top_userbar')) {
            render_top_userbar();
        }
    } catch (Throwable $e) {
        $useSiteLayout = false;
        $siteBootstrapError = $e->getMessage();
        error_log('AI system layout failed: ' . $e->getMessage());
        // Fall through to standalone HTML below
    }
}

if (!$useSiteLayout) {
    // Standalone fallback (no site includes)
    ?><!-- standalone head -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/css/styles.css">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
    .ai-page { width: 95%; max-width: 900px; margin: 40px auto; padding: 20px; }
    .ai-page textarea#prompt-input { width: 100%; min-height: 140px; resize: vertical; }
    .ai-page #submit-btn { width: 100%; margin-top: 10px; }
    .ai-page .loading-box { display: none; margin-top: 20px; padding: 16px 20px; text-align: center; font-weight: bold; }
    .ai-page .spinner {
        display: inline-block; width: 16px; height: 16px;
        border: 3px solid rgba(0,0,0,0.15); border-radius: 50%;
        border-top-color: currentColor; animation: ai-spin 1s ease-in-out infinite;
        vertical-align: middle; margin-right: 10px;
    }
    @keyframes ai-spin { to { transform: rotate(360deg); } }
    .ai-page .error-box { margin-top: 20px; padding: 14px 16px; }
    .ai-page .response-toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; margin-bottom: 8px; }
    .ai-page .response-box { margin-top: 8px; padding: 16px; overflow-x: auto; line-height: 1.55; }
    .ai-page .response-box pre { padding: 12px 14px; overflow-x: auto; margin: 0; white-space: pre; }
    .ai-page .response-box pre code { display: block; background: transparent; padding: 0; white-space: pre; tab-size: 4; }
    .ai-page .status-panel { margin-top: 30px; padding: 20px; }
    .ai-page .status-item { margin-bottom: 8px; }
    .ai-page .copy-status { min-height: 18px; margin-top: 6px; font-size: 12px; text-align: right; }

    /* Force small action buttons against global full-width button styles */
    .ai-page .response-toolbar {
        display: flex !important;
        justify-content: flex-end !important;
        gap: 8px;
        margin-top: 20px;
        margin-bottom: 8px;
    }
    .ai-page .response-toolbar .btn-ai-small,
    .ai-page .response-toolbar button,
    .ai-page button.code-copy-btn,
    .ai-page .code-copy-btn {
        position: static !important;
        display: inline-block !important;
        width: auto !important;
        min-width: 0 !important;
        max-width: none !important;
        flex: 0 0 auto !important;
        align-self: flex-end !important;
        margin: 0 0 6px 0 !important;
        padding: 4px 10px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        line-height: 1.3 !important;
        border-radius: 4px !important;
        cursor: pointer;
    }
    .ai-page .code-wrapper {
        position: relative;
        margin: 15px 0;
        display: block;
    }
    .ai-page .code-wrapper .code-copy-btn {
        float: right;
        clear: both;
    }
    .ai-page .response-box pre {
        clear: both;
        padding: 12px 14px !important;
        overflow-x: auto;
        margin: 0;
        white-space: pre;
    }
</style>
</head>
<body>
<?php
}

// Extra AI-only CSS + marked when using site layout (header may already load styles.css)
if ($useSiteLayout) {
    ?>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
    .ai-page { width: 95%; max-width: 900px; margin: 24px auto; padding: 20px; }
    .ai-page textarea#prompt-input { width: 100%; min-height: 140px; resize: vertical; }
    .ai-page #submit-btn { width: 100%; margin-top: 10px; }
    .ai-page .loading-box { display: none; margin-top: 20px; padding: 16px 20px; text-align: center; font-weight: bold; }
    .ai-page .spinner {
        display: inline-block; width: 16px; height: 16px;
        border: 3px solid rgba(0,0,0,0.15); border-radius: 50%;
        border-top-color: currentColor; animation: ai-spin 1s ease-in-out infinite;
        vertical-align: middle; margin-right: 10px;
    }
    @keyframes ai-spin { to { transform: rotate(360deg); } }
    .ai-page .error-box { margin-top: 20px; padding: 14px 16px; }
    .ai-page .response-toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; margin-bottom: 8px; }
    .ai-page .response-box { margin-top: 8px; padding: 16px; overflow-x: auto; line-height: 1.55; }
    .ai-page .response-box pre { padding: 12px 14px; overflow-x: auto; margin: 0; white-space: pre; }
    .ai-page .response-box pre code { display: block; background: transparent; padding: 0; white-space: pre; tab-size: 4; }
    .ai-page .status-panel { margin-top: 30px; padding: 20px; }
    .ai-page .status-item { margin-bottom: 8px; }
    .ai-page .copy-status { min-height: 18px; margin-top: 6px; font-size: 12px; text-align: right; }

    /* Force small action buttons against global full-width button styles */
    .ai-page .response-toolbar {
        display: flex !important;
        justify-content: flex-end !important;
        gap: 8px;
        margin-top: 20px;
        margin-bottom: 8px;
    }
    .ai-page .response-toolbar .btn-ai-small,
    .ai-page .response-toolbar button,
    .ai-page button.code-copy-btn,
    .ai-page .code-copy-btn {
        position: static !important;
        display: inline-block !important;
        width: auto !important;
        min-width: 0 !important;
        max-width: none !important;
        flex: 0 0 auto !important;
        align-self: flex-end !important;
        margin: 0 0 6px 0 !important;
        padding: 4px 10px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        line-height: 1.3 !important;
        border-radius: 4px !important;
        cursor: pointer;
    }
    .ai-page .code-wrapper {
        position: relative;
        margin: 15px 0;
        display: block;
    }
    .ai-page .code-wrapper .code-copy-btn {
        float: right;
        clear: both;
    }
    .ai-page .response-box pre {
        clear: both;
        padding: 12px 14px !important;
        overflow-x: auto;
        margin: 0;
        white-space: pre;
    }

    <?php
}

?>
<div class="container ai-page">
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
            <button type="button" id="copy-response-btn" class="btn-ai-small" style="display:inline-block;width:auto;max-width:none;padding:4px 10px;font-size:12px;">Copy response</button>
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
                    button.className = 'code-copy-btn btn-ai-small';
                    button.textContent = 'Copy code';
                    button.style.cssText = 'display:inline-block;width:auto;max-width:none;padding:4px 10px;font-size:12px;margin:0 0 8px 0;float:right;';
                    // Place above the code so it never covers the first lines
                    wrapper.insertBefore(button, pre);

                    button.addEventListener('click', function () {
                        const code = pre.querySelector('code');
                        copyText(code ? code.textContent : pre.textContent, button);
                        button.textContent = 'Copied';
                        window.setTimeout(function () {
                            button.textContent = 'Copy code';
                        }, 1500);
                    });

                    /* button already inserted above pre */
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
            <div class="status-item"><strong>Facts on topic:</strong> <?php echo (int)($status['topic_fact_count'] ?? 0); ?></div>
            <div class="status-item"><strong>Last Entities:</strong> <?php echo htmlspecialchars(implode(', ', (array)($status['last_entities'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="status-item"><strong>Intent:</strong> <?php echo htmlspecialchars((string)($status['last_intent'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="status-item"><strong>Question Type:</strong> <?php echo htmlspecialchars((string)($status['question_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="status-item"><strong>Response Time:</strong> <?php echo number_format((float)$status['response_time'], 4); ?> sec</div>
            <div class="status-item"><strong>Memory:</strong> <?php echo number_format((float)($status['memory_mb'] ?? 0), 2); ?> MB
                (peak <?php echo number_format((float)($status['memory_peak_mb'] ?? 0), 2); ?> MB)</div>
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


<?php
// Site footer + DB close (generic reusable helpers only)
if ($useSiteLayout) {
    if (function_exists('cfc_footer')) {
        cfc_footer(
            'https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/ai-system',
            'Source Code'
        );
    }
    if (function_exists('include_footer')) {
        include_footer();
    }
    if (function_exists('close_database')) {
        close_database();
    }
} else {
    echo "</body>\n</html>\n";
}
