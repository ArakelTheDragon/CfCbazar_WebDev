<?php
// ============================================================================
// CfCbazar Group — Markov.php
// File: /includes/libs/markov/Markov.php
// ============================================================================

if (!class_exists('Markov')) {
    class Markov {
        private array $chain = [];

        public function feed(string $text): void {
            $words = preg_split('/\s+/', trim($text));
            for ($i = 0; $i < count($words) - 1; $i++) {
                $word = $words[$i];
                $nextWord = $words[$i + 1];
                $this->chain[$word][] = $nextWord;
            }
        }

        public function generate(int $length = 40): string {
            if (empty($this->chain)) {
                return "Not enough data in Markov chain to generate text yet. Keep chatting!";
            }

            $keys = array_keys($this->chain);
            $currentWord = $keys[array_rand($keys)];
            $result = [$currentWord];

            for ($i = 0; $i < $length; $i++) {
                if (!isset($this->chain[$currentWord]) || empty($this->chain[$currentWord])) {
                    $currentWord = $keys[array_rand($keys)];
                }
                $nextWords = $this->chain[$currentWord];
                $nextWord = $nextWords[array_rand($nextWords)];
                $result[] = $nextWord;
                $currentWord = $nextWord;
            }

            return implode(' ', $result);
        }
    }
}
