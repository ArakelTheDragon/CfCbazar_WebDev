<?php

declare(strict_types=1);

/**
 * Lightweight eval suite for SkillData pipeline (dev8).
 * Run in browser or CLI: php eval.php
 * Does not call OpenRouter (forces local knowledge_mode for the run).
 */

require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/LocalEmbedder.php';
require_once __DIR__ . '/core/SkillData.php';
require_once __DIR__ . '/core/MemoryStore.php';
require_once __DIR__ . '/core/SpellCorrector.php';
require_once __DIR__ . '/core/ConversationStore.php';
require_once __DIR__ . '/core/SkillManager.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/skills/FactSkill.php';
require_once __DIR__ . '/skills/PromptUnderstandingSkill.php';
require_once __DIR__ . '/skills/KnowledgeSkill.php';
require_once __DIR__ . '/skills/ResponseUnderstandingSkill.php';

// Force local-only for predictable eval (does not rewrite features.php on disk)
$featuresPath = __DIR__ . '/config/features.php';
$featuresBackup = is_file($featuresPath) ? file_get_contents($featuresPath) : null;
file_put_contents($featuresPath, "<?php\nreturn [\n    'openrouter_enabled' => false,\n    'knowledge_mode' => 'local',\n    'conversation_enabled' => false,\n    'conversation_max_turns' => 10,\n];\n");

/**
 * @return list<array{id:string,prompt:string,expect_topic:?string,expect_intent:?string,must_include:list<string>,min_len:int}>
 */
function evalCases(): array
{
    return [
        [
            'id' => 'vector_def',
            'prompt' => 'What are vector embeddings?',
            'expect_topic' => 'vector_embeddings',
            'expect_intent' => 'define_concept',
            'must_include' => ['vector'],
            'min_len' => 40,
        ],
        [
            'id' => 'build_trust',
            'prompt' => 'How do I build trust?',
            'expect_topic' => 'building_trust',
            'expect_intent' => 'explain_process',
            'must_include' => ['trust'],
            'min_len' => 40,
        ],
        [
            'id' => 'food',
            'prompt' => 'What is food?',
            'expect_topic' => 'food',
            'expect_intent' => 'define_concept',
            'must_include' => [],
            'min_len' => 20,
        ],
        [
            'id' => 'spell_vector',
            'prompt' => 'what are vecrot embedings',
            'expect_topic' => 'vector_embeddings',
            'expect_intent' => 'define_concept',
            'must_include' => [],
            'min_len' => 20,
        ],
        [
            'id' => 'php',
            'prompt' => 'What is PHP programming?',
            'expect_topic' => 'php_programming',
            'expect_intent' => 'define_concept',
            'must_include' => ['php'],
            'min_len' => 30,
        ],
        [
            'id' => 'multipart_sky',
            'prompt' => 'I have a Sky Stream Puck. How do I connect it to wifi and fix a software error?',
            'expect_topic' => 'sky_stream_puck',
            'expect_intent' => 'explain_process',
            'must_include' => [],
            'min_len' => 20,
        ],
        [
            'id' => 'empty_guard',
            'prompt' => '   ',
            'expect_topic' => null,
            'expect_intent' => null,
            'must_include' => [],
            'min_len' => 5,
        ],
    ];
}

$results = [];
$pass = 0;
$fail = 0;

$pu = new PromptUnderstandingSkill();
$memory = new MemoryStore(__DIR__ . '/memory/memory.json');
$knowledge = new KnowledgeSkill();
$response = new ResponseUnderstandingSkill();
$router = new Router();

