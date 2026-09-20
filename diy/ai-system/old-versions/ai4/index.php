<?php
/**
 * ============================================================================
 * CfCbazar Automated AI Hub
 * File: /ai/index.php
 *
 * PIPELINE
 *
 * Normal prompt:
 *
 *   User Prompt
 *       ↓
 *   PromptUnderstandingSkill
 *       ↓
 *   KnowledgeSkill
 *       ↓
 *   Relevant Local Skill
 *       ↓
 *   Final Answer
 *
 * Diagnostic override:
 *
 *   "diagnostic ai 0f"
 *       ↓
 *   DiagnosticSkill
 *
 * KnowledgeSkill is responsible for the local-memory/OpenRouter stage.
 *
 * IMPORTANT:
 * - PromptUnderstandingSkill runs first for normal prompts.
 * - Diagnostic override bypasses PromptUnderstandingSkill.
 * - OpenRouter is NOT called directly from this dispatcher.
 * - KnowledgeSkill remains the central memory/OpenRouter component.
 * - Only approved local skill classes can be executed.
 * ============================================================================
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


// ============================================================================
// LOAD REUSABLE.PHP
// ============================================================================

$possibleReusablePaths = [
    __DIR__ . '/../includes/reusable.php',
    __DIR__ . '/../../includes/reusable.php',
    $_SERVER['DOCUMENT_ROOT'] . '/includes/reusable.php',
    $_SERVER['DOCUMENT_ROOT'] . '/diy/includes/reusable.php'
];

$reusableLoaded = false;

foreach ($possibleReusablePaths as $path) {

    if (is_file($path)) {
        require_once $path;
        $reusableLoaded = true;
        break;
    }
}


// ============================================================================
// OPENROUTER CONFIGURATION
// ============================================================================
// The live API key should remain in config.php / reusable.php.
// This dispatcher does not call OpenRouter directly.
// ============================================================================

if (!isset($API_openrouter)) {
    $API_openrouter = '';
}


// ============================================================================
// PATHS
// ============================================================================

$localMemoryPath = __DIR__ . '/local_memory.json';
$memoryTopicsDir = __DIR__ . '/memory_topics/';


// ============================================================================
// ENSURE MEMORY DIRECTORY EXISTS
// ============================================================================

if (!is_dir($memoryTopicsDir)) {
    @mkdir($memoryTopicsDir, 0755, true);
}


// ============================================================================
// RESOLVE SKILLS DIRECTORY
// ============================================================================

$possibleSkillDirs = [
    __DIR__ . '/../includes/skills/',
    __DIR__ . '/../../includes/skills/',
    $_SERVER['DOCUMENT_ROOT'] . '/includes/skills/',
    $_SERVER['DOCUMENT_ROOT'] . '/diy/includes/skills/'
];

$skillsDir = '';

foreach ($possibleSkillDirs as $dir) {

    if (is_dir($dir)) {

        $realDir = realpath($dir);

        if ($realDir !== false) {
            $skillsDir =
                rtrim($realDir, '/\\') .
                DIRECTORY_SEPARATOR;

            break;
        }
    }
}


// Fallback path.

if ($skillsDir === '') {
    $skillsDir =
        rtrim($possibleSkillDirs[0], '/\\') .
        DIRECTORY_SEPARATOR;
}


// ============================================================================
// LOAD OPENROUTER AGENT
// ============================================================================
// KnowledgeSkill may use queryOpenRouter() from this file.
// The dispatcher itself does not call OpenRouter.
// ============================================================================

$agentOpenRouterFile =
    $skillsDir . 'agent_openrouter.php';

if (is_file($agentOpenRouterFile)) {
    require_once $agentOpenRouterFile;
}


// ============================================================================
// LOAD LOCAL MEMORY
// ============================================================================

$localMemory = [
    'knowledge' => [],
    'history'   => []
];

if (is_file($localMemoryPath)) {

    $memoryContents =
        @file_get_contents($localMemoryPath);

    if (
        $memoryContents !== false &&
        trim($memoryContents) !== ''
    ) {

        $decodedMemory =
            json_decode(
                $memoryContents,
                true
            );

        if (is_array($decodedMemory)) {
            $localMemory = $decodedMemory;
        }
    }
}


// ============================================================================
// NORMALIZE MEMORY STRUCTURE
// ============================================================================

if (
    !isset($localMemory['knowledge']) ||
    !is_array($localMemory['knowledge'])
) {
    $localMemory['knowledge'] = [];
}

if (
    !isset($localMemory['history']) ||
    !is_array($localMemory['history'])
) {
    $localMemory['history'] = [];
}


// ============================================================================
// PAGE STATE
// ============================================================================

$responseMessage = '';

$executedPipeline = [];

$currentPrompt = '';


// ============================================================================
// POST REQUEST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currentPrompt =
        trim(
            (string)(
                $_POST['prompt'] ?? ''
            )
        );


    // ========================================================================
    // EMPTY PROMPT
    // ========================================================================

    if ($currentPrompt === '') {

        $responseMessage =
            'Please enter a prompt.';

    } else {

        $lowerPrompt =
            strtolower($currentPrompt);

        $targetSkillName = '';

        $forcedLocalSkill = '';


        // ====================================================================
        // EXPLICIT LOCAL OVERRIDE
        // ====================================================================
        //
        // Diagnostic AI 0F deliberately bypasses the normal routing system.
        // ====================================================================

        if (
            $lowerPrompt === 'diagnostic ai 0f' ||
            $lowerPrompt === 'run diagnostic ai 0f'
        ) {

            $forcedLocalSkill =
                'DiagnosticSkill';

            $targetSkillName =
                'DiagnosticSkill';

            $executedPipeline[] =
                'Explicit Local Override: DiagnosticSkill';

            $executedPipeline[] =
                'PromptUnderstandingSkill bypassed by explicit override';
        }


        // ====================================================================
        // NORMAL PIPELINE
        // ====================================================================

        if ($forcedLocalSkill === '') {


            // =================================================================
            // STEP 1 — PROMPT UNDERSTANDING
            // =================================================================
            //
            // This MUST be the first normal skill.
            //
            // PromptUnderstandingSkill determines the user's intent and
            // writes the selected target skill into:
            //
            // $localMemory['parsed_intent']['target_skill']
            //
            // =================================================================

            $promptUnderstandingFile =
                $skillsDir .
                'PromptUnderstandingSkill.php';


            if (is_file($promptUnderstandingFile)) {

                require_once $promptUnderstandingFile;


                if (class_exists('PromptUnderstandingSkill')) {

                    try {

                        $promptUnderstanding =
                            new PromptUnderstandingSkill();


                        $promptUnderstanding->execute(
                            $currentPrompt,
                            $localMemory
                        );


                        $executedPipeline[] =
                            'PromptUnderstandingSkill (Executed First)';


                        // -----------------------------------------------------
                        // Read target skill selected by PromptUnderstanding.
                        // -----------------------------------------------------

                        if (
                            isset(
                                $localMemory['parsed_intent']
                            ) &&
                            is_array(
                                $localMemory['parsed_intent']
                            ) &&
                            !empty(
                                $localMemory['parsed_intent']['target_skill']
                            )
                        ) {

                            $targetSkillName =
                                trim(
                                    (string)
                                    $localMemory[
                                        'parsed_intent'
                                    ]['target_skill']
                                );
                        }


                    } catch (Throwable $e) {

                        $executedPipeline[] =
                            'PromptUnderstandingSkill Error: ' .
                            $e->getMessage();
                    }

                } else {

                    $executedPipeline[] =
                        'PromptUnderstandingSkill class not found';
                }

            } else {

                $executedPipeline[] =
                    'PromptUnderstandingSkill file not found';
            }


            // =================================================================
            // STEP 2 — KNOWLEDGE / MEMORY BROKER
            // =================================================================
            //
            // KnowledgeSkill is deliberately loaded for every normal request.
            //
            // The revised KnowledgeSkill will determine whether:
            //
            // - local memory is sufficient;
            // - OpenRouter knowledge is required;
            // - memory needs updating;
            // - knowledge/context should be passed to the specialist.
            //
            // If the selected target is KnowledgeSkill itself, it may produce
            // the final response.
            // =================================================================

            $knowledgeSkillFile =
                $skillsDir .
                'KnowledgeSkill.php';


            if (is_file($knowledgeSkillFile)) {

                require_once $knowledgeSkillFile;


                if (class_exists('KnowledgeSkill')) {

                    try {

                        $knowledgeSkill =
                            new KnowledgeSkill(
                                $memoryTopicsDir
                            );


                        // -----------------------------------------------------
                        // KnowledgeSkill currently owns the memory stage.
                        //
                        // For a KnowledgeSkill target it is allowed to
                        // generate the final response.
                        //
                        // For another target skill, the revised KnowledgeSkill
                        // can provide/update context without terminating the
                        // specialist pipeline.
                        // -----------------------------------------------------

                        if (
                            $targetSkillName === '' ||
                            $targetSkillName === 'KnowledgeSkill'
                        ) {

                            $knowledgeResponse =
                                $knowledgeSkill->execute(
                                    $currentPrompt,
                                    $localMemory
                                );


                            if (
                                is_string(
                                    $knowledgeResponse
                                ) &&
                                trim(
                                    $knowledgeResponse
                                ) !== ''
                            ) {

                                $responseMessage =
                                    $knowledgeResponse;
                            }


                            $executedPipeline[] =
                                'KnowledgeSkill (Memory/OpenRouter Stage)';


                            // -------------------------------------------------
                            // KnowledgeSkill produced the final response.
                            // Prevent duplicate execution below.
                            // -------------------------------------------------

                            $targetSkillName = '';

                        } else {

                            $executedPipeline[] =
                                'KnowledgeSkill (Memory/OpenRouter Broker)';
                        }


                    } catch (Throwable $e) {

                        $executedPipeline[] =
                            'KnowledgeSkill Error: ' .
                            $e->getMessage();
                    }

                } else {

                    $executedPipeline[] =
                        'KnowledgeSkill class not found';
                }

            } else {

                $executedPipeline[] =
                    'KnowledgeSkill file not found';
            }
        }


        // ====================================================================
        // STEP 3 — EXECUTE SELECTED SPECIALIST
        // ====================================================================
        //
        // PromptUnderstandingSkill selects the specialist.
        //
        // Security:
        // Only explicitly approved local skill classes can be instantiated.
        // ====================================================================

        if (
            $targetSkillName !== '' &&
            $targetSkillName !== 'KnowledgeSkill'
        ) {


            $allowedSkillNames = [

                'CalculatorSkill',

                'KnowledgeSkill',

                'SummarizerSkill',

                'SelfLearningSkill',

                'DiagnosticSkill',

                'BayesSkill',

                'MLPatternSkill'
            ];


            if (
                in_array(
                    $targetSkillName,
                    $allowedSkillNames,
                    true
                )
            ) {


                $targetSkillFile =
                    $skillsDir .
                    $targetSkillName .
                    '.php';


                if (is_file($targetSkillFile)) {

                    require_once $targetSkillFile;


                    if (
                        class_exists(
                            $targetSkillName
                        )
                    ) {

                        try {

                            $targetInstance =
                                new $targetSkillName();


                            if (
                                method_exists(
                                    $targetInstance,
                                    'execute'
                                )
                            ) {

                                $skillResponse =
                                    $targetInstance->execute(
                                        $currentPrompt,
                                        $localMemory
                                    );


                                if (
                                    is_string(
                                        $skillResponse
                                    ) &&
                                    trim(
                                        $skillResponse
                                    ) !== ''
                                ) {

                                    $responseMessage =
                                        $skillResponse;
                                }


                                $executedPipeline[] =
                                    $targetSkillName .
                                    ' (Executed Locally)';

                            } else {

                                $executedPipeline[] =
                                    $targetSkillName .
                                    ' has no execute() method';
                            }


                        } catch (Throwable $e) {

                            $executedPipeline[] =
                                $targetSkillName .
                                ' Error: ' .
                                $e->getMessage();
                        }


                    } else {

                        $executedPipeline[] =
                            $targetSkillName .
                            ' class not found';
                    }


                } else {

                    $executedPipeline[] =
                        $targetSkillName .
                        ' file not found';
                }


            } else {

                $executedPipeline[] =
                    'Rejected unknown skill: ' .
                    $targetSkillName;
            }
        }


        // ====================================================================
        // FALLBACK
        // ====================================================================

        if (
            trim($responseMessage) === ''
        ) {

            $responseMessage =
                "I couldn't produce a response from the available AI skills.";

            $executedPipeline[] =
                'Dispatcher Fallback';
        }


        // ====================================================================
        // SAVE EXECUTION HISTORY
        // ====================================================================

        $localMemory['history'][] = [

            'time' =>
                date('Y-m-d H:i:s'),

            'user' =>
                $currentPrompt,

            'pipeline' =>
                $executedPipeline,

            'engine' =>
                $responseMessage
        ];


        // ====================================================================
        // LIMIT HISTORY
        // ====================================================================

        if (
            count(
                $localMemory['history']
            ) > 500
        ) {

            $localMemory['history'] =
                array_slice(
                    $localMemory['history'],
                    -500
                );
        }


        // ====================================================================
        // SAVE LOCAL MEMORY
        // ====================================================================

        @file_put_contents(

            $localMemoryPath,

            json_encode(

                $localMemory,

                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),

            LOCK_EX
        );
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>CfCbazar Automated AI Hub</title>

    <link
        rel="stylesheet"
        href="../css/styles.css"
    >

    <style>

        body {
            font-family:
                system-ui,
                -apple-system,
                sans-serif;

            background:
                #0f172a;

            color:
                #f8fafc;

            margin:
                0;

            padding:
                20px;
        }


        .container {

            max-width:
                950px;

            margin:
                0 auto;

            background:
                #1e293b;

            padding:
                30px;

            border-radius:
                12px;

            box-shadow:
                0 4px 20px rgba(0, 0, 0, 0.5);
        }


        h1,
        h2 {

            color:
                #38bdf8;
        }


        .card {

            background:
                #334155;

            padding:
                20px;

            border-radius:
                8px;

            margin-bottom:
                20px;
        }


        textarea,
        button {

            width:
                100%;

            padding:
                12px;

            margin-top:
                10px;

            border-radius:
                6px;

            border:
                1px solid #475569;

            background:
                #0f172a;

            color:
                #fff;

            box-sizing:
                border-box;
        }


        textarea {

            min-height:
                140px;

            resize:
                vertical;
        }


        button {

            background:
                #0284c7;

            border:
                none;

            font-weight:
                bold;

            cursor:
                pointer;

            transition:
                background 0.2s;
        }


        button:hover {

            background:
                #0369a1;
        }


        .btn-copy {

            background:
                #059669;

            width:
                auto;

            padding:
                6px 12px;

            font-size:
                0.8rem;

            margin-top:
                8px;

            display:
                inline-block;
        }


        .btn-copy:hover {

            background:
                #047857;
        }


        .output {

            background:
                #0f172a;

            padding:
                20px;

            border-radius:
                8px;

            white-space:
                pre-wrap;

            word-break:
                break-word;

            line-height:
                1.6;
        }


        .pipeline {

            background:
                #0f172a;

            padding:
                15px;

            border-radius:
                8px;

            font-family:
                monospace;

            font-size:
                0.9rem;

            white-space:
                pre-wrap;

            word-break:
                break-word;
        }


        .pipeline-item {

            padding:
                5px 0;

            border-bottom:
                1px solid #334155;
        }


        .pipeline-item:last-child {

            border-bottom:
                none;
        }


        .status {

            padding:
                10px;

            border-radius:
                6px;

            background:
                #164e63;

            margin-bottom:
                20px;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>CfCbazar Automated AI Hub</h1>

    <div class="card">

        <form method="post">

            <label for="prompt">
                Enter your prompt:
            </label>

            <textarea
                id="prompt"
                name="prompt"
                placeholder="Ask CfCbazar AI anything..."
                required
            ><?= htmlspecialchars(
                $currentPrompt,
                ENT_QUOTES,
                'UTF-8'
            ) ?></textarea>

            <button type="submit">
                Run AI
            </button>

        </form>

    </div>


    <?php if ($responseMessage !== ''): ?>

        <div class="card">

            <h2>Response</h2>

            <div
                class="output"
                id="ai-output"
            ><?= htmlspecialchars(
                $responseMessage,
                ENT_QUOTES,
                'UTF-8'
            ) ?></div>

            <button
                type="button"
                class="btn-copy"
                onclick="copyAIResponse()"
            >
                Copy Response
            </button>

        </div>

    <?php endif; ?>


    <?php if (!empty($executedPipeline)): ?>

        <div class="card">

            <h2>Execution Pipeline</h2>

            <div class="pipeline">

                <?php foreach (
                    $executedPipeline
                    as $pipelineItem
                ): ?>

                    <div class="pipeline-item">
                        <?= htmlspecialchars(
                            $pipelineItem,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    <?php endif; ?>

</div>


<script>

function copyAIResponse() {

    const output =
        document.getElementById('ai-output');

    if (!output) {
        return;
    }

    const text =
        output.innerText || output.textContent || '';

    if (
        navigator.clipboard &&
        window.isSecureContext
    ) {

        navigator.clipboard
            .writeText(text)
            .then(function () {

                alert('Response copied.');

            })
            .catch(function () {

                fallbackCopy(text);

            });

    } else {

        fallbackCopy(text);
    }
}


function fallbackCopy(text) {

    const textarea =
        document.createElement('textarea');

    textarea.value =
        text;

    textarea.style.position =
        'fixed';

    textarea.style.left =
        '-9999px';

    document.body.appendChild(
        textarea
    );

    textarea.focus();

    textarea.select();

    try {

        document.execCommand('copy');

        alert('Response copied.');

    } catch (error) {

        alert(
            'Unable to copy the response.'
        );

    }

    document.body.removeChild(
        textarea
    );
}

</script>

</body>

</html>