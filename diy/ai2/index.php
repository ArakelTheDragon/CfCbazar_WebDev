<?php
/* ============================================================
   CfCbazar Group — Local Modular PHP AI Engine (Folder-Based)
   File: /diy/ai2/index.php
   ============================================================ */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* Load central configuration & reusable helper functions */
$reusablePath = $_SERVER['DOCUMENT_ROOT'] . "/includes/reusable.php";
$configPath   = $_SERVER['DOCUMENT_ROOT'] . "/config.php";

if (file_exists($reusablePath)) {
    require_once $reusablePath;
} else {
    error_log("Warning: Reusable functions file not found at " . $reusablePath);
}

if (file_exists($configPath)) {
    require_once $configPath;
} else {
    die("Configuration Error: Mandatory config file not found at " . $configPath);
}

// Return JSON for API calls (e.g. via POST user/prompt parameter), or render HTML if empty
$isApiRequest = isset($_POST['user']) || isset($_GET['user']);
if ($isApiRequest) {
    header("Content-Type: application/json; charset=UTF-8");
}

/* ============================================================
   Skill Interface (Defined BEFORE loading any skill files)
   ============================================================ */

interface AISkillInterface {
    public function getKeywords(): array;
    public function getPriority(): int;
    public function execute(string $input, array &$memory): string;
}

/* ============================================================
   Modular Memory Storage Helpers (Folder + JSON Index)
   ============================================================ */

$memoryIndexFile = __DIR__ . "/local_memory.json";
$memoryDir        = __DIR__ . "/memory_topics";
$skillListFile   = __DIR__ . "/skills/skill_list.json";

// Ensure memory directory exists
if (!is_dir($memoryDir)) {
    @mkdir($memoryDir, 0755, true);
}

/**
 * Clean topic names into safe, standardized filenames
 */
function sanitizeTopicFilename(string $topic): string {
    $clean = strtolower(trim($topic));
    $clean = preg_replace('/[^a-z0-9\-_]/', '_', $clean);
    return trim($clean, '_') . ".json";
}

/**
 * Retrieve detailed memory topic payload from memory_topics/ folder
 */
