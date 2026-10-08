<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/LocalEmbedder.php';

/**
 * Fact extraction + local embedding skill.
 *
 * - Extract factual statements from text or prompts
 * - Classify fact types (definition, procedure, code_html, statement)
 * - Generate vectors via LocalEmbedder (pure PHP)
 * - Score facts against a query for Knowledge decision-making
 *
 * Does NOT verify truth. Does NOT store anything.
 * OpenRouter is NOT used here (chat only in KnowledgeSkill).
 */
class FactSkill
{
    /** @var list<string> */
    private static array $stopWords = [
        'a', 'an', 'the', 'and', 'or', 'but', 'if', 'then', 'so', 'as', 'of', 'at',
        'by', 'for', 'from', 'in', 'into', 'on', 'onto', 'to', 'with', 'without',
        'is', 'are', 'was', 'were', 'be', 'been', 'being', 'do', 'does', 'did',
        'can', 'could', 'would', 'should', 'will', 'may', 'might', 'must',
        'this', 'that', 'these', 'those', 'it', 'its', 'you', 'your', 'we', 'our',
        'they', 'them', 'their', 'i', 'me', 'my', 'what', 'which', 'who', 'whom',
        'how', 'why', 'when', 'where', 'here', 'there', 'about', 'also', 'just',
        'only', 'very', 'more', 'most', 'some', 'any', 'all', 'not', 'no', 'yes',
    ];

    /** @var array<string, list<float>> Request-scoped embed cache */
    private static array $embedCache = [];

    /**
     * Extract facts from text and attach local embeddings.
     *
     * @return list<array<string, mixed>>
     */
    public static function extractFactsFromText(
        string $text,
        string $source = 'openrouter',
        float $confidence = 0.80
    ): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $confidence = max(0.0, min(1.0, $confidence));
        $now = gmdate('c');
        $facts = [];
        $seen = [];

        // Pull fenced code / HTML documents as high-value code_html facts
        foreach (self::extractCodeDocuments($text) as $doc) {
            $key = self::normalizeForComparison(
                function_exists('mb_substr') ? mb_substr($doc, 0, 200) : substr($doc, 0, 200)
            );
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $embedSrc = function_exists('mb_substr') ? mb_substr($doc, 0, 800) : substr($doc, 0, 800);
            $facts[] = self::makeFactRecord(
                $doc,
                'code_html',
                min(0.95, $confidence + 0.10),
                $source,
                $now,
                self::embed($embedSrc),
                self::extractKeywords($embedSrc)
            );
        }

        // Strip code fences before prose extraction so we don't double-count
        $prose = self::stripCodeFences($text);
        $sentences = self::splitIntoStatements($prose);

        foreach ($sentences as $statement) {
            $statement = self::cleanStatement($statement);
            if (!self::isUsableStatement($statement)) {
                continue;
            }

            $key = self::normalizeForComparison($statement);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $type = self::classifyStatement($statement);
            $conf = self::adjustConfidence($confidence, $type, $statement);
            $keywords = self::extractKeywords($statement);

            $facts[] = self::makeFactRecord(
                $statement,
                $type,
                $conf,
                $source,
                $now,
                self::embed($statement),
                $keywords
            );
        }

