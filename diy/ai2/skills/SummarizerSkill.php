<?php

class SummarizerSkill implements AISkillInterface {

    public function getKeywords(): array {
        return ['summarize','summary','explain','shorten','compress'];
    }

    public function getPriority(): int {
        return 15;
    }

    public function execute(string $input, array &$memory): string {

        // Simple local summarizer (no external libs)
        $sentences = preg_split('/(\.|\?|!)/', $input, -1, PREG_SPLIT_NO_EMPTY);

        if (count($sentences) <= 1) {
            return "Summary: " . $input;
        }

        // Take first and last sentences as a simple summary
        $first = trim($sentences[0]);
        $last  = trim($sentences[count($sentences)-1]);

        return "Summary:\n- $first\n- $last";
    }
}

