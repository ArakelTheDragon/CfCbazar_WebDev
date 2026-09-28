<?php

declare(strict_types=1);

if (!class_exists('ResponseUnderstandingSkill', false)) {

    /**
     * Final response synthesis and cleanup layer.
     *
     * Important rule:
     * Code and other structure-sensitive blocks are protected before prose
     * cleanup. They are never split, deduplicated, whitespace-normalized, or
     * converted into ordinary sentences.
     *
     * This class does not verify facts, call OpenRouter, or modify memory.
     */
    class ResponseUnderstandingSkill
    {
        public function execute(mixed $response): string
        {
            $raw = $this->prepareRawResponse($response);

            if ($raw === '') {
                return 'No response generated.';
            }

            // Protect fenced code, indented code, and other structure-sensitive
            // blocks before any prose processing takes place.
            $protected = $this->protectStructuredBlocks($raw);
            $text = $protected['text'];
            $blocks = $protected['blocks'];

            $statements = $this->extractProseStatements($text);
            $statements = $this->deduplicateStatements($statements);

            // Restore the protected blocks exactly as supplied. Code is never
            // passed through whitespace normalization or sentence splitting.
            return trim($this->restoreStructuredBlocks($statements, $blocks));
        }

        private function prepareRawResponse(mixed $response): string
        {
            if (is_array($response)) {
                $raw = $this->arrayToText($response);
            } elseif (is_string($response)) {
                $raw = $response;
            } else {
                return '';
            }

            $raw = trim($raw);
            if ($raw === '') {
                return '';
            }

            // Decode JSON only when the complete response is JSON.
            if ($this->looksLikeJson($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = trim($this->arrayToText($decoded));
                }
            }

            return $raw;
        }

        /**
         * Replace protected blocks with placeholders.
         *
         * Fenced blocks are the primary protection mechanism. Indented code
         * blocks are also protected when they contain multiple code-like lines.
         */
        private function protectStructuredBlocks(string $raw): array
        {
            $blocks = [];
            $counter = 0;

            // Fenced Markdown code blocks. The complete block, including its
            // language marker, is preserved byte-for-byte.
            $fencePattern = '/```[^\r\n]*\r?\n[\s\S]*?```/';

            $text = preg_replace_callback(
                $fencePattern,
                function (array $match) use (&$blocks, &$counter): string {
                    $key = "@@CFCCODEBLOCK_{$counter}__@@";
                    $blocks[$key] = $match[0];
                    $counter++;
                    return "\n{$key}\n";
                },
                $raw
            );

            if ($text === null) {
                $text = $raw;
            }

            // Indented code blocks. Only protect runs of at least two
            // consecutive indented lines so ordinary indented prose is not
            // accidentally converted into code.
            $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
            $output = [];
            $indented = [];

            $flushIndented = function () use (&$indented, &$output, &$blocks, &$counter): void {
                if (count($indented) < 2) {
                    foreach ($indented as $line) {
                        $output[] = $line;
                    }
                    $indented = [];
                    return;
                }

                $key = "@@CFCCODEBLOCK_{$counter}__@@";
                $blocks[$key] = implode("\n", $indented);
                $counter++;
                $output[] = $key;
                $indented = [];
            };

            foreach ($lines as $line) {
                if (preg_match('/^(?: {4}|\t)\S?.*$/', $line) === 1) {
                    $indented[] = $line;
                } else {
                    $flushIndented();
                    $output[] = $line;
                }
            }
            $flushIndented();

            return [
                'text' => implode("\n", $output),
                'blocks' => $blocks,
            ];
        }

        /**
         * Clean and split prose only. Placeholders for protected blocks are
         * deliberately kept intact and become standalone response sections.
         */
        private function extractProseStatements(string $text): array
        {
            // Remove internal headings only from prose. Protected code is gone
            // from this string, so this cannot damage code comments or strings.
            $text = preg_replace(
                '/^\s*#{1,6}\s*(?:Definition of|How .*? works|Why .*? happens|Comparison related to|List of information about|Information about)\s+[^\n]+\s*/im',
                '',
                $text
            ) ?? $text;

            // Remove internal metadata from prose.
            $text = preg_replace('/\(\s*confidence\s*:\s*[0-9.]+\s*,?\s*source\s*:\s*[^)]*\)/i', '', $text) ?? $text;
            $text = preg_replace('/\bconfidence\s*:\s*[0-9.]+\b\s*,?/i', '', $text) ?? $text;
            $text = preg_replace('/\bsource\s*:\s*(?:openrouter|prompt|memory|local)\b\s*,?/i', '', $text) ?? $text;
            $text = preg_replace('/\b(?:prompt_intent|statement)\s*:\s*/i', '', $text) ?? $text;

            // Remove Markdown presentation from prose only.
            $text = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $text) ?? $text;
            $text = preg_replace('/\*\*(.*?)\*\*/s', '$1', $text) ?? $text;
            $text = preg_replace('/__(.*?)__/s', '$1', $text) ?? $text;
            $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '$1', $text) ?? $text;
            $text = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '$1', $text) ?? $text;

            // Markdown list markers become statement boundaries. This is safe
            // because code blocks have already been replaced by placeholders.
            $text = preg_replace('/^[ \t]*(?:[-*+]\s+|\d+[.)]\s+)/m', '|||SPLIT|||', $text) ?? $text;

            // Keep placeholders as individual sections. Do not collapse all
            // newlines into spaces because doing so would make code restoration
            // and readable paragraphs unreliable.
            $chunks = preg_split('/(?:\|\|\|SPLIT\|\|\||\r\n|\r|\n)+/', $text) ?: [];

            $statements = [];

            foreach ($chunks as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '') {
                    continue;
                }

                if ($this->isCodePlaceholder($chunk)) {
                    $statements[] = $chunk;
                    continue;
                }

                $chunk = preg_replace('/^[\s*\-:()]+/', '', $chunk) ?? $chunk;
                $chunk = preg_replace('/[\s*]+$/', '', $chunk) ?? $chunk;
                $chunk = trim($chunk);

                if ($chunk === '' || $this->isMetadataOnly($chunk)) {
                    continue;
                }

                // Split ordinary prose into sentences only. Code is already
                // protected and therefore cannot be affected by this regex.
                $sentences = preg_split(
                    '/(?<=[.!?])\s+(?=[A-Z0-9])/',
                    $chunk
                ) ?: [$chunk];

                foreach ($sentences as $sentence) {
                    $sentence = trim($sentence);
                    if ($sentence === '' || $this->isMetadataOnly($sentence)) {
                        continue;
                    }
                    $statements[] = $sentence;
                }
            }

            return $statements;
        }

        /**
         * Rebuild the final answer while preserving protected blocks exactly.
         */
        private function restoreStructuredBlocks(array $statements, array $blocks): string
        {
            if (empty($statements)) {
                return '';
            }

            $parts = [];

            foreach ($statements as $statement) {
                $statement = trim((string)$statement);
                if ($statement === '') {
                    continue;
                }

                if ($this->isCodePlaceholder($statement) && isset($blocks[$statement])) {
                    $parts[] = $blocks[$statement];
                    continue;
                }

                // Normal prose gets a small amount of whitespace cleanup.
                $statement = preg_replace('/[ \t]+/', ' ', $statement) ?? $statement;
                $parts[] = trim($statement);
            }

            return implode("\n\n", $parts);
        }

        private function deduplicateStatements(array $statements): array
        {
            $result = [];
            $seen = [];

            foreach ($statements as $statement) {
                $statement = trim((string)$statement);
                if ($statement === '') {
                    continue;
                }

                // Never deduplicate code blocks using prose similarity rules.
                if ($this->isCodePlaceholder($statement)) {
                    $result[] = $statement;
                    continue;
                }

                $matchedIndex = null;
                foreach ($result as $index => $existing) {
                    if ($this->isCodePlaceholder($existing)) {
                        continue;
                    }

                    if ($this->areStatementsAlike($statement, $existing)) {
                        $matchedIndex = $index;
                        break;
                    }
                }

                if ($matchedIndex !== null) {
                    if ($this->informativenessScore($statement) > $this->informativenessScore($result[$matchedIndex])) {
                        $result[$matchedIndex] = $statement;
                    }
                    continue;
                }

                $key = $this->normalizeForComparison($statement);
                if ($key !== '' && isset($seen[$key])) {
                    continue;
                }

                if ($key !== '') {
                    $seen[$key] = true;
                }
                $result[] = $statement;
            }

            return $result;
        }

        private function arrayToText(array $data): string
        {
            $parts = [];

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $nested = $this->arrayToText($value);
                    if ($nested !== '') {
                        $parts[] = $nested;
                    }
                    continue;
                }

                if (!is_scalar($value)) {
                    continue;
                }

                $keyString = is_string($key) ? strtolower($key) : '';
                if (in_array($keyString, [
                    'confidence', 'source', 'created_at', 'updated_at',
                    'access_count', 'decay_rate', 'embedding', 'id', 'type'
                ], true)) {
                    continue;
                }

                $valueString = trim((string)$value);
                if ($valueString !== '') {
                    $parts[] = $valueString;
                }
            }

            return implode("\n", $parts);
        }

        private function isCodePlaceholder(string $text): bool
        {
            return preg_match('/^@@CFCCODEBLOCK_\d+__@@$/', trim($text)) === 1;
        }

        private function areStatementsAlike(string $a, string $b): bool
        {
            $normA = $this->normalizeForComparison($a);
            $normB = $this->normalizeForComparison($b);

            if ($normA === '' || $normB === '') {
                return false;
            }

            if ($normA === $normB) {
                return true;
            }

            if (str_contains($normA, $normB) || str_contains($normB, $normA)) {
                return true;
            }

            if ($this->isGreeting($normA) && $this->isGreeting($normB)) {
                return true;
            }

            $wordsA = $this->tokenize($normA);
            $wordsB = $this->tokenize($normB);

            if (empty($wordsA) || empty($wordsB)) {
                return false;
            }

            $intersection = array_intersect($wordsA, $wordsB);
            $union = array_unique(array_merge($wordsA, $wordsB));
            $jaccard = count($union) > 0 ? count($intersection) / count($union) : 0.0;

            if ($jaccard >= 0.70) {
                return true;
            }

            similar_text($normA, $normB, $percent);
            return $percent >= 78.0 && min(count($wordsA), count($wordsB)) >= 5;
        }

        private function normalizeForComparison(string $text): string
        {
            $text = strtolower(trim($text));
            $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;
            $text = preg_replace('/\s+/', ' ', $text) ?? $text;
            return trim($text);
        }

        private function tokenize(string $text): array
        {
            $stopWords = [
                'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been',
                'to', 'of', 'in', 'on', 'for', 'and', 'or', 'with', 'that',
                'this', 'it', 'as', 'by', 'from', 'at'
            ];

            $words = preg_split('/\s+/', trim($text)) ?: [];
            $words = array_filter($words, static function (string $word) use ($stopWords): bool {
                return $word !== '' && !in_array($word, $stopWords, true);
            });

            return array_values(array_unique($words));
        }

        private function isGreeting(string $text): bool
        {
            $words = $this->tokenize($text);
            if (count($words) > 6) {
                return false;
            }

            $greetings = ['hello', 'hi', 'hey', 'greetings', 'welcome'];
            $first = $words[0] ?? '';

            return in_array($first, $greetings, true);
        }

        private function informativenessScore(string $text): int
        {
            $words = $this->tokenize($this->normalizeForComparison($text));
            return count($words) * 2 + strlen($text);
        }

        private function isMetadataOnly(string $text): bool
        {
            $normalized = strtolower(trim($text));
            if ($normalized === '') {
                return true;
            }

            if (preg_match('/^(?:general_topic|prompt|openrouter|statement|confidence|source)$/i', $normalized)) {
                return true;
            }

            if (preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
                return true;
            }

            return false;
        }

        private function looksLikeJson(string $value): bool
        {
            $first = substr(ltrim($value), 0, 1);
            return $first === '{' || $first === '[';
        }
    }
}