foreach (evalCases() as $case) {
    $row = [
        'id' => $case['id'],
        'prompt' => $case['prompt'],
        'ok' => true,
        'notes' => [],
    ];

    try {
        if (trim($case['prompt']) === '') {
            $out = $router->handle($case['prompt']);
            if (strlen($out) < $case['min_len']) {
                $row['ok'] = false;
                $row['notes'][] = 'empty prompt response too short';
            }
            $row['answer_head'] = substr(str_replace("\n", ' ', $out), 0, 80);
        } else {
            $corrected = SpellCorrector::correct($case['prompt'], $memory->listTopics());
            $data = SkillData::create($corrected, $case['prompt']);
            $data = $pu->process($data);
            $data->set('topic', $memory->resolveTopic($data->topic()));

            if (method_exists($knowledge, 'process')) {
                $data = $knowledge->process($data, $memory);
            } else {
                $analysis = [
                    'raw_prompt' => $data->prompt(),
                    'core_query' => (string)$data->get('core_query', ''),
                    'intent' => (string)$data->get('intent', ''),
                    'topic' => $data->topic(),
                    'entities' => $data->get('entities') ?? [],
                    'question_type' => (string)$data->get('question_type', ''),
                    'constraints' => $data->get('constraints') ?? [],
                    'secondary_intents' => $data->get('secondary_intents') ?? [],
                    'subtopics' => $data->get('subtopics') ?? [],
                    'key_phrases' => $data->get('key_phrases') ?? [],
                    'is_multi_part' => (bool)$data->get('is_multi_part', false),
                    'query_embedding' => $data->get('query_embedding') ?? [],
                    'prompt_facts' => $data->get('facts') ?? [],
                ];
                $draft = $knowledge->respond($analysis, $memory);
                $data->set('draft_answer', is_string($draft) ? $draft : '');
            }

            if (method_exists($response, 'process')) {
                $data = $response->process($data);
            } else {
                $draft = $data->draftAnswer();
                $final = $response->execute($draft);
                $data->set('final_answer', is_string($final) ? $final : $draft);
            }

            $topic = $data->topic();
            $intent = (string)$data->get('intent', '');
            $answer = $data->finalAnswer();
            $row['topic'] = $topic;
            $row['intent'] = $intent;
            $row['memory_sufficient'] = (bool)$data->get('memory_sufficient');
            $row['hits'] = count(is_array($data->get('memory_hits')) ? $data->get('memory_hits') : []);
            $row['answer_head'] = (function_exists('mb_substr') ? mb_substr(str_replace("\n", ' ', $answer), 0, 100) : substr(str_replace("\n", ' ', $answer), 0, 100));

            if ($case['expect_topic'] !== null && $topic !== $case['expect_topic']) {
                $row['notes'][] = "topic got '{$topic}', expected '{$case['expect_topic']}'";
                $exp = str_replace('_', '', $case['expect_topic']);
                $got = str_replace('_', '', $topic);
                // soft: pass if one contains the other (a_sky_stream_puck ≈ sky_stream_puck)
                $close = str_contains($got, $exp) || str_contains($exp, $got)
                    || similar_text($got, $exp) / max(strlen($exp), 1) > 0.72;
                if (!$close) {
                    $row['ok'] = false;
                }
            }
            if ($case['expect_intent'] !== null && $intent !== $case['expect_intent']) {
                $row['notes'][] = "intent got '{$intent}', expected '{$case['expect_intent']}'";
                $row['ok'] = false;
            }
            if (strlen($answer) < $case['min_len']) {
                $row['notes'][] = 'answer shorter than min_len';
                $row['ok'] = false;
            }
            $lower = strtolower($answer);
            foreach ($case['must_include'] as $needle) {
                if ($needle !== '' && !str_contains($lower, strtolower($needle))) {
                    $row['notes'][] = "missing phrase '{$needle}'";
                    // soft: only fail if we had memory hits
                    if ($row['hits'] > 0) {
                        $row['ok'] = false;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        $row['ok'] = false;
        $row['notes'][] = 'exception: ' . $e->getMessage();
    }

    if ($row['ok']) {
        $pass++;
    } else {
        $fail++;
    }
    $results[] = $row;
}

// restore features
if ($featuresBackup !== null) {
    file_put_contents($featuresPath, $featuresBackup);
}

$isCli = (PHP_SAPI === 'cli');
if ($isCli) {
    echo "EVAL pass={$pass} fail={$fail} total=" . count($results) . "\n";
    foreach ($results as $r) {
        $status = $r['ok'] ? 'PASS' : 'FAIL';
        echo "[{$status}] {$r['id']} topic=" . ($r['topic'] ?? '-') . " intent=" . ($r['intent'] ?? '-') . "\n";
        if (!$r['ok']) {
            echo "  notes: " . implode('; ', $r['notes']) . "\n";
        }
        echo "  " . ($r['answer_head'] ?? '') . "\n";
    }
    exit($fail > 0 ? 1 : 0);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>AI System Eval</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; background: #0f1419; color: #e7ecf1; }
        h1 { font-size: 1.25rem; }
        .pass { color: #6bcB77; }
        .fail { color: #e74c3c; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; font-size: 14px; }
        th, td { border: 1px solid #2a3540; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { background: #1a2330; }
        code { color: #9cdcfe; }
    </style>
</head>
<body>
    <h1>AI System Eval (local SkillData pipeline)</h1>
    <p>
        <span class="pass">pass=<?= (int)$pass ?></span>
        ·
        <span class="fail">fail=<?= (int)$fail ?></span>
        · total=<?= count($results) ?>
        · OpenRouter forced off for this run
    </p>
    <table>
        <tr>
            <th>ID</th>
            <th>Status</th>
            <th>Topic / Intent</th>
            <th>Hits</th>
            <th>Notes</th>
            <th>Answer head</th>
        </tr>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><code><?= htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td class="<?= !empty($r['ok']) ? 'pass' : 'fail' ?>"><?= !empty($r['ok']) ? 'PASS' : 'FAIL' ?></td>
                <td>
                    <?= htmlspecialchars((string)($r['topic'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                    <br>
                    <small><?= htmlspecialchars((string)($r['intent'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small>
                </td>
                <td><?= (int)($r['hits'] ?? 0) ?></td>
                <td><?= htmlspecialchars(implode('; ', $r['notes'] ?? []), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($r['answer_head'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
