<?php
/* ============================================================
   CfCbazar Group — Naive Bayes Classifier Library
   File: /diy/ai/libs/simplebayes/SimpleBayes.php
   ============================================================ */

class SimpleBayes {

    private array $words = [];
    private array $categories = [];
    private array $docCount = [];
    private int $totalDocs = 0;

    /**
     * Tokenize string into clean words
     */
    private function tokenize(string $text): array {
        $text = strtolower($text);
        // Remove non-alphanumeric characters except spaces
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        $tokens = array_filter(explode(' ', $text));
        return array_values($tokens);
    }

    /**
     * Train the classifier with a document and category label
     */
    public function learn(string $category, string $text): void {
        $category = strtolower(trim($category));
        $tokens = $this->tokenize($text);

        if (empty($tokens)) {
            return;
        }

        if (!isset($this->categories[$category])) {
            $this->categories[$category] = 0;
            $this->docCount[$category] = 0;
        }

        $this->docCount[$category]++;
        $this->totalDocs++;

        foreach ($tokens as $token) {
            if (!isset($this->words[$token][$category])) {
                $this->words[$token][$category] = 0;
            }
            $this->words[$token][$category]++;
            $this->categories[$category]++;
        }
    }

    /**
     * Classify input text into the most likely category
     */
    public function classify(string $text): string {
        $tokens = $this->tokenize($text);

        if (empty($tokens) || empty($this->categories)) {
            return 'general';
        }

        $scores = [];
        $vocabularySize = count($this->words);

        foreach ($this->categories as $category => $totalWordCount) {
            // Prior probability of the category: P(C)
            $prior = log($this->docCount[$category] / $this->totalDocs);
            $score = $prior;

            // Likelihood: P(W|C) with Laplace smoothing
            foreach ($tokens as $token) {
                $wordCount = $this->words[$token][$category] ?? 0;
                $probability = ($wordCount + 1) / ($totalWordCount + $vocabularySize + 1);
                $score += log($probability);
            }

            $scores[$category] = $score;
        }

        arsort($scores);
        return array_key_first($scores) ?? 'general';
    }
}
