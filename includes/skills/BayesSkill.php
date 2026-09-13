<?php
// ============================================================================
// CfCbazar - Local AI BayesSkill Module
// File: /includes/skills/BayesSkill.php
// ============================================================================

if (!class_exists('BayesSkill')) {
    class BayesSkill implements AISkillInterface {

        public function getKeywords(): array {
            return ['classify', 'intent', 'detect', 'category', 'topic', 'bayes'];
        }

        public function getPriority(): int {
            return 20;
        }

        public function execute(string $input, array &$memory): string {
            // Flexible path checking for SimpleBayes library across potential directories
            $libPaths = [
                __DIR__ . '/../libs/simplebayes/SimpleBayes.php',
                __DIR__ . '/../libs/simplebayes/simplebayes.php',
                __DIR__ . '/../../libs/simplebayes/SimpleBayes.php',
                __DIR__ . '/../../libs/simplebayes/simplebayes.php',
                __DIR__ . '/../../libs/SimpleBayes.php'
            ];

            $libLoaded = false;
            foreach ($libPaths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $libLoaded = true;
                    break;
                }
            }

            if (!$libLoaded) {
                return "Bayes library not installed. Upload /libs/simplebayes/SimpleBayes.php to enable classification.";
            }

            if (!class_exists('SimpleBayes')) {
                return "Bayes error: SimpleBayes class not found in library file.";
            }

            $bayes = new SimpleBayes();

            // Train classifier from memory history safely
            if (isset($memory["history"]) && is_array($memory["history"])) {
                foreach ($memory["history"] as $entry) {
                    if (isset($entry["user"])) {
                        $label = $this->detectLabel($entry["user"]);
                        $bayes->learn($label, $entry["user"]);
                    }
                }
            }

            // Predict category
            $prediction = $bayes->classify($input);

            return "Intent Detection: I think this message is about **{$prediction}**.";
        }

        private function detectLabel(string $text): string {
            $t = strtolower($text);

            if (preg_match('/\d|\+|\-|\*|\//', $t)) return 'math';
            if (str_contains($t, 'remember') || str_contains($t, 'learn')) return 'knowledge';
            if (str_contains($t, 'story') || str_contains($t, 'novel')) return 'story';
            if (str_contains($t, 'cook') || str_contains($t, 'recipe')) return 'recipe';
            if (str_contains($t, 'budget') || str_contains($t, 'finance')) return 'finance';
            if (str_contains($t, 'cv') || str_contains($t, 'resume')) return 'cv';

            return 'general';
        }
    }
}
