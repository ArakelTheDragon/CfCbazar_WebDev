<?php
// ============================================================================
// CfCbazar - Local AI SelfLearningSkill Module
// File: /includes/skills/SelfLearningSkill.php
// ============================================================================

if (!interface_exists('AISkillInterface')) {
    interface AISkillInterface {
        public function getKeywords(): array;
        public function getPriority(): int;
        public function execute(string $input, array &$memory): string;
    }
}

if (!class_exists('SelfLearningSkill')) {
    class SelfLearningSkill implements AISkillInterface {

        public function getKeywords(): array {
            return ['learn', 'remember', 'note', 'fact', 'teach', 'openrouter'];
        }

        public function getPriority(): int {
            return 30;
        }

        public function execute(string $input, array &$memory): string {
            // Parse patterns like "remember [topic] is [info]" or "learn [topic] is [info]"
            if (preg_match('/(?:remember|learn|note)\s+(.+?)\s+(?:is|=)\s+(.+)/i', $input, $matches)) {
                $topic = trim(strtolower($matches[1]));
                $info = trim($matches[2]);

                // Initialize knowledge array if not set
                if (!isset($memory['knowledge']) || !is_array($memory['knowledge'])) {
                    $memory['knowledge'] = [];
                }

                // Store in memory knowledge base
                $memory['knowledge'][$topic] = [
                    'info' => $info,
                    'learned_at' => date('Y-m-d H:i:s')
                ];

                return "I have successfully learned and memorized **$topic**: \"$info\"";
            }

            return "To teach me a new fact, use the format: `remember [topic] is [info]`";
        }
    }
}
