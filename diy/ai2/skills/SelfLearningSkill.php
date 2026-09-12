<?php

class SelfLearningSkill implements AISkillInterface {

    public function getKeywords(): array {
        // Included 'is' so auto-learning statements ("X is Y") trigger the skill
        return ['learn', 'remember', 'note', 'fact', 'teach', ' is '];
    }

    public function getPriority(): int {
        return 50; // Increased priority to ensure learning takes precedence
    }

    public function execute(string $input, array &$memory): string {

        if (!isset($memory["knowledge"]) || !is_array($memory["knowledge"])) {
            $memory["knowledge"] = [];
        }

        $inputLower = strtolower(trim($input));

        /* ---------------------------------------------------------
           1. "learn X is Y" — highest priority
        --------------------------------------------------------- */
        if (str_starts_with($inputLower, "learn ")) {

            $fact = trim(substr($inputLower, 6));

            if (strpos($fact, " is ") !== false) {
                [$topic, $info] = explode(" is ", $fact, 2);

                $topic = trim($topic);
                $info  = trim($info);

                if ($topic !== "" && $info !== "") {
                    $memory["knowledge"][$topic] = $info;
                    return "Learned (via learn): [$topic] = $info";
                }
            }

            return "Use format: learn X is Y";
        }

        /* ---------------------------------------------------------
           2. "remember X is Y" — second priority
        --------------------------------------------------------- */
        if (str_starts_with($inputLower, "remember ")) {

            $fact = trim(substr($inputLower, 9));

            if (strpos($fact, " is ") !== false) {
                [$topic, $info] = explode(" is ", $fact, 2);

                $topic = trim($topic);
                $info  = trim($info);

                if ($topic !== "" && $info !== "") {
                    $memory["knowledge"][$topic] = $info;
                    return "Learned (via remember): [$topic] = $info";
                }
            }

            return "Use format: remember X is Y";
        }

        /* ---------------------------------------------------------
           3. Auto-learning: "X is Y"
        --------------------------------------------------------- */
        if (strpos($inputLower, " is ") !== false) {

            [$topic, $info] = explode(" is ", $inputLower, 2);

            $topic = trim($topic);
            $info  = trim($info);

            if (strlen($topic) > 2 && strlen($info) > 2) {
                $memory["knowledge"][$topic] = $info;
                return "Auto-learned: [$topic] = $info";
            }
        }

        return "Self-Learning: No learnable pattern detected.";
    }
}
