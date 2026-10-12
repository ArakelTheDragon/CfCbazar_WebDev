<?php

declare(strict_types=1);

/**
 * Knowledge seeder — write facts + local embeddings into MemoryStore.
 * No external includes. OpenRouter not used.
 */

require_once __DIR__ . '/core/MemoryStore.php';
require_once __DIR__ . '/core/SpellCorrector.php';
require_once __DIR__ . '/skills/FactSkill.php';
require_once __DIR__ . '/core/SkillData.php';

$message = '';
$messageType = '';
$seededCount = 0;
$resolvedTopic = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $rawTopic = (string)($_POST['topic_key'] ?? 'general_topic');
    $rawContent = trim((string)($_POST['raw_content'] ?? ''));

    // Normalize topic slug
    $topicKey = strtolower(trim($rawTopic));
    $topicKey = preg_replace('/[^a-z0-9_]+/', '_', $topicKey) ?? '';
    $topicKey = trim($topicKey, '_');
    if ($topicKey === '') {
        $topicKey = 'general_topic';
    }

    // Spell-correct topic tokens (vecrot_embedings → vector_embeddings)
    $topicReadable = str_replace('_', ' ', $topicKey);
    $topicKey = preg_replace(
        '/[^a-z0-9_]+/',
        '_',
        strtolower(SpellCorrector::correct($topicReadable, []))
    ) ?? $topicKey;
    $topicKey = trim((string)$topicKey, '_') ?: 'general_topic';

    if ($rawContent === '') {
        $message = 'Please provide knowledge content to seed.';
        $messageType = 'error';
    } else {
        try {
            $memory = new MemoryStore(__DIR__ . '/memory/memory.json');

            // Fuzzy-resolve against existing topics
            $topicKey = $memory->resolveTopic($topicKey);
            $resolvedTopic = $topicKey;

            // Split content into statement lines, then FactSkill embeds each
            $lines = preg_split('/\r\n|\r|\n/', $rawContent) ?: [];
            $chunks = [];
            foreach ($lines as $line) {
                $line = trim((string)$line);
                $line = preg_replace('/^[\*\-\x{2022}\d\.\)\s]+/u', '', $line) ?? $line;
                $line = trim($line);
                if ($line !== '' && (function_exists('mb_strlen') ? mb_strlen($line) : strlen($line)) > 2) {
                    $chunks[] = $line;
                }
            }

            // Also accept paragraph blocks if few lines
            if ($chunks === [] && $rawContent !== '') {
                $chunks = [trim($rawContent)];
            }

            $textForFacts = implode("\n", $chunks);
            $facts = FactSkill::extractFactsFromText($textForFacts, 'gui_seed', 0.95);

            // Normalize to SkillData Fact shape + ensure embeddings
            $normalized = [];
            foreach ($facts as $fact) {
                if (!is_array($fact)) {
                    continue;
                }
                $fact = SkillData::normalizeFact($fact);
                $fact['source'] = 'gui_seed';
                $fact['confidence'] = 0.95;
                if (empty($fact['embedding']) || !is_array($fact['embedding'])) {
                    $fact['embedding'] = FactSkill::embed((string)$fact['content']);
                }
                if ($fact['content'] !== '') {
                    $normalized[] = $fact;
                }
            }
            $facts = $normalized;

            if ($facts === []) {
                $message = 'No usable facts could be extracted from the content.';
                $messageType = 'error';
            } else {
                $merged = $memory->mergeTopicFacts($topicKey, $facts);
                $seededCount = count($facts);
                $message = "Seeded {$seededCount} fact(s) into topic '{$topicKey}' "
                    . '(topic now has ' . count($merged) . ' fact(s), with local embeddings).';
                $messageType = 'success';
            }
        } catch (Throwable $e) {
            $message = 'Seed failed: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI System - Knowledge Seeder</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #1e293b; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        h1 { margin-top: 0; color: #38bdf8; font-size: 1.5rem; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #94a3b8; }
        input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; box-sizing: border-box; font-family: inherit; }
        textarea { height: 260px; resize: vertical; line-height: 1.5; }
        button { background: #0284c7; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        button:hover { background: #0369a1; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #064e3b; color: #6ee7b7; border: 1px solid #047857; }
        .alert-error { background: #7f1d1d; color: #fca5a5; border: 1px solid #b91c1c; }
        .hint { color: #94a3b8; font-size: 0.9rem; margin-top: 8px; }
        a { color: #38bdf8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Topic Memory Seeder</h1>
        <p class="hint">Adds facts with <strong>local embeddings</strong> via FactSkill + MemoryStore. No OpenRouter.</p>
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form action="seed.php" method="POST">
            <div class="form-group">
                <label for="topic_key">Topic slug</label>
                <input type="text" id="topic_key" name="topic_key" placeholder="e.g. vector_embeddings, php_programming" required
                       value="<?= htmlspecialchars($resolvedTopic !== '' ? $resolvedTopic : '') ?>">
            </div>

            <div class="form-group">
                <label for="raw_content">Facts / documentation (one statement per line preferred)</label>
                <textarea id="raw_content" name="raw_content" placeholder="Paste facts, bullet points, or short paragraphs..." required></textarea>
            </div>

            <button type="submit">Ingest into memory</button>
        </form>
        <p class="hint" style="margin-top:20px;"><a href="index.php">← Back to AI</a> · <a href="consolidate.php">Consolidate topics</a></p>
    </div>
</body>
</html>