function getMemoryDetail(string $filename): ?array {
    global $memoryDir;
    
    // Sanitize path to prevent nested path repetition
    $cleanFilename = basename($filename);
    $filePath = $memoryDir . "/" . $cleanFilename;

    if (file_exists($filePath)) {
        $raw = file_get_contents($filePath);
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
    return null;
}

/**
 * Save lightweight index to local_memory.json and full payload to memory_topics/
 */
function saveMemoryTopic(string $topic, string $summary, string $fullDetail): void {
    global $memoryIndexFile, $memoryDir, $memory;

    $cleanTopic = strtolower(trim($topic));
    $filename   = sanitizeTopicFilename($cleanTopic);
    $filePath   = $memoryDir . "/" . $filename;
    $now        = date("Y-m-d H:i:s");

    // 1. Save detailed topic payload to dedicated JSON file
    if (file_exists($filePath)) {
        $topicData = json_decode(file_get_contents($filePath), true) ?: [];
        $topicData["topic"]        = $cleanTopic;
        $topicData["summary"]      = $summary;
        $topicData["content"]      = $fullDetail;
        $topicData["detail"]       = $fullDetail;
        $topicData["last_updated"] = $now;
        $topicData["updates"][]    = [
            "timestamp" => $now,
            "source"    => "Engine System",
            "note"      => "Topic memory content updated."
        ];
    } else {
        $topicData = [
            "topic"        => $cleanTopic,
            "summary"      => $summary,
            "content"      => $fullDetail,
            "detail"       => $fullDetail,
            "last_updated" => $now,
            "updates"      => [
                [
                    "timestamp" => $now,
                    "source"    => "Engine System",
                    "note"      => "Initial topic created."
                ]
            ]
        ];
    }

    file_put_contents($filePath, json_encode($topicData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // 2. Update lightweight catalog index
    $memory["knowledge"][$cleanTopic] = [
        "summary" => $summary,
        "file"    => "memory_topics/" . $filename
    ];

    file_put_contents($memoryIndexFile, json_encode($memory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/* Load memory index */
$memory = [];
if (file_exists($memoryIndexFile)) {
    $raw = file_get_contents($memoryIndexFile);
    $memory = json_decode($raw, true);
}

if (!is_array($memory)) {
    $memory = [
        "history" => [],
        "knowledge" => []
    ];
}

if (!isset($memory["knowledge"]) || !is_array($memory["knowledge"])) {
    $memory["knowledge"] = [];
}
if (!isset($memory["history"]) || !is_array($memory["history"])) {
    $memory["history"] = [];
}

// Seed initial default topics if index is empty
if (empty($memory["knowledge"])) {
    saveMemoryTopic(
        "proxima",
        "Deep space signal context",
        "Deep space is quiet — but not silent. A faint signal can change everything."
    );
    saveMemoryTopic(
        "stock market",
        "Equity trading and balance sheet analysis fundamentals",
        "Stock markets facilitate equity trading; fundamental analysis focuses on balance sheets and earnings."
    );
}

/* ============================================================
   Skill Loader (Loads /skills/*.php)
   ============================================================ */

function loadSkillFile(string $className): bool {
    $path = __DIR__ . "/skills/" . $className . ".php";
    if (file_exists($path)) {
        require_once $path;
        return true;
    }
    return false;
}

/* ============================================================
   Plugin Loader (InfinityFree Safe)
   ============================================================ */

function loadPlugins($ai) {
    $pluginDir = __DIR__ . "/plugins";

    if (!is_dir($pluginDir)) return;

    foreach (scandir($pluginDir) as $folder) {
        if ($folder === "." || $folder === "..") continue;

        $fullPath = $pluginDir . "/" . $folder;
        if (!is_dir($fullPath)) continue;

        $pluginPath = $fullPath . "/plugin.php";

        if (file_exists($pluginPath)) {
            require_once $pluginPath;

            $className = $folder . "Plugin";

            if (class_exists($className)) {
                $plugin = new $className();
                $plugin->register($ai);
            }
        }
    }
}

/* ============================================================
   OpenRouter Error Formatter
   ============================================================ */

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
    }

    return implode(' | ', $parts);
}

/* ============================================================
   OpenRouter Integration Helper
   ============================================================ */

function queryOpenRouter(string $userPrompt): ?string {
    global $API_openrouter;

    if (empty($API_openrouter)) {
        return "OpenRouter Error: API key missing.";
    }

    $url = 'https://openrouter.ai/api/v1/chat/completions';
    $model = defined('CFCBAZAR_AI_MODEL') ? CFCBAZAR_AI_MODEL : 'meta-llama/llama-3.1-8b-instruct:free';

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an intelligent, helpful AI assistant. Provide comprehensive, accurate, and well-structured answers using clear Markdown formatting.'
            ],
            [
                'role' => 'user',
                'content' => $userPrompt
            ]
        ]
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonPayload === false) {
        return "OpenRouter Error: Failed to encode JSON payload.";
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return "OpenRouter Error: Unable to initialize cURL.";
    }

    $headers = [
        'Authorization: Bearer ' . trim($API_openrouter),
        'Content-Type: application/json',
        'Accept: application/json',
        'HTTP-Referer: https://cfcbazar.42web.io',
        'X-Title: CfCbazar Local AI Engine'
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
        return "cURL Error ({$httpCode}): " . $curlError;
    }

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        return "OpenRouter Error (HTTP {$httpCode}): Invalid JSON returned.";
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return format_openrouter_error($decoded, $httpCode);
    }

    if (isset($decoded['error'])) {
        return format_openrouter_error($decoded, $httpCode);
    }

    if (isset($decoded['choices'][0]['message']['content'])) {
        return trim($decoded['choices'][0]['message']['content']);
    }

    return "OpenRouter Error: Unexpected response structure.";
}

/* ============================================================
   Core AI Dispatcher
   ============================================================ */

class LocalAIEngine {

    private array $skills = [];

    public function registerSkill(AISkillInterface $skill): void {
        $this->skills[] = $skill;
    }

