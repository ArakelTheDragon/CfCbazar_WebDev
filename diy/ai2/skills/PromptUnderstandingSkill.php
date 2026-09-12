<?php
/* ============================================================
   CfCbazar Group — PromptUnderstandingSkill.php
   File: /diy/ai2/skills/PromptUnderstandingSkill.php
   ============================================================ */

if (!interface_exists('AISkillInterface')) {
    interface AISkillInterface {
        public function getKeywords(): array;
        public function getPriority(): int;
        public function execute(string $input, array &$memory): string;
    }
}

class PromptUnderstandingSkill implements AISkillInterface {

    public function getKeywords(): array {
        return ['intent', 'understand', 'analyze prompt', 'classify', 'meaning', 'prompt'];
    }

    public function getPriority(): int {
        return 5;
    }

    public function execute(string $input, array &$memory): string {
        $inputLower = trim(strtolower($input));

        $matchedTopic = null;
        $matchedContent = "";

        if (isset($memory["knowledge"]) && is_array($memory["knowledge"])) {
            foreach ($memory["knowledge"] as $topic => $data) {
                $topicStr = (string)$topic;
                
                if ($topicStr !== "" && str_contains($inputLower, strtolower($topicStr))) {
                    $matchedTopic = $topicStr;
                    
                    // Safely handle both new array format and legacy string format
                    if (is_array($data)) {
                        $matchedContent = $data["summary"] ?? ($data["content"] ?? "");
                    } else {
                        $matchedContent = (string)$data;
                    }
                    break;
                }
            }
        }

        if ($matchedTopic !== null) {
            return "### Prompt Analysis\n\nIdentified Intent: **Knowledge Query**\nMatched Topic: **" . ucfirst($matchedTopic) . "**\nSummary: " . $matchedContent;
        }

        return "### Prompt Analysis\n\nIdentified Intent: **General Query / Dynamic Task**\nParsed Input: \"" . htmlspecialchars($input) . "\"";
    }
}
