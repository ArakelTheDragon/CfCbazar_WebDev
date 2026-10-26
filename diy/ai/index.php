<?php
declare(strict_types=1);

/**
 * Ollama Cloud API Portal
 *
 * API key must be defined as $OllamaKey in ../../config.php.
 * API endpoint: POST this page with ?api=1 and JSON or form-encoded fields:
 * {"model":"beam","prompt":"Hello"}
 *
 * API usage
 * 
  curl -X POST "https://cfcbazar.8bit.ca/diy/ai/index.php?api=1" \
  -d "model=beam" \
  --data-urlencode "prompt=Explain blockchain"
 */

require_once __DIR__ . '/../../config.php';

$isApiRequest = isset($_GET['api']) && $_GET['api'] === '1';

if ($isApiRequest) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['error' => 'POST requests are required.']);
        exit;
    }

    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));

    if ($contentType === 'application/json') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput === false ? '' : $rawInput, true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON request.']);
            exit;
        }
    } elseif ($contentType === 'application/x-www-form-urlencoded' ||
              $contentType === 'multipart/form-data') {
        $input = $_POST;
    } else {
        http_response_code(415);
        echo json_encode([
            'error' => 'Use application/json or form-encoded data.',
            'example' => ['model' => 'beam', 'prompt' => 'Hello']
        ]);
        exit;
    }

    $model = $input['model'] ?? '';
    $prompt = $input['prompt'] ?? '';

    if (!is_string($model) || trim($model) === '' ||
        !is_string($prompt) || trim($prompt) === '') {
        http_response_code(400);
        echo json_encode(['error' => 'A valid model and prompt are required.']);
        exit;
    }

    $model = trim($model);
    $prompt = trim($prompt);

    if (strlen($prompt) > 100000) {
        http_response_code(413);
        echo json_encode(['error' => 'Prompt is too long.']);
        exit;
    }

    if (!isset($OllamaKey) || !is_string($OllamaKey) || trim($OllamaKey) === '') {
        error_log('Ollama API key is missing or empty.');
        http_response_code(500);
        echo json_encode(['error' => 'API configuration is unavailable.']);
        exit;
    }

    if (!function_exists('curl_init')) {
        error_log('PHP cURL extension is unavailable.');
        http_response_code(500);
        echo json_encode(['error' => 'Server HTTP client is unavailable.']);
        exit;
    }

    $payload = json_encode([
        'model' => $model,
        'prompt' => $prompt,
        'stream' => false,
    ]);

    if ($payload === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Could not encode the request.']);
        exit;
    }

    $ch = curl_init('https://ollama.com/api/generate');
    if ($ch === false) {
        error_log('Could not initialize Ollama cURL request.');
        http_response_code(500);
        echo json_encode(['error' => 'Could not initialize the AI request.']);
        exit;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim($OllamaKey),
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 180,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log('Ollama cURL error: ' . $curlError);
        http_response_code(502);
        echo json_encode(['error' => 'Could not connect to the AI service.']);
        exit;
    }

    if ($httpCode !== 200) {
        error_log('Ollama returned HTTP status ' . $httpCode . '.');
        http_response_code(502);
        echo json_encode(['error' => 'The AI service returned an error.']);
        exit;
    }

    $result = json_decode($response, true);
    if (!is_array($result) || !isset($result['response']) || !is_string($result['response'])) {
        error_log('Ollama returned an unexpected or invalid JSON response.');
        http_response_code(502);
        echo json_encode(['error' => 'Invalid response from the AI service.']);
        exit;
    }

    echo json_encode($result);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ollama Cloud API Portal</title>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.6/dist/purify.min.js"></script>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .card {
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            width: 100%;
            max-width: 800px;
            padding: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }

        h1 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: #38bdf8;
            text-align: center;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #94a3b8;
        }

        select,
        textarea {
            width: 100%;
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 10px 14px;
            color: #f8fafc;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s ease;
        }

        select:focus,
        textarea:focus {
            border-color: #38bdf8;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        button {
            width: 100%;
            background-color: #0284c7;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        button:hover {
            background-color: #0369a1;
        }

        button:disabled {
            background-color: #475569;
            cursor: not-allowed;
        }

        .response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
        }

        .copy-btn {
            background-color: #334155;
            color: #cbd5e1;
            border: none;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 0.75rem;
            cursor: pointer;
            width: auto;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .copy-btn:hover {
            background-color: #475569;
            color: #fff;
        }

        .response-box {
            margin-top: 6px;
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 16px;
            min-height: 120px;
            max-height: 450px;
            overflow-y: auto;
            font-size: 0.95rem;
            color: #e2e8f0;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .response-box p {
            margin-bottom: 10px;
        }

        .response-box ul,
        .response-box ol {
            margin-left: 20px;
            margin-bottom: 10px;
        }

        .response-box strong {
            color: #38bdf8;
        }

        .code-block-wrapper {
            position: relative;
            margin: 14px 0;
            background-color: #090d16;
            border: 1px solid #334155;
            border-radius: 8px;
            overflow: hidden;
        }

        .code-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #1e293b;
            padding: 6px 12px;
            font-size: 0.75rem;
            color: #94a3b8;
            border-bottom: 1px solid #334155;
        }

        .response-box pre {
            padding: 14px;
            overflow-x: auto;
            font-family: SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 0.875rem;
            color: #38bdf8;
            margin: 0;
        }

        .error {
            color: #f87171;
            font-family: monospace;
            white-space: pre-wrap;
        }

        .security-badge {
            font-size: 0.75rem;
            color: #34d399;
            text-align: right;
            margin-top: -12px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Ollama Cloud Portal</h1>
        <div class="security-badge">Key loaded securely from server config</div>

        <div class="form-group">
            <label for="model">Select Cloud Model</label>
            <select id="model">
                <option value="beam">beam</option>
                <option value="gemma4:31b">gemma4:31b</option>
                <option value="gpt-oss:120b">gpt-oss:120b</option>
                <option value="gpt-oss:20b">gpt-oss:20b</option>
                <option value="nemotron-3-nano:30b">nemotron-3-nano:30b</option>
                <option value="nemotron-3-super">nemotron-3-super</option>
                <option value="nemotron-3-ultra">nemotron-3-ultra</option>
            </select>
        </div>

        <div class="form-group">
            <label for="prompt">Prompt</label>
            <textarea id="prompt" placeholder="Type your prompt here..."></textarea>
        </div>

        <button id="sendBtn" type="button">Generate Response</button>

        <div class="form-group" style="margin-top: 20px;">
            <div class="response-header">
                <label for="response" style="margin-bottom: 0;">Response</label>
                <button class="copy-btn" id="copyRespBtn" type="button" hidden>
                    Copy Full Response
                </button>
            </div>
            <div id="response" class="response-box" aria-live="polite">Output will appear here...</div>
        </div>
    </main>

    <script>
        "use strict";

        let rawResponseText = "";

        const modelSelect = document.getElementById("model");
        const promptInput = document.getElementById("prompt");
        const responseBox = document.getElementById("response");
        const sendBtn = document.getElementById("sendBtn");
        const copyRespBtn = document.getElementById("copyRespBtn");

        sendBtn.addEventListener("click", sendPrompt);
        copyRespBtn.addEventListener("click", copyResponse);

        async function sendPrompt() {
            const model = modelSelect.value;
            const prompt = promptInput.value.trim();

            if (!prompt) {
                alert("Please enter a prompt.");
                promptInput.focus();
                return;
            }

            sendBtn.disabled = true;
            copyRespBtn.hidden = true;
            responseBox.classList.remove("error");
            responseBox.textContent = "Generating response via server proxy...";
            rawResponseText = "";

            try {
                const res = await fetch("?api=1", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({ model, prompt })
                });

                const responseText = await res.text();
                let data;

                try {
                    data = JSON.parse(responseText);
                } catch {
                    throw new Error(
                        `The server returned an invalid response (HTTP ${res.status}).`
                    );
                }

                if (!res.ok || data.error) {
                    throw new Error(data.error || `Request failed (HTTP ${res.status}).`);
                }

                rawResponseText = typeof data.response === "string"
                    ? data.response
                    : "";

                if (!rawResponseText) {
                    responseBox.textContent = "No response received.";
                    return;
                }

                if (typeof marked === "undefined" ||
                    typeof DOMPurify === "undefined") {
                    responseBox.textContent = rawResponseText;
                    return;
                }

                const parsedHTML = marked.parse(rawResponseText);
                responseBox.innerHTML = DOMPurify.sanitize(parsedHTML);
                wrapCodeBlocks(responseBox);
                copyRespBtn.hidden = false;
            } catch (err) {
                responseBox.classList.add("error");
                responseBox.textContent = `Error: ${
                    err instanceof Error ? err.message : "An unexpected error occurred."
                }`;
            } finally {
                sendBtn.disabled = false;
            }
        }

        function wrapCodeBlocks(container) {
            const preElements = container.querySelectorAll("pre");

            preElements.forEach((pre) => {
                if (pre.parentElement?.classList.contains("code-block-wrapper")) {
                    return;
                }

                const wrapper = document.createElement("div");
                wrapper.className = "code-block-wrapper";

                const header = document.createElement("div");
                header.className = "code-header";

                const code = pre.querySelector("code");
                const className = code?.className || "";
                const languageMatch = className.match(/language-([a-zA-Z0-9_+-]+)/);
                const language = languageMatch ? languageMatch[1] : "Code";

                const langSpan = document.createElement("span");
                langSpan.textContent = language;

                const copyBtn = document.createElement("button");
                copyBtn.type = "button";
                copyBtn.className = "copy-btn";
                copyBtn.textContent = "Copy Code";

                copyBtn.addEventListener("click", async () => {
                    try {
                        await navigator.clipboard.writeText(code?.innerText ?? pre.innerText);
                        copyBtn.textContent = "Copied!";
                    } catch {
                        copyBtn.textContent = "Copy failed";
                    }

                    setTimeout(() => {
                        copyBtn.textContent = "Copy Code";
                    }, 2000);
                });

                header.appendChild(langSpan);
                header.appendChild(copyBtn);

                const parent = pre.parentNode;
                if (!parent) {
                    return;
                }

                parent.insertBefore(wrapper, pre);
                wrapper.appendChild(header);
                wrapper.appendChild(pre);
            });
        }

        async function copyResponse() {
            if (!rawResponseText) {
                return;
            }

            try {
                await navigator.clipboard.writeText(rawResponseText);
                copyRespBtn.textContent = "Copied Response!";
            } catch {
                copyRespBtn.textContent = "Copy failed";
            }

            setTimeout(() => {
                copyRespBtn.textContent = "Copy Full Response";
            }, 2000);
        }
    </script>
</body>
</html>