        return $facts;
    }

    /**
     * Extract prompt intent + lightweight content signals for PU / Knowledge.
     *
     * @return list<array<string, mixed>>
     */
    public static function extractFactsFromPrompt(string $prompt): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return [];
        }

        $now = gmdate('c');
        $keywords = self::extractKeywords($prompt);
        $facts = [];

        $facts[] = self::makeFactRecord(
            $prompt,
            'prompt_intent',
            0.70,
            'prompt',
            $now,
            self::embed($prompt),
            $keywords
        );

        // Core query without question fluff — better vector for retrieval
        $core = self::coreQueryFromPrompt($prompt);
        if ($core !== '' && self::normalizeForComparison($core) !== self::normalizeForComparison($prompt)) {
            $facts[] = self::makeFactRecord(
                $core,
                'prompt_core',
                0.75,
                'prompt',
                $now,
                self::embed($core),
                self::extractKeywords($core)
            );
        }

        return $facts;
    }

    /**
     * Local embedding only (pure PHP).
     *
     * @return list<float>
     */
    public static function embed(string $text): array
    {
        return self::embedCached($text);
    }

    /**
     * Cached embedding for request-scoped performance.
     * Clears automatically between requests (static array is per-request).
     *
     * @return list<float>
     */
    public static function embedCached(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return array_fill(0, LocalEmbedder::DIMENSIONS, 0.0);
        }

        $key = md5($text);

        if (!isset(self::$embedCache[$key])) {
            self::$embedCache[$key] = LocalEmbedder::embed($text);
        }

        return self::$embedCache[$key];
    }

    /**
     * Clear the embed cache (call at end of request if needed).
     */
    public static function clearEmbedCache(): void
    {
        self::$embedCache = [];
    }

    /**
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public static function embedBatch(array $texts): array
    {
        return LocalEmbedder::embedBatch($texts);
    }

    /**
     * Score a fact against a query (vector + keyword). Used by Knowledge ranking.
     *
     * @param array<string, mixed> $fact
     * @param list<float> $queryEmbedding
     * @param list<string> $queryKeywords
     * @return array{vector: float, keyword: float, combined: float}
     */
    public static function scoreFactAgainstQuery(
        array $fact,
        string $queryText,
        array $queryEmbedding = [],
        array $queryKeywords = []
    ): array {
        $content = trim((string)($fact['content'] ?? $fact['value'] ?? ''));
        $vectorScore = 0.0;

        $factEmb = $fact['embedding'] ?? null;
        if (is_array($factEmb) && $queryEmbedding !== [] && count($factEmb) === count($queryEmbedding)) {
            $vectorScore = self::cosineSimilarity($queryEmbedding, $factEmb);
        } elseif ($content !== '' && $queryText !== '') {
            $vectorScore = self::cosineSimilarity(self::embed($queryText), self::embed($content));
        }

        if ($queryKeywords === []) {
            $queryKeywords = self::extractKeywords($queryText);
        }

        $factKeywords = [];
        if (isset($fact['keywords']) && is_array($fact['keywords'])) {
            $factKeywords = array_map('strval', $fact['keywords']);
        } elseif (isset($fact['metadata']['keywords']) && is_array($fact['metadata']['keywords'])) {
            $factKeywords = array_map('strval', $fact['metadata']['keywords']);
        } else {
            $factKeywords = self::extractKeywords($content);
        }

        $keywordScore = self::keywordOverlapScore($queryKeywords, $factKeywords, $queryText, $content);

        // Type prior: definitions / procedures slightly preferred for Q&A
        $type = strtolower((string)($fact['type'] ?? 'statement'));
        $typeBoost = match ($type) {
            'definition' => 0.04,
            'procedure' => 0.03,
            'code_html' => 0.05,
            'example' => 0.02,
            'warning' => 0.04,
            'best_practice' => 0.05,
            'prompt_core' => 0.02,
            'prompt_intent' => -0.05,
            default => 0.0,
        };

        $combined = min(1.0, (0.62 * $vectorScore) + (0.30 * $keywordScore) + $typeBoost);

        return [
            'vector' => $vectorScore,
            'keyword' => $keywordScore,
            'combined' => $combined,
        ];
    }

    /**
     * Merge fact collections, keeping or generating local embeddings.
     *
     * @return list<array<string, mixed>>
     */
    public static function mergeFacts(array $a, array $b): array
    {
        $merged = [];
        $seen = [];

        foreach ([$a, $b] as $collection) {
            foreach ($collection as $fact) {
                if (is_string($fact)) {
                    $fact = ['content' => trim($fact)];
                }
                if (!is_array($fact)) {
                    continue;
                }

                $content = trim((string)(
                    $fact['content'] ?? $fact['value'] ?? $fact['text'] ?? ''
                ));
                if ($content === '') {
                    continue;
                }

                $key = self::normalizeForComparison($content);
                if ($key !== '' && isset($seen[$key])) {
                    // Keep higher confidence when duplicate
                    $idx = $seen[$key];
                    $oldConf = (float)($merged[$idx]['confidence'] ?? 0);
                    $newConf = (float)($fact['confidence'] ?? 0);
                    if ($newConf > $oldConf) {
                        $merged[$idx] = self::normalizeFactArray($fact, $content);
                    }
                    continue;
                }

                if ($key !== '') {
                    $seen[$key] = count($merged);
                }

                $merged[] = self::normalizeFactArray($fact, $content);
            }
        }

        return array_values($merged);
    }

    /**
     * Pure PHP cosine similarity.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $lenA = count($a);
        $lenB = count($b);
        if ($lenA === 0 || $lenA !== $lenB) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < $lenA; $i++) {
            $va = (float)$a[$i];
            $vb = (float)$b[$i];
            $dot += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Important content words from text (for keyword overlap / PU).
     *
     * @return list<string>
     */
    public static function extractKeywords(string $text, int $limit = 12): array
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        $text = preg_replace('/[^a-z0-9\s\-]+/u', ' ', $text) ?? $text;
        $parts = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        $stop = array_flip(self::$stopWords);

        foreach ($parts as $w) {
            $w = trim($w, '-_');
            if ($w === '' || isset($stop[$w])) {
                continue;
            }
            if (strlen($w) < 3 && !preg_match('/^(php|css|html|sql|api|ai)$/i', $w)) {
                continue;
            }
            $out[$w] = true;
            if (count($out) >= $limit) {
                break;
            }
        }

        return array_keys($out);
    }

    public static function coreQueryFromPrompt(string $prompt): string
    {
        $s = trim($prompt);
        $s = preg_replace(
            '/^(?:please\s+)?(?:can\s+you\s+|could\s+you\s+|would\s+you\s+)?(?:tell\s+me\s+|explain\s+|describe\s+|what\s+is\s+|what\s+are\s+|how\s+(?:do\s+i|to)\s+|make\s+me\s+|create\s+|write\s+|generate\s+|build\s+)?/i',
            '',
            $s
        ) ?? $s;
        $s = trim($s, " \t\n\r\0\x0B?.!");
        return trim($s);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private static function extractCodeDocuments(string $text): array
    {
        $docs = [];

        if (preg_match_all('/```(?:html|php|htm)?\s*\n(.*?)```/is', $text, $m)) {
            foreach ($m[1] as $block) {
                $block = trim($block);
                if ($block !== '' && preg_match('/<!DOCTYPE\s+html|<html\b|<\?php/i', $block)) {
                    $docs[] = $block;
                }
            }
        }

        // Bare HTML document in the response
        if (preg_match('/<!DOCTYPE\s+html[\s\S]*<\/html>/i', $text, $m2)) {
            $docs[] = trim($m2[0]);
        }

        return $docs;
    }

    private static function stripCodeFences(string $text): string
    {
        $text = preg_replace('/```[\s\S]*?```/u', "\n", $text) ?? $text;
        $text = preg_replace('/<!DOCTYPE\s+html[\s\S]*<\/html>/i', "\n", $text) ?? $text;
        return $text;
    }

    /**
     * @return list<string>
     */
    private static function splitIntoStatements(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^[ \t]*(?:[-*+] |\d+[.)] )/m', '', $text) ?? $text;

        $lines = preg_split('/\n+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $statements = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Prefer sentence boundaries; keep long instructional lines whole
            if ((function_exists('mb_strlen') ? mb_strlen($line) : strlen($line)) > 280
                && preg_match('/[.!?]+\s+/u', $line)
            ) {
                $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9"\'])/u', $line) ?: [$line];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $statements[] = $part;
                    }
                }
            } else {
                $statements[] = $line;
            }
        }

        return $statements;
    }

    private static function cleanStatement(string $statement): string
    {
        $statement = trim($statement);
        $statement = preg_replace('/^#{1,6}\s+/', '', $statement) ?? $statement;
        $statement = preg_replace('/^[-*+]\s+/', '', $statement) ?? $statement;
        $statement = preg_replace('/^\d+[.)]\s+/', '', $statement) ?? $statement;
        $statement = preg_replace('/\*\*(.*?)\*\*/s', '$1', $statement) ?? $statement;
        $statement = preg_replace('/__(.*?)__/s', '$1', $statement) ?? $statement;
        $statement = preg_replace('/`([^`]*)`/', '$1', $statement) ?? $statement;
        $statement = preg_replace('/\s+/u', ' ', $statement) ?? $statement;

        return trim($statement);
    }

    private static function isUsableStatement(string $statement): bool
    {
        $len = function_exists('mb_strlen') ? mb_strlen($statement) : strlen($statement);
        if ($statement === '' || $len < 12) {
            return false;
        }
        if ($len > 1200) {
            return false;
        }

        if (preg_match(
            '/^(?:source|confidence|metadata|prompt_intent|statement|openrouter)\s*:/i',
            $statement
        )) {
            return false;
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $statement)) {
            return false;
        }

        // Filler / non-facts
        if (preg_match(
            '/^(?:sure|of course|certainly|here is|here\'s|as an ai|i hope this helps|let me know|happy to help)\b/i',
            $statement
        )) {
            return false;
        }

        if (preg_match(
            '/\b(?:it seems you are asking|you are asking about|i do not have|i don\'t have|as an ai language model)\b/i',
            $statement
        )) {
            return false;
        }

        // Incomplete tails
        if (preg_match('/:\s*$/', $statement) || preg_match('/\bonly if it:\s*$/i', $statement)) {
            return false;
        }

        // Prefer statements with some substance (letter density)
        $letters = preg_match_all('/[a-zA-Z]/u', $statement);
        if ($letters < 8) {
            return false;
        }

        return true;
    }

    private static function classifyStatement(string $statement): string
    {
        $s = function_exists('mb_strtolower') ? mb_strtolower($statement) : strtolower($statement);

        if (preg_match('/<!doctype\s+html|<html\b|<\?php/i', $statement)) {
            return 'code_html';
        }
        if (preg_match('/\b(?:is|are)\s+(?:a|an|the)\b/i', $statement)
            || preg_match('/\b(?:is|are)\s+(?:dense|numeric|used|called|known)\b/i', $s)
            || preg_match('/\b(?:refers to|defined as|means that|is known as|are dense)\b/i', $s)
            || preg_match('/^[A-Z][^.]{0,80}\s+(?:is|are)\s+/', $statement)
        ) {
            return 'definition';
        }
        if (preg_match('/\b(?:step\s*\d|first|then|next|finally|to\s+\w+|you\s+should|make sure)\b/i', $s)
            || preg_match('/^\d+[.)]/', $statement)
        ) {
            return 'procedure';
        }

        // New fact types
        if (preg_match('/\b(?:example|for instance|such as|like|e\.g\.)\b/i', $s)) {
            return 'example';
        }
        if (preg_match('/\b(?:warning|caution|be careful|note|important)\b/i', $s)) {
            return 'warning';
        }
        if (preg_match('/\b(?:best practice|recommended|should|prefer|it is recommended)\b/i', $s)) {
            return 'best_practice';
        }

        return 'statement';
    }

    private static function adjustConfidence(float $base, string $type, string $statement): float
    {
        $c = $base;
        if ($type === 'definition') {
            $c += 0.05;
        } elseif ($type === 'procedure') {
            $c += 0.03;
        } elseif ($type === 'code_html') {
            $c += 0.08;
        } elseif ($type === 'example') {
            $c += 0.02;
        } elseif ($type === 'warning') {
            $c += 0.04;
        } elseif ($type === 'best_practice') {
            $c += 0.06;
        }

        $len = function_exists('mb_strlen') ? mb_strlen($statement) : strlen($statement);
        if ($len < 40) {
            $c -= 0.05;
        } elseif ($len > 80 && $len < 400) {
            $c += 0.03;
        }

        return max(0.05, min(0.98, $c));
    }

    /**
     * @param list<string> $queryKeywords
     * @param list<string> $factKeywords
     */
    private static function keywordOverlapScore(
        array $queryKeywords,
        array $factKeywords,
        string $queryText,
        string $content
    ): float {
        if ($queryKeywords === [] || $factKeywords === []) {
            // fallback substring-ish
            $q = function_exists('mb_strtolower') ? mb_strtolower($queryText) : strtolower($queryText);
            $c = function_exists('mb_strtolower') ? mb_strtolower($content) : strtolower($content);
            if ($q === '' || $c === '') {
                return 0.0;
            }
            $hits = 0;
            foreach (preg_split('/\s+/', $q) ?: [] as $w) {
                if (strlen($w) >= 4 && str_contains($c, $w)) {
                    $hits++;
                }
            }
            return min(1.0, $hits * 0.12);
        }

        $factSet = array_flip($factKeywords);
        $hits = 0;
        foreach ($queryKeywords as $kw) {
            if (isset($factSet[$kw])) {
                $hits++;
            }
        }

        return min(1.0, $hits / max(1, min(6, count($queryKeywords))));
    }

    /**
     * @param list<string> $keywords
     * @param list<float> $embedding
     * @return array<string, mixed>
     */
    private static function makeFactRecord(
        string $content,
        string $type,
        float $confidence,
        string $source,
        string $timestamp,
        array $embedding = [],
        array $keywords = []
    ): array {
        return [
            'type'         => $type,
            'value'        => $content,
            'content'      => $content,
            'confidence'   => $confidence,
            'source'       => $source,
            'created_at'   => $timestamp,
            'updated_at'   => $timestamp,
            'access_count' => 0,
            'decay_rate'   => 0.05,
            'embedding'    => $embedding,
            'keywords'     => $keywords,
            'metadata'     => [
                'type'       => $type,
                'confidence' => $confidence,
                'source'     => $source,
                'keywords'   => $keywords,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $fact
     * @return array<string, mixed>
     */
    private static function normalizeFactArray(array $fact, string $content): array
    {
        if (!isset($fact['content'])) {
            $fact['content'] = $content;
        }
        if (!isset($fact['value'])) {
            $fact['value'] = $content;
        }
        if (empty($fact['embedding']) || !is_array($fact['embedding'])) {
            $fact['embedding'] = self::embed($content);
        } elseif (count($fact['embedding']) !== LocalEmbedder::DIMENSIONS) {
            $fact['embedding'] = self::embed($content);
        }
        if (empty($fact['keywords']) || !is_array($fact['keywords'])) {
            $fact['keywords'] = self::extractKeywords($content);
        }
        if (!isset($fact['type'])) {
            $fact['type'] = self::classifyStatement($content);
        }

        return $fact;
    }

    private static function normalizeForComparison(string $text): string
    {
        $text = trim($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B.,!?;:");

        return $text;
    }
}
