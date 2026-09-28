<?php

declare(strict_types=1);

/**
 * Fact extraction skill.
 *
 * Responsibility:
 * - Extract factual statements from text.
 * - Extract the user's prompt as a prompt-intent record when requested.
 * - Normalize extracted records into the structure expected by MemoryStore.
 *
 * This class does NOT verify whether a fact is true. KnowledgeSkill decides
 * when external knowledge is needed; MemoryStore is responsible for storage.
 */
class FactSkill
{
    /**
     * Extract individual statements from an OpenRouter response or other text.
     *
     * The default confidence is intentionally 0.80 because this value represents
     * the system's acquisition confidence, not independent factual verification.
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

            $facts[] = self::makeFactRecord(
                $statement,
                'statement',
                $confidence,
                $source,
                $now
            );
        }

        return $facts;
    }

    /**
     * Store the original prompt as a prompt-intent record when the caller needs
     * it for context. This is not presented as an externally acquired fact.
     */
    public static function extractFactsFromPrompt(string $prompt): array
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            return [];
        }

        $now = gmdate('c');

        return [
            self::makeFactRecord(
                $prompt,
                'prompt_intent',
                0.70,
                'prompt',
                $now
            )
        ];
    }

    /**
     * Merge fact collections while removing exact textual duplicates.
     *
     * MemoryStore also performs persistent deduplication. Keeping this small
     * deduplication step here prevents unnecessary duplicate records from being
     * passed further through the pipeline.
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

                // Keep existing fields intact so MemoryStore can normalize them.
                if (!isset($fact['content'])) {
                    $fact['content'] = $content;
                }

                if (!isset($fact['value'])) {
                    $fact['value'] = $content;
                }

                $merged[] = $fact;
            }
        }

        return array_values($merged);
    }

    /**
     * Split prose into usable statements while preserving bullet/list entries.
     */
    private static function splitIntoStatements(string $text): array
    {
        // Normalize line endings and common Markdown list markers.
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^[ \t]*(?:[-*+] |\d+[.)] )/m', '', $text) ?? $text;

        $lines = preg_split('/\n+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $statements = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Split normal prose at sentence boundaries. The lookahead helps
            // avoid breaking decimal numbers such as 3.14 and common initials.
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

        // If the response did not contain line/sentence boundaries, retain the
        // complete text as one statement rather than silently losing it.
        if ($statements === [] && trim($text) !== '') {
            $statements[] = trim($text);
        }

        return $statements;
    }

    private static function cleanStatement(string $statement): string
    {
        $statement = trim($statement);

        // Remove common Markdown formatting without altering the factual text.
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

        // Do not store obvious response metadata as knowledge.
        if (preg_match(
            '/^(?:source|confidence|metadata|prompt_intent|statement|openrouter)\s*:/i',
            $statement
        )) {
            return false;
        }

        // Do not create facts from an isolated numeric value.
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
        string $timestamp
    ): array {
        return [
            'type' => $type,
            'value' => $content,
            'content' => $content,
            'confidence' => $confidence,
            'source' => $source,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'access_count' => 0,
            'decay_rate' => 0.05,
            'metadata' => [
                'type' => $type,
                'confidence' => $confidence,
                'source' => $source,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'access_count' => 0,
                'decay_rate' => 0.05
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
