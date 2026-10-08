<?php

declare(strict_types=1);

/**
 * Topic consolidator — merge similar/misspelled topics, re-embed via FactSkill.
 */

require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/MemoryStore.php';
require_once __DIR__ . '/core/SpellCorrector.php';
require_once __DIR__ . '/skills/FactSkill.php';

class MemoryConsolidator
{
    private MemoryStore $memory;
    private string $memoryIndexFile;
    private string $topicsDir;
    private float $similarityThreshold;

    public function __construct(
        string $memoryIndexFile = __DIR__ . '/memory/memory.json',
        float $similarityThreshold = 0.82
    ) {
        $this->memoryIndexFile = $memoryIndexFile;
        $this->topicsDir = rtrim(dirname($memoryIndexFile), '/\\') . '/topics';
        $this->similarityThreshold = $similarityThreshold;
        $this->memory = new MemoryStore($memoryIndexFile);
    }

    public function scanForDuplicates(): array
    {
        $topics = $this->memory->listTopics();
        $candidates = [];
        $n = count($topics);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $topics[$i];
                $b = $topics[$j];
                $similarity = $this->calculateTopicSimilarity($a, $b);
                if ($similarity >= $this->similarityThreshold) {
                    $factsA = count($this->memory->getTopicFacts($a));
                    $factsB = count($this->memory->getTopicFacts($b));
                    // Prefer the longer / more "canonical" name as primary
                    $primary = $this->preferPrimary($a, $b, $factsA, $factsB);
                    $secondary = $primary === $a ? $b : $a;
                    $candidates[] = [
                        'primary'         => $primary,
                        'secondary'       => $secondary,
                        'similarity'      => round($similarity * 100, 2),
                        'primary_facts'   => $primary === $a ? $factsA : $factsB,
                        'secondary_facts' => $primary === $a ? $factsB : $factsA,
                        'name_score'      => round($this->nameSimilarity(
                            $this->normalizeTopicKey($a),
                            $this->normalizeTopicKey($b)
                        ) * 100, 1),
                        'content_score'   => round($this->contentSimilarity($a, $b) * 100, 1),
                    ];
                }
            }
        }

        return $candidates;
    }

    public function executeMerge(string $primaryKey, string $secondaryKey): array
    {
        $primaryKey = $this->memory->resolveTopic($primaryKey);
        $secondaryKey = trim($secondaryKey);

        if ($primaryKey === '' || $secondaryKey === '' || $primaryKey === $secondaryKey) {
            return ['success' => false, 'message' => 'Invalid primary/secondary topic keys.'];
        }

        $primaryFacts = $this->memory->getTopicFacts($primaryKey);
        $secondaryFacts = $this->memory->getTopicFacts($secondaryKey);

        // Merge + ensure local embeddings (FactSkill re-embeds on dimension mismatch)
        $merged = FactSkill::mergeFacts($primaryFacts, $secondaryFacts);
        $this->memory->setTopicFacts($primaryKey, $merged);

        // Remove secondary topic file
        $secondaryFile = $this->topicsDir . '/' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $secondaryKey) . '.json';
        if (is_file($secondaryFile)) {
            @unlink($secondaryFile);
        }

        // Rebuild index entry for secondary removal via list + update
        $this->rebuildIndexWithout($secondaryKey);

        return [
            'success'     => true,
            'primary'     => $primaryKey,
            'secondary'   => $secondaryKey,
            'total_facts' => count($merged),
            'message'     => "Merged '{$secondaryKey}' → '{$primaryKey}' ({$this->countFacts($merged)} facts, embeddings refreshed).",
        ];
    }

    /**
     * Re-embed all facts missing embeddings or wrong dimensions.
     */
    public function backfillEmbeddings(?string $topic = null): array
    {
        $topics = $topic !== null ? [$this->memory->resolveTopic($topic)] : $this->memory->listTopics();
        $updatedTopics = 0;
        $updatedFacts = 0;

        foreach ($topics as $t) {
            $facts = $this->memory->getTopicFacts($t);
            $changed = false;
            foreach ($facts as &$fact) {
                $content = trim((string)($fact['content'] ?? $fact['value'] ?? ''));
                if ($content === '') {
                    continue;
                }
                $emb = $fact['embedding'] ?? [];
                $needs = !is_array($emb) || $emb === [] || count($emb) !== \LocalEmbedder::DIMENSIONS;
                if ($needs) {
                    $fact['embedding'] = FactSkill::embed($content);
                    $changed = true;
                    $updatedFacts++;
                }
            }
            unset($fact);
            if ($changed) {
                $this->memory->setTopicFacts($t, $facts);
                $updatedTopics++;
            }
        }

        return [
            'topics' => $updatedTopics,
            'facts'  => $updatedFacts,
            'message'=> "Backfill done: {$updatedFacts} fact(s) in {$updatedTopics} topic(s).",
        ];
    }

    private function preferPrimary(string $a, string $b, int $factsA, int $factsB): string
    {
        // Prefer spell-corrected form if one corrects to the other
        $corrA = $this->memory->resolveTopic($a);
        $corrB = $this->memory->resolveTopic($b);
        if ($corrA === $b) {
            return $b;
        }
        if ($corrB === $a) {
            return $a;
        }

        // Prefer fewer underscore-typo patterns (back_holes → black_holes style)
        $scoreA = $this->canonicalNameScore($a);
        $scoreB = $this->canonicalNameScore($b);
        if ($scoreA !== $scoreB) {
            return $scoreA > $scoreB ? $a : $b;
        }

        // Prefer more facts, then longer name
        if ($factsA !== $factsB) {
            return $factsA >= $factsB ? $a : $b;
        }
        return strlen($a) >= strlen($b) ? $a : $b;
    }

    /**
     * Higher = more likely a clean canonical topic name.
     */
    private function canonicalNameScore(string $key): float
    {
        $readable = str_replace('_', ' ', $key);
        $corrected = SpellCorrector::correct($readable, []);
        $score = 0.0;
        if (strtolower($corrected) === strtolower($readable)) {
            $score += 0.5;
        }
        // Prefer known lexicon density
        $parts = preg_split('/[_\s]+/', strtolower($key)) ?: [];
        $known = 0;
        foreach ($parts as $p) {
            if (strlen($p) >= 4) {
                $known++;
            }
        }
        $score += min(0.5, $known * 0.1);
        return $score;
    }

    /**
     * Topic similarity for merge suggestions.
     * Name-only matches are NOT enough — content/embedding must agree
     * (prevents black_holes↔castles and different sky_* processes).
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

        $nameScore = $this->nameSimilarity($normA, $normB);
        $contentScore = $this->contentSimilarity($keyA, $keyB);

        // Near-identical names (typos only): allow merge if name is very high
        // even with sparse/empty content (e.g. vecrot_embedings → vector_embeddings)
        if ($nameScore >= 0.86) {
            // Ensure clear typos pass the default 0.82 scan threshold
            return max(0.83, $nameScore * 0.95 + $contentScore * 0.05);
        }

        // Shared words but different meaning → content must dominate
        // Reject strong name-only false positives (sky_X vs sky_Y, holes vs castles)
        if ($contentScore < 0.28) {
            return min($nameScore * 0.5, $contentScore + 0.12);
        }

        // Balanced: both name and content must contribute
        $combined = (0.35 * $nameScore) + (0.65 * $contentScore);

        // Extra: token Jaccard on topic keys (discriminates sky_X vs sky_Y_Z)
        $jaccard = $this->tokenJaccard($normA, $normB);
        if ($jaccard < 0.45 && $contentScore < 0.55) {
            $combined *= 0.75;
        }

        return max(0.0, min(1.0, $combined));
    }

    private function nameSimilarity(string $normA, string $normB): float
    {
        if ($normA === '' || $normB === '') {
            return 0.0;
        }

        // Spell-correct then compare (back holes → black holes)
        $corrA = strtolower(SpellCorrector::correct($normA, []));
        $corrB = strtolower(SpellCorrector::correct($normB, []));
        if ($corrA === $corrB && $corrA !== '') {
            return 0.96;
        }

        $levenshteinDist = levenshtein(
            function_exists('mb_substr') ? mb_substr($corrA, 0, 255) : substr($corrA, 0, 255),
            function_exists('mb_substr') ? mb_substr($corrB, 0, 255) : substr($corrB, 0, 255)
        );
        $maxLen = max(strlen($corrA), strlen($corrB), 1);
        $levSimilarity = 1.0 - ($levenshteinDist / $maxLen);

        similar_text($corrA, $corrB, $percent);
        $similarTextScore = $percent / 100.0;

        // Do not let similar_text alone invent high scores on short unrelated strings
        $score = (0.55 * $levSimilarity) + (0.45 * $similarTextScore);
        if ($this->tokenJaccard($corrA, $corrB) < 0.25) {
            $score *= 0.6;
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Content similarity from topic meta + sample fact embeddings/keywords.
     */
    private function contentSimilarity(string $keyA, string $keyB): float
    {
        try {
            $metaA = $this->memory->getTopicMeta($keyA);
            $metaB = $this->memory->getTopicMeta($keyB);
        } catch (Throwable $e) {
            $metaA = [];
            $metaB = [];
        }

        $blobA = $this->topicContentBlob($keyA, $metaA);
        $blobB = $this->topicContentBlob($keyB, $metaB);

        if ($blobA === '' || $blobB === '') {
            return 0.0;
        }

        // Keyword Jaccard
        $kwA = FactSkill::extractKeywords($blobA, 24);
        $kwB = FactSkill::extractKeywords($blobB, 24);
        $kwScore = 0.0;
        if ($kwA !== [] && $kwB !== []) {
            $inter = count(array_intersect($kwA, $kwB));
            $union = count(array_unique(array_merge($kwA, $kwB)));
            $kwScore = $union > 0 ? $inter / $union : 0.0;
        }

        // Embedding cosine on content samples
        $embA = FactSkill::embed(function_exists('mb_substr') ? mb_substr($blobA, 0, 600) : substr($blobA, 0, 600));
        $embB = FactSkill::embed(function_exists('mb_substr') ? mb_substr($blobB, 0, 600) : substr($blobB, 0, 600));
        $vecScore = FactSkill::cosineSimilarity($embA, $embB);

        return max(0.0, min(1.0, (0.45 * $kwScore) + (0.55 * $vecScore)));
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function topicContentBlob(string $topic, array $meta): string
    {
        $parts = [];
        $parts[] = str_replace('_', ' ', $topic);
        if (!empty($meta['description'])) {
            $parts[] = (string)$meta['description'];
        }
        foreach (['tags', 'aliases', 'subtopics'] as $k) {
            if (!empty($meta[$k]) && is_array($meta[$k])) {
                $parts[] = implode(' ', $meta[$k]);
            }
        }

        $facts = $this->memory->getTopicFacts($topic);
        $n = 0;
        foreach ($facts as $f) {
            if (!is_array($f)) {
                continue;
            }
            $c = trim((string)($f['content'] ?? $f['value'] ?? ''));
            if ($c === '' || ($f['type'] ?? '') === 'prompt_intent') {
                continue;
            }
            // Prefer non-code snippets for topic identity
            if (($f['type'] ?? '') === 'code_html') {
                $c = function_exists('mb_substr') ? mb_substr($c, 0, 120) : substr($c, 0, 120);
            }
            $parts[] = $c;
            $n++;
            if ($n >= 8) {
                break;
            }
        }

        return trim(implode("
", $parts));
    }

    private function tokenJaccard(string $a, string $b): float
    {
        $ta = array_values(array_filter(preg_split('/\s+/', strtolower($a)) ?: [], fn($w) => strlen($w) >= 3));
        $tb = array_values(array_filter(preg_split('/\s+/', strtolower($b)) ?: [], fn($w) => strlen($w) >= 3));
        if ($ta === [] || $tb === []) {
            return 0.0;
        }
        $inter = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));
        return $union > 0 ? $inter / $union : 0.0;
    }

    private function normalizeTopicKey(string $key): string
    {
        $clean = strtolower(str_replace(['_', '-'], ' ', $key));
        $fillers = ['you know about', 'tell me about', 'what is', 'how to', 'make me a', 'remember', 'simple', 'the', 'a', 'an', 'topic'];
        foreach ($fillers as $filler) {
            $clean = str_replace($filler, '', $clean);
        }
        return trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);
    }

    private function rebuildIndexWithout(string $removeKey): void
    {
        if (!is_file($this->memoryIndexFile)) {
            return;
        }
        $index = json_decode((string)file_get_contents($this->memoryIndexFile), true);
        if (!is_array($index)) {
            return;
        }
        unset($index[$removeKey]);
        // Also remove nested styles
        if (isset($index['topics']) && is_array($index['topics'])) {
            unset($index['topics'][$removeKey]);
        }
        file_put_contents(
            $this->memoryIndexFile,
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function countFacts(array $facts): int
    {
        return count($facts);
    }
}

// ---------------------------------------------------------------------------
// GUI
// ---------------------------------------------------------------------------
$consolidator = new MemoryConsolidator();
$logs = [];
$scanResults = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'scan') {
        $scanResults = $consolidator->scanForDuplicates();
        $logs[] = empty($scanResults)
            ? 'Scan complete: no similar topics above threshold.'
            : 'Scan complete: ' . count($scanResults) . ' candidate pair(s).';
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
            if (!empty($res['success'])) {
                $mergedCount++;
            }
        }
        $logs[] = "Bulk merge complete: {$mergedCount} pair(s).";
        $scanResults = $consolidator->scanForDuplicates();
    } elseif ($action === 'backfill') {
        $res = $consolidator->backfillEmbeddings();
        $logs[] = $res['message'];
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
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #121212; color: #e0e0e0; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #1e1e1e; padding: 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
        h1 { margin-top: 0; color: #fff; border-bottom: 1px solid #333; padding-bottom: 12px; font-size: 1.5rem; }
        .btn { background: #0070f3; color: white; border: none; padding: 10px 18px; font-size: 14px; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #0059b3; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-warn { background: #d97706; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .logs { margin-top: 16px; padding: 12px; background: #252525; border-left: 4px solid #0070f3; border-radius: 4px; font-family: monospace; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #252525; border-radius: 6px; overflow: hidden; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; font-size: 14px; }
        th { background: #2a2a2a; color: #fff; }
        .similarity-badge { background: #3b82f6; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .actions-bar { display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap; }
        a { color: #38bdf8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Memory Topic Consolidator</h1>
        <p>Merge similar/misspelled topics using <strong>name + content/embedding</strong> (not name-only). Refresh local embeddings. No OpenRouter.</p>

        <div class="actions-bar">
            <form method="POST" action=""><input type="hidden" name="action" value="scan">
                <button type="submit" class="btn">Rescan</button></form>
            <form method="POST" action=""><input type="hidden" name="action" value="backfill">
                <button type="submit" class="btn btn-warn">Backfill embeddings</button></form>
            <?php if (!empty($scanResults)): ?>
                <form method="POST" action=""><input type="hidden" name="action" value="merge_all">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Merge all pairs?');">Consolidate all</button></form>
            <?php endif; ?>
        </div>

        <?php if (!empty($logs)): ?>
            <div class="logs">
                <?php foreach ($logs as $log): ?>
                    <div>&gt; <?= htmlspecialchars((string)$log) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($scanResults)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Primary</th>
                        <th>Secondary (merge into primary)</th>
                        <th>Similarity</th>
                        <th>Name</th>
                        <th>Content</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scanResults as $row): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($row['primary']) ?></strong><br>
                                <small style="color:#a0a0a0;"><?= (int)$row['primary_facts'] ?> fact(s)</small>
                            </td>
                            <td>
                                <strong style="color:#f87171;"><?= htmlspecialchars($row['secondary']) ?></strong><br>
                                <small style="color:#a0a0a0;"><?= (int)$row['secondary_facts'] ?> fact(s)</small>
                            </td>
                            <td><span class="similarity-badge"><?= htmlspecialchars((string)$row['similarity']) ?>%</span></td>
                            <td><?= htmlspecialchars((string)($row['name_score'] ?? '—')) ?>%</td>
                            <td><?= htmlspecialchars((string)($row['content_score'] ?? '—')) ?>%</td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="merge_single">
                                    <input type="hidden" name="primary" value="<?= htmlspecialchars($row['primary']) ?>">
                                    <input type="hidden" name="secondary" value="<?= htmlspecialchars($row['secondary']) ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Merge</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-top:24px;color:#10b981;">No duplicate topics detected.</p>
        <?php endif; ?>

        <p style="margin-top:24px;"><a href="index.php">← Back to AI</a> · <a href="seed.php">Seed knowledge</a></p>
    </div>
</body>
</html>
