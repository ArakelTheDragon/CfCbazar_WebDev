<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/LocalEmbedder.php';

/**
 * Fact extraction + local embedding skill.
 *
 * - Extract factual statements from text or prompts.
 * - Generate vectors via LocalEmbedder (pure PHP, no API).
 * - Normalize records for MemoryStore.
 *
 * Does NOT verify truth. Does NOT store anything.
 * OpenRouter is NOT used here (chat only in KnowledgeSkill).
 */
class FactSkill
{
    /**
     * Extract facts from text and attach local embeddings.
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
     * Local embedding only (pure PHP).
     *
     * @return list<float>
     */
    public static function embed(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return array_fill(0, LocalEmbedder::DIMENSIONS, 0.0);
        }

        return LocalEmbedder::embed($text);
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
     * Merge fact collections, keeping or generating local embeddings.
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

                // Keep existing local embedding or generate
                if (empty($fact['embedding']) || !is_array($fact['embedding'])) {
                    $fact['embedding'] = self::embed($content);
                } elseif (count($fact['embedding']) !== LocalEmbedder::DIMENSIONS) {
                    // Re-embed if dimension mismatch (e.g. old OpenRouter vectors)
                    $fact['embedding'] = self::embed($content);
                }

                $merged[] = $fact;
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
            $va = (float) $a[$i];
            $vb = (float) $b[$i];
            $dot   += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    private static function splitIntoStatements(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^[ \t]*(?:[-*+] |\d+[.)] )/m', '', $text) ?? $text;

        // Preserve code blocks as single statements
        $statements = [];
        $inCodeBlock = false;
        $codeBlockContent = '';
        $currentStatement = '';

        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Detect code fence markers
            if (preg_match('/^```(\w*)$/', $trimmedLine, $matches)) {
                if ($inCodeBlock) {
                    // End of code block
                    $codeBlockContent .= $line . "\n";
                    $statements[] = trim($codeBlockContent);
                    $codeBlockContent = '';
                    $inCodeBlock = false;
                } else {
                    // Start of code block
                    if ($currentStatement !== '') {
                        $statements[] = trim($currentStatement);
                        $currentStatement = '';
                    }
                    $codeBlockContent = $line . "\n";
                    $inCodeBlock = true;
                }
                continue;
            }

            if ($inCodeBlock) {
                // Preserve code block exactly as-is
                $codeBlockContent .= $line . "\n";
            } else {
                // Regular text processing
                if ($trimmedLine === '') {
                    if ($currentStatement !== '') {
                        $statements[] = trim($currentStatement);
                        $currentStatement = '';
                    }
                } else {
                    $currentStatement .= ($currentStatement !== '' ? ' ' : '') . $trimmedLine;
                }
            }
        }

        // Handle remaining content
        if ($inCodeBlock && $codeBlockContent !== '') {
            $statements[] = trim($codeBlockContent);
        } elseif ($currentStatement !== '') {
            $statements[] = trim($currentStatement);
        }

        // Split non-code statements by sentence boundaries
        $finalStatements = [];
        foreach ($statements as $statement) {
            // Check if this is a code block
            if (preg_match('/^```/', $statement)) {
                $finalStatements[] = $statement;
            } else {
                // Split by sentence boundaries
                $parts = preg_split(
                    '/(?<=[.!?])\s+(?=[A-Z0-9"\'\(])|(?<=[.!?])$/',
                    $statement,
                    -1,
                    PREG_SPLIT_NO_EMPTY
                );

                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $finalStatements[] = $part;
                        }
                    }
                }
            }
        }

        if ($finalStatements === [] && trim($text) !== '') {
            $finalStatements[] = trim($text);
        }

        return $finalStatements;
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
