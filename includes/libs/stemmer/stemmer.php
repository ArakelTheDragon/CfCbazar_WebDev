<?php
// ============================================================================
// CfCbazar Group — Stemmer Library
// File: /includes/libs/stemmer/stemmber.php
// ============================================================================

if (!class_exists('Stemmer')) {
    class Stemmer {
        /**
         * Stem a given word by stripping common English suffixes.
         */
        public function stem(string $word): string {
            $word = strtolower(trim($word));
            
            if (strlen($word) <= 3) {
                return $word;
            }

            // Common English suffix rules
            $suffixes = ['edly', 'ing', 'ion', 'ity', 'ed', 'ly', 'es', 'er', 'al', 's'];
            
            foreach ($suffixes as $suffix) {
                if (str_ends_with($word, $suffix)) {
                    $stemmed = substr($word, 0, -strlen($suffix));
                    if (strlen($stemmed) >= 3) {
                        return $stemmed;
                    }
                }
            }

            return $word;
        }

        /**
         * Stem an entire text string word by word.
         */
        public function stemText(string $text): string {
            $words = preg_split('/\s+/', trim($text));
            $stemmedWords = [];
            
            foreach ($words as $word) {
                $cleanWord = preg_replace('/[^a-zA-Z0-9]/', '', $word);
                if (!empty($cleanWord)) {
                    $stemmedWords[] = $this->stem($cleanWord);
                }
            }

            return implode(' ', $stemmedWords);
        }
    }
}
