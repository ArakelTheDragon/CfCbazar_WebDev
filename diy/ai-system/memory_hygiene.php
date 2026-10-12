<?php

declare(strict_types=1);

/**
 * Memory hygiene: merge noisy topics, prune junk facts, seed solid cores.
 * Run once (browser or CLI): php memory_hygiene.php
 */

require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/LocalEmbedder.php';
require_once __DIR__ . '/core/SkillData.php';
require_once __DIR__ . '/core/MemoryStore.php';
require_once __DIR__ . '/skills/FactSkill.php';

$isCli = (PHP_SAPI === 'cli');
$log = [];

function logMsg(array &$log, string $msg): void
{
    $log[] = $msg;
}

$memory = new MemoryStore(__DIR__ . '/memory/memory.json');

// ---------------------------------------------------------------------------
// 1) Merge maps: secondary → primary
// ---------------------------------------------------------------------------
$merges = [
    'a_sky_stream_puck' => 'sky_stream_puck',
    'i_get_a_software_error_on' => 'sky_stream_puck',
    'to_fix_a_software_error_on' => 'sky_stream_puck',
    'make_me_a_simple_html_page' => 'html_page',
    'explain_quantum_computing_in_simple_english' => 'quantum_computing',
    'you_know_about_underwater_volcanoes' => 'underwater_volcanoes',
    'you_know_about_the_cfcbazar_ai' => 'cfcbazar_ai_system',
    'vecrot_embedings' => 'vector_embeddings',
    'vecrot_embeddings' => 'vector_embeddings',
    'vector_embedings' => 'vector_embeddings',
];

$topicsDir = __DIR__ . '/memory/topics';

foreach ($merges as $secondary => $primary) {
    $secFile = $topicsDir . '/' . $secondary . '.json';
    if (!is_file($secFile)) {
        logMsg($log, "skip merge (missing): {$secondary}");
        continue;
    }
    $secFacts = $memory->getTopicFacts($secondary);
    $merged = $memory->mergeTopicFacts($primary, $secFacts);
    @unlink($secFile);
    // Refresh index entry for removed topic by rebuilding later
    logMsg($log, "merged {$secondary} → {$primary} (" . count($merged) . " facts)");
}

// ---------------------------------------------------------------------------
// 2) Prune junk fact patterns from all topics
// ---------------------------------------------------------------------------
$junkPatterns = [
    '/^user safety:\s*safe$/i',
    '/^of course\.?$/i',
    '/^here is a simple html page you can use:?$/i',
    '/^make me a simple/i',
    '/^what is a sky stream puck\?$/i',
    '/^i get a software error/i',
    '/^how to fix a software error/i',
    '/^hello!?$/i',
    '/^hi!?$/i',
    '/^good morning$/i',
    '/^thanks for your help$/i',
    '/^\/remember$/i',
    '/^what can you help me with\?$/i',
    '/^what do you know about/i',
    '/^explain quantum computing/i',
    '/fasttext/i', // off-topic under php for our use case when alone
];

foreach ($memory->listTopics() as $topic) {
    $facts = $memory->getTopicFacts($topic);
    $kept = [];
    $removed = 0;
    foreach ($facts as $fact) {
        if (!is_array($fact)) {
            continue;
        }
        $content = trim((string)($fact['content'] ?? $fact['value'] ?? ''));
        if ($content === '') {
            $removed++;
            continue;
        }
        // Incomplete tails
        if (preg_match('/:\s*$/', $content) || preg_match('/only if it:\s*$/i', $content)) {
            $removed++;
            continue;
        }
        $junk = false;
        foreach ($junkPatterns as $re) {
            if (preg_match($re, $content)) {
                $junk = true;
                break;
            }
        }
        // Prompt-echo short questions stored as facts
        if (!$junk && self_isPromptEcho($content)) {
            $junk = true;
        }
        if ($junk) {
            $removed++;
            continue;
        }
        $kept[] = SkillData::normalizeFact($fact);
    }
    if ($removed > 0) {
        $memory->setTopicFacts($topic, $kept);
        logMsg($log, "pruned {$topic}: removed {$removed}, kept " . count($kept));
    }
}

function self_isPromptEcho(string $content): bool
{
    $c = strtolower(trim($content));
    if (strlen($c) < 80 && str_ends_with($c, '?')) {
        return true;
    }
    if (preg_match('/^(what|how|why|when|where|who|can you|do you|make me|create|write)\b/i', $c)
        && strlen($c) < 100) {
        return true;
    }
    return false;
}

