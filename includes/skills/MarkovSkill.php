<?php
// ============================================================================
// CfCbazar Group — MarkovSkill.php
// File: /includes/skills/MarkovSkill.php
// ============================================================================

if (!class_exists('MarkovSkill')) {
    class MarkovSkill implements AISkillInterface {

        public function getKeywords(): array {
            return ['story', 'novel', 'continue', 'write'];
        }

        public function getPriority(): int {
            return 12;
        }

        public function execute(string $input, array &$memory): string {
            $libPath = __DIR__ . '/../includes/libs/markov/Markov.php';
            if (file_exists($libPath)) {
                require_once $libPath;
            } else {
                require_once __DIR__ . '/../../includes/libs/markov/Markov.php';
            }

            $markov = new Markov();

            if (isset($memory['history']) && is_array($memory['history'])) {
                foreach ($memory['history'] as $entry) {
                    if (isset($entry['user'])) {
                        $markov->feed($entry['user']);
                    }
                }
            }

            return "Markov Story Generator:\n" . $markov->generate(40);
        }
    }
}
