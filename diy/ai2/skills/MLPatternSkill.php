<?php

class MLPatternSkill implements AISkillInterface {

    public function getKeywords(): array {
        return ['learn','pattern','classify','predict','ml'];
    }

    public function getPriority(): int {
        return 40;
    }

    public function execute(string $input, array &$memory): string {

        if (empty($memory['history'])) {
            return "ML: Not enough data to learn yet.";
        }

        // Build dataset from memory
        $samples = [];
        $labels  = [];

        foreach ($memory['history'] as $entry) {
            $samples[] = $this->tokenize($entry['user']);
            $labels[]  = $this->detectLabel($entry['user']);
        }

        // Tokenize input
        $inputTokens = $this->tokenize($input);

        // Compute TF-IDF vectors
        $tfidfData = $this->tfidf($samples);
        $inputVector = $this->tfidfSingle($inputTokens, $samples);

        // Predict using simple KNN (k=3)
        $prediction = $this->knnPredict($inputVector, $tfidfData, $labels, 3);

        return "ML Prediction: This message is about **$prediction**.";
    }

    /* ============================================================
       PURE PHP TOKENIZER
       ============================================================ */
    private function tokenize(string $text): array {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9 ]/', ' ', $text);
        $parts = explode(' ', $text);
        return array_values(array_filter($parts));
    }

    /* ============================================================
       PURE PHP TF-IDF
       ============================================================ */
    private function tfidf(array $documents): array {
        $df = [];
        $tfidf = [];

        // Count document frequency
        foreach ($documents as $doc) {
            $unique = array_unique($doc);
            foreach ($unique as $word) {
                if (!isset($df[$word])) $df[$word] = 0;
                $df[$word]++;
            }
        }

        $totalDocs = count($documents);

        // Compute TF-IDF for each document
        foreach ($documents as $i => $doc) {
            $tf = array_count_values($doc);
            $vector = [];

            foreach ($tf as $word => $count) {
                $idf = log($totalDocs / ($df[$word] ?? 1));
                $vector[$word] = $count * $idf;
            }

            $tfidf[$i] = $vector;
        }

        return $tfidf;
    }

    private function tfidfSingle(array $tokens, array $documents): array {
        $df = [];
        $totalDocs = count($documents);

        foreach ($documents as $doc) {
            foreach (array_unique($doc) as $word) {
                if (!isset($df[$word])) $df[$word] = 0;
                $df[$word]++;
            }
        }

        $tf = array_count_values($tokens);
        $vector = [];

        foreach ($tf as $word => $count) {
            $idf = log($totalDocs / ($df[$word] ?? 1));
            $vector[$word] = $count * $idf;
        }

        return $vector;
    }

    /* ============================================================
       PURE PHP KNN (k nearest neighbors)
       ============================================================ */
    private function knnPredict(array $input, array $dataset, array $labels, int $k): string {
        $distances = [];

        foreach ($dataset as $i => $vector) {
            $distances[$i] = $this->cosineDistance($input, $vector);
        }

        asort($distances);

        $nearest = array_slice(array_keys($distances), 0, $k);

        $votes = [];
        foreach ($nearest as $idx) {
            $label = $labels[$idx];
            if (!isset($votes[$label])) $votes[$label] = 0;
            $votes[$label]++;
        }

        arsort($votes);
        return array_key_first($votes);
    }

    private function cosineDistance(array $a, array $b): float {
        $dot = 0;
        $normA = 0;
        $normB = 0;

        foreach ($a as $word => $valA) {
            $valB = $b[$word] ?? 0;
            $dot += $valA * $valB;
            $normA += $valA * $valA;
        }

        foreach ($b as $valB) {
            $normB += $valB * $valB;
        }

        if ($normA == 0 || $normB == 0) return 1;

        return 1 - ($dot / (sqrt($normA) * sqrt($normB)));
    }

    /* ============================================================
       SIMPLE LABEL DETECTOR
       ============================================================ */
    private function detectLabel(string $text): string {
        $text = strtolower($text);

        if (preg_match('/\d/', $text)) return 'math';
        if (strpos($text, 'recipe') !== false) return 'cooking';
        if (strpos($text, 'ai') !== false) return 'ai';
        if (strpos($text, 'php') !== false) return 'coding';
        if (strpos($text, 'mining') !== false) return 'finance';

        return 'general';
    }
}

