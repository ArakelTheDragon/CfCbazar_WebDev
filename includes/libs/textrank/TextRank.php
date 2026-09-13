<?php
// ============================================================================
// CfCbazar Group — SummarizerSkill.php
// File: /includes/skills/SummarizerSkill.php
// ============================================================================

if (!class_exists('SummarizerSkill')) {
    class SummarizerSkill implements AISkillInterface {

        public function getKeywords(): array {
            return ['summarize', 'summary', 'explain'];
        }

        public function getPriority(): int {
            return 15;
        }

        public function execute(string $input, array &$memory): string {
            // Flexible path checking for TextRank library
            $libPaths = [
                __DIR__ . '/../libs/textrank/TextRank.php',
                __DIR__ . '/../libs/textrank/textrank.php',
                __DIR__ . '/../../libs/textrank/TextRank.php',
                __DIR__ . '/libs/textrank/TextRank.php'
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
                return "TextRank library not installed. Upload /libs/textrank/TextRank.php to enable summarization.";
            }

            if (!class_exists('TextRank')) {
                return "Summarizer error: TextRank class not found in library file.";
            }

            $text = "";
            if (isset($memory['history']) && is_array($memory['history'])) {
                foreach ($memory['history'] as $entry) {
                    if (isset($entry['user'])) {
                        $text .= $entry['user'] . ". ";
                    }
                }
            }

            if (empty(trim($text))) {
                return "No interaction history available to summarize.";
            }

            $ranker = new TextRank();
            $summary = $ranker->summarizeText($text);

            return "Summary of your interactions:\n" . $summary;
        }
    }
}
