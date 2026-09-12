<?php

// /cfcbazar.42web.io/htdocs/diy/ai/skills/BayesSkill.php

class BayesSkill implements AISkillInterface {

    public function getKeywords(): array {
        return ['classify', 'intent', 'detect', 'category', 'topic', 'bayes'];
    }

    public function getPriority(): int {
        return 20;
    }

    public function execute(string $input, array &$memory): string {

        // Check both exact casing and fallback lowercase casing for compatibility
        $libExact = __DIR__ . '/../libs/simplebayes/SimpleBayes.php';
        $libLower = __DIR__ . '/../libs/simplebayes/simplebayes.php';

        if (file_exists($libExact)) {
            require_once $libExact;
        } elseif (file_exists($libLower)) {
            require_once $libLower;
        } else {
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
