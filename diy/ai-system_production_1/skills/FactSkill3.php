<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Helpers.php';

/**
 * Fact extraction + embedding skill.
 *
 * Responsibility:
 * - Extract factual statements from text or prompts.
 * - Generate vector embeddings via OpenRouter (pure PHP).
 * - Normalize records for MemoryStore (facts include embedding).
 *
 * Does NOT verify truth. Does NOT store anything.
 */
class FactSkill
{
    private const EMBEDDING_MODEL = 'openai/text-embedding-3-small';
    private const EMBEDDING_URL   = 'https://openrouter.ai/api/v1/embeddings';

    /**
     * Extract facts from text and attach embeddings.
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
        $sentences = self::splitIntoStatements($text);
        $facts = [];
        $seen = [];
        $now = gmdate('c');

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

            $embedding = self::embed($statement);

            $facts[] = self::makeFactRecord(
                $statement,
                'statement',
                $confidence,
                $source,
                $now,
                $embedding
            );
        }

        return $facts;
    }

    /**
     * Extract prompt as intent record + embedding.
     */
    public static function extractFactsFromPrompt(string $prompt): array
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            return [];
        }

        $now = gmdate('c');
        $embedding = self::embed($prompt);

        return [
            self::makeFactRecord(
                $prompt,
                'prompt_intent',
                0.70,
                'prompt',
                $now,
                $embedding
            )
        ];
    }

    /**
     * Generate embedding vector for a single text via OpenRouter.
     * Returns float[] or empty array on failure.
     */
    public static function embed(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        try {
            $config = require __DIR__ . '/../config/openrouter.php';
        } catch (Throwable $e) {
            return [];
        }

        $payload = [
            'model' => self::EMBEDDING_MODEL,
            'input' => $text,
        ];

        $response = Helpers::httpPostJson(
            self::EMBEDDING_URL,
            ['Authorization: Bearer ' . $config['api_key']],
            $payload
        );

        if (!is_array($response) || isset($response['error'])) {
            return [];
        }

        $vector = $response['data'][0]['embedding'] ?? null;

        if (!is_array($vector)) {
            return [];
        }

        // Ensure pure floats
        return array_map('floatval', $vector);
    }

    /**
     * Batch embed multiple texts (one request if possible, fallback sequential).
     * Returns array of float[] in same order.
     */
    public static function embedBatch(array $texts): array
    {
        $results = [];
        foreach ($texts as $text) {
            $results[] = self::embed((string)$text);
        }
        return $results;
    }

    /**
     * Merge fact collections, keeping embeddings when present.
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

                $content = trim((string) (
                    $fact['content']
                    ?? $fact['value']
                    ?? $fact['text']
                    ?? ''
                ));

                if ($content === '') {
                    continue;
                }

                $key = self::normalizeForComparison($content);

                if ($key !== '' && isset($seen[$key])) {
                    continue;
                }

                if ($key !== '') {
                    $seen[$key] = true;
                }

                if (!isset($fact['content'])) {
                    $fact['content'] = $content;
                }

                if (!isset($fact['value'])) {
                    $fact['value'] = $content;
                }

                // Preserve or generate embedding
                if (empty($fact['embedding']) || !is_array($fact['embedding'])) {
                    $fact['embedding'] = self::embed($content);
                }

                $merged[] = $fact;
            }
        }

        return array_values($merged);
    }

    /**
     * Pure PHP cosine similarity between two vectors.
     * Returns 0.0 on invalid input.
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
            $dot   += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    // -------------------------------------------------------------------------
    // Internal helpers (unchanged logic)
    // -------------------------------------------------------------------------

    private static function splitIntoStatements(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^[ \t]*(?:[-*+] |\d+[.)] )/m', '', $text) ?? $text;

        $lines = preg_split('/\n+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $statements = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split(
                '/(?<=[.!?])\s+(?=[A-Z0-9"\'\(])|(?<=[.!?])$/',
                $line,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            if (is_array($parts)) {
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $statements[] = $part;
                    }
                }
            }
        }

        if ($statements === [] && trim($text) !== '') {
            $statements[] = trim($text);
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

        return trim($statement);
    }

    private static function isUsableStatement(string $statement): bool
    {
        if ($statement === '' || mb_strlen($statement) < 2) {
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

        return true;
    }

    private static function makeFactRecord(
        string $content,
        string $type,
        float $confidence,
        string $source,
        string $timestamp,
        array $embedding = []
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
            'metadata'     => [
                'type'         => $type,
                'confidence'   => $confidence,
                'source'       => $source,
                'created_at'   => $timestamp,
                'updated_at'   => $timestamp,
                'access_count' => 0,
                'decay_rate'   => 0.05,
            ]
        ];
    }

    private static function normalizeForComparison(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B.,!?;:");

        return $text;
    }
}
