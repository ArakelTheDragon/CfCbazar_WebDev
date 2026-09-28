<?php

declare(strict_types=1);

require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/MemoryStore.php';
require_once __DIR__ . '/skills/FactSkill.php';

class MemoryConsolidator
{
    private string $memoryIndexFile;
    private string $topicsDir;
    private float $similarityThreshold;

    public function __construct(
        string $memoryIndexFile = __DIR__ . '/memory/memory.json',
        string $topicsDir = __DIR__ . '/memory/topics',
        float $similarityThreshold = 0.70
    ) {
        $this->memoryIndexFile = $memoryIndexFile;
        $this->topicsDir = rtrim($topicsDir, '/\\');
        $this->similarityThreshold = $similarityThreshold;
    }

    /**
     * Scans the topics index and finds candidate pairs for merging.
     */
    public function scanForDuplicates(): array
    {
        $index = $this->loadIndex();
        $topicKeys = array_keys($index);
        $candidates = [];
        $visited = [];

        $totalTopics = count($topicKeys);
        for ($i = 0; $i < $totalTopics; $i++) {
            $keyA = $topicKeys[$i];
            if (isset($visited[$keyA])) {
                continue;
            }

            for ($j = $i + 1; $j < $totalTopics; $j++) {
                $keyB = $topicKeys[$j];
                if (isset($visited[$keyB])) {
                    continue;
                }

                $similarity = $this->calculateTopicSimilarity($keyA, $keyB);
                if ($similarity >= $this->similarityThreshold) {
                    $candidates[] = [
                        'primary'    => $keyA,
                        'secondary'  => $keyB,
                        'similarity' => round($similarity * 100, 2),
                        'primary_facts' => $index[$keyA]['fact_count'] ?? 0,
                        'secondary_facts' => $index[$keyB]['fact_count'] ?? 0,
                    ];
                }
            }
        }

        return $candidates;
    }

