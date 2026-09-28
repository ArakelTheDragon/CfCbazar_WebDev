<?php
require_once __DIR__ . '/../../includes/reusable.php';

$message = '';
$messageType = '';

$memoryFilePath = __DIR__ . '/memory/memory.json';
$topicsDir = __DIR__ . '/memory/topics';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawTopic = $_POST['topic_key'] ?? 'general_topic';
    $rawContent = trim($_POST['raw_content'] ?? '');
    
    // Normalize and sanitize topic key identifier
    $topicKey = strtolower(trim($rawTopic));
    $topicKey = preg_replace('/[^a-z0-9_]/', '_', $topicKey);
    $topicKey = trim($topicKey, '_');
    
    if (empty($topicKey)) {
        $topicKey = 'general_topic';
    }

    if (!empty($rawContent)) {
        // Extract discrete facts line by line
        $lines = explode("\n", str_replace("\r", "", $rawContent));
        $extractedFacts = [];
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed)) {
                // Remove list markers and bullet points
                $cleanFact = preg_replace('/^[\*\-\•\d+\.]+\s*/', '', $trimmed);
                if (strlen($cleanFact) > 2) {
                    $extractedFacts[] = $cleanFact;
                }
            }
        }

        $timestamp = date('c');

        // 1. Update global index (memory/memory.json)
        if (!is_dir(dirname($memoryFilePath))) {
            mkdir(dirname($memoryFilePath), 0755, true);
        }

        $memoryData = [];
        if (file_exists($memoryFilePath)) {
            $memoryData = json_decode(file_get_contents($memoryFilePath), true) ?? [];
        }

        if (!isset($memoryData['topics'])) {
            $memoryData['topics'] = [];
        }

        if (!isset($memoryData['topics'][$topicKey])) {
            $memoryData['topics'][$topicKey] = [
                'created_at' => $timestamp,
                'last_updated' => $timestamp,
                'facts' => []
            ];
        }

        foreach ($extractedFacts as $fact) {
            $memoryData['topics'][$topicKey]['facts'][] = [
                'fact' => $fact,
                'source' => 'gui_seed',
                'confidence' => 1.0,
                'added_at' => $timestamp
            ];
        }
        $memoryData['topics'][$topicKey]['last_updated'] = $timestamp;

        file_put_contents($memoryFilePath, json_encode($memoryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Append turn object to specific topic log (memory/topics/{topic_key}.json)
        if (!is_dir($topicsDir)) {
            mkdir($topicsDir, 0755, true);
        }

        $topicFilePath = $topicsDir . '/' . $topicKey . '.json';
        $topicData = [];
        if (file_exists($topicFilePath)) {
            $topicData = json_decode(file_get_contents($topicFilePath), true) ?? [];
        }

        $seedEntry = [
            'type' => 'seed_ingestion',
            'timestamp' => $timestamp,
            'source' => 'gui_seed',
            'raw_length' => strlen($rawContent),
            'facts_count' => count($extractedFacts),
            'statements' => $extractedFacts
        ];

        $topicData[] = $seedEntry;

        file_put_contents($topicFilePath, json_encode($topicData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $message = "Successfully extracted " . count($extractedFacts) . " facts into topic '{$topicKey}' across memory.json and memory/topics/{$topicKey}.json.";
        $messageType = "success";
    } else {
        $message = "Please provide knowledge content to seed.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI System - Knowledge Seeder</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #1e293b; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        h1 { margin-top: 0; color: #38bdf8; font-size: 1.5rem; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #94a3b8; }
        input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #f8fafc; box-sizing: border-box; font-family: inherit; }
        textarea { height: 260px; resize: vertical; line-height: 1.5; }
        button { background: #0284c7; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #0369a1; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #064e3b; color: #6ee7b7; border: 1px solid #047857; }
        .alert-error { background: #7f1d1d; color: #fca5a5; border: 1px solid #b91c1c; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Topic Memory Seeder</h1>
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <form action="seed.php" method="POST">
            <div class="form-group">
                <label for="topic_key">Topic Slug / Identifier</label>
                <input type="text" id="topic_key" name="topic_key" placeholder="e.g., php_programming, artificial_intelligence" required>
            </div>
            
            <div class="form-group">
                <label for="raw_content">Raw Information / Documentation</label>
                <textarea id="raw_content" name="raw_content" placeholder="Paste raw text, bullet points, or documentation here..." required></textarea>
            </div>
            
            <button type="submit">Ingest Knowledge & Update Memory</button>
        </form>
    </div>
</body>
</html>
