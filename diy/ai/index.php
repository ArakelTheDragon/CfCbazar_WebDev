<?php
/**
 * FOLDER & FILE STRUCTURE:
 * project_root/
 * ├── ai/
 * │   ├── index.php
 * │   ├── local_memory.json
 * │   └── memory_topics/
 * ├── css/
 * │   └── styles.css
 * ├── includes/
 * │   ├── reusable.php
 * │   └── skills/
 * │       ├── PromptUnderstandingSkill.php
 * │       ├── CalculatorSkill.php
 * │       ├── KnowledgeSkill.php
 * │       ├── SummarizerSkill.php
 * │       ├── SelfLearningSkill.php
 * │       ├── DiagnosticSkill.php
 * │       ├── BayesSkill.php
 * │       ├── MLPatternSkill.php
 * │       └── agent_openrouter.php
 * ├── libs/
 * │   ├── phpml/
 * │   │   └── autoload.php
 * │   ├── markov/
 * │   │   └── Markov.php
 * │   ├── stemmer/
 * │   │   └── stemmer.php
 * │   ├── simplebayes/
 * │   │   └── SimpleBayes.php
 * │   └── textrank/
 * │       └── TextRank.php
 * └── config.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Automatically load reusable.php / config.php to retrieve $API_openrouter and other settings
$possibleReusablePaths = [
    __DIR__ . '/../reusable.php',
    __DIR__ . '/../../reusable.php',
    $_SERVER['DOCUMENT_ROOT'] . '/reusable.php',
    $_SERVER['DOCUMENT_ROOT'] . '/diy/reusable.php'
];

foreach ($possibleReusablePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Fallback check if $API_openrouter wasn't set by reusable.php
if (empty($API_openrouter)) {
    $API_openrouter = 'YourKey;
}

$localMemoryPath = __DIR__ . '/local_memory.json';
$memoryTopicsDir = __DIR__ . '/memory_topics/';

if (!is_dir($memoryTopicsDir)) {
    mkdir($memoryTopicsDir, 0755, true);
}

// Flexible path resolution targeting includes/skills/
$possibleSkillDirs = [
    __DIR__ . '/../includes/skills/',          
    __DIR__ . '/../../includes/skills/',        
    $_SERVER['DOCUMENT_ROOT'] . '/includes/skills/', 
    $_SERVER['DOCUMENT_ROOT'] . '/diy/includes/skills/' 
];

$skillsDir = '';
foreach ($possibleSkillDirs as $dir) {
    if (is_dir($dir)) {
        $skillsDir = realpath($dir) . '/';
        break;
    }
}
if (empty($skillsDir)) {
    $skillsDir = $possibleSkillDirs[0];
}

// Include the unmodified OpenRouter agent file if present
$agentOpenRouterFile = $skillsDir . 'agent_openrouter.php';
if (file_exists($agentOpenRouterFile)) {
    require_once $agentOpenRouterFile;
}

// Load local memory
$localMemory = [];
if (file_exists($localMemoryPath)) {
    $localMemory = json_decode(file_get_contents($localMemoryPath), true) ?? ['knowledge' => [], 'history' => []];
}

$responseMessage = "";
$executedPipeline = [];
$openRouterHandled = false;
$currentPrompt = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPrompt = trim($_POST['prompt'] ?? '');
    
    if (!empty($currentPrompt)) {
        $lowerPrompt = strtolower($currentPrompt);
        $forcedLocalSkill = '';

        // Check for triggers that bypass OpenRouter and execute local skills directly
        if ($lowerPrompt === 'diagnostic ai 0f' || $lowerPrompt === 'run diagnostic ai 0f') {
            $forcedLocalSkill = 'DiagnosticSkill';
            $executedPipeline[] = "Bypassed OpenRouter (Explicit Local Override)";
        }

        // ====================================================================
        // STEP 1: Attempt OpenRouter Query (Skipped if forced locally)
        // ====================================================================
        if (empty($forcedLocalSkill) && function_exists('call_openrouter') && !empty($API_openrouter)) {
            $messages = [['role' => 'user', 'content' => $currentPrompt]];
            $orResponse = call_openrouter($messages);

            if (is_array($orResponse) && !empty($orResponse['success']) && $orResponse['success']) {
                $responseData = $orResponse['data'];
                $aiAnswer = $responseData['choices'][0]['message']['content'] ?? '';

                if (!empty(trim($aiAnswer))) {
                    $openRouterHandled = true;
                    $responseMessage = "### OpenRouter AI Response\n\n" . trim($aiAnswer);
                    $executedPipeline[] = "OpenRouter API (Remote Query Success)";

                    // Generate clean topic key from prompt
                    $words = explode(' ', trim($currentPrompt));
                    $topicKey = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '_', implode('_', array_slice($words, 0, 4))));
                    if (empty($topicKey)) {
                        $topicKey = 'query_' . time();
                    }

                    $timestamp = date('Y-m-d H:i:s');
                    $isDifferentOrMissing = false;

                    // Check if topic is missing or different in local memory json
                    if (!isset($localMemory['knowledge'][$topicKey]) || $localMemory['knowledge'][$topicKey]['info'] !== $aiAnswer) {
                        $localMemory['knowledge'][$topicKey] = [
                            'info' => $aiAnswer,
                            'prompt' => $currentPrompt,
                            'source' => 'OpenRouter',
                            'updated_at' => $timestamp
                        ];
                        $isDifferentOrMissing = true;
                        $executedPipeline[] = "Local Memory Updated";
                    } else {
                        $executedPipeline[] = "Local Memory Verified (Matches OpenRouter)";
                    }

                    // Add/Update individual topic file in memory_topics folder if new or changed
                    if ($isDifferentOrMissing && is_dir($memoryTopicsDir)) {
                        $topicFilePath = $memoryTopicsDir . $topicKey . '.json';
                        $topicData = [
                            'topic' => $topicKey,
                            'prompt' => $currentPrompt,
                            'content' => $aiAnswer,
                            'source' => 'OpenRouter',
                            'updated_at' => $timestamp
                        ];
                        file_put_contents($topicFilePath, json_encode($topicData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $executedPipeline[] = "Memory Topic File Created/Updated (/memory_topics/{$topicKey}.json)";
                    }
                }
            }
        }

        // ====================================================================
        // STEP 2: Graceful Fallback / Direct Local Skill Execution
        // ====================================================================
        if (!$openRouterHandled) {
            $targetSkillName = 'KnowledgeSkill'; // Default fallback

            if (!empty($forcedLocalSkill)) {
                $targetSkillName = $forcedLocalSkill;
                $executedPipeline[] = "Direct Skill Router (" . $forcedLocalSkill . ")";
            } else {
                $routerSkillFile = $skillsDir . 'PromptUnderstandingSkill.php';
                if (file_exists($routerSkillFile)) {
                    require_once $routerSkillFile;
                    if (class_exists('PromptUnderstandingSkill')) {
                        $routerInstance = new PromptUnderstandingSkill();
                        $routerInstance->execute($currentPrompt, $localMemory);
                        
                        if (isset($localMemory['parsed_intent']['target_skill'])) {
                            $targetSkillName = $localMemory['parsed_intent']['target_skill'];
                        }
                    }
                }
                $executedPipeline[] = "PromptUnderstandingSkill (Local Router Fallback)";
            }

            // Execute target local skill
            $targetSkillFile = $skillsDir . $targetSkillName . '.php';
            if (file_exists($targetSkillFile)) {
                require_once $targetSkillFile;
                if (class_exists($targetSkillName)) {
                    $targetInstance = new $targetSkillName();
                    if (method_exists($targetInstance, 'execute')) {
                        $responseMessage = $targetInstance->execute($currentPrompt, $localMemory);
                        $executedPipeline[] = $targetSkillName . " (Executed locally)";
                    }
                }
            }

            if (empty($responseMessage)) {
                $responseMessage = "General AI: I’m processing locally. Teach me new facts using: remember [topic] is [info].";
                $executedPipeline[] = "Dispatcher Fallback";
            }
        }

        // Save execution to history in local_memory.json
        $localMemory['history'][] = [
            'time' => date('Y-m-d H:i:s'),
            'user' => $currentPrompt,
            'pipeline' => $executedPipeline,
            'engine' => $responseMessage
        ];
        file_put_contents($localMemoryPath, json_encode($localMemory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        $responseMessage = "Please enter a prompt.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CfCbazar Automated AI Hub</title>
    <!-- Include global CfCbazar stylesheet if available -->
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 950px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        h1, h2 { color: #38bdf8; }
        .card { background: #334155; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        textarea, button { width: 100%; padding: 12px; margin-top: 10px; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: #fff; box-sizing: border-box; }
        button { background: #0284c7; border: none; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #0369a1; }
        .btn-copy { background: #059669; width: auto; padding: 6px 12px; font-size: 0.8rem; margin-top: 8px; display: inline-block; }
        .btn-copy:hover { background: #047857; }
        .output { background: #0f172a; padding: 15px; border-radius: 6px; border-left: 4px solid #38bdf8; white-space: pre-wrap; line-height: 1.5; font-size: 0.9rem; }
        .user-prompt-box { background: #0f172a; padding: 12px; border-radius: 6px; border-left: 4px solid #10b981; margin-bottom: 15px; white-space: pre-wrap; }
        .meta-info { font-size: 0.85rem; color: #94a3b8; margin-top: 5px; }
    </style>
    <script>
        function copyContent(elementId, btnElement) {
            const textContent = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(textContent).then(() => {
                const originalText = btnElement.innerText;
                btnElement.innerText = 'Copied!';
                setTimeout(() => { btnElement.innerText = originalText; }, 2000);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
            });
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>CfCbazar Automated AI Hub (`ai/index.php`)</h1>
        
        <div class="card">
            <h2>Hybrid OpenRouter & Local Memory Pipeline</h2>
            <p class="meta-info">Queries try OpenRouter first, sync results to `local_memory.json` & `memory_topics/`, and fall back to local skills if offline.</p>
            
            <form method="POST">
                <label for="prompt">Enter Input / Prompt:</label>
                <textarea name="prompt" id="prompt" rows="4" placeholder="Ask anything..."><?php echo htmlspecialchars($currentPrompt); ?></textarea>

                <button type="submit" style="margin-top: 15px;">Execute AI Pipeline</button>
            </form>

            <?php if (!empty($responseMessage)): ?>
                <div style="margin-top: 25px;">
                    <h3>Interaction Results</h3>
                    
                    <!-- User Prompt Section with Copy Option -->
                    <div class="meta-info" style="margin-bottom: 5px;"><strong>User Prompt:</strong></div>
                    <div class="user-prompt-box" id="userPromptText"><?php echo htmlspecialchars($currentPrompt); ?></div>
                    <button type="button" class="btn-copy" onclick="copyContent('userPromptText', this)">Copy User Prompt</button>

                    <!-- AI Response Section with Copy Option -->
                    <div class="meta-info" style="margin-top: 15px; margin-bottom: 5px;">
                        <strong>Pipeline Executed:</strong> <?php echo htmlspecialchars(implode(' -> ', $executedPipeline)); ?>
                    </div>
                    <div class="output" id="aiResponseText"><?php echo htmlspecialchars($responseMessage); ?></div>
                    <button type="button" class="btn-copy" onclick="copyContent('aiResponseText', this)">Copy AI Response</button>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Memory & Topic Status</h2>
            <p class="meta-info">Stored Knowledge Topics: <?php echo count($localMemory['knowledge'] ?? []); ?> | History Logs: <?php echo count($localMemory['history'] ?? []); ?> | Topic Files: <?php echo count(glob($memoryTopicsDir . '*.json')); ?></p>
        </div>
    </div>
</body>
</html>