    /**
     * Merges a secondary topic into a primary topic and updates memory index.
     */
    public function executeMerge(string $primaryKey, string $secondaryKey): array
    {
        $index = $this->loadIndex();

        if (!isset($index[$primaryKey]) || !isset($index[$secondaryKey])) {
            return [
                'success' => false,
                'message' => "One or both topic keys ('{$primaryKey}', '{$secondaryKey}') do not exist in index."
            ];
        }

        $primaryFile   = $this->topicsDir . '/' . $primaryKey . '.json';
        $secondaryFile = $this->topicsDir . '/' . $secondaryKey . '.json';

        $primaryFacts   = file_exists($primaryFile) ? (json_decode((string)file_get_contents($primaryFile), true) ?: []) : [];
        $secondaryFacts = file_exists($secondaryFile) ? (json_decode((string)file_get_contents($secondaryFile), true) ?: []) : [];

        // 1. Merge facts array safely and deduplicate
        $mergedFacts = FactSkill::mergeFacts($primaryFacts, $secondaryFacts);

        // 2. Save merged content back to primary topic JSON
        if (file_put_contents($primaryFile, json_encode($mergedFacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            return [
                'success' => false,
                'message' => "Failed to write merged data to '{$primaryFile}'."
            ];
        }

        // 3. Delete secondary topic JSON file
        if (file_exists($secondaryFile)) {
            @unlink($secondaryFile);
        }

        // 4. Update memory.json index
        $index[$primaryKey]['fact_count'] = count($mergedFacts);
        $index[$primaryKey]['updated_at'] = date('c');

        unset($index[$secondaryKey]);

        if (file_put_contents($this->memoryIndexFile, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            return [
                'success' => false,
                'message' => "Failed to update memory index file."
            ];
        }

        return [
            'success'     => true,
            'primary'     => $primaryKey,
            'secondary'   => $secondaryKey,
            'total_facts' => count($mergedFacts),
            'message'     => "Successfully merged '{$secondaryKey}' into '{$primaryKey}'. Updated memory index."
        ];
    }

    /**
     * Calculates similarity score between two topic string keys.
     */
    private function calculateTopicSimilarity(string $keyA, string $keyB): float
    {
        if ($keyA === $keyB) {
            return 1.0;
        }

        $normA = $this->normalizeTopicKey($keyA);
        $normB = $this->normalizeTopicKey($keyB);

        if ($normA === $normB && $normA !== '') {
            return 1.0;
        }

        // Levenshtein / Edit distance check for typos
        $levenshteinDist = levenshtein($normA, $normB);
        $maxLen = max(strlen($normA), strlen($normB));
        $levSimilarity = $maxLen > 0 ? (1.0 - ($levenshteinDist / $maxLen)) : 0.0;

        // Similar text percentage
        similar_text($normA, $normB, $percent);
        $similarTextScore = $percent / 100.0;

        return max($levSimilarity, $similarTextScore);
    }

    private function normalizeTopicKey(string $key): string
    {
        $clean = strtolower(str_replace(['_', '-'], ' ', $key));
        
        // Remove common fillers to isolate core topic words
        $fillers = ['you know about', 'tell me about', 'what is', 'how to', 'make me a', 'remember', 'simple', 'the', 'a', 'an'];
        foreach ($fillers as $filler) {
            $clean = str_replace($filler, '', $clean);
        }

        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    private function loadIndex(): array
    {
        if (!file_exists($this->memoryIndexFile)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($this->memoryIndexFile), true);
        return is_array($data) ? $data : [];
    }
}

// GUI Controller
$consolidator = new MemoryConsolidator();
$logs = [];
$scanResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'scan') {
        $scanResults = $consolidator->scanForDuplicates();
        if (empty($scanResults)) {
            $logs[] = "Scan complete: No duplicate or similar topics detected above the threshold.";
        } else {
            $logs[] = "Scan complete: Found " . count($scanResults) . " candidate pair(s) for consolidation.";
        }
    } elseif ($action === 'merge_single') {
        $primary = trim((string)($_POST['primary'] ?? ''));
        $secondary = trim((string)($_POST['secondary'] ?? ''));

        if ($primary !== '' && $secondary !== '') {
            $res = $consolidator->executeMerge($primary, $secondary);
            $logs[] = $res['message'];
        }
        $scanResults = $consolidator->scanForDuplicates();
    } elseif ($action === 'merge_all') {
        $candidates = $consolidator->scanForDuplicates();
        $mergedCount = 0;
        foreach ($candidates as $cand) {
            $res = $consolidator->executeMerge($cand['primary'], $cand['secondary']);
            if ($res['success']) {
                $mergedCount++;
            }
        }
        $logs[] = "Bulk merge complete: Successfully consolidated {$mergedCount} duplicate topic(s).";
        $scanResults = $consolidator->scanForDuplicates();
    }
} else {
    $scanResults = $consolidator->scanForDuplicates();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CfCbazar AI - Memory Topic Consolidator</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #121212; color: #e0e0e0; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #1e1e1e; padding: 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
        h1 { margin-top: 0; color: #fff; border-bottom: 1px solid #333; padding-bottom: 12px; font-size: 1.5rem; }
        .btn { background: #0070f3; color: white; border: none; padding: 10px 18px; font-size: 14px; border-radius: 4px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0059b3; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .logs { margin-top: 16px; padding: 12px; background: #252525; border-left: 4px solid #0070f3; border-radius: 4px; font-family: monospace; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #252525; border-radius: 6px; overflow: hidden; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; font-size: 14px; }
        th { background: #2a2a2a; color: #fff; }
        .similarity-badge { background: #3b82f6; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .actions-bar { display: flex; gap: 12px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Memory Topic Consolidator</h1>
        <p>Scans <code>memory/memory.json</code> and <code>memory/topics/</code> to detect similar or duplicate topic indexes (e.g. typos, overlapping prompts), merges their factual entries into a single topic, deletes obsolete files, and updates the index.</p>

        <div class="actions-bar">
            <form method="POST" action="">
                <input type="hidden" name="action" value="scan">
                <button type="submit" class="btn">Rescan Memory</button>
            </form>

            <?php if (!empty($scanResults)): ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="merge_all">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Consolidate all detected duplicate topics?');">Consolidate All Pairs</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($logs)): ?>
            <div class="logs">
                <?php foreach ($logs as $log): ?>
                    <div>&gt; <?= htmlspecialchars($log) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($scanResults)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Primary Topic</th>
                        <th>Duplicate / Secondary Topic</th>
                        <th>Similarity</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scanResults as $row): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($row['primary']) ?></strong>
                                <br><small style="color: #a0a0a0;"><?= $row['primary_facts'] ?> fact(s)</small>
                            </td>
                            <td>
                                <strong style="color: #f87171;"><?= htmlspecialchars($row['secondary']) ?></strong>
                                <br><small style="color: #a0a0a0;"><?= $row['secondary_facts'] ?> fact(s)</small>
                            </td>
                            <td>
                                <span class="similarity-badge"><?= $row['similarity'] ?>%</span>
                            </td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="merge_single">
                                    <input type="hidden" name="primary" value="<?= htmlspecialchars($row['primary']) ?>">
                                    <input type="hidden" name="secondary" value="<?= htmlspecialchars($row['secondary']) ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Merge Pair</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-top: 24px; color: #10b981;">No duplicate topic indexes detected.</p>
        <?php endif; ?>
    </div>
</body>
</html>