    public function process(string $input, array &$memory): string {

        $inputLower = trim(strtolower($input));
        $bestSkill = null;
        $bestScore = 0;

        // 1. Check registered modular skills first
        foreach ($this->skills as $skill) {
            $score = 0;

            foreach ($skill->getKeywords() as $kw) {
                $cleanKw = trim(strtolower($kw));
                if ($cleanKw !== "" && str_contains($inputLower, $cleanKw)) {
                    $score += strlen($cleanKw) * $skill->getPriority();
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSkill = $skill;
            }
        }

        if ($bestSkill && $bestScore > 0) {
            return $bestSkill->execute($input, $memory);
        }

        // 2. Check local memory index and fetch detailed file
        $matchedTopicKey = null;
        $matchedIndex    = null;

        if (isset($memory["knowledge"]) && is_array($memory["knowledge"])) {
            foreach ($memory["knowledge"] as $topic => $meta) {
                if (str_contains($inputLower, strtolower($topic))) {
                    $matchedTopicKey = $topic;
                    $matchedIndex    = $meta;
                    break;
                }
            }
        }

        $localDetail = null;
        if ($matchedIndex !== null) {
            // Handle legacy string entry vs new array structure
            if (is_array($matchedIndex) && !empty($matchedIndex["file"])) {
                $detailData = getMemoryDetail($matchedIndex["file"]);
                if ($detailData) {
                    $localDetail = $detailData["content"] ?? $detailData["detail"] ?? $detailData["summary"] ?? null;
                }
            } elseif (is_string($matchedIndex)) {
                $localDetail = $matchedIndex;
            }
        }

        // 3. Query OpenRouter to compare, update existing memory, or ingest new facts
        $openRouterReply = queryOpenRouter($input);

        if ($localDetail !== null) {
            if ($openRouterReply && trim($openRouterReply) !== trim($localDetail) && !str_contains($openRouterReply, 'OpenRouter Error')) {
                // Generate a concise 1-line summary for the index
                $lines = explode("\n", trim($openRouterReply));
                $summary = substr(trim($lines[0]), 0, 120);

                saveMemoryTopic($matchedTopicKey, $summary, $openRouterReply);
                return "Updated Knowledge Topic [{$matchedTopicKey}]:\n\n" . $openRouterReply;
            }

            return "Knowledge [{$matchedTopicKey}]:\n\n{$localDetail}";
        }

        // 4. Save completely new topic to separate JSON file and catalog index
        if ($openRouterReply && !str_contains($openRouterReply, 'OpenRouter Error')) {
            $lines = explode("\n", trim($openRouterReply));
            $summary = substr(trim($lines[0]), 0, 120);

            saveMemoryTopic($inputLower, $summary, $openRouterReply);
            return "OpenRouter AI (Saved to Knowledge Folder):\n\n" . $openRouterReply;
        }

        return "General AI: I’m processing locally. Teach me new facts using: remember [topic] is [info].";
    }
}

/* ============================================================
   Initialize AI + Load Skills + Load Plugins
   ============================================================ */

$ai = new LocalAIEngine();

/* Load skill list from JSON */
$skillList = [];

if (file_exists($skillListFile)) {
    $json = file_get_contents($skillListFile);
    $skillList = json_decode($json, true);
}

/* Fallback default skills if JSON missing */
if (!is_array($skillList)) {
    $skillList = [
        "CalculatorSkill",
        "KnowledgeSkill",
        "GeneratorSkill",
        "SummarizerSkill",
        "BayesSkill",
        "MLPatternSkill",
        "DiagnosticSkill",
        "SelfLearningSkill",
        "PromptUnderstandingSkill",
        "ImageGenerationSkill"
    ];
}

/* Load skills */
foreach ($skillList as $skillClass) {
    if (loadSkillFile($skillClass)) {
        if (class_exists($skillClass)) {
            $ai->registerSkill(new $skillClass());
        }
    }
}

/* Load plugins */
loadPlugins($ai);

/* ============================================================
   Handle User Request
   ============================================================ */

$prompt = $_POST["prompt"] ?? $_POST["user"] ?? $_GET["user"] ?? "";
$response = "";

if (!empty($prompt)) {

    $promptClean = trim($prompt);
    $response = $ai->process($promptClean, $memory);

    $memory["history"][] = [
        "time"   => date("Y-m-d H:i:s"),
        "user"   => $promptClean,
        "engine" => $response
    ];

    file_put_contents($memoryIndexFile, json_encode($memory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // Handle JSON response format for API calls
    if ($isApiRequest) {
        echo json_encode(["engine" => $response]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CfCbazar Local AI Engine</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
    body { background:#0f172a; color:#f8fafc; font-family:Arial, sans-serif; margin:0; padding:0; }
    .container { max-width:800px; margin:40px auto; background:#1e293b; padding:25px; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.3); }
    h1 { text-align:center; margin-bottom:20px; color:#f8fafc; font-size:24px; }
    textarea { width:100%; height:110px; padding:12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff; font-size:15px; box-sizing:border-box; resize:vertical; }
    .btn-submit { width:100%; padding:12px; margin-top:12px; background:#2563eb; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer; }
    .btn-submit:hover { background:#1d4ed8; }

    .response-card { margin-top:25px; padding:20px; background:#334155; border-left:4px solid #38bdf8; border-radius:8px; position:relative; }
    .response-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #475569; padding-bottom:10px; }
    .response-title { font-weight:bold; color:#38bdf8; font-size:16px; }

    .btn-copy { background:#475569; color:#f8fafc; border:none; padding:6px 14px; border-radius:5px; font-size:13px; font-weight:bold; cursor:pointer; transition:background 0.2s; }
    .btn-copy:hover { background:#64748b; }
    .btn-copy.copied { background:#16a34a; }

    /* Markdown & Media Styles */
    .md-body { line-height:1.6; font-size:15px; color:#e2e8f0; }
    .md-body h1, .md-body h2, .md-body h3 { color:#f8fafc; margin-top:1.2em; margin-bottom:0.6em; }
    .md-body p { margin-bottom:1em; }
    .md-body ul, .md-body ol { padding-left:20px; margin-bottom:1em; }
    .md-body li { margin-bottom:0.4em; }
    .md-body code { background:#0f172a; color:#38bdf8; padding:2px 6px; border-radius:4px; font-family:monospace; font-size:14px; }
    .md-body pre { background:#0f172a; padding:14px; border-radius:6px; overflow-x:auto; border:1px solid #475569; position:relative; }
    .md-body pre code { background:none; padding:0; color:#f8fafc; }
    .md-body blockquote { border-left:3px solid #64748b; margin:0; padding-left:15px; color:#94a3b8; }
    .md-body table { width:100%; border-collapse:collapse; margin-bottom:1em; }
    .md-body th, .md-body td { border:1px solid #475569; padding:8px 12px; text-align:left; }
    .md-body th { background:#1e293b; }
    .md-body img { max-width:100%; height:auto; border-radius:8px; margin:10px 0; border:1px solid #475569; display:block; }

    .code-box-header { display:flex; justify-content:flex-end; margin-bottom:5px; }
    .btn-code-copy { background:#334155; color:#94a3b8; border:1px solid #475569; font-size:11px; padding:3px 8px; border-radius:4px; cursor:pointer; }
    .btn-code-copy:hover { color:#fff; background:#475569; }
</style>
</head>
<body>

<div class="container">
    <h1>CfCbazar Local PHP AI Engine</h1>

    <form method="POST">
        <textarea name="prompt" placeholder="Ask anything, generate code, create blueprints, or draw images..."><?= htmlspecialchars($prompt) ?></textarea>
        <button type="submit" class="btn-submit">Run Local AI</button>
    </form>

    <?php if (!empty($response)): ?>
        <div class="response-card">
            <div class="response-header">
                <span class="response-title">AI Engine Output</span>
                <button class="btn-copy" onclick="copyResponseText(this)">Copy Response</button>
            </div>
            <div id="raw-response" style="display:none;"><?= htmlspecialchars($response) ?></div>
            <div id="formatted-response" class="md-body"></div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var rawElem = document.getElementById("raw-response");
        var targetElem = document.getElementById("formatted-response");
        if (rawElem && targetElem) {
            targetElem.innerHTML = marked.parse(rawElem.textContent);

            // Attach copy action to code blocks inside response
            targetElem.querySelectorAll("pre").forEach(function(preBlock) {
                var btn = document.createElement("button");
                btn.className = "btn-code-copy";
                btn.innerText = "Copy Code";
                btn.onclick = function() {
                    var codeText = preBlock.querySelector("code") ? preBlock.querySelector("code").innerText : preBlock.innerText;
                    navigator.clipboard.writeText(codeText).then(function() {
                        btn.innerText = "Copied!";
                        setTimeout(function() { btn.innerText = "Copy Code"; }, 2000);
                    });
                };
                var headerDiv = document.createElement("div");
                headerDiv.className = "code-box-header";
                headerDiv.appendChild(btn);
                preBlock.parentNode.insertBefore(headerDiv, preBlock);
            });
        }
    });

    function copyResponseText(button) {
        var rawText = document.getElementById("raw-response").textContent;
        navigator.clipboard.writeText(rawText).then(function() {
            button.textContent = "Copied!";
            button.classList.add("copied");
            setTimeout(function() {
                button.textContent = "Copy Response";
                button.classList.remove("copied");
            }, 2000);
        }).catch(function(err) {
            console.error("Copy failed: ", err);
        });
    }
</script>

</body>
</html>
