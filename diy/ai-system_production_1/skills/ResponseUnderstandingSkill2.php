<?php

declare(strict_types=1);

if (!class_exists('ResponseUnderstandingSkill', false)) {

    /**
     * Final response synthesis and cleanup layer.
     *
     * Responsibility:
     * - Accept the response produced by KnowledgeSkill.
     * - Remove internal metadata and presentation artifacts.
     * - Split the material into useful statements.
     * - Merge genuinely redundant statements.
     * - Return one clean response for the user.
     *
     * This class does not verify facts. KnowledgeSkill/FactSkill are
     * responsible for the knowledge supplied to this stage.
     */
    class ResponseUnderstandingSkill
    {
        public function execute(mixed $response): string
        {
            $statements = $this->extractStatements($response);

            if (empty($statements)) {
                return "No response generated.";
            }

            $clusters = [];

            foreach ($statements as $statement) {
                $matchedIndex = null;

                foreach ($clusters as $index => $cluster) {
                    if ($this->areStatementsAlike($statement, $cluster['best'])) {
                        $matchedIndex = $index;
                        break;
                    }
                }

                if ($matchedIndex === null) {
                    $clusters[] = [
                        'best' => $statement,
                    ];
                    continue;
                }

                // Keep the more informative statement when two statements
                // express substantially the same information.
                if ($this->informativenessScore($statement) > $this->informativenessScore($clusters[$matchedIndex]['best'])) {
                    $clusters[$matchedIndex]['best'] = $statement;
                }
            }

            $selected = [];
            foreach ($clusters as $cluster) {
                $selected[] = $cluster['best'];
            }

            return trim(preg_replace('/\s+/', ' ', implode(' ', $selected)) ?? '');
        }

        /**
         * Convert raw KnowledgeSkill output into clean factual statements.
         */
        private function extractStatements(mixed $response): array
        {
            if (is_array($response)) {
                $raw = $this->arrayToText($response);
            } elseif (is_string($response)) {
                $raw = $response;
            } else {
                $raw = '';
            }

            $raw = trim($raw);

            if ($raw === '') {
                return [];
            }

            // Remove JSON escaping when an upstream component supplied JSON text.
            if ($this->looksLikeJson($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $this->arrayToText($decoded);
                }
            }

            // Remove internal KnowledgeSkill headings.
            $raw = preg_replace(
                '/^\s*#{1,6}\s*(?:Definition of|How .*? works|Why .*? happens|Comparison related to|List of information about|Information about)\s+[^\n]+\s*/im',
                '',
                $raw
            ) ?? $raw;

            // Remove common internal metadata without removing normal content.
            $raw = preg_replace('/\(\s*confidence\s*:\s*[0-9.]+\s*,?\s*source\s*:\s*[^)]*\)/i', '', $raw) ?? $raw;
            $raw = preg_replace('/\bconfidence\s*:\s*[0-9.]+\b\s*,?/i', '', $raw) ?? $raw;
            $raw = preg_replace('/\bsource\s*:\s*(?:openrouter|prompt|memory|local)\b\s*,?/i', '', $raw) ?? $raw;

            // Remove common internal field labels.
            $raw = preg_replace('/\b(?:prompt_intent|statement)\s*:\s*/i', '', $raw) ?? $raw;

            // Remove Markdown presentation while retaining the actual words.
            $raw = preg_replace('/```(?:[a-zA-Z0-9_+-]+)?\s*(.*?)```/s', '$1', $raw) ?? $raw;
            $raw = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $raw) ?? $raw;
            $raw = preg_replace('/\*\*(.*?)\*\*/s', '$1', $raw) ?? $raw;
            $raw = preg_replace('/__(.*?)__/s', '$1', $raw) ?? $raw;
            $raw = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '$1', $raw) ?? $raw;
            $raw = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '$1', $raw) ?? $raw;

            // Turn Markdown bullets/numbers into statement boundaries.
            $raw = preg_replace('/^[ \t]*(?:[-*+]\s+|\d+[.)]\s+)/m', '|||SPLIT|||', $raw) ?? $raw;

            // Preserve paragraph/sentence boundaries before final normalization.
            $chunks = preg_split('/(?:\|\|\|SPLIT\|\|\||\r\n|\r|\n)+/', $raw) ?: [];

            $statements = [];

            foreach ($chunks as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '') {
                    continue;
                }

                // Remove leftover internal punctuation around metadata fields.
                $chunk = preg_replace('/^[\s*\-:()]+/', '', $chunk) ?? $chunk;
                $chunk = preg_replace('/[\s*]+$/', '', $chunk) ?? $chunk;
                $chunk = trim($chunk);

                if ($this->isMetadataOnly($chunk)) {
                    continue;
                }

                // Split ordinary prose into sentences, while avoiding splitting
                // common abbreviations and decimal numbers.
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

            return $this->removeExactDuplicates($statements);
        }

        /**
         * Convert structured arrays into readable text without exposing
         * internal metadata fields to the final response.
         */
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
                    'confidence',
                    'source',
                    'created_at',
                    'updated_at',
                    'access_count',
                    'decay_rate',
                    'embedding',
                    'id',
                    'type'
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

            // A complete statement containing a shorter equivalent statement.
            if (str_contains($normA, $normB) || str_contains($normB, $normA)) {
                return true;
            }

            // Greetings are intentionally consolidated, but only when both
            // statements are actually short greetings.
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

            // Require substantial word overlap rather than the previous broad
            // 50% similar_text threshold, which could merge unrelated facts.
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

        private function removeExactDuplicates(array $statements): array
        {
            $unique = [];
            $seen = [];

            foreach ($statements as $statement) {
                $key = $this->normalizeForComparison($statement);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $unique[] = $statement;
            }

            return $unique;
        }

        private function looksLikeJson(string $value): bool
        {
            $first = substr(ltrim($value), 0, 1);
            return $first === '{' || $first === '[';
        }
    }
}
