<?php

if (!class_exists('ResponseUnderstandingSkill', false)) {

    class ResponseUnderstandingSkill
    {
        /**
         * Cleans, sanitizes, clusters, and formats raw AI/memory output dumps into a single response.
         *
         * @param mixed $response Raw string or array from Router / KnowledgeSkill
         * @return string Clean, single combined answer
         */
        public function execute(mixed $response): string
        {
            // 1. Extract clean individual statements from raw dump
            $statements = $this->extractStatements($response);

            if (empty($statements)) {
                return "*(No response generated)*";
            }

            // 2. Cluster similar or redundant statements and pick the longest from each cluster
            $clusters = [];

            foreach ($statements as $stmt) {
                $matchedIndex = null;

                foreach ($clusters as $index => $cluster) {
                    if ($this->areStatementsAlike($stmt, $cluster['best'])) {
                        $matchedIndex = $index;
                        break;
                    }
                }

                if ($matchedIndex !== null) {
                    // Keep the longer, more informative candidate statement
                    if ($this->getWordCount($stmt) > $this->getWordCount($clusters[$matchedIndex]['best'])) {
                        $clusters[$matchedIndex]['best'] = $stmt;
                    }
                } else {
                    // Create a new cluster for distinct facts/statements
                    $clusters[] = [
                        'best' => $stmt
                    ];
                }
            }

            // 3. Extract best candidates
            $selected = [];
            foreach ($clusters as $cluster) {
                $selected[] = $cluster['best'];
            }

            // 4. Merge into a single clean string
            $finalAnswer = implode(' ', $selected);
            $finalAnswer = preg_replace('/\s+/', ' ', $finalAnswer);

            return trim($finalAnswer);
        }

        /**
         * Strips metadata, Markdown artifacts, source brackets, confidence tags, and splits into clean statements.
         */
        private function extractStatements(mixed $response): array
        {
            $raw = is_array($response) ? json_encode($response) : (string)$response;

            // Step A: Strip system headers like "Information about general_topic"
            $raw = preg_replace('/(?:\b|n?)formation about\s+[a-z0-9_]+/i', '', $raw);

            // Step B: Strip complete and unclosed parenthetical confidence/source tags
            // E.g., "( 0.7 prompt)", "( 0.8 openrouter)", "( **:", "(confidence: 0.8)", or trailing "("
            $raw = preg_replace('/\(\s*[\d\.]*\s*(?:prompt|openrouter|confidence|source|\*\*|:)[^)]*\)?/i', '', $raw);
            $raw = preg_replace('/\(\s*[\d\.]*\s*$/', '', $raw);

            // Step C: Replace metadata/bullet delimiters with a unified split marker
            // E.g., "- ** **:", "** **:", "statement:", "prompt_intent:", "source:", "confidence:"
            $raw = preg_replace('/(?:-\s*)?(?:\*\*\s*\*\*:?|statement:|prompt_intent:|source:|confidence:)/i', '|||SPLIT|||', $raw);

            // Step D: Split by custom marker or newlines
            $chunks = preg_split('/(?:\|\|\|SPLIT\|\|\||\r\n|\r|\n)+/', $raw);

            $clean = [];
            foreach ($chunks as $chunk) {
                $chunk = trim($chunk);

                // Strip leftover markdown symbols (*), colons (:), dashes (-), and brackets
                $chunk = preg_replace('/^[\s\*\-\:\(\)]+/', '', $chunk);
                $chunk = preg_replace('/[\s\*\-\:\(\)]+$/', '', $chunk);
                $chunk = trim($chunk);

                // Skip residual metadata tokens, empty strings, and single isolated characters
                if (
                    $chunk === '' ||
                    mb_strlen($chunk) < 2 ||
                    preg_match('/^(?:general_topic|prompt|openrouter|statement|confidence|source|Hi!?)$/i', $chunk) ||
                    preg_match('/^\d+(\.\d+)?$/', $chunk)
                ) {
                    continue;
                }

                $clean[] = $chunk;
            }

            return $clean;
        }

        /**
         * Determines if two statements are alike using string similarity, word overlap, and greeting heuristics.
         */
        private function areStatementsAlike(string $stmtA, string $stmtB): bool
        {
            $normA = strtolower(trim(preg_replace('/[^\w\s]/u', '', $stmtA)));
            $normB = strtolower(trim(preg_replace('/[^\w\s]/u', '', $stmtB)));

            if ($normA === '' || $normB === '') {
                return false;
            }

            // 1. Direct substring containment
            if (str_contains($normA, $normB) || str_contains($normB, $normA)) {
                return true;
            }

            // 2. Greeting heuristic: cluster short salutations together ("Hello", "Hi there", "👋 How can I assist you today")
            $greetingKeywords = ['hello', 'hi', 'hey', 'greetings', 'assist', 'help'];
            $isA_Greeting = false;
            $isB_Greeting = false;

            foreach ($greetingKeywords as $kw) {
                if (str_contains($normA, $kw)) $isA_Greeting = true;
                if (str_contains($normB, $kw)) $isB_Greeting = true;
            }

            if ($isA_Greeting && $isB_Greeting && $this->getWordCount($stmtA) <= 7 && $this->getWordCount($stmtB) <= 7) {
                return true;
            }

            // 3. Textual similarity percentage
            similar_text($normA, $normB, $percent);
            if ($percent >= 50.0) {
                return true;
            }

            // 4. Word-level overlap ratio
            $wordsA = array_filter(explode(' ', $normA));
            $wordsB = array_filter(explode(' ', $normB));

            $intersection = array_intersect($wordsA, $wordsB);
            $minCount = min(count($wordsA), count($wordsB));

            if ($minCount > 0 && (count($intersection) / $minCount) >= 0.50) {
                return true;
            }

            return false;
        }

        /**
         * Returns word count for candidate sentence comparison.
         */
        private function getWordCount(string $text): int
        {
            $words = preg_split('/\s+/', trim($text));
            return count(array_filter($words));
        }
    }

}
