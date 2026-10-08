<?php

declare(strict_types=1);

/**
 * Eval suite for the SkillData pipeline (memory-first, local by default).
 *
 * CLI:  php eval.php
 * Web:  /eval.php
 * Opt:  ?or=1  — do not force OpenRouter off (uses live features.php)
 *
 * Temporarily forces openrouter_enabled=false + knowledge_mode=local unless ?or=1,
 * then restores config/features.php.
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

$featuresPath = __DIR__ . '/config/features.php';
$featuresBackup = is_file($featuresPath) ? file_get_contents($featuresPath) : null;

$allowOpenRouter = false;
if (PHP_SAPI === 'cli') {
    $allowOpenRouter = in_array('--or', $argv ?? [], true);
} else {
    $allowOpenRouter = isset($_GET['or']) && (string)$_GET['or'] === '1';
}

if (!$allowOpenRouter) {
    $forceLocal = <<<'PHP'
<?php
declare(strict_types=1);
return [
    "openrouter_enabled" => false,
    "knowledge_mode" => "local",
    "conversation_enabled" => false,
    "conversation_max_turns" => 10,
    "spell_correct_enabled" => true,
    "adaptive_thresholds_enabled" => true,
    "weighted_sufficiency_enabled" => true,
    "type_diversity_enabled" => true,
    "intent_synthesis_priority_enabled" => true,
];
PHP;
    file_put_contents($featuresPath, $forceLocal);
}

/**
 * @return list<array<string, mixed>>
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
            'soft_topic' => true,
        ],
        [
            'id' => 'spell_vector',
            'prompt' => 'what are vecrot embedings',
            'expect_topic' => 'vector_embeddings',
            'expect_intent' => 'define_concept',
            'must_include' => ['vector'],
            'min_len' => 30,
            'soft_topic' => true,
        ],
        [
            'id' => 'build_trust',
            'prompt' => 'How do I build trust?',
            'expect_topic' => 'building_trust',
            'expect_intent' => 'explain_process',
            'must_include' => ['trust'],
            'min_len' => 40,
            'soft_topic' => true,
        ],
        [
            'id' => 'food',
            'prompt' => 'What is food?',
            'expect_topic' => 'food',
            'expect_intent' => 'define_concept',
            'must_include' => [],
            'min_len' => 20,
            'soft_topic' => true,
        ],
        [
            'id' => 'php',
            'prompt' => 'What is PHP programming?',
            'expect_topic' => 'php_programming',
            'expect_intent' => 'define_concept',
            'must_include' => ['php'],
            'min_len' => 30,
            'soft_topic' => true,
        ],
        [
            'id' => 'multipart_sky',
            'prompt' => 'I have a Sky Stream Puck. How do I connect it to wifi and fix a software error?',
            'expect_topic' => 'sky_stream_puck',
            'expect_intent' => 'explain_process',
            'must_include' => [],
            'min_len' => 30,
            'soft_topic' => true,
        ],
        [
            'id' => 'php_page_bare',
            'prompt' => 'Make me a simple PHP page',
            'expect_topic' => 'php_page',
            'expect_intent' => 'generate_code',
            'must_include' => ['php'],
            'min_len' => 40,
            'soft_topic' => true,
        ],
        [
            'id' => 'php_page_specific',
            'prompt' => 'Make me a PHP page that says "Beta AI system"',
            'expect_topic' => 'php_page',
            'expect_intent' => 'generate_code',
            'must_include' => ['Beta AI system'],
            'min_len' => 40,
            'soft_topic' => true,
        ],
        [
            'id' => 'empty_guard',
            'prompt' => '   ',
            'expect_topic' => null,
            'expect_intent' => null,
            'must_include' => [],
            'min_len' => 5,
            'soft_topic' => true,
        ],
    ];
}

$results = [];
$pass = 0;
$fail = 0;

$memory = new MemoryStore(__DIR__ . '/memory/memory.json');
$pu = new PromptUnderstandingSkill();
$knowledge = new KnowledgeSkill();
$responseSkill = new ResponseUnderstandingSkill();
$router = new Router();

foreach (evalCases() as $case) {
    $row = [
        'id' => $case['id'],
        'prompt' => $case['prompt'],
        'ok' => true,
        'notes' => [],
        'topic' => '',
        'intent' => '',
        'hits' => 0,
        'confidence' => null,
        'sufficient' => null,
        'answer_head' => '',
    ];

    try {
        $prompt = (string)$case['prompt'];

        if (trim($prompt) === '') {
            $out = $router->handle($prompt);
            $row['answer_head'] = substr(str_replace("\n", ' ', $out), 0, 100);
            if (strlen(trim($out)) < (int)$case['min_len']) {
                $row['ok'] = false;
                $row['notes'][] = 'empty-prompt response too short';
            }
        } else {
            $features = is_file($featuresPath) ? (require $featuresPath) : [];
            $spellOn = !isset($features['spell_correct_enabled']) || !empty($features['spell_correct_enabled']);
            $corrected = $spellOn
                ? SpellCorrector::correct($prompt, $memory->listTopics())
                : $prompt;

            $data = SkillData::create($corrected, $prompt);
            $data = $pu->process($data, $memory);

            // Knowledge may further refine topic
            $data = $knowledge->process($data, $memory);
            $draft = $data->draftAnswer();

            if (method_exists($responseSkill, 'process')) {
                $data = $responseSkill->process($data);
                $answer = $data->finalAnswer();
            } else {
                $answer = $draft;
            }

            $topic = $data->topic();
            $intent = (string)$data->get('intent', '');
            $hits = is_array($data->get('memory_hits')) ? count($data->get('memory_hits')) : 0;
            $confidence = $data->get('answer_confidence', null);
            $sufficient = $data->get('memory_sufficient', null);

            $row['topic'] = $topic;
            $row['intent'] = $intent;
            $row['hits'] = $hits;
            $row['confidence'] = is_numeric($confidence) ? (float)$confidence : null;
            $row['sufficient'] = is_bool($sufficient) ? $sufficient : null;
            $row['answer_head'] = substr(str_replace("\n", ' ', $answer), 0, 120);

            if ($case['expect_topic'] !== null) {
                $expected = (string)$case['expect_topic'];
                $soft = !empty($case['soft_topic']);
                if ($topic !== $expected) {
                    // Accept if expected is substring of topic or resolved alias
                    $resolved = $memory->resolveTopic($expected);
                    if ($topic !== $resolved && !str_contains($topic, $expected) && !str_contains($expected, $topic)) {
                        $row['notes'][] = "topic got '{$topic}', expected '{$expected}'";
                        if (!$soft) {
                            $row['ok'] = false;
                        }
                    }
                }
            }

            if ($case['expect_intent'] !== null && $intent !== $case['expect_intent']) {
                $row['notes'][] = "intent got '{$intent}', expected '{$case['expect_intent']}'";
                // intent mismatch is soft unless answer is empty
                if (strlen(trim($answer)) < 10) {
                    $row['ok'] = false;
                }
            }

            if (strlen(trim($answer)) < (int)$case['min_len']) {
                $row['notes'][] = 'answer shorter than min_len';
                $row['ok'] = false;
            }

            $lower = strtolower($answer);
            foreach ((array)$case['must_include'] as $needle) {
                $needle = (string)$needle;
                if ($needle === '') {
                    continue;
                }
                if (!str_contains($lower, strtolower($needle))) {
                    $row['notes'][] = "missing phrase '{$needle}'";
                    $row['ok'] = false;
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

// Restore features.php
if ($featuresBackup !== null) {
    file_put_contents($featuresPath, $featuresBackup);
}

$modeLabel = $allowOpenRouter ? 'OpenRouter allowed (live features)' : 'OpenRouter forced off for this run';
$isCli = (PHP_SAPI === 'cli');

if ($isCli) {
    echo "AI System Eval — pass={$pass} fail={$fail} total=" . count($results) . " — {$modeLabel}\n";
    foreach ($results as $r) {
        $status = $r['ok'] ? 'PASS' : 'FAIL';
        $conf = $r['confidence'] !== null ? sprintf('%.0f%%', $r['confidence'] * 100) : '-';
        $suf = $r['sufficient'] === null ? '-' : ($r['sufficient'] ? 'yes' : 'no');
        echo "[{$status}] {$r['id']} topic=" . ($r['topic'] ?: '-')
            . " intent=" . ($r['intent'] ?: '-')
            . " hits={$r['hits']} conf={$conf} suf={$suf}\n";
        if ($r['notes'] !== []) {
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
        .pass { color: #6bcb77; }
        .fail { color: #e74c3c; }
        .meta { color: #9aa7b5; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; font-size: 13px; }
        th, td { border: 1px solid #2a3540; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { background: #1a2330; }
        code { color: #9cdcfe; }
        a { color: #6cb6ff; }
    </style>
</head>
<body>
    <h1>AI System Eval (SkillData pipeline)</h1>
    <p class="meta">
        <span class="pass">pass=<?= (int)$pass ?></span>
        ·
        <span class="fail">fail=<?= (int)$fail ?></span>
        · total=<?= count($results) ?>
        · <?= htmlspecialchars($modeLabel, ENT_QUOTES, 'UTF-8') ?>
        · <a href="?or=1">run with OpenRouter allowed</a>
    </p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Topic / Intent</th>
                <th>Hits</th>
                <th>Conf</th>
                <th>Suf</th>
                <th>Notes</th>
                <th>Answer head</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><code><?= htmlspecialchars((string)$r['id'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td class="<?= !empty($r['ok']) ? 'pass' : 'fail' ?>"><?= !empty($r['ok']) ? 'PASS' : 'FAIL' ?></td>
                <td>
                    <?= htmlspecialchars((string)($r['topic'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>
                    <br><small><?= htmlspecialchars((string)($r['intent'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></small>
                </td>
                <td><?= (int)$r['hits'] ?></td>
                <td><?= $r['confidence'] !== null ? htmlspecialchars(number_format($r['confidence'] * 100, 0) . '%', ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><?php
                    if ($r['sufficient'] === null) {
                        echo '—';
                    } else {
                        echo $r['sufficient'] ? 'yes' : 'no';
                    }
                ?></td>
                <td><?= htmlspecialchars(implode('; ', (array)$r['notes']), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$r['answer_head'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