// ---------------------------------------------------------------------------
// 3) Seed solid core topics (merge, do not wipe unrelated knowledge)
// ---------------------------------------------------------------------------
$seeds = [
    'vector_embeddings' => [
        'Vector embeddings are dense numeric vectors that represent the meaning of text, images, or other data in a continuous space.',
        'Similar items have embeddings that are close together when measured with cosine similarity or Euclidean distance.',
        'Embeddings let systems search by meaning (semantic search) instead of only exact keyword match.',
        'This AI system builds local embeddings in pure PHP (1536 dimensions) so memory can be searched without external embedding APIs.',
        'When a user asks a question, the system embeds the query and ranks stored facts by vector similarity plus keyword overlap.',
    ],
    'building_trust' => [
        'Building trust means consistently showing reliability, honesty, and competence over time.',
        'Keep promises and follow through on small commitments; reliability compounds.',
        'Be honest about delays and mistakes; transparency prevents surprise and rebuilds credibility.',
        'Show competence by delivering quality work and clear communication.',
        'Listen actively and respect others’ time and boundaries to strengthen relational trust.',
    ],
    'food' => [
        'Food is material that living organisms consume to obtain energy and nutrients for growth and maintenance.',
        'Macronutrients include carbohydrates, proteins, and fats; micronutrients include vitamins and minerals.',
        'A balanced diet combines variety, moderation, and adequate hydration.',
        'Food safety involves proper storage, cooking temperatures, and hygiene to reduce illness risk.',
    ],
    'php_programming' => [
        'PHP is a server-side scripting language widely used to build dynamic websites and APIs.',
        'PHP scripts run on the server and typically send HTML, JSON, or other responses to the client.',
        'Modern PHP supports strict types, classes, namespaces, and Composer for dependency management on capable hosts.',
        'Shared hosting often restricts Composer and extensions, so pure-PHP solutions are required in those environments.',
        'This CfCbazar AI system is implemented in pure PHP with JSON memory and local vector embeddings.',
    ],
    'sky_stream_puck' => [
        'A Sky Stream Puck is a compact streaming device from Sky used to watch TV and on-demand content.',
        'Connect the puck to power and to the TV with HDMI, then follow on-screen setup for network access.',
        'To join Wi‑Fi: open network settings on the puck, select the network, and enter the password.',
        'Software errors often improve after a restart: unplug power for 30 seconds, then plug back in.',
        'If problems continue, check for system updates, re-enter Wi‑Fi credentials, or factory-reset only as a last step (you will need account details again).',
    ],
    'html_page' => [
        'An HTML page is a text file with markup that browsers render as a web page.',
        'A minimal HTML5 document includes <!DOCTYPE html>, <html>, <head> with a <title>, and a <body>.',
        'Save simple pages as index.html and open them in a browser or serve them from a web host.',
    ],
    'php_page' => [
        'A simple PHP page can mix PHP logic with HTML output in a single .php file.',
        'Use declare(strict_types=1) and htmlspecialchars when echoing user-related strings into HTML.',
        'On shared hosting, pure PHP without Composer is the most portable approach.',
    ],
    'cfcbazar_ai_system' => [
        'The CfCbazar AI system is a pure-PHP assistant with a SkillData pipeline: Prompt Understanding → Knowledge → Response Understanding.',
        'Long-term knowledge is stored as JSON topics under memory/topics with local vector embeddings.',
        'OpenRouter is optional and used in hybrid mode only to fill gaps; local memory is preferred.',
        'FactSkill extracts statements and embeddings; MemoryStore stores and retrieves them by topic and similarity.',
    ],
];

foreach ($seeds as $topic => $lines) {
    $facts = [];
    foreach ($lines as $line) {
        $facts[] = SkillData::normalizeFact([
            'content' => $line,
            'type' => 'statement',
            'source' => 'hygiene_seed',
            'confidence' => 0.95,
            'embedding' => FactSkill::embed($line),
            'created_at' => gmdate('c'),
        ]);
    }
    $merged = $memory->mergeTopicFacts($topic, $facts);
    logMsg($log, "seeded {$topic}: now " . count($merged) . " facts");
}

// ---------------------------------------------------------------------------
// 4) Rebuild index from remaining topic files
// ---------------------------------------------------------------------------
$index = [];
foreach (glob($topicsDir . '/*.json') ?: [] as $file) {
    $topic = basename($file, '.json');
    $facts = $memory->getTopicFacts($topic);
    $index[$topic] = [
        'topic' => $topic,
        'file' => 'topics/' . $topic . '.json',
        'fact_count' => count($facts),
        'last_updated' => gmdate('c'),
    ];
}
file_put_contents(
    __DIR__ . '/memory/memory.json',
    json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
logMsg($log, 'index rebuilt: ' . count($index) . ' topics');

// Output
if ($isCli) {
    echo "MEMORY HYGIENE\n";
    foreach ($log as $line) {
        echo " - {$line}\n";
    }
    exit(0);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Memory Hygiene</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; background: #0f1419; color: #e7ecf1; }
        li { margin: 4px 0; }
        code { color: #9cdcfe; }
    </style>
</head>
<body>
    <h1>Memory Hygiene</h1>
    <p>Completed. Re-run <code>eval.php</code> next.</p>
    <ul>
        <?php foreach ($log as $line): ?>
            <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
