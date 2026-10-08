<?php

declare(strict_types=1);

require_once __DIR__ . '/SkillData.php';

if (!class_exists('FactSkill', false)) {
    require_once __DIR__ . '/../skills/FactSkill.php';
}

if (!class_exists('SpellCorrector', false)) {
    require_once __DIR__ . '/SpellCorrector.php';
}

if (!class_exists('MemoryStore', false)) {

/**
 * Lightweight JSON memory store for PHP-only hosting.
 *
 * Stores facts + embeddings. Supports vector similarity search (pure PHP).
 */
class MemoryStore
{
    private string $indexPath;
    private string $baseDir;
    private int $maxFactsPerTopic;
    private int $maxTotalFacts;

    public function __construct(
        string $path,
        int $maxFactsPerTopic = 100,
        int $maxTotalFacts = 5000
    ) {
        $this->indexPath = $path;
        $this->baseDir = rtrim(dirname($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'topics';
        $this->maxFactsPerTopic = max(1, $maxFactsPerTopic);
        $this->maxTotalFacts = max($this->maxFactsPerTopic, $maxTotalFacts);

        $this->ensureDirectory(dirname($this->indexPath));
        $this->ensureDirectory($this->baseDir);
    }

    /**
     * List known topic keys (safe filenames without .json).
     *
     * @return list<string>
     */
    public function listTopics(): array
    {
        $topics = [];
        $index = $this->loadIndex();

        foreach (($index['topics'] ?? []) as $key => $meta) {
            $name = is_array($meta) ? (string)($meta['topic'] ?? $key) : (string)$key;
            $safe = $this->safeTopicName($name);
            if ($safe !== '') {
                $topics[$safe] = true;
            }
        }

        // From topic files (source of truth for presence)
        foreach ($this->topicFiles() as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            if ($base !== '') {
                $topics[$base] = true;
            }
        }

        return array_keys($topics);
    }

    /**
     * Rebuild flat fact index in memory.json from all topic files.
     */
    public function rebuildFactIndex(): int
    {
        $index = $this->emptyIndex();
        $count = 0;

        foreach ($this->topicFiles() as $file) {
            $topic = pathinfo($file, PATHINFO_FILENAME);
            $doc = $this->readTopicDocument($topic);
            $facts = $doc['facts'];
            $index['topics'][$topic] = $this->topicIndexEntry($topic, $facts, $doc);
            foreach ($facts as $fact) {
                if (!is_array($fact)) {
                    continue;
                }
                $entry = $this->factIndexEntry($topic, $fact);
                if ($entry === null) {
                    continue;
                }
                $index['facts'][$entry['id']] = $entry;
                $count++;
            }
        }

        $index['updated_at'] = gmdate('c');
        $index['fact_count'] = $count;
        $this->saveIndex($index);
        return $count;
    }

    /**
     * Resolve a candidate topic to an existing one via exact or fuzzy match.
     * Returns the safe topic name to use for storage/retrieval.
     */
    public function resolveTopic(string $candidate, int $maxDistance = 0): string
    {
        $safe = $this->safeTopicName($candidate);
        if ($safe === 'general_topic') {
            return $safe;
        }

        $known = $this->listTopics();
        if ($known === []) {
            return $safe;
        }

        // Spell-correct topic tokens so vecrot_embedings → vector_embeddings
        $readable = str_replace('_', ' ', $safe);
        $correctedReadable = SpellCorrector::correct($readable, []);
        $correctedSafe = $this->safeTopicName($correctedReadable);
        if ($correctedSafe !== $safe && $correctedSafe !== 'general_topic') {
            if (in_array($correctedSafe, $known, true)) {
                return $correctedSafe;
            }
            // Prefer corrected form even if file does not exist yet
            $safe = $correctedSafe;
        }

        // Prefer canonical spellings when candidate is a known misspelling
        $canonical = $this->canonicalTopicAlias($safe);
        if ($canonical !== null) {
            return $canonical;
        }

        // Exact match
        if (in_array($safe, $known, true)) {
            return $safe;
        }

        // Alias / tag match from topic metadata
        $aliasHit = $this->resolveViaAliases($safe);
        if ($aliasHit !== null) {
            return $aliasHit;
        }

        // Adaptive distance: longer names allow more edits
        $len = strlen($safe);
        if ($maxDistance <= 0) {
            $maxDistance = $len <= 8 ? 2 : ($len <= 16 ? 3 : 4);
        }

        $best = null;
        $bestDist = PHP_INT_MAX;

        foreach ($known as $topic) {
            if (abs(strlen($topic) - $len) > $maxDistance + 1) {
                continue;
            }
            $dist = levenshtein($safe, $topic);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $topic;
            }
        }

        // Readable form (underscores as spaces)
        $candidateReadable = str_replace('_', ' ', $safe);
        foreach ($known as $topic) {
            $topicReadable = str_replace('_', ' ', $topic);
            if (abs(strlen($topicReadable) - strlen($candidateReadable)) > $maxDistance + 1) {
                continue;
            }
            $dist = levenshtein($candidateReadable, $topicReadable);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $topic;
            }
        }

        // Token-wise: if most tokens fuzzy-match a known topic's tokens, prefer it
        $candTokens = array_values(array_filter(explode('_', $safe)));
        if (count($candTokens) >= 2) {
            foreach ($known as $topic) {
                $topicTokens = array_values(array_filter(explode('_', $topic)));
                if (count($topicTokens) !== count($candTokens)) {
                    continue;
                }
                $tokenDist = 0;
                $ok = true;
                for ($i = 0; $i < count($candTokens); $i++) {
                    $d = levenshtein($candTokens[$i], $topicTokens[$i]);
                    if ($d > 2) {
                        $ok = false;
                        break;
                    }
                    $tokenDist += $d;
                }
                if ($ok && $tokenDist < $bestDist) {
                    $bestDist = $tokenDist;
                    $best = $topic;
                }
            }
        }

        return ($best !== null && $bestDist <= $maxDistance) ? $best : $safe;
    }

    public function getTopicFacts(string $topic): array
    {
        $doc = $this->readTopicDocument($topic);
        return $doc['facts'];
    }

    /**
     * Topic envelope metadata (description, aliases, subtopics, tags, vectors).
     *
     * @return array{
     *   topic: string,
     *   description: string,
     *   aliases: list<string>,
     *   subtopics: list<string>,
     *   tags: list<string>,
     *   vectors: array<string, mixed>
     * }
     */
    public function getTopicMeta(string $topic): array
    {
        $doc = $this->readTopicDocument($topic);
        $facts = $doc['facts'] ?? [];
        $confSum = 0.0;
        $n = 0;
        foreach ($facts as $f) {
            if (!is_array($f)) {
                continue;
            }
            $confSum += (float)($f['confidence'] ?? 0.5);
            $n++;
        }
        return [
            'topic' => $doc['topic'],
            'description' => $doc['description'],
            'aliases' => $doc['aliases'],
            'subtopics' => $doc['subtopics'],
            'tags' => $doc['tags'],
            'vectors' => $doc['vectors'],
            'fact_count' => $n,
            'avg_score' => $n > 0 ? round($confSum / $n, 4) : 0.0,
        ];
    }

    /**
     * Update topic metadata; rebuilds label vectors with FactSkill/LocalEmbedder.
     *
     * @param array<string, mixed> $meta
     */
    public function setTopicMeta(string $topic, array $meta): void
    {
        $doc = $this->readTopicDocument($topic);
        $doc = $this->applyMetaToDocument($doc, $meta);
        $doc['vectors'] = $this->buildTopicVectors($doc);
        $this->writeTopicDocument($topic, $doc);
        $this->updateTopicIndex($topic, $doc['facts'], $doc);
    }

    /**
     * Find related topic keys by embedding the query against topic description/aliases/tags.
     *
     * @param list<float> $queryEmbedding
     * @return list<string>
     */
    /**
     * Pick the best existing topic for a prompt using name tokens + content embeddings.
     * Avoids ambiguous short names (e.g. "sky verification" → celestial vs call-center).
     *
     * @param list<float> $queryEmbedding
     */
    public function findBestMatchingTopic(string $prompt, array $queryEmbedding = [], float $minScore = 0.22): ?string
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return null;
        }
        if ($queryEmbedding === []) {
            $queryEmbedding = FactSkill::embed($prompt);
        }

        $promptLower = function_exists('mb_strtolower') ? mb_strtolower($prompt) : strtolower($prompt);
        $promptKw = FactSkill::extractKeywords($prompt, 16);
        $bestTopic = null;
        $bestScore = 0.0;

        foreach ($this->listTopics() as $topic) {
            $meta = $this->getTopicMeta($topic);
            $name = str_replace('_', ' ', $topic);
            $nameTokens = array_values(array_filter(
                preg_split('/\s+/', strtolower($name)) ?: [],
                static fn($w) => strlen($w) >= 3
            ));

            // Token coverage both ways
            $nameHits = 0;
            foreach ($nameTokens as $tok) {
                if (str_contains($promptLower, $tok)) {
                    $nameHits++;
                }
            }
            $nameCoverage = $nameTokens !== [] ? $nameHits / count($nameTokens) : 0.0;

            $promptHits = 0;
            foreach ($promptKw as $kw) {
                if (str_contains(strtolower($name), $kw)) {
                    $promptHits++;
                }
            }
            $promptCoverage = $promptKw !== [] ? $promptHits / min(8, count($promptKw)) : 0.0;

            // Description / label embedding
            $desc = trim((string)($meta['description'] ?? ''));
            $label = $desc !== '' ? $desc : $name;
            $descVec = $meta['vectors']['description'] ?? null;
            if (!is_array($descVec) || $descVec === []) {
                $descVec = FactSkill::embed($label);
            }
            $vecScore = FactSkill::cosineSimilarity($queryEmbedding, $descVec);

            // Sample fact keywords overlap
            $facts = $this->getTopicFacts($topic);
            $factBlob = '';
            $n = 0;
            foreach ($facts as $f) {
                if (!is_array($f) || ($f['type'] ?? '') === 'code_html') {
                    continue;
                }
                $c = trim((string)($f['content'] ?? $f['value'] ?? ''));
                if ($c === '') {
                    continue;
                }
                $factBlob .= ' ' . $c;
                $n++;
                if ($n >= 6) {
                    break;
                }
            }
            $factKw = FactSkill::extractKeywords($factBlob, 20);
            $factInter = 0;
            if ($factKw !== [] && $promptKw !== []) {
                $factInter = count(array_intersect($promptKw, $factKw));
            }
            $factScore = $factKw !== [] ? min(1.0, $factInter / max(3, min(6, count($promptKw)))) : 0.0;

            // Prefer longer, more specific topic names when coverage is high
            $specificity = min(1.0, count($nameTokens) / 6.0);

            $score = (0.30 * $nameCoverage)
                + (0.20 * $promptCoverage)
                + (0.25 * $vecScore)
                + (0.20 * $factScore)
                + (0.05 * $specificity);

            // Strong boost when most of a long topic name appears in the prompt
            if (count($nameTokens) >= 3 && $nameCoverage >= 0.6) {
                $score += 0.12;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTopic = $topic;
            }
        }

        if ($bestTopic === null || $bestScore < $minScore) {
            return null;
        }

        return $bestTopic;
    }

    public function findRelatedTopics(array $queryEmbedding, string $queryText = '', int $limit = 4, float $minScore = 0.18): array
    {
        if ($queryEmbedding === [] && trim($queryText) === '') {
            return [];
        }
        if ($queryEmbedding === [] && $queryText !== '') {
            $queryEmbedding = FactSkill::embed($queryText);
        }

        $scored = [];
        foreach ($this->listTopics() as $topic) {
            $doc = $this->readTopicDocument($topic);
            $best = 0.0;

            // Description vector
            $descVec = $doc['vectors']['description'] ?? null;
            if (!is_array($descVec) || $descVec === []) {
                $label = trim($doc['description'] !== '' ? $doc['description'] : $topic);
                $descVec = FactSkill::embed($label);
            }
            $best = max($best, FactSkill::cosineSimilarity($queryEmbedding, $descVec));

            // Alias / tag label vectors
            $labelMap = $doc['vectors']['labels'] ?? [];
            if (!is_array($labelMap)) {
                $labelMap = [];
            }
            foreach (array_merge($doc['aliases'], $doc['tags'], [$topic]) as $label) {
                $label = trim((string)$label);
                if ($label === '') {
                    continue;
                }
                $vec = $labelMap[$label] ?? null;
                if (!is_array($vec) || $vec === []) {
                    $vec = FactSkill::embed(str_replace('_', ' ', $label));
                }
                $best = max($best, FactSkill::cosineSimilarity($queryEmbedding, $vec));
            }

            // Keyword fallback on description/aliases
            if ($queryText !== '') {
                $blob = strtolower(
                    $doc['description'] . ' ' . implode(' ', $doc['aliases']) . ' ' . implode(' ', $doc['tags']) . ' ' . $topic
                );
                foreach (FactSkill::extractKeywords($queryText) as $kw) {
                    if ($kw !== '' && str_contains($blob, $kw)) {
                        $best = max($best, 0.22);
                    }
                }
            }

            if ($best >= $minScore) {
                $scored[$topic] = $best;
            }
        }

        arsort($scored, SORT_NUMERIC);
        return array_slice(array_keys($scored), 0, max(1, $limit));
    }


    public function setTopicFacts(string $topic, array $facts): void
    {
        $doc = $this->readTopicDocument($topic);
        $normalized = $this->normalizeFacts($facts);
        $normalized = $this->pruneTopicFacts($normalized);
        $doc['facts'] = $normalized;
        if (($doc['vectors']['description'] ?? null) === null) {
            $doc['vectors'] = $this->buildTopicVectors($doc);
        }
        $this->writeTopicDocument($topic, $doc);
        $this->updateTopicIndex($topic, $normalized, $doc);
        $this->pruneGlobal();
    }

    public function mergeTopicFacts(string $topic, array $newFacts): array
    {
        $doc = $this->readTopicDocument($topic);
        $existing = $doc['facts'];
        $incoming = $this->normalizeFacts($newFacts);
        $safe = $this->safeTopicName($topic);

        // Stamp topic + ensure tags include topic slug on every incoming fact
        foreach ($incoming as &$fact) {
            $fact['topic'] = $safe;
            $tags = is_array($fact['tags'] ?? null) ? $fact['tags'] : [];
            if ($safe !== '' && !in_array($safe, $tags, true)) {
                $tags[] = $safe;
            }
            $fact['tags'] = array_values(array_unique(array_filter(array_map('strval', $tags))));
            if (empty($fact['keywords']) || !is_array($fact['keywords'])) {
                $fact['keywords'] = FactSkill::extractKeywords((string)($fact['content'] ?? ''));
            }
            if (empty($fact['embedding']) || !is_array($fact['embedding'])) {
                $fact['embedding'] = FactSkill::embed((string)($fact['content'] ?? ''));
            }
        }
        unset($fact);

        $merged = $this->mergeWithoutExactDuplicates($existing, $incoming);
        $merged = $this->pruneTopicFacts($merged);
        $doc['facts'] = $merged;
        if (($doc['vectors']['description'] ?? null) === null || ($doc['description'] ?? '') === '') {
            $doc['vectors'] = $this->buildTopicVectors($doc);
        }
        $this->writeTopicDocument($topic, $doc);
        $this->updateTopicIndex($topic, $merged, $doc);
        $this->pruneGlobal();

        return $merged;
    }

    /**
     * Merge topic metadata without wiping facts (description, aliases, tags, subtopics).
     *
     * @param array<string, mixed> $meta
     */
    public function mergeTopicMeta(string $topic, array $meta): void
    {
        $doc = $this->readTopicDocument($topic);
        $existing = [
            'description' => (string)($doc['description'] ?? ''),
            'aliases' => array_values($doc['aliases'] ?? []),
            'subtopics' => array_values($doc['subtopics'] ?? []),
            'tags' => array_values($doc['tags'] ?? []),
        ];
        if (isset($meta['description']) && trim((string)$meta['description']) !== '') {
            // Keep longer/more informative description
            $newDesc = trim((string)$meta['description']);
            if ($existing['description'] === '' || strlen($newDesc) > strlen($existing['description'])) {
                $existing['description'] = $newDesc;
            }
        }
        foreach (['aliases', 'subtopics', 'tags'] as $k) {
            if (!isset($meta[$k]) || !is_array($meta[$k])) {
                continue;
            }
            $merged = array_merge($existing[$k], array_map('strval', $meta[$k]));
            $existing[$k] = array_values(array_unique(array_filter(array_map('trim', $merged))));
        }
        $this->setTopicMeta($topic, $existing);
    }

    /**
     * Vector similarity search across one topic or all topics.
     *
     * @param array  $queryEmbedding  float[]
     * @param float  $minScore        minimum cosine similarity (0-1)
     * @param int    $limit           max results
     * @param string|null $topic      restrict to topic (null = all)
     * @return array  facts sorted by similarity descending, each with '_score'
     */
    /**
     * Vector search over facts. When $topic is null, candidates are keyword-prefiltered
     * (if keywords provided) so we do not cosine-score the entire store on shared hosting.
     *
     * @param list<float> $queryEmbedding
     * @param list<string> $queryKeywords Optional; strongly recommended for global search
     * @return list<array<string, mixed>>
     */
    public function searchByVector(
        array $queryEmbedding,
        float $minScore = 0.55,
        int $limit = 10,
        ?string $topic = null,
        array $queryKeywords = []
    ): array {
        if ($queryEmbedding === []) {
            return [];
        }

        $candidates = [];
        $index = $this->loadIndex();
        $flat = $index['facts'] ?? [];
        $safeTopic = $topic !== null ? $this->safeTopicName($topic) : null;

        if (is_array($flat) && $flat !== []) {
            foreach ($flat as $fact) {
                if (!is_array($fact)) {
                    continue;
                }
                if ($safeTopic !== null && ($fact['topic'] ?? '') !== $safeTopic) {
                    continue;
                }
                $candidates[] = $fact + ['_topic' => (string)($fact['topic'] ?? $safeTopic ?? '')];
            }
        } elseif ($safeTopic !== null) {
            foreach ($this->getTopicFacts($safeTopic) as $fact) {
                $candidates[] = $fact + ['_topic' => $safeTopic];
            }
        } else {
            $candidates = $this->getAllFacts();
        }

        // Global search: pre-filter by cheap keyword overlap before float-heavy cosine
        $maxVectorCandidates = 400;
        if ($safeTopic === null && count($candidates) > $maxVectorCandidates) {
            if ($queryKeywords !== []) {
                $candidates = $this->prefilterFactsByKeywords($candidates, $queryKeywords, $maxVectorCandidates);
            } else {
                // No keywords: still cap work (prefer facts that have embeddings + recent)
                $candidates = array_slice($candidates, 0, $maxVectorCandidates);
            }
        } elseif ($queryKeywords !== [] && count($candidates) > $maxVectorCandidates) {
            $candidates = $this->prefilterFactsByKeywords($candidates, $queryKeywords, $maxVectorCandidates);
        }

        $scored = [];
        foreach ($candidates as $fact) {
            $emb = $fact['embedding'] ?? [];
            if (!is_array($emb) || $emb === []) {
                continue;
            }
            $score = FactSkill::cosineSimilarity($queryEmbedding, $emb);
            if ($score >= $minScore) {
                $fact['_score'] = $score;
                $scored[] = $fact;
            }
        }

        usort($scored, static fn(array $a, array $b): int =>
            ((float)($b['_score'] ?? 0) <=> (float)($a['_score'] ?? 0))
        );

        return array_slice($scored, 0, max(1, $limit));
    }

    /**
     * Keep facts with any keyword hit on content/keywords/tags; fill remainder by order.
     *
     * @param list<array<string, mixed>> $facts
     * @param list<string> $keywords
     * @return list<array<string, mixed>>
     */
    private function prefilterFactsByKeywords(array $facts, array $keywords, int $limit): array
    {
        $keywords = array_values(array_filter(array_map(
            static fn($k) => strtolower(trim((string)$k)),
            $keywords
        ), static fn($k) => $k !== '' && strlen($k) >= 3));

        if ($keywords === [] || $facts === []) {
            return array_slice($facts, 0, $limit);
        }

        $hit = [];
        $miss = [];
        foreach ($facts as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            $blob = strtolower(
                (string)($fact['content'] ?? $fact['value'] ?? '') . ' '
                . implode(' ', is_array($fact['keywords'] ?? null) ? $fact['keywords'] : []) . ' '
                . implode(' ', is_array($fact['tags'] ?? null) ? $fact['tags'] : []) . ' '
                . (string)($fact['topic'] ?? $fact['_topic'] ?? '')
            );
            $ok = false;
            foreach ($keywords as $kw) {
                if (str_contains($blob, $kw)) {
                    $ok = true;
                    break;
                }
            }
            if ($ok) {
                $hit[] = $fact;
            } else {
                $miss[] = $fact;
            }
        }

        $out = $hit;
        if (count($out) < $limit) {
            $out = array_merge($out, array_slice($miss, 0, $limit - count($out)));
        }
        return array_slice($out, 0, $limit);
    }

    /**
     * Convenience: embed text then search (passes keywords for global pre-filter).
     */
    public function searchByText(
        string $text,
        float $minScore = 0.55,
        int $limit = 10,
        ?string $topic = null
    ): array {
        $embedding = FactSkill::embed($text);
        if ($embedding === []) {
            return [];
        }
        $keywords = FactSkill::extractKeywords($text, 12);
        return $this->searchByVector($embedding, $minScore, $limit, $topic, $keywords);
    }

    public function getAllFacts(): array
    {
        $index = $this->loadIndex();
        $flat = $index['facts'] ?? [];
        if (is_array($flat) && $flat !== []) {
            $all = [];
            foreach ($flat as $fact) {
                if (is_array($fact)) {
                    $fact['_topic'] = (string)($fact['topic'] ?? '');
                    $all[] = $fact;
                }
            }
            return $all;
        }

        $all = [];
        foreach ($this->topicFiles() as $file) {
            $topic = pathinfo($file, PATHINFO_FILENAME);
            foreach ($this->normalizeTopicData($this->readJsonFile($file)) as $fact) {
                $fact['_topic'] = $topic;
                $all[] = $fact;
            }
        }
        return $all;
    }

    private function normalizeTopicData(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return $this->normalizeFacts($data);
        }

        foreach (['facts', 'statements', 'items', 'entries'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->normalizeFacts($data[$key]);
            }
        }

        $collected = [];
        foreach ($data as $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $collected = array_merge($collected, $value);
                } elseif (isset($value['facts']) && is_array($value['facts'])) {
                    $collected = array_merge($collected, $value['facts']);
                } elseif (isset($value['statements']) && is_array($value['statements'])) {
                    $collected = array_merge($collected, $value['statements']);
                }
            }
        }

        return $this->normalizeFacts($collected);
    }

    private function normalizeFacts(array $facts): array
    {
        $normalized = [];

        foreach ($facts as $fact) {
            if (!is_array($fact)) {
                continue;
            }

            $record = SkillData::normalizeFact($fact);

            // Preserve storage-only fields used by pruning / metadata
            if (isset($fact['id'])) {
                $record['id'] = $fact['id'];
            }
            if (isset($fact['updated_at'])) {
                $record['updated_at'] = $fact['updated_at'];
            } else {
                $record['updated_at'] = $record['created_at'];
            }
            $record['access_count'] = max(0, (int)($fact['access_count'] ?? 0));
            $record['decay_rate'] = max(0.0, (float)($fact['decay_rate'] ?? 0.05));

            if ($record['content'] === '') {
                continue;
            }

            // Ensure embedding present and non-zero when content exists
            $emb = $record['embedding'] ?? null;
            $needEmbed = !is_array($emb) || $emb === [];
            if (!$needEmbed && defined('LocalEmbedder::DIMENSIONS') && count($emb) !== LocalEmbedder::DIMENSIONS) {
                $needEmbed = true;
            }
            if (!$needEmbed && is_array($emb)) {
                $sum = 0.0;
                foreach ($emb as $v) {
                    $sum += abs((float)$v);
                    if ($sum > 1e-9) {
                        break;
                    }
                }
                if ($sum < 1e-9) {
                    $needEmbed = true;
                }
            }
            if ($needEmbed) {
                $record['embedding'] = FactSkill::embed($record['content']);
            }

            $normalized[] = $record;
        }

        return $normalized;
    }

    private function mergeWithoutExactDuplicates(array $existing, array $incoming): array
    {
        $merged = $existing;
        $known = [];

        foreach ($existing as $fact) {
            $known[$this->dedupKey($fact)] = true;
        }

        foreach ($incoming as $fact) {
            $key = $this->dedupKey($fact);
            if (isset($known[$key])) {
                continue;
            }

            $merged[] = $fact;
            $known[$key] = true;
        }

        return $merged;
    }

    private function dedupKey(array $fact): string
    {
        return strtolower(trim((string) ($fact['content'] ?? $fact['value'] ?? '')));
    }

    private function pruneTopicFacts(array $facts): array
    {
        if (count($facts) <= $this->maxFactsPerTopic) {
            return array_values($facts);
        }

        usort($facts, fn (array $a, array $b): int => strcmp(
            (string) ($a['updated_at'] ?? $a['created_at'] ?? ''),
            (string) ($b['updated_at'] ?? $b['created_at'] ?? '')
        ));

        return array_values(array_slice($facts, -$this->maxFactsPerTopic));
    }

    private function pruneGlobal(): void
    {
        $entries = [];
        $total = 0;

        foreach ($this->topicFiles() as $file) {
            $facts = $this->normalizeTopicData($this->readJsonFile($file));
            $total += count($facts);

            foreach ($facts as $index => $fact) {
                $entries[] = [
                    'file' => $file,
                    'index' => $index,
                    'date' => (string) ($fact['updated_at'] ?? $fact['created_at'] ?? ''),
                ];
            }
        }

        if ($total <= $this->maxTotalFacts) {
            return;
        }

        usort($entries, fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
        $removeCount = $total - $this->maxTotalFacts;
        $removeByFile = [];

        foreach (array_slice($entries, 0, $removeCount) as $entry) {
            $removeByFile[$entry['file']][] = $entry['index'];
        }

        foreach ($removeByFile as $file => $indexes) {
            $facts = $this->normalizeTopicData($this->readJsonFile($file));
            foreach (array_reverse($indexes) as $index) {
                unset($facts[$index]);
            }
            $facts = array_values($facts);

            if ($facts === []) {
                @unlink($file);
            } else {
                $this->writeJsonFile($file, $facts);
            }
        }
    }

    private function updateTopicIndex(string $topic, array $facts, ?array $doc = null): void
    {
        $index = $this->loadIndex();
        $key = $this->safeTopicName($topic);

        $index['topics'][$key] = $this->topicIndexEntry($key, $facts, $doc);

        // Refresh flat fact entries for this topic only
        foreach (array_keys($index['facts']) as $fid) {
            $entry = $index['facts'][$fid];
            if (is_array($entry) && ($entry['topic'] ?? '') === $key) {
                unset($index['facts'][$fid]);
            }
        }
        foreach ($facts as $fact) {
            if (!is_array($fact)) {
                continue;
            }
            $entry = $this->factIndexEntry($key, $fact);
            if ($entry !== null) {
                $index['facts'][$entry['id']] = $entry;
            }
        }

        $index['updated_at'] = gmdate('c');
        $index['fact_count'] = count($index['facts']);
        $this->saveIndex($index);
    }

    /**
     * @return array{version: int, updated_at: string, fact_count: int, topics: array<string, mixed>, facts: array<string, mixed>}
     */
    private function loadIndex(): array
    {
        $raw = $this->readJsonFile($this->indexPath);
        if (!is_array($raw) || array_is_list($raw)) {
            return $this->emptyIndex();
        }

        // v2 shape
        if (isset($raw['version']) && (int)$raw['version'] >= 2 && isset($raw['topics']) && is_array($raw['topics'])) {
            $raw['facts'] = is_array($raw['facts'] ?? null) ? $raw['facts'] : [];
            $raw['fact_count'] = (int)($raw['fact_count'] ?? count($raw['facts']));
            return $raw;
        }

        // Legacy: top-level topic keys
        $index = $this->emptyIndex();
        foreach ($raw as $key => $meta) {
            if (!is_string($key) || in_array($key, ['version', 'topics', 'facts', 'updated_at', 'fact_count'], true)) {
                continue;
            }
            if (!is_array($meta)) {
                continue;
            }
            $safe = $this->safeTopicName((string)($meta['topic'] ?? $key));
            $index['topics'][$safe] = [
                'topic' => $safe,
                'file' => (string)($meta['file'] ?? ('topics/' . $safe . '.json')),
                'fact_count' => (int)($meta['fact_count'] ?? 0),
                'last_updated' => (string)($meta['last_updated'] ?? ''),
                'description' => (string)($meta['description'] ?? ''),
                'aliases' => array_values($meta['aliases'] ?? []),
                'tags' => array_values($meta['tags'] ?? []),
                'subtopics' => array_values($meta['subtopics'] ?? []),
            ];
        }
        return $index;
    }

    /**
     * @param array<string, mixed> $index
     */
    private function saveIndex(array $index): void
    {
        $index['version'] = 2;
        $index['updated_at'] = gmdate('c');
        $index['fact_count'] = isset($index['facts']) && is_array($index['facts']) ? count($index['facts']) : 0;
        if (!isset($index['topics']) || !is_array($index['topics'])) {
            $index['topics'] = [];
        }
        if (!isset($index['facts']) || !is_array($index['facts'])) {
            $index['facts'] = [];
        }
        $this->writeJsonFile($this->indexPath, $index);
    }

    /**
     * @return array{version: int, updated_at: string, fact_count: int, topics: array<string, mixed>, facts: array<string, mixed>}
     */
    private function emptyIndex(): array
    {
        return [
            'version' => 2,
            'updated_at' => gmdate('c'),
            'fact_count' => 0,
            'topics' => [],
            'facts' => [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $facts
     * @param array<string, mixed>|null $doc
     * @return array<string, mixed>
     */
    private function topicIndexEntry(string $topic, array $facts, ?array $doc = null): array
    {
        $confSum = 0.0;
        $n = 0;
        foreach ($facts as $f) {
            if (!is_array($f)) {
                continue;
            }
            $confSum += (float)($f['confidence'] ?? 0.5);
            $n++;
        }
        $entry = [
            'topic' => $topic,
            'file' => 'topics/' . $topic . '.json',
            'fact_count' => count($facts),
            'avg_score' => $n > 0 ? round($confSum / $n, 4) : 0.0,
            'last_updated' => gmdate('c'),
            'description' => '',
            'aliases' => [],
            'tags' => [],
            'subtopics' => [],
        ];
        if (is_array($doc)) {
            $entry['description'] = (string)($doc['description'] ?? '');
            $entry['aliases'] = array_values($doc['aliases'] ?? []);
            $entry['tags'] = array_values($doc['tags'] ?? []);
            $entry['subtopics'] = array_values($doc['subtopics'] ?? []);
        }
        return $entry;
    }

    /**
     * Flat index row for one fact (includes embedding for retrieval).
     *
     * @param array<string, mixed> $fact
     * @return array<string, mixed>|null
     */
    private function factIndexEntry(string $topic, array $fact): ?array
    {
        $record = SkillData::normalizeFact($fact);
        if ($record['content'] === '') {
            return null;
        }
        $record['topic'] = $this->safeTopicName($topic);
        return $record;
    }

    private function topicFiles(): array
    {
        $files = glob($this->baseDir . DIRECTORY_SEPARATOR . '*.json');
        return is_array($files) ? $files : [];
    }

    private function getTopicPath(string $topic): string
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . $this->safeTopicName($topic) . '.json';
    }

    private function safeTopicName(string $topic): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($topic));
        $safe = trim((string) $safe, '_');
        return $safe !== '' ? $safe : 'general_topic';
    }

    private function createFactId(): string
    {
        try {
            return 'fact_' . bin2hex(random_bytes(6));
        } catch (Throwable) {
            return 'fact_' . uniqid('', true);
        }
    }


    /**
     * @return array{
     *   topic: string,
     *   description: string,
     *   aliases: list<string>,
     *   subtopics: list<string>,
     *   tags: list<string>,
     *   vectors: array<string, mixed>,
     *   facts: list<array<string, mixed>>
     * }
     */
    private function readTopicDocument(string $topic): array
    {
        $safe = $this->safeTopicName($topic);
        $file = $this->getTopicPath($safe);
        $raw = is_file($file) ? $this->readJsonFile($file) : [];

        $facts = $this->normalizeTopicData($raw);
        $description = '';
        $aliases = [];
        $subtopics = [];
        $tags = [];
        $vectors = [];

        if (is_array($raw) && !array_is_list($raw)) {
            $description = trim((string)($raw['description'] ?? ''));
            $aliases = $this->stringList($raw['aliases'] ?? $raw['topic_aliases'] ?? []);
            // User shape: "topic": ["php_simple_page", "php_facts"] as alias list
            if (isset($raw['topic']) && is_array($raw['topic'])) {
                $aliases = array_values(array_unique(array_merge($aliases, $this->stringList($raw['topic']))));
            }
            $subtopics = $this->stringList($raw['subtopics'] ?? []);
            $tags = $this->stringList($raw['tags'] ?? []);
            $vectors = is_array($raw['vectors'] ?? null) ? $raw['vectors'] : [];
        }

        return [
            'topic' => $safe,
            'description' => $description,
            'aliases' => $aliases,
            'subtopics' => $subtopics,
            'tags' => $tags,
            'vectors' => $vectors,
            'facts' => $facts,
        ];
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function writeTopicDocument(string $topic, array $doc): void
    {
        $safe = $this->safeTopicName($topic);
        $payload = [
            'topic' => $safe,
            'description' => (string)($doc['description'] ?? ''),
            'aliases' => array_values($doc['aliases'] ?? []),
            'subtopics' => array_values($doc['subtopics'] ?? []),
            'tags' => array_values($doc['tags'] ?? []),
            'vectors' => is_array($doc['vectors'] ?? null) ? $doc['vectors'] : [],
            'facts' => array_values($doc['facts'] ?? []),
        ];
        $this->writeJsonFile($this->getTopicPath($safe), $payload);
    }

    /**
     * @param array<string, mixed> $doc
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function applyMetaToDocument(array $doc, array $meta): array
    {
        if (isset($meta['description'])) {
            $doc['description'] = trim((string)$meta['description']);
        }
        if (isset($meta['aliases']) || isset($meta['topic']) && is_array($meta['topic'])) {
            $aliases = $this->stringList($meta['aliases'] ?? []);
            if (isset($meta['topic']) && is_array($meta['topic'])) {
                $aliases = array_merge($aliases, $this->stringList($meta['topic']));
            }
            $doc['aliases'] = array_values(array_unique(array_filter($aliases)));
        }
        if (isset($meta['subtopics'])) {
            $doc['subtopics'] = $this->stringList($meta['subtopics']);
        }
        if (isset($meta['tags'])) {
            $doc['tags'] = $this->stringList($meta['tags']);
        }
        return $doc;
    }

    /**
     * @param array<string, mixed> $doc
     * @return array<string, mixed>
     */
    private function buildTopicVectors(array $doc): array
    {
        $topic = (string)($doc['topic'] ?? '');
        $description = trim((string)($doc['description'] ?? ''));
        $descText = $description !== '' ? $description : str_replace('_', ' ', $topic);

        $labels = [];
        foreach (array_merge([$topic], $doc['aliases'] ?? [], $doc['tags'] ?? [], $doc['subtopics'] ?? []) as $label) {
            $label = trim((string)$label);
            if ($label === '') {
                continue;
            }
            $labels[$label] = FactSkill::embed(str_replace('_', ' ', $label));
        }

        return [
            'description' => FactSkill::embed($descText),
            'labels' => $labels,
        ];
    }

    private function resolveViaAliases(string $safeCandidate): ?string
    {
        foreach ($this->listTopics() as $topic) {
            if ($topic === $safeCandidate) {
                return $topic;
            }
            $doc = $this->readTopicDocument($topic);
            foreach (array_merge($doc['aliases'], $doc['tags']) as $alias) {
                $a = $this->safeTopicName((string)$alias);
                if ($a !== '' && $a === $safeCandidate) {
                    return $topic;
                }
            }
        }
        return null;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $s = trim((string)$item);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return array_values(array_unique($out));
    }

    private function readJsonFile(string $file): mixed
    {
        $json = @file_get_contents($file);
        if ($json === false || trim($json) === '') {
            return [];
        }

        $data = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : [];
    }

    private function writeJsonFile(string $file, array $data): void
    {
        $directory = dirname($file);
        $this->ensureDirectory($directory);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporary = $file . '.tmp.' . bin2hex(random_bytes(4));

        $handle = @fopen($temporary, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to create temporary memory file: ' . $temporary);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock memory file: ' . $temporary);
            }

            if (fwrite($handle, $json) === false || !fflush($handle)) {
                throw new RuntimeException('Unable to write memory file: ' . $file);
            }

            flock($handle, LOCK_UN);
            fclose($handle);

            if (!@rename($temporary, $file)) {
                @unlink($temporary);
                throw new RuntimeException('Unable to replace memory file: ' . $file);
            }
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
            @unlink($temporary);
            throw $exception;
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create memory directory: ' . $directory);
        }
    }

    /**
     * Map known misspellings / noisy topics to canonical keys.
     */
    private function canonicalTopicAlias(string $safe): ?string
    {
        $aliases = [
            'vecrot_embedings' => 'vector_embeddings',
            'vecrot_embeddings' => 'vector_embeddings',
            'vector_embedings' => 'vector_embeddings',
            'vector_embedding' => 'vector_embeddings',
            'a_sky_stream_puck' => 'sky_stream_puck',
            'sky_stream_pick' => 'sky_stream_puck',
            'skystream_puck' => 'sky_stream_puck',
            'i_build_true' => 'building_trust',
            'build_true' => 'building_trust',
            'building_true' => 'building_trust',
        ];
        if (isset($aliases[$safe])) {
            return $aliases[$safe];
        }
        $stripped = preg_replace('/^(?:a|an|the)_+/i', '', $safe) ?? $safe;
        if ($stripped !== $safe && $stripped !== '') {
            if (isset($aliases[$stripped])) {
                return $aliases[$stripped];
            }
            // Only resolve to stripped form if that topic already exists
            if (in_array($stripped, $this->listTopics(), true)) {
                return $stripped;
            }
        }
        return null;
    }
}

} // class_exists MemoryStore
