<?php
// ============================================================================
// CfCbazar - Local AI SummarizerSkill Module
// File: /includes/skills/SummarizerSkill.php
// ============================================================================

if (!class_exists('SummarizerSkill')) {
    class SummarizerSkill implements AISkillInterface {

        public function getKeywords(): array {
            return ['summarize', 'summary', 'tl;dr', 'shorten', 'condense', 'key points'];
        }

        public function getPriority(): int {
            return 20;
        }

        public function execute(string $input, array &$memory): string {
            // Include TextRank library if not already loaded
            if (!class_exists('TextRank')) {
                $textRankFile = __DIR__ . '/../libs/textrank/TextRank.php';
                if (file_exists($textRankFile)) {
                    require_once $textRankFile;
                }
            }

            // Clean input prompt to extract text to summarize
            $textToSummarize = preg_replace('/^(summarize|summary|tl;dr|shorten|condense)\s*(this:)?\s*/i', '', $input);
            if (empty(trim($textToSummarize))) {
                $textToSummarize = $input; // Fallback to full input if regex clears everything
            }

            // Check if TextRank class is now available
            if (class_exists('TextRank')) {
                // Initialize TextRank (adjust method call according to your library class implementation)
                $summarizer = new TextRank();
                if (method_exists($summarizer, 'summarize')) {
                    $summary = $summarizer->summarize($textToSummarize);
                } elseif (method_exists($summarizer, 'getSummary')) {
                    $summary = $summarizer->getSummary($textToSummarize);
                } else {
                    $summary = "TextRank loaded, but summary execution method not found.";
                }
                return "### Text Summary (TextRank)\n\n" . trim($summary);
            }

            // Fallback lightweight extraction summary if library is missing
            $sentences = preg_split('/(?<=[.?!])\s+/', $textToSummarize);
            $shortSummary = count($sentences) > 1 ? $sentences[0] . ' ' . ($sentences[1] ?? '') : $textToSummarize;

            return "### Text Summary (Fallback Lightweight)\n\n" . trim($shortSummary);
        }
    }
}
